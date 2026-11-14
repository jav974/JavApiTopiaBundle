<?php

namespace Jav\ApiTopiaBundle\GraphQL\Relay;

/**
 * Builds connection results following the pagination algorithm of the GraphQL Cursor Connections Specification.
 *
 * @see https://relay.dev/graphql/connections.htm#sec-Pagination-algorithm
 */
final class ConnectionBuilder
{
    /**
     * @param array<mixed> $items The whole list
     * @return array{edges: list<array{cursor: string, node: mixed}>, pageInfo: array{startCursor: ?string, endCursor: ?string, hasPreviousPage: bool, hasNextPage: bool}}
     */
    public static function fromArray(array $items, ConnectionArguments $args): array
    {
        /** @var array{edges: list<array{cursor: string, node: mixed}>, pageInfo: array{startCursor: ?string, endCursor: ?string, hasPreviousPage: bool, hasNextPage: bool}} */
        return self::fromSlice(array_values($items), 0, count($items), $args);
    }

    /**
     * @param array<mixed> $slice Items of the list, starting at position $sliceStart
     * @param int $sliceStart Position of the first item of the slice in the list
     * @param int $totalCount Number of items in the whole list
     * @return array{edges: list<array{cursor: string, node: mixed}>, pageInfo: array{startCursor: ?string, endCursor: ?string, hasPreviousPage: bool, hasNextPage: bool}}|null
     *         null when the slice does not contain all the edges to return (see requiredWindow())
     */
    public static function fromSlice(array $slice, int $sliceStart, int $totalCount, ConnectionArguments $args): ?array
    {
        $slice = array_values($slice);
        [$start, $end, $afterExists, $beforeExists, $cursorEdgeCount] = self::compute($totalCount, $args);

        if ($start < $end && ($start < $sliceStart || $end > $sliceStart + count($slice))) {
            return null;
        }

        $edges = [];

        for ($position = $start; $position < $end; $position++) {
            $edges[] = [
                'cursor' => ArrayCursor::fromOffset($position),
                'node' => $slice[$position - $sliceStart],
            ];
        }

        return [
            'edges' => $edges,
            'pageInfo' => [
                // "startCursor and endCursor must be the cursors corresponding to the first and last nodes in edges"
                'startCursor' => $edges[0]['cursor'] ?? null,
                'endCursor' => $edges !== [] ? $edges[count($edges) - 1]['cursor'] : null,
                'hasPreviousPage' => $args->last !== null
                    ? $cursorEdgeCount > $args->last
                    // We know the position of the "after" edge: elements exist prior to it (at least itself)
                    : $afterExists,
                'hasNextPage' => $args->first !== null
                    ? $cursorEdgeCount > $args->first
                    // We know the position of the "before" edge: elements exist following it (at least itself)
                    : $beforeExists,
            ],
        ];
    }

    /**
     * The positions of the edges to return, once the total count is known.
     *
     * @return array{offset: int, limit: int}
     */
    public static function requiredWindow(int $totalCount, ConnectionArguments $args): array
    {
        [$start, $end] = self::compute($totalCount, $args);

        return ['offset' => $start, 'limit' => $end - $start];
    }

    /**
     * @return array{0: int, 1: int, 2: bool, 3: bool, 4: int} start, end (exclusive), after exists, before exists, edges count after cursors
     */
    private static function compute(int $totalCount, ConnectionArguments $args): array
    {
        $after = $args->afterOffset();
        $before = $args->beforeOffset();

        // ApplyCursorsToEdges: a cursor that does not match any edge removes nothing
        $afterExists = $after !== null && $after < $totalCount;
        $beforeExists = $before !== null && $before < $totalCount;

        $start = $afterExists ? $after + 1 : 0;
        $end = $beforeExists ? $before : $totalCount;
        $end = max($start, $end);
        $cursorEdgeCount = $end - $start;

        // EdgesToReturn
        if ($args->first !== null && $cursorEdgeCount > $args->first) {
            $end = $start + $args->first;
        }

        if ($args->last !== null && $end - $start > $args->last) {
            $start = $end - $args->last;
        }

        return [$start, $end, $afterExists, $beforeExists, $cursorEdgeCount];
    }
}

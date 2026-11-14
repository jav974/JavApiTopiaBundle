<?php

namespace Jav\ApiTopiaBundle\Tests\Unit\Relay;

use GraphQL\Error\UserError;
use Jav\ApiTopiaBundle\GraphQL\Relay\ArrayCursor;
use Jav\ApiTopiaBundle\GraphQL\Relay\ConnectionArguments;
use Jav\ApiTopiaBundle\GraphQL\Relay\ConnectionBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * GraphQL Cursor Connections Specification, "Pagination algorithm".
 *
 * @see https://relay.dev/graphql/connections.htm#sec-Pagination-algorithm
 */
class ConnectionTest extends TestCase
{
    private const LETTERS = ['A', 'B', 'C', 'D', 'E'];

    private static function c(int $offset): string
    {
        return ArrayCursor::fromOffset($offset);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, list<string>, bool, bool}>
     */
    public static function cases(): iterable
    {
        // args, expected nodes, hasPreviousPage, hasNextPage
        yield 'no arguments: all edges' => [[], ['A', 'B', 'C', 'D', 'E'], false, false];
        yield 'first' => [['first' => 2], ['A', 'B'], false, true];
        yield 'first larger than the list' => [['first' => 10], ['A', 'B', 'C', 'D', 'E'], false, false];
        yield 'first 0' => [['first' => 0], [], false, true];
        yield 'first + after' => [['first' => 2, 'after' => self::c(1)], ['C', 'D'], true, true];
        yield 'first + after reaching the end' => [['first' => 2, 'after' => self::c(2)], ['D', 'E'], true, false];
        yield 'after the last edge' => [['first' => 2, 'after' => self::c(4)], [], true, false];
        yield 'last' => [['last' => 2], ['D', 'E'], true, false];
        yield 'last + before' => [['last' => 2, 'before' => self::c(3)], ['B', 'C'], true, true];
        yield 'last + before reaching the start' => [['last' => 2, 'before' => self::c(2)], ['A', 'B'], false, true];
        yield 'before the first edge' => [['last' => 2, 'before' => self::c(0)], [], false, true];
        yield 'after + before' => [['after' => self::c(0), 'before' => self::c(4)], ['B', 'C', 'D'], true, true];
        yield 'after + before + first' => [['after' => self::c(0), 'before' => self::c(4), 'first' => 2], ['B', 'C'], true, true];
        yield 'after + before + last' => [['after' => self::c(0), 'before' => self::c(4), 'last' => 2], ['C', 'D'], true, true];
        yield 'first + last (discouraged but defined)' => [['first' => 4, 'last' => 2], ['C', 'D'], true, true];
        yield 'after == before' => [['after' => self::c(2), 'before' => self::c(2)], [], true, true];
        // ApplyCursorsToEdges: "If afterEdge exists" / "If beforeEdge exists": unknown cursors remove nothing
        yield 'unknown after cursor is ignored' => [['first' => 2, 'after' => self::c(99)], ['A', 'B'], false, true];
        yield 'malformed after cursor is ignored' => [['first' => 2, 'after' => 'garbage'], ['A', 'B'], false, true];
        yield 'unknown before cursor is ignored' => [['last' => 2, 'before' => self::c(99)], ['D', 'E'], true, false];
    }

    /**
     * @param array<string, mixed> $args
     * @param list<string> $expected
     */
    #[DataProvider('cases')]
    public function testFromArray(array $args, array $expected, bool $hasPreviousPage, bool $hasNextPage): void
    {
        $connection = ConnectionBuilder::fromArray(self::LETTERS, ConnectionArguments::fromArgs($args));

        $this->assertSame($expected, array_column($connection['edges'], 'node'));
        $this->assertSame($hasPreviousPage, $connection['pageInfo']['hasPreviousPage'], 'hasPreviousPage');
        $this->assertSame($hasNextPage, $connection['pageInfo']['hasNextPage'], 'hasNextPage');

        foreach ($connection['edges'] as $edge) {
            $this->assertSame($edge['node'], self::LETTERS[ArrayCursor::toOffset($edge['cursor'])]);
        }

        // "startCursor and endCursor must be the cursors corresponding to the first and last nodes in edges"
        $this->assertSame($connection['edges'][0]['cursor'] ?? null, $connection['pageInfo']['startCursor']);
        $this->assertSame($connection['edges'] ? end($connection['edges'])['cursor'] : null, $connection['pageInfo']['endCursor']);
    }

    /**
     * The window given to resolvers, then the slice they return, must give the same result as the whole list.
     *
     * @param array<string, mixed> $args
     * @param list<string> $expected
     */
    #[DataProvider('cases')]
    public function testResolverWindowThenSlice(array $args, array $expected, bool $hasPreviousPage, bool $hasNextPage): void
    {
        $arguments = ConnectionArguments::fromArgs($args);
        ['offset' => $offset, 'limit' => $limit] = $arguments->window();
        $slice = array_slice(self::LETTERS, $offset, $limit === PHP_INT_MAX ? null : $limit);
        $sliceStart = $offset >= 0 ? $offset : max(0, count(self::LETTERS) + $offset);

        $connection = ConnectionBuilder::fromSlice($slice, $sliceStart, count(self::LETTERS), $arguments);

        if ($connection === null) {
            // The window was computed from a cursor that does not exist: reload the required one
            $window = ConnectionBuilder::requiredWindow(count(self::LETTERS), $arguments);
            $connection = ConnectionBuilder::fromSlice(array_slice(self::LETTERS, $window['offset'], $window['limit']), $window['offset'], count(self::LETTERS), $arguments);
        }

        $this->assertNotNull($connection);
        $this->assertSame($expected, array_column($connection['edges'], 'node'));
        $this->assertSame($hasPreviousPage, $connection['pageInfo']['hasPreviousPage']);
        $this->assertSame($hasNextPage, $connection['pageInfo']['hasNextPage']);
    }

    public function testSliceNotContainingThePageIsDetected(): void
    {
        $arguments = ConnectionArguments::fromArgs(['first' => 2, 'after' => self::c(2)]);

        $this->assertNull(ConnectionBuilder::fromSlice(['A', 'B'], 0, 5, $arguments));
        $this->assertSame(['offset' => 3, 'limit' => 2], ConnectionBuilder::requiredWindow(5, $arguments));
    }

    public function testEmptyList(): void
    {
        $connection = ConnectionBuilder::fromArray([], ConnectionArguments::fromArgs(['first' => 2]));

        $this->assertSame([], $connection['edges']);
        $this->assertSame(['startCursor' => null, 'endCursor' => null, 'hasPreviousPage' => false, 'hasNextPage' => false], $connection['pageInfo']);
    }

    #[DataProvider('negativeArguments')]
    public function testNegativeFirstOrLastThrows(string $name): void
    {
        $this->expectException(UserError::class);
        $this->expectExceptionMessage(sprintf('Argument "%s" must be a non-negative integer.', $name));

        ConnectionArguments::fromArgs([$name => -1]);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function negativeArguments(): iterable
    {
        yield ['first'];
        yield ['last'];
    }

    public function testMaxPageSize(): void
    {
        $this->expectException(UserError::class);
        $this->expectExceptionMessage('Cannot request more than 3 items per page.');

        ConnectionArguments::fromArgs(['last' => 4], 0, 3);
    }

    public function testImplicitFirst(): void
    {
        $arguments = ConnectionArguments::fromArgs([], 2);
        $connection = ConnectionBuilder::fromArray(self::LETTERS, $arguments);

        $this->assertSame(2, $arguments->first);
        $this->assertSame(['A', 'B'], array_column($connection['edges'], 'node'));
        $this->assertTrue($connection['pageInfo']['hasNextPage']);
        $this->assertNull(ConnectionArguments::fromArgs(['last' => 1], 2)->first);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array{offset: int, limit: int}}>
     */
    public static function windows(): iterable
    {
        yield 'first' => [['first' => 3], ['offset' => 0, 'limit' => 3]];
        yield 'first + after' => [['first' => 3, 'after' => self::c(4)], ['offset' => 5, 'limit' => 3]];
        yield 'last only: counted from the end' => [['last' => 3], ['offset' => -3, 'limit' => 3]];
        yield 'last + before' => [['last' => 3, 'before' => self::c(10)], ['offset' => 7, 'limit' => 3]];
        yield 'last + before near the start' => [['last' => 3, 'before' => self::c(1)], ['offset' => 0, 'limit' => 1]];
        yield 'after + before' => [['after' => self::c(1), 'before' => self::c(5)], ['offset' => 2, 'limit' => 3]];
        yield 'before < after' => [['after' => self::c(5), 'before' => self::c(1)], ['offset' => 6, 'limit' => 0]];
        yield 'unbounded' => [['after' => self::c(1)], ['offset' => 2, 'limit' => PHP_INT_MAX]];
    }

    /**
     * @param array<string, mixed> $args
     * @param array{offset: int, limit: int} $expected
     */
    #[DataProvider('windows')]
    public function testWindow(array $args, array $expected): void
    {
        $this->assertSame($expected, ConnectionArguments::fromArgs($args)->window());
    }
}

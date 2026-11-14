<?php

namespace Jav\ApiTopiaBundle\GraphQL\Relay;

use GraphQL\Error\UserError;

/**
 * The pagination arguments of a connection field: first, after, last, before.
 *
 * @see https://relay.dev/graphql/connections.htm#sec-Arguments
 */
final class ConnectionArguments
{
    private function __construct(
        public readonly ?int $first,
        public readonly ?int $last,
        public readonly ?string $after,
        public readonly ?string $before,
    ) {
    }

    /**
     * @param array<string, mixed> $args
     * @param int $defaultFirst Page size applied when neither "first" nor "last" is given (0: none)
     * @param int $maxPageSize Maximum value of "first" and "last" (0: unlimited)
     * @throws UserError
     */
    public static function fromArgs(array $args, int $defaultFirst = 0, int $maxPageSize = 0): self
    {
        $first = isset($args['first']) ? (int) $args['first'] : null;
        $last = isset($args['last']) ? (int) $args['last'] : null;

        foreach (['first' => $first, 'last' => $last] as $name => $value) {
            // EdgesToReturn: "If first/last is less than 0: Throw an error."
            if ($value !== null && $value < 0) {
                throw new UserError(sprintf('Argument "%s" must be a non-negative integer.', $name));
            }

            if ($value !== null && $maxPageSize > 0 && $value > $maxPageSize) {
                throw new UserError(sprintf('Cannot request more than %d items per page.', $maxPageSize));
            }
        }

        // Servers are free to bound unbounded connections: an implicit "first" keeps pageInfo consistent
        if ($first === null && $last === null && $defaultFirst > 0) {
            $first = $defaultFirst;
        }

        return new self(
            $first,
            $last,
            isset($args['after']) && is_string($args['after']) ? $args['after'] : null,
            isset($args['before']) && is_string($args['before']) ? $args['before'] : null,
        );
    }

    public function afterOffset(): ?int
    {
        return ArrayCursor::toOffset($this->after);
    }

    public function beforeOffset(): ?int
    {
        return ArrayCursor::toOffset($this->before);
    }

    /**
     * The slice of the list a resolver should load, before knowing the total number of items.
     *
     * A negative offset means "count from the end of the list" (when only "last" is known).
     *
     * @return array{offset: int, limit: int}
     */
    public function window(): array
    {
        $after = $this->afterOffset();
        $before = $this->beforeOffset();
        $start = $after !== null ? $after + 1 : 0;
        $end = $before;

        if ($end !== null && $end < $start) {
            $end = $start;
        }

        if ($this->first !== null) {
            $end = $end === null ? $start + $this->first : min($end, $start + $this->first);
        }

        if ($this->last !== null) {
            if ($end === null) {
                return ['offset' => -$this->last, 'limit' => $this->last];
            }

            $start = max($start, $end - $this->last);
        }

        if ($end === null) {
            // No bound at all: the whole list after the cursor
            return ['offset' => $start, 'limit' => PHP_INT_MAX];
        }

        return ['offset' => $start, 'limit' => max(0, $end - $start)];
    }
}

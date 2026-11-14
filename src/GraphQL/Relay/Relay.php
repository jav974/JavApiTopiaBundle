<?php

namespace Jav\ApiTopiaBundle\GraphQL\Relay;

/**
 * Public helpers for application code (resolvers, tests).
 *
 * Replaces the GraphQLRelay\Relay helpers of ivome/graphql-relay-php, with the same identifier and cursor formats.
 */
final class Relay
{
    /**
     * @param string $type The global ID type: "<schema name>.<GraphQL type name>" for ApiResource objects
     */
    public static function toGlobalId(string $type, string|int $id): string
    {
        return GlobalId::encode($type, $id);
    }

    /**
     * @return array{type: string, id: string}|null
     */
    public static function fromGlobalId(string $globalId): ?array
    {
        return GlobalId::decode($globalId);
    }

    public static function offsetToCursor(int $offset): string
    {
        return ArrayCursor::fromOffset($offset);
    }

    public static function cursorToOffset(string $cursor): ?int
    {
        return ArrayCursor::toOffset($cursor);
    }
}

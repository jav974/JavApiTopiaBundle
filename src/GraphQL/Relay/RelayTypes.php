<?php

namespace Jav\ApiTopiaBundle\GraphQL\Relay;

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

/**
 * GraphQL type definitions required by the Relay server specification.
 *
 * Names and descriptions are kept identical to the ones of graphql-relay-js (and ivome/graphql-relay-php),
 * so that generated schemas do not change.
 */
final class RelayTypes
{
    private static ?ObjectType $pageInfo = null;

    /**
     * "The server must provide a type called PageInfo."
     */
    public static function pageInfo(): ObjectType
    {
        return self::$pageInfo ??= new ObjectType([
            'name' => 'PageInfo',
            'description' => 'Information about pagination in a connection.',
            'fields' => [
                'hasNextPage' => [
                    'type' => Type::nonNull(Type::boolean()),
                    'description' => 'When paginating forwards, are there more items?',
                ],
                'hasPreviousPage' => [
                    'type' => Type::nonNull(Type::boolean()),
                    'description' => 'When paginating backwards, are there more items?',
                ],
                'startCursor' => [
                    'type' => Type::string(),
                    'description' => 'When paginating backwards, the cursor to continue.',
                ],
                'endCursor' => [
                    'type' => Type::string(),
                    'description' => 'When paginating forwards, the cursor to continue.',
                ],
            ],
        ]);
    }

    /**
     * Forward and backward pagination arguments.
     *
     * @return array<string, array{type: Type}>
     */
    public static function connectionArgs(): array
    {
        return [
            'after' => ['type' => Type::string()],
            'first' => ['type' => Type::int()],
            'before' => ['type' => Type::string()],
            'last' => ['type' => Type::int()],
        ];
    }

    public static function edgeType(string $name, Type $nodeType): ObjectType
    {
        return new ObjectType([
            'name' => $name . 'Edge',
            'description' => 'An edge in a connection',
            'fields' => fn () => [
                'node' => [
                    'type' => $nodeType,
                    'description' => 'The item at the end of the edge',
                ],
                'cursor' => [
                    'type' => Type::nonNull(Type::string()),
                    'description' => 'A cursor for use in pagination',
                ],
            ],
        ]);
    }

    /**
     * @param string $name The connection type name, it must end with "Connection"
     * @param array<string, mixed> $extraFields Additional connection fields (e.g. totalCount)
     */
    public static function connectionType(string $name, ObjectType $edgeType, array $extraFields = []): ObjectType
    {
        if (!str_ends_with($name, 'Connection')) {
            throw new \InvalidArgumentException(sprintf('Connection type names must end with "Connection", "%s" given.', $name));
        }

        return new ObjectType([
            'name' => $name,
            'description' => 'A connection to a list of items.',
            'fields' => fn () => array_merge([
                'pageInfo' => [
                    'type' => Type::nonNull(self::pageInfo()),
                    'description' => 'Information to aid in pagination.',
                ],
                'edges' => [
                    'type' => Type::listOf($edgeType),
                    'description' => 'Information to aid in pagination',
                ],
            ], $extraFields),
        ]);
    }

    /**
     * "The server must provide an interface called Node. That interface must include exactly one field, called id that returns a non-null ID."
     *
     * @param callable(mixed, mixed, ResolveInfo): ?ObjectType $resolveType
     */
    public static function nodeInterface(callable $resolveType): InterfaceType
    {
        return new InterfaceType([
            'name' => 'Node',
            'description' => 'An object with an ID',
            'fields' => [
                'id' => [
                    'type' => Type::nonNull(Type::id()),
                    'description' => 'The id of the object',
                ],
            ],
            'resolveType' => $resolveType,
        ]);
    }

    /**
     * "The server must provide a root field called node that returns the Node interface.
     * This root field must take exactly one argument, a non-null ID named id."
     *
     * @param callable(string, mixed, ResolveInfo): mixed $fetchNode Receives the global ID, returns the object or null
     * @return array<string, mixed>
     */
    public static function nodeField(InterfaceType $nodeInterface, callable $fetchNode): array
    {
        return [
            'name' => 'node',
            'description' => 'Fetches an object given its ID',
            'type' => $nodeInterface,
            'args' => [
                'id' => [
                    'type' => Type::nonNull(Type::id()),
                    'description' => 'The ID of an object',
                ],
            ],
            'resolve' => fn ($root, array $args, $context, ResolveInfo $info) => $fetchNode($args['id'], $context, $info),
        ];
    }

    /**
     * Plural identifying root field: "a non-null list of non-nulls" argument, a list of nullable nodes of the same length and order.
     *
     * @param callable(string, mixed, ResolveInfo): mixed $fetchNode Receives a global ID, returns the object or null
     * @return array<string, mixed>
     */
    public static function nodesField(InterfaceType $nodeInterface, callable $fetchNode): array
    {
        return [
            'name' => 'nodes',
            'description' => 'Fetches objects given their IDs',
            'type' => Type::nonNull(Type::listOf($nodeInterface)),
            'args' => [
                'ids' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull(Type::id()))),
                    'description' => 'The IDs of objects',
                ],
            ],
            'resolve' => fn ($root, array $args, $context, ResolveInfo $info) => array_map(
                fn (string $id) => $fetchNode($id, $context, $info),
                $args['ids']
            ),
        ];
    }

    /**
     * The "id" field of objects implementing Node.
     *
     * @param callable(mixed): (string|int|null) $idFetcher Returns the raw identifier of the object
     * @return array<string, mixed>
     */
    public static function globalIdField(string $typeName, callable $idFetcher): array
    {
        return [
            'name' => 'id',
            'description' => 'The ID of an object',
            'type' => Type::nonNull(Type::id()),
            'resolve' => function ($object) use ($typeName, $idFetcher) {
                $id = $idFetcher($object);

                return $id === null ? null : GlobalId::encode($typeName, $id);
            },
        ];
    }

    /**
     * A mutation following the Relay input object mutation convention: a single non-null "input" argument
     * and a payload, both carrying an optional "clientMutationId".
     *
     * @param array<string, mixed>|null $inputFields Input fields (ignored when $inputType is given)
     * @param array<string, mixed> $outputFields
     * @param callable(array<string, mixed>, mixed, ResolveInfo): array<string, mixed> $mutateAndGetPayload
     * @return array{type: ObjectType, args: array<string, mixed>, resolve: callable, inputType: InputObjectType}
     */
    public static function mutation(string $name, ?array $inputFields, array $outputFields, callable $mutateAndGetPayload, ?InputObjectType $inputType = null): array
    {
        $outputType = new ObjectType([
            'name' => $name . 'Payload',
            'fields' => fn () => $outputFields + ['clientMutationId' => ['type' => Type::string()]],
        ]);

        $inputType ??= new InputObjectType([
            'name' => $name . 'Input',
            'fields' => fn () => ($inputFields ?? []) + ['clientMutationId' => ['type' => Type::string()]],
        ]);

        return [
            'type' => $outputType,
            'args' => ['input' => ['type' => Type::nonNull($inputType)]],
            'resolve' => function ($root, array $args, $context, ResolveInfo $info) use ($mutateAndGetPayload) {
                $payload = $mutateAndGetPayload($args['input'], $context, $info);
                $payload['clientMutationId'] = $args['input']['clientMutationId'] ?? null;

                return $payload;
            },
            'inputType' => $inputType,
        ];
    }
}

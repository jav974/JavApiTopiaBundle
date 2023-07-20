<?php

namespace Jav\ApiTopiaBundle\GraphQL;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\WrappingType;
use GraphQLRelay\Relay;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Attribute;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\QueryCollection;

class FieldsBuilder
{
    private TypeResolver $typeResolver;

    public function __construct(
        private readonly TypeRegistry $typeRegistry,
        private readonly ResolverProvider $resolverProvider,
    ) {
    }

    public function setTypeResolver(TypeResolver $typeResolver): void
    {
        $this->typeResolver = $typeResolver;
    }

    /**
     * @return array<string, mixed>
     */
    public function getObjectTypeField(string $schemaName, string $classPath, ?string $comment, bool $allowsNull, ?Attribute $attribute, bool $isCollection): array
    {
        $field = [];
        $outputClassName = ReflectionUtils::getClassNameFromClassPath($classPath);
        $type = $this->typeResolver->resolveTypeFromSchema($classPath, $schemaName);
        $type = $this->typeResolver->resolve($schemaName, $type, $isCollection);

        if (!$allowsNull) {
            $type = Type::nonNull($type);
        }

        if ($attribute) {
            $field['args'] = $this->typeResolver->resolveAttributeArgs($schemaName, $attribute);
            $field['resolve'] = $this->resolverProvider->getResolveCallback($schemaName, $attribute);
        }

        if ($attribute instanceof QueryCollection && $attribute->paginationEnabled) {
            if ($attribute->paginationType === QueryCollection::PAGINATION_TYPE_CURSOR) {
                $type = $this->getRelayConnectionType($schemaName, $outputClassName, $type);
                $field['args'] += Relay::connectionArgs();
            } elseif ($attribute->paginationType === QueryCollection::PAGINATION_TYPE_OFFSET) {
                $type = $this->getOffsetConnectionType($schemaName, $outputClassName, $type);
                $field['args'] += $this->getOffsetConnectionArgs();
            }
        }

        $field['type'] = $type;

        if ($comment) {
            $field['description'] = $comment;
        }

        return $field;
    }

    public function getOffsetConnectionType(string $schemaName, string $outputClassName, Type $type): ObjectType
    {
        if ($type instanceof WrappingType) {
            $type = $type->getInnermostType();
        }

        $connectionName = $outputClassName . 'OffsetConnection';

        if (!$this->typeRegistry->has("$schemaName.$connectionName")) {
            $this->typeRegistry->register("$schemaName.$connectionName", new ObjectType([
                'name' => $connectionName,
                'fields' => [
                    'items' => [
                        'type' => Type::listOf($type),
                        'description' => 'The list of items in the connection.',
                    ],
                    'totalCount' => [
                        'type' => Type::nonNull(Type::int()),
                        'description' => 'The total count of items in the connection.',
                        'resolve' => fn (array $root) => $root['totalCount'] ?? 0,
                    ],
                ],
            ]));
        }

        /** @noinspection PhpIncompatibleReturnTypeInspection */
        // @phpstan-ignore-next-line @scrutinizer-ignore-next-line
        return $this->typeRegistry->get("$schemaName.$connectionName");
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getOffsetConnectionArgs(): array
    {
        return [
            'limit' => [
                'type' => Type::int()
            ],
            'offset' => [
                'type' => Type::int()
            ]
        ];
    }

    public function getRelayConnectionType(string $schemaName, string $outputClassName, Type $type): ObjectType
    {
        if ($type instanceof WrappingType) {
            $type = $type->getInnermostType();
        }

        $edgeName = $outputClassName . 'Edge';
        $connectionName = $outputClassName . 'CursorConnection';

        if (!$this->typeRegistry->has("$schemaName.$edgeName")) {
            $this->typeRegistry->register("$schemaName.$edgeName", Relay::edgeType([
                'nodeType' => $type,
                'name' => $outputClassName,
            ]));
        }

        $edge = $this->typeRegistry->get("$schemaName.$edgeName");

        if (!$this->typeRegistry->has("$schemaName.$connectionName")) {
            $this->typeRegistry->register("$schemaName.$connectionName", Relay::connectionType([
                'nodeType' => $type,
                'edgeType' => $edge,
                'connectionFields' => [
                    'totalCount' => [
                        'type' => Type::nonNull(Type::int()),
                        'description' => 'The total count of items in the connection.',
                        'resolve' => fn (array $root) => $root['totalCount'] ?? 0,
                    ],
                ],
                'name' => $outputClassName . 'Cursor'
            ]));
        }

        /** @noinspection PhpIncompatibleReturnTypeInspection */
        // @phpstan-ignore-next-line @scrutinizer-ignore-next-line
        return $this->typeRegistry->get("$schemaName.$connectionName");
    }
}

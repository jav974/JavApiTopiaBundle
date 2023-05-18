<?php

namespace Jav\ApiTopiaBundle\GraphQL;

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\NullableType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQLRelay\Connection\Connection;
use GraphQLRelay\Relay;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\ApiResource;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Attribute;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\SubQueryCollection;
use RuntimeException;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\QueryCollection;

class TypeResolver
{
    private array $types;
    private array $nodeDefinition;

    public function __construct(
        private readonly ResourceLoader $resourceLoader,
        private readonly ResolverProvider $resolverProvider,
        private readonly ?NodeResolverInterface $nodeResolver = null
    ) {
        $this->types = Type::getStandardTypes();
        $this->nodeDefinition = Relay::nodeDefinitions(
            function ($globalId) {
                $idComponents = Relay::fromGlobalId($globalId);

                return $this->nodeResolver?->resolve($idComponents['type'], $idComponents['id']);
            },
            function ($object) {
                return null;
            }
        );
        $this->types['Node'] = $this->nodeDefinition['nodeInterface'];
        $this->types['PageInfo'] = Connection::pageInfoType();
    }

    public function getNodeDefinition(): array
    {
        return $this->nodeDefinition;
    }

    public function resolve(string $schemaName, string $classPath, bool $isCollection, bool $input = false): Type
    {
        if (str_contains($classPath, '\\')) {
            $typeName = substr($classPath, strrpos($classPath, '\\') + 1);
        } else {
            $typeName = $classPath;
        }

        if (in_array($typeName, ['int', 'float', 'string', 'boolean', 'bool', 'ID'])) {
            $typeName = $typeName === 'bool' ? 'boolean' : $typeName;
            $typeName = ucfirst($typeName);
        }

        if ($input) {
            $typeName = "input.$typeName";
        }

        if (!isset($this->types[$typeName]) && !isset($this->types["$schemaName.$typeName"])) {
            $this->types["$schemaName.$typeName"] = $this->createType($schemaName, $classPath, $input);
        }

        $type = $this->getType($typeName, $schemaName);

        if ($isCollection) {
            return Type::listOf(Type::nonNull($type));
        }

        return $type;
    }

    public function getType(string $name, ?string $schemaName = null): Type
    {
        if (isset($this->types[$name])) {
            return $this->types[$name];
        }

        if ($schemaName !== null) {
            $name = "$schemaName.$name";
        }

        return $this->types[$name] ?? throw new \InvalidArgumentException(sprintf('Type "%s" not found.', $name));
    }

    private function createType(string $schemaName, string $classPath, bool $input = false): Type
    {
        $reflectionClass = $this->resourceLoader->getReflectionClass($schemaName, $classPath);

        if (!$reflectionClass) {
            throw new RuntimeException(sprintf('Class "%s" not found in schema "%s".', $classPath, $schemaName));
        }

        return $input ? $this->createInputType($schemaName, $reflectionClass) : $this->createObjectType($schemaName, $reflectionClass);
    }

    public function getObjectTypeField(string $schemaName, string $classPath, ?string $comment, bool $allowsNull, ?Attribute $attribute, bool $isCollection): array
    {
        $field = [];
        $outputClassName = str_replace('[]', '', $classPath);

        if (str_contains($outputClassName, '\\')) {
            $outputClassName = substr($outputClassName, strrpos($outputClassName, '\\') + 1);
        }

        $type = $this->resolveTypeFromSchema($outputClassName, $schemaName);
        $type = $this->resolve($schemaName, $type, $isCollection);

        if (!$allowsNull) {
            $type = Type::nonNull($type);
        }

        if ($attribute) {
            $field['args'] = $this->resolveAttributeArgs($schemaName, $attribute);
            $field['resolve'] = $this->resolverProvider->getResolveCallback($attribute);
        }

        if ($attribute instanceof QueryCollection) {
            $type = $this->getRelayConnection($schemaName, $outputClassName, $type);
            $field['args'] += Relay::connectionArgs();
        }

        $field['type'] = $type;

        if ($comment) {
            $field['description'] = $comment;
        }

        return $field;
    }

    private function createObjectType(string $schemaName, \ReflectionClass $reflectionClass): ObjectType
    {
        // If it is an ApiResource, make it available in the schema with Relay specification
        $isNodeInterface = (bool)($reflectionClass->getAttributes(ApiResource::class)[0] ?? null);

        $description = TypeUtils::getDescriptionFromDocComment($reflectionClass->getDocComment() ?: null);
        $objectTypeData = [
            'name' => $reflectionClass->getShortName(),
            'description' => $description,
            'fields' => function () use ($reflectionClass, $schemaName, $isNodeInterface) {
                $fields = [];

                if ($isNodeInterface) {
                    $fields['id'] = Relay::globalIdField();
                }

                foreach ($reflectionClass->getProperties() as $reflectionProperty) {
                    $queryCollectionAttribute = $reflectionProperty->getAttributes(SubQueryCollection::class)[0] ?? null;
                    $comment = $reflectionProperty->getDocComment() ?: null;
                    /** @var QueryCollection $queryCollection */
                    $queryCollection = $queryCollectionAttribute?->newInstance();
                    $type = $reflectionProperty->getType()?->getName();
                    $isCollection = in_array($type ?? '', ['iterable', 'array']);
                    $type = $isCollection ? $queryCollection?->output ?? TypeUtils::getTypeFromDocComment($comment) : $type;
                    $propertyName = $reflectionProperty->getName();

                    if ($propertyName === 'id' && $isNodeInterface) {
                        $propertyName = '_id';
                    }

                    $fields[$propertyName] = $this->getObjectTypeField(
                        $schemaName,
                        $type,
                        TypeUtils::getDescriptionFromDocComment($comment),
                        $reflectionProperty->getType()?->allowsNull() ?? true,
                        $queryCollection,
                        $isCollection
                    );
                }

                return $fields;
            },
        ];

        if ($isNodeInterface) {
            $objectTypeData['interfaces'] = [$this->nodeDefinition['nodeInterface']];
        }

        $type = new ObjectType($objectTypeData);

        $this->types[$schemaName . '.' . $reflectionClass->getShortName()] = $type;

        return $type;
    }

    private function getRelayConnection(string $schemaName, string $outputClassName, Type $type)
    {
        if ($type instanceof NonNull || $type instanceof ListOfType) {
            $type = $type->getInnerMostType();
        }

        $edgeName = $outputClassName . 'Edge';
        $connectionName = $outputClassName . 'CursorConnection';

        if (isset($this->types["$schemaName.$edgeName"])) {
            $edge = $this->types["$schemaName.$edgeName"];
        } else {
            $edge = Relay::edgeType([
                'nodeType' => $type,
                'name' => $outputClassName,
            ]);
            $this->types["$schemaName.$edgeName"] = $edge;
        }

        if (isset($this->types["$schemaName.$connectionName"])) {
            $connection = $this->types["$schemaName.$connectionName"];
        } else {
            $connection = Relay::connectionType([
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
            ]);

            $this->types["$schemaName.$connectionName"] = $connection;
        }

        return $connection;
    }

    private function createInputType(string $schemaName, \ReflectionClass $reflectionClass): InputObjectType
    {
        $description = TypeUtils::getDescriptionFromDocComment($reflectionClass->getDocComment() ?: null);

        $type = new InputObjectType([
            'name' => $reflectionClass->getShortName(),
            'description' => $description,
            'fields' => function () use ($reflectionClass, $schemaName) {
                $fields = [];

                foreach ($reflectionClass->getProperties() as $reflectionProperty) {
                    $comment = $reflectionProperty->getDocComment() ?: null;
                    $type = $reflectionProperty->getType()?->getName();
                    $type = TypeUtils::getTypeFromDocComment($comment) ?: $type;
                    $type = $this->resolveTypeFromSchema($type, $schemaName);
                    $allowsNull = $reflectionProperty->getType()?->allowsNull() ?? true;
                    $type = $this->resolve($schemaName, $type, false, !$reflectionProperty->getType()?->isBuiltin());

                    if (!$allowsNull) {
                        $type = Type::nonNull($type);
                    }

                    $propertyName = $reflectionProperty->getName();

                    $fields[$propertyName] = [
                        'type' => $type,
                        'description' => TypeUtils::getDescriptionFromDocComment($comment),
                    ];
                }

                return $fields;
            },
        ]);

        $this->types[$schemaName . '.' . $reflectionClass->getShortName()] = $type;

        return $type;
    }

    public function setType(string $schemaName, string $name, Type $type): void
    {
        $this->types["$schemaName.$name"] = $type;
    }

    public function resolveAttributeArgs(string $schemaName, Attribute $attribute, bool $input = false): array
    {
        $args = [];

        foreach ($attribute->args ?? [] as $name => $arg) {
            $type = $arg['type'] ?? 'String';
            $description = $arg['description'] ?? null;

            if (str_ends_with($type, '!')) {
                $type = substr($type, 0, -1);
                $allowsNull = false;
            } else {
                $allowsNull = true;
            }

            if (isset($this->types[$type])) {
                $type = $this->types[$type];
            } elseif (isset($this->types["$schemaName.$type"])) {
                $type = $this->types["$schemaName.$type"];
            } else {
                $reflection = $this->resourceLoader->getResourceByClassName($schemaName, $type)['reflection'] ?? null;
                $classPath = $reflection?->getName() ?? $type;
                $type = $this->createType($schemaName, $classPath, $input);
            }

            if (!$allowsNull) {
                $type = Type::nonNull($type);
            }

            $args[$name] = [
                'type' => $type,
                'description' => $description,
            ];
        }

        return $args;
    }

    private function resolveTypeFromSchema(?string $type, string $schemaName)
    {
        if (!$type) {
            return null;
        }

        preg_match('/([a-zA-Z0-9\\\]+)/', $type, $matches);
        $type = $matches[1];

        $reflectionClass = $this->resourceLoader->getReflectionClass($schemaName, $type);

        if (!$reflectionClass) {
            $reflectionClass = $this->resourceLoader->getResourceByClassName($schemaName, $type)['reflection'] ?? null;
        }

        return $reflectionClass?->getName() ?? $type;
    }
}

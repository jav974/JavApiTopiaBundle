<?php

namespace Jav\ApiTopiaBundle\GraphQL;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\NullableType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\PhpEnumType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\InterfaceType;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Query;
use Jav\ApiTopiaBundle\GraphQL\Relay\GlobalId;
use Jav\ApiTopiaBundle\GraphQL\Relay\RelayTypes;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\ApiResource;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Attribute;
use Jav\ApiTopiaBundle\Serializer\Serializer;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class TypeResolver
{
    private InterfaceType $nodeInterface;

    /** @var array<string, array<string, Query|null>> Item queries able to refetch a node, by schema and type name */
    private array $nodeQueries = [];

    /** @var (callable(string, string): ?Query)|null Finds the item query of a type without loading every resource */
    private $nodeQueryLocator = null;

    public function __construct(
        private readonly ResourceLoader $resourceLoader,
        private readonly ResolverProvider $resolverProvider,
        private readonly Serializer $serializer,
        private readonly ReflectionUtils $reflectionUtils,
        private readonly TypeRegistry $typeRegistry,
        private readonly FieldsBuilder $fieldsBuilder,
        private readonly ?NodeResolverInterface $nodeResolver = null
    ) {
        $this->nodeInterface = RelayTypes::nodeInterface(fn ($object, $context, ResolveInfo $info) => $this->resolveNodeType($object, $info));
        $this->typeRegistry->register('Node', $this->nodeInterface);
        $this->fieldsBuilder->setTypeResolver($this);
    }

    /**
     * Finds the concrete GraphQL object type of a value returned by the node resolver.
     */
    private function resolveNodeType(mixed $object, ResolveInfo $info): ObjectType
    {
        $typeName = null;

        if (is_object($object)) {
            $typeName = (new ReflectionClass($object))->getShortName();
        } elseif (is_array($object) && isset($object['__typename']) && is_string($object['__typename'])) {
            $typeName = $object['__typename'];
        }

        $type = $typeName !== null && $info->schema->hasType($typeName) ? $info->schema->getType($typeName) : null;

        if (!$type instanceof ObjectType) {
            throw new RuntimeException(sprintf(
                'Cannot resolve the GraphQL type of the node returned by %s: return an object whose short class name is a type of the schema, or an array with a "__typename" key (got %s).',
                NodeResolverInterface::class,
                $typeName !== null ? sprintf('type "%s"', $typeName) : get_debug_type($object)
            ));
        }

        return $type;
    }

    /**
     * The "node" and "nodes" root fields of a schema.
     *
     * @return array{node: array<string, mixed>, nodes: array<string, mixed>}
     */
    public function getNodeFields(string $schemaName): array
    {
        $fetch = fn (string $globalId, mixed $context, ResolveInfo $info) => $this->fetchNode($schemaName, $globalId, $context, $info);

        return [
            'node' => RelayTypes::nodeField($this->nodeInterface, $fetch),
            'nodes' => RelayTypes::nodesField($this->nodeInterface, $fetch),
        ];
    }

    /**
     * Refetches an object from its global ID.
     *
     * The application NodeResolverInterface is used when there is one. Otherwise the object is refetched with the
     * item Query of its type that takes an "id" argument, so node(id) returns the identical object (Relay refetch).
     */
    public function fetchNode(string $schemaName, string $globalId, mixed $context, ResolveInfo $info): mixed
    {
        $decoded = GlobalId::decode($globalId);

        if ($decoded === null) {
            return null;
        }

        if ($this->nodeResolver !== null) {
            return $this->nodeResolver->resolve($decoded['type'], $decoded['id']);
        }

        $parts = explode('.', $decoded['type'], 2);

        if (count($parts) !== 2 || $parts[0] !== $schemaName) {
            return null;
        }

        $query = $this->getNodeQuery($schemaName, $parts[1]);

        if ($query === null) {
            return null;
        }

        $id = $this->castNodeId($decoded['id'], $query->args['id']['type'] ?? 'ID');

        if ($id === null) {
            return null;
        }

        $resolve = $this->resolverProvider->getResolveCallback($schemaName, $query);

        return $resolve(null, ['id' => $id], $context, $info);
    }

    /**
     * @param callable(string, string): ?Query $locator Receives the schema and type names
     */
    public function setNodeQueryLocator(callable $locator): void
    {
        $this->nodeQueryLocator = $locator;
    }

    /**
     * The item query refetching nodes of a type, found through the schema index (see SchemaBuilder).
     */
    private function getNodeQuery(string $schemaName, string $typeName): ?Query
    {
        if ($this->nodeQueryLocator === null) {
            return null;
        }

        return $this->nodeQueries[$schemaName][$typeName] ??= ($this->nodeQueryLocator)($schemaName, $typeName);
    }

    /**
     * Converts the raw identifier to the type of the "id" argument of the item query (ID and String stay strings).
     */
    private function castNodeId(string $id, string $argType): int|string|null
    {
        if (rtrim($argType, '!') !== 'Int') {
            return $id;
        }

        return preg_match('/^-?\d+$/', $id) === 1 ? (int) $id : null;
    }

    public function resolve(string $schemaName, string $classPath, bool $isCollection, bool $input = false): Type&NullableType
    {
        $typeName = match ($classPath) {
            UploadedFile::class, 'UploadedFile' => 'Upload',
            DateTimeInterface::class, DateTime::class, DateTimeImmutable::class => 'String',
            default => ReflectionUtils::getClassNameFromClassPath($classPath),
        };

        if (in_array($typeName, ['int', 'float', 'string', 'boolean', 'bool', 'ID'])) {
            $typeName = $typeName === 'bool' ? 'boolean' : $typeName;
            $typeName = ucfirst($typeName);
        }

        if ($input) {
            $typeName = "input.$typeName";
        }

        if (!$this->typeRegistry->has($typeName) && !$this->typeRegistry->has("$schemaName.$typeName")) {
            $this->typeRegistry->register("$schemaName.$typeName", $this->createType($schemaName, $classPath, $input));
        }

        $type = $this->getType($typeName, $schemaName);

        if ($isCollection) {
            return Type::listOf(Type::nonNull($type));
        }

        return $type;
    }

    /**
     * @return (Type&NullableType)|null
     */
    public function findType(string $name, string $schemaName): ?Type
    {
        if ($this->typeRegistry->has($name)) {
            return $this->typeRegistry->get($name);
        }

        return $this->typeRegistry->has("$schemaName.$name") ? $this->typeRegistry->get("$schemaName.$name") : null;
    }

    public function getType(string $name, ?string $schemaName = null): Type&NullableType
    {
        if ($this->typeRegistry->has($name)) {
            return $this->typeRegistry->get($name);
        }

        if ($schemaName !== null) {
            $name = "$schemaName.$name";
        }

        return $this->typeRegistry->get($name);
    }

    private function createType(string $schemaName, string $classPath, bool $input = false): Type&NullableType
    {
        $reflectionClass = $this->resourceLoader->getReflectionClass($schemaName, $classPath);

        if (!$reflectionClass) {
            throw new RuntimeException(sprintf('Class/Enum "%s" not found in schema "%s".', $classPath, $schemaName));
        }

        if ($reflectionClass->isEnum()) {
            return $this->createEnumType($schemaName, $reflectionClass);
        }

        return $input ? $this->createInputType($schemaName, $reflectionClass) : $this->createObjectType($schemaName, $reflectionClass);
    }

    /**
     * @param ReflectionClass<object> $reflectionClass
     */
    private function createObjectType(string $schemaName, ReflectionClass $reflectionClass): ObjectType
    {
        // If it is an ApiResource, make it available in the schema with Relay specification
        $attribute = ($reflectionClass->getAttributes(ApiResource::class)[0] ?? null)?->newInstance();
        $isNodeInterface = (bool)($attribute);
        $objectTypeData = [
            'name' => $reflectionClass->getShortName(),
            'description' => $attribute->description ?? ReflectionUtils::getDescriptionFromDocComment($reflectionClass->getDocComment() ?: null),
            // Types created while building these fields belong to this type (lazy schema index)
            'fields' => fn () => $this->typeRegistry->withOwner('type:' . $reflectionClass->getShortName(), function () use ($reflectionClass, $schemaName, $isNodeInterface) {
                $properties = $this->reflectionUtils->extractFieldsInfoFromReflectionClass($reflectionClass);
                $fields = [];

                if ($isNodeInterface) {
                    $fields['id'] = RelayTypes::globalIdField(
                        "$schemaName.{$reflectionClass->getShortName()}",
                        fn ($object) => is_object($object) ? $object->id : (is_array($object) ? $object['id'] : null)
                    );
                }

                foreach ($properties as $fieldInfo) {
                    $propertyName = $fieldInfo['name'];
                    $propertyMetadata = $fieldInfo['metadata'];

                    if ($propertyName === 'id' && $isNodeInterface) {
                        $propertyName = '_id';
                    }

                    $fields[$propertyName] = $this->fieldsBuilder->getObjectTypeField(
                        $schemaName,
                        $fieldInfo['type'],
                        $fieldInfo['description'],
                        $fieldInfo['allowsNull'],
                        $fieldInfo['attribute'],
                        $fieldInfo['isCollection']
                    );

                    if ($propertyName === '_id' && $isNodeInterface) {
                        $fields[$propertyName]['resolve'] = fn ($object) => is_object($object) ? $object->id : (is_array($object) ? $object['id'] : null);
                    } elseif (!$fieldInfo['attribute']) {
                        $fields[$propertyName]['resolve'] = function ($object) use ($propertyName, $propertyMetadata, $fieldInfo) {
                            if ($fieldInfo['isEnum']) {
                                return $object->{$propertyName};
                            }

                            if (is_object($object) && isset($object->{$propertyName})) {
                                return $this->serializer->normalize(
                                    $object->{$propertyName},
                                    null,
                                    $propertyMetadata?->getNormalizationContexts()['*'] ?? []
                                );
                            }

                            return is_array($object) ? $object[$propertyName] ?? null : null;
                        };
                    }
                }

                return $fields;
            }),
        ];

        if ($isNodeInterface) {
            $objectTypeData['interfaces'] = [$this->nodeInterface];
        }

        $type = new ObjectType($objectTypeData);

        $this->typeRegistry->register($schemaName . '.' . $reflectionClass->getShortName(), $type);

        return $type;
    }

    /**
     * @param ReflectionClass<object> $reflectionClass
     */
    private function createInputType(string $schemaName, ReflectionClass $reflectionClass): InputObjectType
    {
        $description = ReflectionUtils::getDescriptionFromDocComment($reflectionClass->getDocComment() ?: null);
        $type = new InputObjectType([
            'name' => $reflectionClass->getShortName(),
            'description' => $description,
            'fields' => fn () => $this->typeRegistry->withOwner('type:' . $reflectionClass->getShortName(), function () use ($reflectionClass, $schemaName) {
                $properties = $this->reflectionUtils->extractFieldsInfoFromReflectionClass($reflectionClass);
                $fields = [];

                foreach ($properties as $fieldName => $fieldInfo) {
                    $type = $this->resolveTypeFromSchema($fieldInfo['type'], $schemaName);
                    $type = $this->resolve($schemaName, $type, $fieldInfo['isCollection'], !$fieldInfo['isBuiltin']);
                    $fields[$fieldName] = [
                        'type' => $fieldInfo['allowsNull'] ? $type : Type::nonNull($type),
                        'description' => $fieldInfo['description'],
                    ];
                }

                return $fields;
            }),
        ]);

        $this->typeRegistry->register($schemaName . '.' . $reflectionClass->getShortName(), $type);

        return $type;
    }

    /**
     * @param ReflectionClass<object>|\ReflectionEnum<\UnitEnum> $reflectionEnum
     */
    private function createEnumType(string $schemaName, \ReflectionClass|\ReflectionEnum $reflectionEnum): Type&NullableType
    {
        $type = new PhpEnumType($reflectionEnum->getName());

        $this->typeRegistry->register($schemaName . '.' . $reflectionEnum->getShortName(), $type);

        return $type;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
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

            if ($this->typeRegistry->has($type)) {
                $type = $this->typeRegistry->get($type);
            } elseif ($this->typeRegistry->has("$schemaName.$type")) {
                $type = $this->typeRegistry->get("$schemaName.$type");
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

    public function resolveTypeFromSchema(string $type, string $schemaName): string
    {
        return $this->resourceLoader->getReflectionClass($schemaName, $type)?->getName() ?? $type;
    }
}

<?php

namespace Jav\ApiTopiaBundle\GraphQL;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\NullableType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\WrappingType;
use GraphQL\Upload\UploadType;
use GraphQLRelay\Connection\Connection;
use GraphQLRelay\Relay;
use InvalidArgumentException;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\ApiResource;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Attribute;
use Jav\ApiTopiaBundle\Serializer\Serializer;
use ReflectionClass;
use RuntimeException;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\QueryCollection;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class TypeResolver
{
    /** @var array<string, Type&NullableType> */
    private array $types;
    /** @var array<string, Type&NullableType>  */
    private array $nodeDefinition;

    public function __construct(
        private readonly ResourceLoader $resourceLoader,
        private readonly ResolverProvider $resolverProvider,
        private readonly Serializer $serializer,
        private readonly ReflectionUtils $reflectionUtils,
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
        $this->types['input.Upload'] = new UploadType();
        $this->types['Upload'] =& $this->types['input.Upload'];
    }

    /**
     * @return array<string, Type&NullableType>
     */
    public function getNodeDefinition(): array
    {
        return $this->nodeDefinition;
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

        if (!isset($this->types[$typeName]) && !isset($this->types["$schemaName.$typeName"])) {
            $this->types["$schemaName.$typeName"] = $this->createType($schemaName, $classPath, $input);
        }

        $type = $this->getType($typeName, $schemaName);

        if ($isCollection) {
            return Type::listOf(Type::nonNull($type));
        }

        return $type;
    }

    public function getType(string $name, ?string $schemaName = null): Type&NullableType
    {
        if (isset($this->types[$name])) {
            return $this->types[$name];
        }

        if ($schemaName !== null) {
            $name = "$schemaName.$name";
        }

        return $this->types[$name] ?? throw new InvalidArgumentException(sprintf('Type "%s" not found.', $name));
    }

    private function createType(string $schemaName, string $classPath, bool $input = false): Type&NullableType
    {
        $reflectionClass = $this->resourceLoader->getReflectionClass($schemaName, $classPath);

        if (!$reflectionClass) {
            throw new RuntimeException(sprintf('Class "%s" not found in schema "%s".', $classPath, $schemaName));
        }

        return $input ? $this->createInputType($schemaName, $reflectionClass) : $this->createObjectType($schemaName, $reflectionClass);
    }

    /**
     * @return array<string, mixed>
     */
    public function getObjectTypeField(string $schemaName, string $classPath, ?string $comment, bool $allowsNull, ?Attribute $attribute, bool $isCollection): array
    {
        $field = [];
        $outputClassName = ReflectionUtils::getClassNameFromClassPath($classPath);
        $type = $this->resolveTypeFromSchema($classPath, $schemaName);
        $type = $this->resolve($schemaName, $type, $isCollection);

        if (!$allowsNull) {
            $type = Type::nonNull($type);
        }

        if ($attribute) {
            $field['args'] = $this->resolveAttributeArgs($schemaName, $attribute);
            $field['resolve'] = $this->resolverProvider->getResolveCallback($schemaName, $attribute);
        }

        if ($attribute instanceof QueryCollection && $attribute->paginationEnabled) {
            if ($attribute->paginationType === QueryCollection::PAGINATION_TYPE_CURSOR) {
                $type = $this->getRelayConnection($schemaName, $outputClassName, $type);
                $field['args'] += Relay::connectionArgs();
            } elseif ($attribute->paginationType === QueryCollection::PAGINATION_TYPE_OFFSET) {
                $type = $this->getOffsetConnection($schemaName, $outputClassName, $type);
                $field['args'] += $this->getOffsetConnectionArgs();
            }
        }

        $field['type'] = $type;

        if ($comment) {
            $field['description'] = $comment;
        }

        return $field;
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
            'description' => $attribute?->description ?? ReflectionUtils::getDescriptionFromDocComment($reflectionClass->getDocComment() ?: null),
            'fields' => function () use ($reflectionClass, $schemaName, $isNodeInterface) {
                $properties = $this->reflectionUtils->extractFieldsInfoFromReflectionClass($reflectionClass);
                $fields = [];

                if ($isNodeInterface) {
                    $fields['id'] = Relay::globalIdField(
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

                    $fields[$propertyName] = $this->getObjectTypeField(
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
                        $fields[$propertyName]['resolve'] = function ($object) use ($propertyName, $propertyMetadata) {
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
            },
        ];

        if ($isNodeInterface) {
            $objectTypeData['interfaces'] = [$this->nodeDefinition['nodeInterface']];
        }

        $type = new ObjectType($objectTypeData);

        $this->types[$schemaName . '.' . $reflectionClass->getShortName()] = $type;

        return $type;
    }

    private function getOffsetConnection(string $schemaName, string $outputClassName, Type $type): ObjectType
    {
        if ($type instanceof WrappingType) {
            $type = $type->getInnerMostType();
        }

        $connectionName = $outputClassName . 'OffsetConnection';

        // @phpstan-ignore-next-line
        return $this->types["$schemaName.$connectionName"] ??= new ObjectType([
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
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getOffsetConnectionArgs(): array
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

    private function getRelayConnection(string $schemaName, string $outputClassName, Type $type): ObjectType
    {
        if ($type instanceof WrappingType) {
            $type = $type->getInnerMostType();
        }

        $edgeName = $outputClassName . 'Edge';
        $connectionName = $outputClassName . 'CursorConnection';

        $edge = $this->types["$schemaName.$edgeName"] ??= Relay::edgeType([
            'nodeType' => $type,
            'name' => $outputClassName,
        ]);

        // @phpstan-ignore-next-line
        return $this->types["$schemaName.$connectionName"] ??= Relay::connectionType([
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
            'fields' => function () use ($reflectionClass, $schemaName) {
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
            },
        ]);

        $this->types[$schemaName . '.' . $reflectionClass->getShortName()] = $type;

        return $type;
    }

    public function setType(string $schemaName, string $name, Type&NullableType $type): void
    {
        $this->types["$schemaName.$name"] = $type;
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

    private function resolveTypeFromSchema(string $type, string $schemaName): string
    {
        return $this->resourceLoader->getReflectionClass($schemaName, $type)?->getName() ?? $type;
    }
}

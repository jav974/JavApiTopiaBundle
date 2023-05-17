<?php

namespace Jav\ApiTopiaBundle\GraphQL;

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Attribute;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\SubQueryCollection;
use RuntimeException;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\QueryCollection;

class TypeResolver
{
    private array $types;

    public function __construct(private readonly ResourceLoader $resourceLoader, private readonly ResolverProvider $resolverProvider)
    {
        $this->types = Type::getStandardTypes();
    }

    public function resolve(string $schemaName, string $classPath, bool $isCollection, bool $input = false): Type
    {
        $typeName = basename($classPath);

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

    private function createObjectType(string $schemaName, \ReflectionClass $reflectionClass): ObjectType
    {
        $description = TypeUtils::getDescriptionFromDocComment($reflectionClass->getDocComment() ?: null);

        $type = new ObjectType([
            'name' => $reflectionClass->getShortName(),
            'description' => $description,
            'fields' => function () use ($reflectionClass, $schemaName) {
                $fields = [];

                foreach ($reflectionClass->getProperties() as $reflectionProperty) {
                    $queryCollectionAttribute = $reflectionProperty->getAttributes(SubQueryCollection::class)[0] ?? null;
                    $comment = $reflectionProperty->getDocComment() ?: null;
                    /** @var QueryCollection $queryCollection */
                    $queryCollection = $queryCollectionAttribute?->newInstance();
                    $type = $reflectionProperty->getType()?->getName();
                    $isCollection = in_array($type ?? '', ['iterable', 'array']);
                    $type = $isCollection ? $queryCollection?->output ?? TypeUtils::getTypeFromDocComment($comment) : $type;
                    $type = $this->resolveTypeFromSchema($type, $schemaName);
                    $allowsNull = $reflectionProperty->getType()?->allowsNull() ?? true;
                    $type = $this->resolve($schemaName, $type, $isCollection);

                    if (!$allowsNull) {
                        $type = Type::nonNull($type);
                    }

                    $propertyName = $reflectionProperty->getName();

                    $fields[$propertyName] = [
                        'type' => $type,
                        'description' => TypeUtils::getDescriptionFromDocComment($comment),
                    ];

                    if ($queryCollection) {
                        $fields[$propertyName]['args'] = $this->resolveAttributeArgs($schemaName, $queryCollection);
                        $fields[$propertyName]['resolve'] = $this->resolverProvider->getResolveCallback($queryCollection);
                    }
                }

                return $fields;
            },
        ]);

        $this->types[$schemaName . '.' . $reflectionClass->getShortName()] = $type;

        return $type;
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

    public function resolveMutationAttributeArgs(string $schemaName, Attribute $attribute): InputObjectType
    {
        $name = "{$attribute->name}Input";

        return $this->types[$name] ??= new InputObjectType([
            'name' => $name,
            'fields' => $this->resolveAttributeArgs($schemaName, $attribute, true),
        ]);
    }

    public function resolveMutationOutput(string $schemaName, string $fieldName, string $outputType, Attribute $attribute): ObjectType
    {
        $name = "{$attribute->name}Output";

        return $this->types[$name] ??= new ObjectType([
            'name' => $name,
            'fields' => [
                $fieldName => [
                    'type' => $this->resolve($schemaName, $outputType, false)
                ]
            ],
        ]);
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

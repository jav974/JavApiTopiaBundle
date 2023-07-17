<?php

namespace Jav\ApiTopiaBundle\GraphQL;

use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\SubQuery;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\SubQueryCollection;
use Jav\ApiTopiaBundle\Serializer\Serializer;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;

class ReflectionUtils
{
    public function __construct(
        private readonly ResourceLoader $resourceLoader,
        private readonly Serializer $serializer
    ) {
    }

    /**
     * @param ReflectionClass<object> $reflectionClass
     * @return array<string, mixed>
     */
    public function extractFieldsInfoFromReflectionClass(ReflectionClass $reflectionClass): array
    {
        $properties = $this->serializer->getPropertyInfoExtractor()->getProperties($reflectionClass->getName());
        $properties = array_fill_keys($properties ?? [], null);
        $metadata = $this->resourceLoader->getClassMetatadaFactory()->getMetadataFor($reflectionClass->getName())->getAttributesMetadata();
        $propertyInfoExtractor = $this->serializer->getPropertyInfoExtractor();

        foreach ($properties as $name => &$info) {
            $types = $propertyInfoExtractor->getTypes($reflectionClass->getName(), $name);
            $type = $types[0] ?? null;

            if ($type === null) {
                continue;
            }

            try {
                $reflectionProperty = new ReflectionProperty($reflectionClass->getName(), $name);
                $attribute = $reflectionProperty->getAttributes(SubQueryCollection::class)[0] ?? $reflectionProperty->getAttributes(SubQuery::class)[0] ?? null;
                $isBuiltin = $reflectionProperty->getType() instanceof ReflectionNamedType && $reflectionProperty->getType()->isBuiltin();
            } catch (ReflectionException) {
                $attribute = null;
                $isBuiltin = false;
            }

            $collectionValueType = $type->getCollectionValueTypes()[0] ?? null;
            $innerType = !$type->isCollection() ? $type : $collectionValueType;

            $info = [
                'name' => $name,
                'type' => $innerType?->getClassName() ?? $innerType?->getBuiltinType() ?? 'string',
                'isCollection' => $type->isCollection(),
                'allowsNull' => $type->isNullable(),
                'description' => $propertyInfoExtractor->getLongDescription($reflectionClass->getName(), $name)
                    ?? $propertyInfoExtractor->getShortDescription($reflectionClass->getName(), $name) ?? null,
                'isBuiltin' => $isBuiltin,
                'attribute' => $attribute?->newInstance(),
                'metadata' => $metadata[$name] ?? null,
            ];
        }

        return $properties;
    }

    public static function getDescriptionFromDocComment(?string $docComment): ?string
    {
        $docComment = explode("\n", $docComment ?? '');
        $comment = '';

        foreach ($docComment as $line) {
            if (preg_match('/\*\s+(.*)/', $line, $matches)) {
                $line = $matches[1];

                if (!str_starts_with($line, '@')) {
                    $comment .= $line . "\n";
                }
            }
        }

        $comment = trim($comment);
        return !empty($comment) ? $comment : null;
    }

    public static function getClassNameFromClassPath(string $classPath): string
    {
        return str_contains($classPath, '\\') ? substr($classPath, strrpos($classPath, '\\') + 1) : $classPath;
    }
}

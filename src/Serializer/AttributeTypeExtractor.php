<?php

namespace Jav\ApiTopiaBundle\Serializer;

use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Attribute;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\QueryCollection;
use Symfony\Component\PropertyInfo\PropertyDescriptionExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\PropertyInfo\Type as LegacyType;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\Serializer\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;

class AttributeTypeExtractor implements PropertyTypeExtractorInterface, PropertyDescriptionExtractorInterface
{
    public function __construct(private readonly ClassMetadataFactoryInterface $classMetadataFactory)
    {
    }

    /**
     * Symfony >= 7.1 (TypeInfo).
     *
     * @param array<mixed> $context
     */
    public function getType(string $class, string $property, array $context = []): ?Type
    {
        $attribute = $this->getAttributeForProperty($class, $property);

        if ($attribute?->output === null || !class_exists(Type::class)) {
            return null;
        }

        $objectType = Type::object($attribute->output);

        return Type::nullable($attribute instanceof QueryCollection ? Type::list($objectType) : $objectType);
    }

    /**
     * Symfony < 8.0 (legacy PropertyInfo types).
     *
     * @param array<mixed> $context
     * @return LegacyType[]|null
     */
    public function getTypes(string $class, string $property, array $context = []): ?array
    {
        $attribute = $this->getAttributeForProperty($class, $property);

        if ($attribute?->output === null) {
            return null;
        }

        $isCollection = $attribute instanceof QueryCollection;

        return [
            new LegacyType(
                $isCollection ? LegacyType::BUILTIN_TYPE_ARRAY : LegacyType::BUILTIN_TYPE_OBJECT,
                true,
                $isCollection ? null : $attribute->output,
                $isCollection,
                null,
                !$isCollection ? null : new LegacyType(
                    LegacyType::BUILTIN_TYPE_OBJECT,
                    true,
                    $attribute->output
                ),
            )
        ];
    }

    /**
     * @param array<mixed> $context
     */
    public function getShortDescription(string $class, string $property, array $context = []): ?string
    {
        return $this->getAttributeForProperty($class, $property)?->description;
    }

    /**
     * @param array<mixed> $context
     */
    public function getLongDescription(string $class, string $property, array $context = []): ?string
    {
        return $this->getAttributeForProperty($class, $property)?->description;
    }

    private function getAttributeForProperty(string $class, string $property): ?Attribute
    {
        /** @var ClassMetadata $metadata */
        $metadata = $this->classMetadataFactory->getMetadataFor($class);
        try {
            return ($metadata->getReflectionClass()->getProperty($property)->getAttributes(
                Attribute::class,
                \ReflectionAttribute::IS_INSTANCEOF
            )[0] ?? null)?->newInstance();
        } catch (\ReflectionException) {
            return null;
        }
    }
}

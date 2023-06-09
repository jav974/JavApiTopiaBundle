<?php

namespace Jav\ApiTopiaBundle\Serializer;

use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Attribute;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\QueryCollection;
use Symfony\Component\PropertyInfo\PropertyDescriptionExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;

class AttributeTypeExtractor implements PropertyTypeExtractorInterface, PropertyDescriptionExtractorInterface
{
    public function __construct(private readonly ClassMetadataFactoryInterface $classMetadataFactory)
    {
    }

    /**
     * @param array<mixed> $context
     * @return Type[]|null
     */
    public function getTypes(string $class, string $property, array $context = []): ?array
    {
        $attribute = $this->getAttributeForProperty($class, $property);

        if ($attribute?->output === null) {
            return null;
        }

        $isCollection = $attribute instanceof QueryCollection;

        return [
            new Type(
                $isCollection ? Type::BUILTIN_TYPE_ARRAY : Type::BUILTIN_TYPE_OBJECT,
                true,
                $isCollection ? Type::BUILTIN_TYPE_ARRAY : $attribute->output,
                $isCollection,
                null,
                !$isCollection ? null : new Type(
                    Type::BUILTIN_TYPE_OBJECT,
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

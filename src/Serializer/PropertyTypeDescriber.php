<?php

namespace Jav\ApiTopiaBundle\Serializer;

use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\Type as LegacyType;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\CompositeTypeInterface;
use Symfony\Component\TypeInfo\Type\NullableType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\WrappingTypeInterface;

/**
 * Describes a property type in a format independent of the Symfony version.
 *
 * Symfony < 7.2 only provides PropertyInfo "getTypes()" (legacy Type objects), Symfony 8 only provides
 * TypeInfo through "getType()". This class reads whichever is available.
 *
 * @internal
 *
 * @phpstan-type TypeDescription array{className: ?string, builtin: ?string, isCollection: bool, nullable: bool}
 */
final class PropertyTypeDescriber
{
    public static function supportsTypeInfo(): bool
    {
        return class_exists(NullableType::class) && method_exists(PropertyInfoExtractor::class, 'getType');
    }

    /**
     * @return TypeDescription|null
     */
    public static function describe(PropertyInfoExtractor $extractor, string $class, string $property): ?array
    {
        if (self::supportsTypeInfo()) {
            $type = $extractor->getType($class, $property);

            return $type !== null ? self::describeTypeInfo($type) : null;
        }

        /** @var LegacyType[]|null $types */
        $types = $extractor->getTypes($class, $property); // @phpstan-ignore method.notFound
        $type = $types[0] ?? null;

        return $type !== null ? self::describeLegacy($type) : null;
    }

    /**
     * @return TypeDescription
     */
    private static function describeLegacy(LegacyType $type): array
    {
        $innerType = !$type->isCollection() ? $type : ($type->getCollectionValueTypes()[0] ?? null);

        return [
            'className' => $innerType?->getClassName(),
            'builtin' => $innerType?->getBuiltinType(),
            'isCollection' => $type->isCollection(),
            'nullable' => $type->isNullable(),
        ];
    }

    /**
     * @return TypeDescription
     */
    private static function describeTypeInfo(Type $type): array
    {
        $nullable = $type->isNullable();
        $type = self::unwrap($type);
        $isCollection = false;

        if ($type instanceof CollectionType) {
            $isCollection = true;
            $value = self::unwrap($type->getCollectionValueType());
            $type = $value instanceof BuiltinType && $value->getTypeIdentifier()->value === 'mixed' ? null : $value;
        } elseif ($type instanceof BuiltinType && in_array($type->getTypeIdentifier()->value, ['array', 'iterable'], true)) {
            $isCollection = true;
            $type = null;
        }

        return [
            'className' => $type instanceof ObjectType ? $type->getClassName() : null,
            'builtin' => match (true) {
                $type instanceof ObjectType => 'object',
                $type instanceof BuiltinType => self::normalizeBuiltin($type->getTypeIdentifier()->value),
                default => null,
            },
            'isCollection' => $isCollection,
            'nullable' => $nullable,
        ];
    }

    /**
     * Removes nullability and picks the first member of union types.
     */
    private static function unwrap(Type $type): Type
    {
        while (true) {
            if ($type instanceof NullableType) {
                $type = $type->getWrappedType();
                continue;
            }

            if ($type instanceof CompositeTypeInterface) {
                $candidates = array_values(array_filter(
                    $type->getTypes(),
                    fn (Type $t) => !($t instanceof BuiltinType && $t->getTypeIdentifier()->value === 'null')
                ));

                if (isset($candidates[0])) {
                    $type = $candidates[0];
                    continue;
                }
            }

            if ($type instanceof WrappingTypeInterface && !$type instanceof CollectionType) {
                $type = $type->getWrappedType();
                continue;
            }

            return $type;
        }
    }

    private static function normalizeBuiltin(string $identifier): string
    {
        return match ($identifier) {
            'true', 'false' => 'bool',
            default => $identifier,
        };
    }
}

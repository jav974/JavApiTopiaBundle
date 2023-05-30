<?php

namespace Jav\ApiTopiaBundle\GraphQL;

use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\SubQuery;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\SubQueryCollection;
use ReflectionNamedType;
use ReflectionProperty;

class ReflectionUtils
{
    /**
     * @return array<string, mixed>
     */
    public static function extractFieldInfoFromProperty(ReflectionProperty $reflectionProperty): array
    {
        $attribute = $reflectionProperty->getAttributes(SubQueryCollection::class)[0] ?? $reflectionProperty->getAttributes(SubQuery::class)[0] ?? null;
        $comment = $reflectionProperty->getDocComment() ?: null;
        /** @var SubQuery|SubQueryCollection|null $attributeInstance */
        $attributeInstance = $attribute?->newInstance();
        $reflectionType = $reflectionProperty->getType();
        $type = null;

        if ($reflectionType instanceof ReflectionNamedType) {
            $type = $reflectionType->getName();
        }

        $isCollection = in_array($type ?? '', ['iterable', 'array']);
        $docCommentType = self::getTypeFromDocComment($comment);
        $isCollection = $isCollection || str_contains($docCommentType ?? '', '[]');
        $docCommentType = $docCommentType ? str_replace('[]', '', $docCommentType) : null;
        $type = $isCollection ? $attributeInstance?->output ?? $docCommentType ?? $type : $type;
        $propertyName = $reflectionProperty->getName();

        return [
            'name' => $propertyName,
            'description' => self::getDescriptionFromDocComment($comment),
            'type' => $type,
            'isCollection' => $isCollection,
            'attribute' => $attributeInstance,
            'allowsNull' => $reflectionType?->allowsNull() ?? true,
            'isBuiltin' => $reflectionType instanceof ReflectionNamedType && $reflectionType->isBuiltin(),
        ];
    }

    public static function getTypeFromDocComment(?string $docComment): ?string
    {
        if (preg_match('/@var\s+(\S+)/', $docComment ?? '', $matches)) {
            $types = array_map('trim', explode("|", $matches[1]));

            foreach ($types as $type) {
                if (strtolower($type) !== 'null') {
                    return $type;
                }
            }

            return null;
        }

        return null;
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

<?php

namespace Jav\ApiTopiaBundle\GraphQL;

use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\QueryCollection;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\SubQueryCollection;
use JetBrains\PhpStorm\ArrayShape;

class ReflectionUtils
{
    #[ArrayShape([
        'name' => "string",
        'description' => "string|null",
        'type' => "string|null",
        'isCollection' => "bool",
        'queryCollection' => "QueryCollection|null",
        'allowsNull' => "bool"
    ])]
    public static function extractFieldInfoFromProperty(\ReflectionProperty $reflectionProperty): array
    {
        $queryCollectionAttribute = $reflectionProperty->getAttributes(SubQueryCollection::class)[0] ?? null;
        $comment = $reflectionProperty->getDocComment() ?: null;
        /** @var QueryCollection|null $queryCollection */
        $queryCollection = $queryCollectionAttribute?->newInstance();
        $type = $reflectionProperty->getType()?->getName();
        $isCollection = in_array($type ?? '', ['iterable', 'array']);
        $docCommentType = self::getTypeFromDocComment($comment);
        $isCollection = $isCollection || str_contains($docCommentType ?? '', '[]');
        $docCommentType = $docCommentType ? str_replace('[]', '', $docCommentType) : null;
        $type = $isCollection ? $queryCollection?->output ?? $docCommentType ?? $type : $type;
        $propertyName = $reflectionProperty->getName();

        return [
            'name' => $propertyName,
            'description' => self::getDescriptionFromDocComment($comment),
            'type' => $type,
            'isCollection' => $isCollection,
            'queryCollection' => $queryCollection,
            'allowsNull' => $reflectionProperty->getType()?->allowsNull() ?? true
        ];
    }

    public static function getTypeFromDocComment(?string $docComment): ?string
    {
        if (preg_match('/@var\s+(\S+)/', $docComment ?? '', $matches)) {
            return $matches[1];
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

<?php

namespace Jav\ApiTopiaBundle\GraphQL;

class TypeUtils
{
    public static function getTypeFromDocComment(?string $docComment): ?string
    {
        if (preg_match('/@var\s+([^\s]+)/', $docComment ?? '', $matches)) {
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
}
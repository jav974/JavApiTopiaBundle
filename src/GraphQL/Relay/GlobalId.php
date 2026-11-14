<?php

namespace Jav\ApiTopiaBundle\GraphQL\Relay;

/**
 * Global Object Identification: opaque, globally unique identifiers.
 *
 * Format: base64("<type>:<id>"), compatible with graphql-relay-js and ivome/graphql-relay-php,
 * so identifiers already handed to clients keep working.
 *
 * @see https://relay.dev/graphql/objectidentification.htm
 */
final class GlobalId
{
    public const DELIMITER = ':';

    public static function encode(string $type, string|int $id): string
    {
        return base64_encode($type . self::DELIMITER . $id);
    }

    /**
     * @return array{type: string, id: string}|null null when the value is not a global ID
     */
    public static function decode(string $globalId): ?array
    {
        $decoded = base64_decode($globalId, true);

        if ($decoded === false) {
            return null;
        }

        $position = strpos($decoded, self::DELIMITER);

        if ($position === false || $position === 0) {
            return null;
        }

        return [
            'type' => substr($decoded, 0, $position),
            'id' => substr($decoded, $position + 1),
        ];
    }
}

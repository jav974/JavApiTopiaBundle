<?php

namespace Jav\ApiTopiaBundle\GraphQL\Relay;

/**
 * Position based cursors: base64("arrayconnection:<offset>").
 *
 * The format is the one of graphql-relay-js and ivome/graphql-relay-php, so cursors already handed to clients keep working.
 * Clients must treat cursors as opaque strings.
 */
final class ArrayCursor
{
    public const PREFIX = 'arrayconnection:';

    public static function fromOffset(int $offset): string
    {
        return base64_encode(self::PREFIX . $offset);
    }

    /**
     * @return int|null null when the cursor is malformed
     */
    public static function toOffset(?string $cursor): ?int
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }

        $decoded = base64_decode($cursor, true);

        if ($decoded === false || !str_starts_with($decoded, self::PREFIX)) {
            return null;
        }

        $offset = substr($decoded, strlen(self::PREFIX));

        return ctype_digit($offset) ? (int) $offset : null;
    }
}

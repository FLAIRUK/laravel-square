<?php

namespace FLAIRUK\Square\Support;

use InvalidArgumentException;

/**
 * Idempotency keys for Square write requests.
 *
 * Square rejects a reused key if the request body differs, and keeps the
 * result of the first request for a key, so a retried request never charges
 * twice. Keys are UUID-formatted (36 characters), within the 45-character
 * limit of CreatePayment and the other endpoints.
 *
 * @see https://developer.squareup.com/docs/build-basics/common-api-patterns/idempotency
 */
final class IdempotencyKey
{
    /**
     * A random (version 4) UUID, for one-off requests.
     */
    public static function generate(): string
    {
        return self::format(random_bytes(16), 0x40);
    }

    /**
     * A deterministic key derived from the given parts, e.g. for('order', $order->id, 'payment').
     *
     * The same parts always give the same key, so a retried job or a double
     * submit sends the same key and Square returns the original result instead
     * of creating a second payment. Formatted as a version 8 UUID from SHA-256.
     */
    public static function for(string|int ...$parts): string
    {
        if ($parts === []) {
            throw new InvalidArgumentException('IdempotencyKey::for() needs at least one part.');
        }

        $hash = hash('sha256', implode("\0", array_map(strval(...), $parts)), true);

        return self::format(substr($hash, 0, 16), 0x80);
    }

    private static function format(string $bytes, int $version): string
    {
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | $version);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}

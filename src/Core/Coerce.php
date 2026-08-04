<?php

declare(strict_types=1);

namespace GetPayin\Paylink\Core;

use GetPayin\Paylink\Core\Exceptions\PaylinkException;

/**
 * Canonical value → string coercion used for BOTH the signature concatenation
 * and the JSON request body. Deriving both from this single function guarantees
 * the bytes we sign are exactly the bytes we send, so the server (which
 * re-stringifies the decoded JSON when it rebuilds the signature) always agrees.
 *
 * `null` becomes an empty string — this matches PHP's `implode`, which renders
 * null as `''` (relevant for webhook fields such as a null `message`). For
 * request bodies, absent optional fields are dropped before reaching this
 * function, so they never contribute an empty string.
 *
 * Pass monetary amounts as strings ('100.00') when the exact wire form matters;
 * float coercion follows PHP's native string cast and may drop trailing zeros.
 */
final class Coerce
{
    /**
     * Coerce a single value to its canonical wire string.
     *
     * @param  mixed  $value  A scalar, null, or bool provided by the caller.
     *
     * @throws PaylinkException When the value is a non-finite float or a type
     *                          that has no canonical string form (array, object).
     */
    public static function toString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            if (! is_finite($value)) {
                throw new PaylinkException('Cannot serialize non-finite number.');
            }

            return (string) $value;
        }

        throw new PaylinkException('Cannot serialize value of type ' . get_debug_type($value) . '.');
    }
}

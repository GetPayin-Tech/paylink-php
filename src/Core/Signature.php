<?php

declare(strict_types=1);

namespace GetPayin\Paylink\Core;

/**
 * Build a PayLink signature: HMAC-SHA256 over the empty-string concatenation of
 * the already-stringified, already-ordered values, base64-encoded. This mirrors
 * `ExternalPaymentIntegration::buildSignatureString()` on the server
 * (`base64_encode(hash_hmac('sha256', implode('', $data), $hashToken, true))`).
 *
 * Callers are responsible for supplying values in the exact wire order and
 * coercing them with {@see Coerce::toString()}.
 */
final class Signature
{
    /**
     * Compute the base64-encoded HMAC-SHA256 signature for the ordered values.
     *
     * @param  array<int, string>  $orderedValues  Wire values in signature order.
     */
    public static function build(array $orderedValues, string $hashToken): string
    {
        return base64_encode(hash_hmac('sha256', implode('', $orderedValues), $hashToken, true));
    }

    /**
     * Constant-time comparison of two signature strings. Returns false for
     * length mismatches without leaking timing.
     */
    public static function equals(string $expected, string $provided): bool
    {
        return hash_equals($expected, $provided);
    }
}

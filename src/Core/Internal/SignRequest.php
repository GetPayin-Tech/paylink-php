<?php

declare(strict_types=1);

namespace GetPayin\Paylink\Core\Internal;

use GetPayin\Paylink\Core\Coerce;
use GetPayin\Paylink\Core\Signature;

/**
 * Turn a camelCase SDK request array into the signed wire body for an
 * {@see EndpointSpec}. The returned array is ready to POST as JSON: it contains
 * the endpoint's fields (snake_case), the public `token`, and the computed
 * `signature`.
 *
 * The signature is built from the same coerced strings that go into the body, in
 * the spec's order, skipping fields the caller did not provide — exactly how the
 * server reconstructs it from `Arr::except($request->validated(), ...)`.
 */
final class SignRequest
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    public static function build(EndpointSpec $spec, array $input, string $publicToken, string $hashToken): array
    {
        $signedValues = [];
        $body = [];

        $append = static function (mixed $value, string $wireKey, bool $signed) use (&$signedValues, &$body): void {
            if ($value === null) {
                return;
            }

            $stringValue = Coerce::toString($value);
            $body[$wireKey] = $stringValue;

            if ($signed) {
                $signedValues[] = $stringValue;
            }
        };

        foreach ($spec->fields as $field) {
            $append($input[$field->sdk] ?? null, $field->wire, $field->signed);
        }

        if ($spec->countryStateBlock) {
            self::appendCountryStateBlock($input, $append);
        }

        $signature = Signature::build($signedValues, $hashToken);

        return [...$body, 'token' => $publicToken, 'signature' => $signature];
    }

    /**
     * Append the per-country state fields (US: us_state, postal_code; CA:
     * canada_state, postal_code) after the endpoint's listed fields.
     *
     * @param  array<string, mixed>  $input
     */
    private static function appendCountryStateBlock(array $input, callable $append): void
    {
        $country = $input['country'] ?? null;

        if ($country === 'US') {
            $append($input['usState'] ?? null, 'us_state', true);
            $append($input['postalCode'] ?? null, 'postal_code', true);

            return;
        }

        if ($country === 'CA') {
            $append($input['canadaState'] ?? null, 'canada_state', true);
            $append($input['postalCode'] ?? null, 'postal_code', true);
        }
    }
}

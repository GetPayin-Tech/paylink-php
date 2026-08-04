<?php

declare(strict_types=1);

namespace GetPayin\Paylink\Core\Internal;

/**
 * One endpoint's signing contract: the HTTP `method`, the `path`, the ordered
 * `fields`, and `countryStateBlock`, which appends the per-country state fields
 * (us_state/canada_state + postal_code) after `fields`.
 */
final class EndpointSpec
{
    /**
     * @param  array<int, FieldSpec>  $fields
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $fields,
        public readonly bool $countryStateBlock = false,
    ) {}
}

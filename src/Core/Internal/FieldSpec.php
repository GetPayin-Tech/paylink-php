<?php

declare(strict_types=1);

namespace GetPayin\Paylink\Core\Internal;

/**
 * One request field: `sdk` is the camelCase key on the SDK request array, `wire`
 * is the snake_case key sent over the wire, and `signed` (default true) marks
 * whether it contributes to the signature — `false` for fields such as
 * `payment_mode` that are sent but excluded.
 */
final class FieldSpec
{
    public function __construct(
        public readonly string $sdk,
        public readonly string $wire,
        public readonly bool $signed = true,
    ) {}
}

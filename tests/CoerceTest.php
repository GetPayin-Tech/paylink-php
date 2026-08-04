<?php

declare(strict_types=1);

use GetPayin\Paylink\Core\Coerce;
use GetPayin\Paylink\Core\Exceptions\PaylinkException;

it('renders null as an empty string', function (): void {
    expect(Coerce::toString(null))->toBe('');
});

it('passes strings through unchanged', function (): void {
    expect(Coerce::toString('100.00'))->toBe('100.00');
});

it('renders booleans as 1 and 0', function (): void {
    expect(Coerce::toString(true))->toBe('1')
        ->and(Coerce::toString(false))->toBe('0');
});

it('stringifies integers and finite floats', function (): void {
    expect(Coerce::toString(250))->toBe('250')
        ->and(Coerce::toString(10.5))->toBe('10.5');
});

it('rejects non-finite floats', function (): void {
    Coerce::toString(INF);
})->throws(PaylinkException::class);

it('rejects unsupported types', function (): void {
    Coerce::toString(['array']);
})->throws(PaylinkException::class);

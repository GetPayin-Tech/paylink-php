<?php

declare(strict_types=1);

use GetPayin\Paylink\Core\Config;
use GetPayin\Paylink\Core\Exceptions\PaylinkConfigException;

it('requires a non-empty public token', function (): void {
    Config::resolve('', 'secret');
})->throws(PaylinkConfigException::class);

it('requires a non-empty hash token', function (): void {
    Config::resolve('pub', '   ');
})->throws(PaylinkConfigException::class);

it('rejects a non-positive timeout', function (): void {
    Config::resolve('pub', 'secret', timeoutMs: 0);
})->throws(PaylinkConfigException::class);

it('rejects negative retries', function (): void {
    Config::resolve('pub', 'secret', maxRetries: -1);
})->throws(PaylinkConfigException::class);

it('applies defaults for base url, timeout, and retries', function (): void {
    $config = Config::resolve('pub', 'secret');

    expect($config->baseUrl)->toBe('https://pay.getpayin.com')
        ->and($config->timeoutMs)->toBe(30_000)
        ->and($config->maxRetries)->toBe(2);
});

it('strips trailing slashes from the base url', function (): void {
    expect(Config::resolve('pub', 'secret', baseUrl: 'https://sandbox.example.com//')->baseUrl)
        ->toBe('https://sandbox.example.com');
});

it('keeps the hash token readable for signing but redacts it from serialization', function (): void {
    $config = Config::resolve('pub', 'super-secret');

    expect($config->hashToken())->toBe('super-secret')
        ->and(json_encode($config))->not->toContain('super-secret')
        ->and(json_encode($config))->toContain('***redacted***')
        ->and(print_r($config->__debugInfo(), true))->not->toContain('super-secret');
});

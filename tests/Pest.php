<?php

declare(strict_types=1);

use GetPayin\Paylink\Core\Config;
use GetPayin\Paylink\Core\Http\RequestExecutor;
use GetPayin\Paylink\Core\PaylinkClient;
use GetPayin\Paylink\Tests\Support\FakeTransport;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/*
 * The SDK is framework-agnostic, so the suite runs on PHPUnit's base TestCase
 * (Pest's default) — no Laravel application is booted.
 */
uses(TestCase::class)->in(__DIR__);

/**
 * @return array<string, array{0: array<string, mixed>}>
 */
function goldenCases(): array
{
    $json = json_decode((string) file_get_contents(__DIR__ . '/fixtures/golden-signatures.json'), true);
    $cases = [];

    foreach ($json['cases'] as $case) {
        $case['hashToken'] = $json['hashToken'];
        $cases[$case['name']] = [$case];
    }

    return $cases;
}

/**
 * @param  array<int, string>  $endpoints
 * @return array<string, array{0: array<string, mixed>}>
 */
function goldenCasesFor(array $endpoints): array
{
    return array_filter(goldenCases(), static fn (array $row): bool => in_array($row[0]['endpoint'], $endpoints, true));
}

function makeExecutor(FakeTransport $transport, int $maxRetries = 2, ?LoggerInterface $logger = null): RequestExecutor
{
    return new RequestExecutor(Config::resolve(
        publicToken: 'public_token',
        hashToken: 'secret',
        baseUrl: 'https://api.test',
        maxRetries: $maxRetries,
        transport: $transport,
        retryBaseDelayMs: 0,
        logger: $logger,
    ));
}

function makeClient(FakeTransport $transport, string $hashToken = 'secret', ?LoggerInterface $logger = null): PaylinkClient
{
    return new PaylinkClient(
        publicToken: 'public_token',
        hashToken: $hashToken,
        baseUrl: 'https://api.test',
        transport: $transport,
        retryBaseDelayMs: 0,
        logger: $logger,
    );
}

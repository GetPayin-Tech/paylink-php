<?php

declare(strict_types=1);

namespace GetPayin\Paylink\Core\Http;

use GetPayin\Paylink\Core\Exceptions\PaylinkConnectionException;

/**
 * A single HTTP round-trip. Implementations must apply `$timeoutMs` to the
 * request and throw {@see PaylinkConnectionException} when no response is
 * produced (network failure, DNS error, or timeout). A returned {@see Response}
 * — even a 4xx/5xx — is treated as a successful round-trip; only the absence of
 * a response is a connection error.
 *
 * Swap the default {@see CurlTransport} to inject a proxy-aware client, a
 * PSR-18 adapter, or a fake in tests.
 */
interface Transport
{
    /**
     * Perform one HTTP round-trip.
     *
     * @param  array<string, string>  $headers  Request headers, name => value.
     * @param  string|null  $body  Raw request body, or null for bodyless methods.
     * @param  int  $timeoutMs  Timeout for this attempt, in milliseconds.
     *
     * @throws PaylinkConnectionException When no response is produced.
     */
    public function send(string $method, string $url, array $headers, ?string $body, int $timeoutMs): Response;
}

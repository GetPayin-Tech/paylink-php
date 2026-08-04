<?php

declare(strict_types=1);

namespace GetPayin\Paylink\Laravel;

use GetPayin\Paylink\Core\Http\Response;
use GetPayin\Paylink\Core\Http\Transport;
use Illuminate\Support\Facades\Http;

/**
 * A {@see Transport} that routes requests through Laravel's HTTP client (the
 * `Http` facade / Guzzle) instead of the default cURL transport. Use it inside a
 * Laravel app to inherit the framework's connection pooling, global middleware,
 * and `Http::fake()` test helpers:
 *
 *   $paylink = new PaylinkClient(
 *       publicToken: config('services.paylink.public_token'),
 *       hashToken: config('services.paylink.hash_token'),
 *       transport: new LaravelHttpTransport(),
 *   );
 *
 * The core SDK does not depend on Laravel; this adapter is the opt-in bridge and
 * requires illuminate/http (already present in any Laravel app).
 */
final class LaravelHttpTransport implements Transport
{
    public function send(string $method, string $url, array $headers, ?string $body, int $timeoutMs): Response
    {
        $request = Http::withHeaders($headers)->timeout((int) max(1, ceil($timeoutMs / 1000)));

        if ($body !== null) {
            $request = $request->withBody($body, $headers['Content-Type'] ?? 'application/json');
        }

        $response = $request->send($method, $url);

        return new Response($response->status(), $response->body(), $this->normalizeHeaders($response->headers()));
    }

    /**
     * @param  array<string, array<int, string>>  $headers
     * @return array<string, string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $values) {
            $normalized[strtolower($name)] = implode(', ', $values);
        }

        return $normalized;
    }
}

<?php

declare(strict_types=1);

namespace GetPayin\Paylink\Tests\Support;

use GetPayin\Paylink\Core\Exceptions\PaylinkConnectionException;
use GetPayin\Paylink\Core\Http\Response;
use GetPayin\Paylink\Core\Http\Transport;
use RuntimeException;

/**
 * In-memory {@see Transport} for tests. Queue up responses (or connection
 * errors) to return in order, and inspect the recorded requests afterwards.
 */
final class FakeTransport implements Transport
{
    /** @var array<int, Response|PaylinkConnectionException> */
    private array $queue;

    /** @var array<int, array{method: string, url: string, headers: array<string, string>, body: string|null}> */
    public array $requests = [];

    public function __construct(Response | PaylinkConnectionException ...$responses)
    {
        $this->queue = $responses;
    }

    public function send(string $method, string $url, array $headers, ?string $body, int $timeoutMs): Response
    {
        $this->requests[] = compact('method', 'url', 'headers', 'body');

        $next = array_shift($this->queue);

        if ($next === null) {
            throw new RuntimeException('FakeTransport has no queued response for this request.');
        }

        if ($next instanceof PaylinkConnectionException) {
            throw $next;
        }

        return $next;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function ok(array $data): Response
    {
        return new Response(200, (string) json_encode(['success' => true, 'data' => $data]));
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public static function fail(int $status, array $body): Response
    {
        return new Response($status, (string) json_encode($body));
    }

    /**
     * @return array{method: string, url: string, headers: array<string, string>, body: string|null}
     */
    public function lastRequest(): array
    {
        return $this->requests[array_key_last($this->requests)];
    }

    /**
     * @return array<string, mixed>
     */
    public function lastBody(): array
    {
        return (array) json_decode((string) $this->lastRequest()['body'], true);
    }
}

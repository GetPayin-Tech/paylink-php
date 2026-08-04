<?php

declare(strict_types=1);

namespace GetPayin\Paylink\Core\Http;

use GetPayin\Paylink\Core\Concerns\LogsAction;
use GetPayin\Paylink\Core\Config;
use GetPayin\Paylink\Core\Exceptions\PaylinkApiException;
use GetPayin\Paylink\Core\Exceptions\PaylinkConnectionException;
use GetPayin\Paylink\Core\Exceptions\PaylinkException;
use GetPayin\Paylink\Core\Version;
use JsonException;
use Psr\Log\LoggerInterface;

/**
 * Executes a request against the integration API and returns the `data` payload
 * of the success envelope (or the whole decoded body when there is no `data`
 * key). Throws {@see PaylinkApiException} for non-success responses and
 * {@see PaylinkConnectionException} for network/timeout failures.
 *
 * Retries transient failures (429, 5xx, connection errors) with exponential
 * backoff and full jitter, honoring `Retry-After` when the server sends it. A
 * request is only ever replayed when doing so cannot double-charge: GETs,
 * requests carrying an `Idempotency-Key`, and calls explicitly flagged
 * `replaySafe`. A bare `vcc.charge` or `cards.charge` is NEVER retried.
 */
final class RequestExecutor
{
    use LogsAction;

    private const RETRY_MAX_DELAY_MS = 8_000;

    public function __construct(private readonly Config $config) {}

    public function execute(RequestOptions $options): mixed
    {
        return $this->logTimedAction(
            sprintf('%s %s', $options->method, $options->path),
            fn (): mixed => $this->run($options),
        );
    }

    protected function logger(): LoggerInterface
    {
        return $this->config->logger;
    }

    /**
     * The attempt/retry loop behind {@see execute()}. Retries are logged at
     * warning; an exhausted transient failure at error. Non-retryable client
     * errors are re-thrown for the caller to handle without logging.
     */
    private function run(RequestOptions $options): mixed
    {
        $maxAttempts = $this->isReplaySafe($options) ? $this->config->maxRetries + 1 : 1;

        for ($attempt = 1; ; $attempt++) {
            try {
                return $this->attempt($options);
            } catch (PaylinkException $error) {
                if ($attempt >= $maxAttempts || ! $this->isTransient($error)) {
                    if ($this->isTransient($error)) {
                        $this->logError('Request failed after exhausting retries', $this->errorContext($options, $error, $attempt));
                    }

                    throw $error;
                }

                $delayMs = $this->backoffMs($error, $attempt);
                $this->logWarning('Request failed; retrying', $this->errorContext($options, $error, $attempt) + ['delay_ms' => $delayMs]);
                $this->sleepMs($delayMs);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function errorContext(RequestOptions $options, PaylinkException $error, int $attempt): array
    {
        return [
            'method' => $options->method,
            'path' => $options->path,
            'attempt' => $attempt,
            'error' => $error::class,
            'status' => $error instanceof PaylinkApiException ? $error->status : null,
        ];
    }

    /**
     * Execute a request whose successful response is a JSON object, returning it
     * as an associative array. Every integration endpoint returns an object, so
     * resources use this to keep their result mapping typed.
     *
     * @return array<string, mixed>
     */
    public function object(RequestOptions $options): array
    {
        $data = $this->execute($options);

        if (! is_array($data)) {
            throw new PaylinkException('Expected a JSON object in the PayLink API response.');
        }

        return $data;
    }

    /** The User-Agent sent on every request. */
    public static function userAgent(): string
    {
        return 'paylink-php/' . Version::VALUE . ' php/' . PHP_VERSION;
    }

    /**
     * Whether replaying this request is safe. Reads are always safe; writes are
     * only safe when the server can dedupe them via an idempotency key.
     */
    private function isReplaySafe(RequestOptions $options): bool
    {
        return $options->replaySafe ?? ($options->method === 'GET' || $options->idempotencyKey !== null);
    }

    /** Whether the failure is worth another attempt (429, 5xx, or a connection error). */
    private function isTransient(PaylinkException $error): bool
    {
        if ($error instanceof PaylinkConnectionException) {
            return true;
        }

        if ($error instanceof PaylinkApiException) {
            return $error->status === 429 || $error->status >= 500;
        }

        return false;
    }

    /**
     * Exponential backoff with full jitter, bounded by RETRY_MAX_DELAY_MS. A
     * server-sent `Retry-After` wins over the computed delay.
     */
    private function backoffMs(PaylinkException $error, int $attempt): int
    {
        if ($error instanceof PaylinkApiException && $error->retryAfterMs !== null) {
            return min($error->retryAfterMs, self::RETRY_MAX_DELAY_MS);
        }

        $ceiling = (int) min($this->config->retryBaseDelayMs * (2 ** ($attempt - 1)), self::RETRY_MAX_DELAY_MS);

        return $ceiling > 0 ? (int) (mt_rand(0, $ceiling * 1000) / 1000) : 0;
    }

    private function sleepMs(int $ms): void
    {
        if ($ms > 0) {
            usleep($ms * 1000);
        }
    }

    /** Send one request and map the response onto the success envelope or an error. */
    private function attempt(RequestOptions $options): mixed
    {
        $url = $this->buildUrl($options->path, $options->query);

        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => self::userAgent(),
        ];
        $body = null;

        if ($options->method !== 'GET') {
            $headers['Content-Type'] = 'application/json';
            $body = json_encode($options->body ?? (object) [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if ($options->idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $options->idempotencyKey;
        }

        $response = $this->config->transport->send(
            $options->method,
            $url,
            $headers,
            $body,
            $this->config->timeoutMs,
        );

        $parsed = $this->safeJsonParse($response->body);

        if (! $response->isSuccessful() || $this->isFailureEnvelope($parsed)) {
            throw $this->toApiError(
                $response->status,
                $parsed,
                $response->body,
                $this->parseRetryAfter($response->header('Retry-After')),
            );
        }

        if (is_array($parsed) && array_key_exists('data', $parsed)) {
            return $parsed['data'];
        }

        return $parsed;
    }

    /**
     * Join the base URL, path, and (optional) query string.
     *
     * @param  array<string, string>|null  $query
     */
    private function buildUrl(string $path, ?array $query): string
    {
        $url = $this->config->baseUrl . $path;

        if ($query === null || $query === []) {
            return $url;
        }

        return $url . '?' . http_build_query($query);
    }

    /** Decode a JSON body, returning null for an empty body and the raw text on parse failure. */
    private function safeJsonParse(string $text): mixed
    {
        if (trim($text) === '') {
            return null;
        }

        try {
            return json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $text;
        }
    }

    /** Whether a body is an application-level failure envelope (`{ success: false }`). */
    private function isFailureEnvelope(mixed $parsed): bool
    {
        return is_array($parsed) && array_key_exists('success', $parsed) && $parsed['success'] === false;
    }

    /** Convert a `Retry-After` header (delta-seconds or an HTTP date) to milliseconds. */
    private function parseRetryAfter(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (is_numeric($value)) {
            return max(0, (int) ((float) $value * 1000));
        }

        $timestamp = strtotime($value);

        if ($timestamp !== false) {
            return max(0, ($timestamp - time()) * 1000);
        }

        return null;
    }

    /** Build a {@see PaylinkApiException} from the decoded error body. */
    private function toApiError(int $status, mixed $parsed, string $rawText, ?int $retryAfterMs): PaylinkApiException
    {
        $envelope = is_array($parsed) ? $parsed : [];
        $nestedData = isset($envelope['data']) && is_array($envelope['data']) ? $envelope['data'] : [];

        $message = $this->firstString($envelope['message'] ?? null, $nestedData['message'] ?? null)
            ?? "PayLink API request failed with status {$status}.";

        $errors = isset($envelope['errors']) && is_array($envelope['errors']) ? $envelope['errors'] : null;

        return new PaylinkApiException($message, $status, $errors, $parsed ?? $rawText, $retryAfterMs);
    }

    /** The first non-empty string among the arguments, or null. */
    private function firstString(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }
}

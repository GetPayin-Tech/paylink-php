<?php

declare(strict_types=1);

namespace GetPayin\Paylink\Core;

use GetPayin\Paylink\Core\Exceptions\PaylinkConfigException;
use GetPayin\Paylink\Core\Http\CurlTransport;
use GetPayin\Paylink\Core\Http\Transport;
use JsonSerializable;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Resolved, validated client configuration. The signing secret is held in a
 * private property and redacted from {@see __debugInfo()} and
 * {@see jsonSerialize()}, so `var_dump()`, `print_r()` (via casting), and
 * `json_encode()` on a client or resource never spill it in plaintext. It stays
 * readable through {@see hashToken()} for signing.
 */
final class Config implements JsonSerializable
{
    public const DEFAULT_BASE_URL = 'https://pay.getpayin.com';

    public const DEFAULT_TIMEOUT_MS = 30_000;

    public const DEFAULT_MAX_RETRIES = 2;

    public const DEFAULT_RETRY_BASE_DELAY_MS = 250;

    public function __construct(
        public readonly string $publicToken,
        private readonly string $hashToken,
        public readonly string $baseUrl,
        public readonly int $timeoutMs,
        public readonly int $maxRetries,
        public readonly int $retryBaseDelayMs,
        public readonly Transport $transport,
        public readonly LoggerInterface $logger,
    ) {}

    /**
     * Validate raw client options and build the resolved config, defaulting the
     * transport to {@see CurlTransport}.
     *
     * @throws PaylinkConfigException When a token is empty, the timeout is not
     *                                positive, or a retry count is negative.
     */
    public static function resolve(
        string $publicToken,
        string $hashToken,
        string $baseUrl = self::DEFAULT_BASE_URL,
        int $timeoutMs = self::DEFAULT_TIMEOUT_MS,
        int $maxRetries = self::DEFAULT_MAX_RETRIES,
        ?Transport $transport = null,
        int $retryBaseDelayMs = self::DEFAULT_RETRY_BASE_DELAY_MS,
        ?LoggerInterface $logger = null,
    ): self {
        return new self(
            self::requireNonEmpty($publicToken, 'publicToken'),
            self::requireNonEmpty($hashToken, 'hashToken'),
            rtrim($baseUrl, '/'),
            self::requirePositive($timeoutMs, 'timeoutMs'),
            self::requireNonNegative($maxRetries, 'maxRetries'),
            self::requireNonNegative($retryBaseDelayMs, 'retryBaseDelayMs'),
            $transport ?? new CurlTransport,
            $logger ?? new NullLogger,
        );
    }

    /** The signing secret, exposed only for computing request signatures. */
    public function hashToken(): string
    {
        return $this->hashToken;
    }

    /**
     * Redacted view used by `var_dump()`.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return $this->jsonSerialize();
    }

    /**
     * Redacted view used by `json_encode()`; the hash token never appears.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'publicToken' => $this->publicToken,
            'hashToken' => '***redacted***',
            'baseUrl' => $this->baseUrl,
            'timeoutMs' => $this->timeoutMs,
            'maxRetries' => $this->maxRetries,
        ];
    }

    /** @throws PaylinkConfigException When the trimmed value is empty. */
    private static function requireNonEmpty(string $value, string $name): string
    {
        if (trim($value) === '') {
            throw new PaylinkConfigException("{$name} is required and must be a non-empty string.");
        }

        return $value;
    }

    /** @throws PaylinkConfigException When the value is zero or negative. */
    private static function requirePositive(int $value, string $name): int
    {
        if ($value <= 0) {
            throw new PaylinkConfigException("{$name} must be a positive integer.");
        }

        return $value;
    }

    /** @throws PaylinkConfigException When the value is negative. */
    private static function requireNonNegative(int $value, string $name): int
    {
        if ($value < 0) {
            throw new PaylinkConfigException("{$name} must be a non-negative integer.");
        }

        return $value;
    }
}

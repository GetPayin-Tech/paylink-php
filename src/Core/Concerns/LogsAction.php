<?php

declare(strict_types=1);

namespace GetPayin\Paylink\Core\Concerns;

use GetPayin\Paylink\Core\PaylinkClient;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Structured logging for SDK components, written to the caller-supplied PSR-3
 * logger. In a Laravel app the logger is a specific channel — pass
 * `Log::channel('paylink')` to {@see PaylinkClient} and every
 * line below lands there. Outside Laravel any PSR-3 logger works; with none, a
 * NullLogger swallows the output.
 *
 * Every message is prefixed with the component name (`[RequestExecutor] ...`)
 * and every context array is passed through {@see maskSensitiveData()} so a
 * token, secret, or card field can never reach the log.
 */
trait LogsAction
{
    /** The channel these logs are written to. */
    abstract protected function logger(): LoggerInterface;

    /**
     * @param  array<string, mixed>  $context
     */
    protected function logDebug(string $message, array $context = []): void
    {
        $this->writeLog(LogLevel::DEBUG, $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function logInfo(string $message, array $context = []): void
    {
        $this->writeLog(LogLevel::INFO, $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function logWarning(string $message, array $context = []): void
    {
        $this->writeLog(LogLevel::WARNING, $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function logError(string $message, array $context = []): void
    {
        $this->writeLog(LogLevel::ERROR, $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function logCritical(string $message, array $context = []): void
    {
        $this->writeLog(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function logAlert(string $message, array $context = []): void
    {
        $this->writeLog(LogLevel::ALERT, $message, $context);
    }

    /**
     * Log to a specific channel/logger, overriding the component's default.
     *
     * @param  array<string, mixed>  $context
     */
    protected function logChannel(LoggerInterface $channel, string $level, string $message, array $context = []): void
    {
        $channel->log($level, $this->buildLogMessage($message), $this->maskSensitiveData($context));
    }

    /**
     * Measure how long a callback takes and log the duration on completion,
     * whether it returns or throws.
     *
     * @param  array<string, mixed>  $context
     */
    protected function logTimedAction(string $message, callable $callback, array $context = []): mixed
    {
        $start = microtime(true);

        try {
            return $callback();
        } finally {
            $durationMs = round((microtime(true) - $start) * 1000, 2);
            $this->logInfo($message, array_merge($context, ['duration_ms' => $durationMs]));
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function writeLog(string $level, string $message, array $context): void
    {
        $this->logger()->log($level, $this->buildLogMessage($message), $this->maskSensitiveData($context));
    }

    private function buildLogMessage(string $message): string
    {
        return sprintf('[%s] %s', $this->logComponent(), $message);
    }

    /** The short class name used as the log prefix. */
    private function logComponent(): string
    {
        $class = static::class;
        $separator = strrpos($class, '\\');

        return $separator === false ? $class : substr($class, $separator + 1);
    }

    /**
     * Recursively replace the values of sensitive keys with a mask.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function maskSensitiveData(array $data): array
    {
        $sensitiveKeys = [
            'password', 'password_confirmation', 'cvv', 'card_cvv', 'card_number',
            'token', 'secret', 'key', 'api_key', 'hash_token', 'hashtoken',
            'authorization', 'signature',
        ];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->maskSensitiveData($value);

                continue;
            }

            if (in_array(strtolower((string) $key), $sensitiveKeys, true)) {
                $data[$key] = '********';
            }
        }

        return $data;
    }
}

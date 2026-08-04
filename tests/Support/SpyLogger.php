<?php

declare(strict_types=1);

namespace GetPayin\Paylink\Tests\Support;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * In-memory PSR-3 logger that records every line for assertions.
 */
final class SpyLogger extends AbstractLogger
{
    /** @var array<int, array{level: string, message: string, context: array<string, mixed>}> */
    public array $records = [];

    /**
     * @param  mixed  $level
     * @param  array<string, mixed>  $context
     */
    public function log($level, string | Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    /**
     * @return array<int, array{level: string, message: string, context: array<string, mixed>}>
     */
    public function withLevel(string $level): array
    {
        return array_values(array_filter($this->records, static fn (array $record): bool => $record['level'] === $level));
    }
}

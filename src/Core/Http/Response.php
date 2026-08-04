<?php

declare(strict_types=1);

namespace GetPayin\Paylink\Core\Http;

/**
 * The minimal HTTP response the SDK relies on. Header names are stored
 * lower-cased so lookups are case-insensitive.
 */
final class Response
{
    /**
     * @param  array<string, string>  $headers  Lower-cased header name => value.
     */
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly array $headers = [],
    ) {}

    /** Whether the status is in the 2xx range. */
    public function isSuccessful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /** Case-insensitive header lookup; null when the header is absent. */
    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}

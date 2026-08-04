<?php

declare(strict_types=1);

namespace GetPayin\Paylink\Core\Exceptions;

/**
 * Thrown when the API responds with a non-success envelope (`{ success: false }`)
 * or a non-2xx status. Carries the HTTP status, the server-provided field
 * `errors` (keyed by field), and the raw response body (decoded, or the raw text
 * if it was not valid JSON) for inspection.
 *
 * Use {@see isIdempotencyConflict()} (409), {@see isRateLimited()} (429), and
 * {@see isForbidden()} (403 — e.g. a product such as card tokenization or
 * recurring payments is not enabled) to branch on the common cases without
 * matching on status codes.
 */
class PaylinkApiException extends PaylinkException
{
    /**
     * @param  array<string, mixed>|null  $errors
     */
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly ?array $errors = null,
        public readonly mixed $raw = null,
        public readonly ?int $retryAfterMs = null,
    ) {
        parent::__construct($message);
    }

    /** True when the API rejected the request as a duplicate (idempotency conflict). */
    public function isIdempotencyConflict(): bool
    {
        return $this->status === 409;
    }

    /**
     * True when the request was rate limited (HTTP 429). Every v2 integration
     * route is rate limited server-side, so this is an expected condition under
     * burst traffic — check {@see $retryAfterMs} for the server's backoff hint.
     */
    public function isRateLimited(): bool
    {
        return $this->status === 429;
    }

    /**
     * True when the API forbade the request (HTTP 403) — typically because the
     * relevant product (e.g. card tokenization or recurring payments) is not
     * enabled for the integration's account.
     */
    public function isForbidden(): bool
    {
        return $this->status === 403;
    }
}

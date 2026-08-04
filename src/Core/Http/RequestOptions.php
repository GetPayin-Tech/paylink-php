<?php

declare(strict_types=1);

namespace GetPayin\Paylink\Core\Http;

/**
 * Options for a single API request.
 *
 * - `path` begins with a slash, e.g. `/api/v2/integration/init`.
 * - `body` is the already string-coerced wire fields for POSTs.
 * - `query` holds query parameters (used for the recurring status GET).
 * - `idempotencyKey` is sent as the `Idempotency-Key` header when provided.
 * - `replaySafe` overrides the default replay-safety rule; set `true` for POSTs
 *   that are pure reads (check-status) so they can be retried.
 */
final class RequestOptions
{
    /**
     * @param  array<string, string>|null  $body
     * @param  array<string, string>|null  $query
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly ?array $body = null,
        public readonly ?array $query = null,
        public readonly ?string $idempotencyKey = null,
        public readonly ?bool $replaySafe = null,
    ) {}
}

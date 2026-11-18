<?php

declare(strict_types=1);

namespace GetPayin\Paylink\Core\Webhook;

/**
 * A verified webhook event with camelCase fields; `raw` holds the full decoded
 * payload. Compare `event` against {@see WebhookEventType} to branch by type.
 *
 * The card properties are filled only on `card_token.created`, and only once the
 * card was actually vaulted: a `card_token.failed` event carries the tokenisation
 * and nothing else. They sit after `raw` so that positional construction of an
 * event built before they existed still works.
 */
final class WebhookEvent
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $event,
        public readonly ?string $eventTriggeredAt,
        public readonly ?string $timezone,
        public readonly bool $success,
        public readonly ?int $invoiceId,
        public readonly ?string $invoiceStatus,
        public readonly ?string $message,
        public readonly ?string $authCode,
        public readonly ?string $mandateId,
        public readonly ?string $externalReference,
        public readonly ?string $subscriptionStatus,
        public readonly array $raw,
        public readonly ?string $tokenizationId = null,
        public readonly ?string $cardToken = null,
        public readonly ?string $cardBrand = null,
        public readonly ?string $cardLast4 = null,
        public readonly ?string $cardExpMonth = null,
        public readonly ?string $cardExpYear = null,
    ) {}

    /** Resolve the raw event string to a {@see WebhookEventType}, or null if unknown. */
    public function type(): ?WebhookEventType
    {
        return WebhookEventType::tryFrom($this->event);
    }
}

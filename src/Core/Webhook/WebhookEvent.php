<?php

declare(strict_types=1);

namespace GetPayin\Paylink\Core\Webhook;

/**
 * A verified webhook event with camelCase fields; `raw` holds the full decoded
 * payload. Compare `event` against {@see WebhookEventType} to branch by type.
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
    ) {}

    /** Resolve the raw event string to a {@see WebhookEventType}, or null if unknown. */
    public function type(): ?WebhookEventType
    {
        return WebhookEventType::tryFrom($this->event);
    }
}

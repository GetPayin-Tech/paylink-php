<?php

declare(strict_types=1);

namespace GetPayin\Paylink\Core;

use GetPayin\Paylink\Core\Concerns\LogsAction;
use GetPayin\Paylink\Core\Exceptions\PaylinkException;
use GetPayin\Paylink\Core\Exceptions\PaylinkSignatureException;
use GetPayin\Paylink\Core\Webhook\WebhookEvent;
use JsonException;
use Psr\Log\LoggerInterface;

/**
 * Verifies inbound webhook signatures and maps the payload to a
 * {@see WebhookEvent}.
 */
final class Webhooks
{
    use LogsAction;

    /** Always-signed webhook fields, in concatenation order (null renders as ''). */
    private const ALWAYS_SIGNED = ['success', 'invoice_id', 'invoice_status', 'message'];

    /**
     * The fields a recurring mandate adds, in the order the server adds them.
     *
     * This mirrors the server's opt-OUT signing: a field is signed when the block
     * that writes it runs, so `auth_code` (added after the signature is computed
     * server-side) is deliberately absent here. Getting membership backwards
     * breaks verification for every webhook carrying the field.
     */
    private const MANDATE_SIGNED = ['mandate_id', 'external_reference', 'subscription_status'];

    /**
     * The fields a card-token event adds, in the order the server adds them.
     *
     * `card_token.created` and `card_token.failed` are the only events that carry
     * a tokenisation, and both are on by default for every integration. The
     * server writes `tokenization_id` and `external_reference` first, then the
     * card's details — and only when the card was actually vaulted, so a failed
     * tokenisation signs the first two alone.
     *
     * Order matters because the signature is a separator-less concatenation: on
     * an event with no mandate, `external_reference` is signed AFTER
     * `tokenization_id`, not at the position it holds on a subscription event.
     */
    private const TOKENIZATION_SIGNED = [
        'tokenization_id',
        'external_reference',
        'card_token',
        'card_brand',
        'card_last4',
        'card_exp_month',
        'card_exp_year',
    ];

    public function __construct(private readonly Config $config) {}

    /**
     * Verify an inbound webhook and return the parsed event. Accepts the decoded
     * array or the raw JSON string. Pass `$hashToken` to verify a webhook for a
     * different integration than the client's. Throws
     * {@see PaylinkSignatureException} if the signature is missing or wrong.
     *
     * PayLink webhook signatures carry no timestamp, so this does not protect
     * against replay — pair it with your own idempotency on `invoice_id`.
     *
     * @param  array<string, mixed>|string  $payload
     */
    public function verify(array | string $payload, ?string $hashToken = null): WebhookEvent
    {
        $parsed = $this->parse($payload);
        $hashToken ??= $this->config->hashToken();

        $provided = $parsed['signature'] ?? null;

        if (! is_string($provided) || $provided === '') {
            $this->logWarning('Webhook rejected: missing signature', ['event' => $parsed['event'] ?? null]);

            throw new PaylinkSignatureException('Webhook payload is missing a signature.');
        }

        if (! Signature::equals($this->computeSignature($parsed, $hashToken), $provided)) {
            $this->logWarning('Webhook rejected: signature mismatch', [
                'event' => $parsed['event'] ?? null,
                'invoice_id' => $parsed['invoice_id'] ?? null,
            ]);

            throw new PaylinkSignatureException;
        }

        return $this->toEvent($parsed);
    }

    protected function logger(): LoggerInterface
    {
        return $this->config->logger;
    }

    /**
     * Non-throwing variant of {@see verify()}.
     *
     * @param  array<string, mixed>|string  $payload
     */
    public function isValid(array | string $payload, ?string $hashToken = null): bool
    {
        try {
            $this->verify($payload, $hashToken);

            return true;
        } catch (PaylinkSignatureException) {
            return false;
        }
    }

    /**
     * Rebuild the expected signature the way the server assembles it.
     *
     * `PaymentIntegrationWebhookJob` appends a whole block at a time — the
     * mandate fields when the invoice belongs to one, the tokenisation fields
     * when the event is about a card — and PHP keeps a key at the position it
     * was first written. A block is therefore gated on its own leading field
     * rather than on the individual fields inside it: `external_reference`
     * belongs to whichever block introduced it, and is never signed twice.
     *
     * @param  array<string, mixed>  $payload
     */
    private function computeSignature(array $payload, string $hashToken): string
    {
        $keys = self::ALWAYS_SIGNED;

        foreach ([self::MANDATE_SIGNED, self::TOKENIZATION_SIGNED] as $block) {
            if (! array_key_exists($block[0], $payload)) {
                continue;
            }

            foreach ($block as $key) {
                if (array_key_exists($key, $payload) && ! in_array($key, $keys, true)) {
                    $keys[] = $key;
                }
            }
        }

        $values = array_map(
            static fn (string $key): string => Coerce::toString($payload[$key] ?? null),
            $keys,
        );

        return Signature::build($values, $hashToken);
    }

    /**
     * Normalize the payload to a decoded array.
     *
     * @param  array<string, mixed>|string  $payload
     * @return array<string, mixed>
     *
     * @throws PaylinkException When a string payload is not a JSON object.
     */
    private function parse(array | string $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new PaylinkException('Webhook payload is not valid JSON.', 0, $error);
        }

        if (! is_array($decoded)) {
            throw new PaylinkException('Webhook payload is not a JSON object.');
        }

        return $decoded;
    }

    /**
     * Map a verified payload to a {@see WebhookEvent} with camelCase fields.
     *
     * @param  array<string, mixed>  $payload
     */
    private function toEvent(array $payload): WebhookEvent
    {
        return new WebhookEvent(
            event: (string) ($payload['event'] ?? ''),
            eventTriggeredAt: isset($payload['event_triggered_at']) ? (string) $payload['event_triggered_at'] : null,
            timezone: isset($payload['timezone']) ? (string) $payload['timezone'] : null,
            success: (int) ($payload['success'] ?? 0) === 1,
            invoiceId: isset($payload['invoice_id']) ? (int) $payload['invoice_id'] : null,
            invoiceStatus: isset($payload['invoice_status']) ? (string) $payload['invoice_status'] : null,
            message: isset($payload['message']) ? (string) $payload['message'] : null,
            authCode: isset($payload['auth_code']) ? (string) $payload['auth_code'] : null,
            mandateId: isset($payload['mandate_id']) ? (string) $payload['mandate_id'] : null,
            externalReference: isset($payload['external_reference']) ? (string) $payload['external_reference'] : null,
            subscriptionStatus: isset($payload['subscription_status']) ? (string) $payload['subscription_status'] : null,
            raw: $payload,
            tokenizationId: isset($payload['tokenization_id']) ? (string) $payload['tokenization_id'] : null,
            cardToken: isset($payload['card_token']) ? (string) $payload['card_token'] : null,
            cardBrand: isset($payload['card_brand']) ? (string) $payload['card_brand'] : null,
            cardLast4: isset($payload['card_last4']) ? (string) $payload['card_last4'] : null,
            cardExpMonth: isset($payload['card_exp_month']) ? (string) $payload['card_exp_month'] : null,
            cardExpYear: isset($payload['card_exp_year']) ? (string) $payload['card_exp_year'] : null,
        );
    }
}

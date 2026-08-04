<?php

declare(strict_types=1);

use GetPayin\Paylink\Core\Coerce;
use GetPayin\Paylink\Core\Exceptions\PaylinkSignatureException;
use GetPayin\Paylink\Core\Signature;
use GetPayin\Paylink\Core\Webhook\WebhookEventType;
use GetPayin\Paylink\Tests\Support\FakeTransport;

/**
 * Sign a payload over the four always-signed fields, mirroring the server for
 * payloads that carry no optional signed fields.
 *
 * @param  array<string, mixed>  $payload
 */
function signWebhook(array $payload, string $hashToken): string
{
    $values = array_map(
        static fn (string $key): string => Coerce::toString($payload[$key] ?? null),
        ['success', 'invoice_id', 'invoice_status', 'message'],
    );

    return Signature::build($values, $hashToken);
}

/**
 * @return array<string, mixed>
 */
function paidWebhook(string $hashToken): array
{
    $payload = [
        'event' => 'invoice.paid', 'event_triggered_at' => '2026-08-04T10:00:00Z',
        'timezone' => 'Z', 'success' => 1, 'invoice_id' => 123, 'invoice_status' => 'PAID',
        'message' => null,
    ];
    $payload['signature'] = signWebhook($payload, $hashToken);

    return $payload;
}

it('verifies a valid webhook and maps camelCase fields', function (): void {
    $event = makeClient(new FakeTransport)->webhooks->verify(paidWebhook('secret'));

    expect($event->event)->toBe('invoice.paid')
        ->and($event->invoiceId)->toBe(123)
        ->and($event->success)->toBeTrue()
        ->and($event->message)->toBeNull()
        ->and($event->type())->toBe(WebhookEventType::InvoicePaid);
});

it('accepts the raw JSON string body', function (): void {
    $event = makeClient(new FakeTransport)->webhooks->verify((string) json_encode(paidWebhook('secret')));

    expect($event->invoiceId)->toBe(123);
});

it('throws when the signature is missing', function (): void {
    $payload = paidWebhook('secret');
    unset($payload['signature']);

    makeClient(new FakeTransport)->webhooks->verify($payload);
})->throws(PaylinkSignatureException::class);

it('rejects a tampered signature', function (): void {
    $payload = paidWebhook('secret');
    $payload['signature'] = 'not-the-real-signature';

    expect(makeClient(new FakeTransport)->webhooks->isValid($payload))->toBeFalse();
});

it('honours a per-call hash token override', function (): void {
    $payload = paidWebhook('other-integration-secret');
    $webhooks = makeClient(new FakeTransport, 'secret')->webhooks;

    expect($webhooks->isValid($payload))->toBeFalse()
        ->and($webhooks->isValid($payload, 'other-integration-secret'))->toBeTrue();
});

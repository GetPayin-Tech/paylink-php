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

/**
 * A `card_token.created` payload signed the way the server signs one: the
 * tokenisation block follows the always-signed four, and `external_reference`
 * sits after `tokenization_id` rather than in the slot a mandate gives it.
 *
 * @return array<string, mixed>
 */
function cardTokenWebhook(string $hashToken): array
{
    $payload = [
        'event' => 'card_token.created', 'event_triggered_at' => '2026-11-18T09:15:00+02:00',
        'timezone' => 'EET', 'success' => 1, 'invoice_id' => 7781, 'invoice_status' => 'REVERSED',
        'message' => 'Card stored for future payments.', 'tokenization_id' => 'TKZ-4f21',
        'external_reference' => 'cust-42', 'card_token' => 'CRD-9a07', 'card_brand' => 'VISA',
        'card_last4' => '4242', 'card_exp_month' => '12', 'card_exp_year' => '2029',
    ];

    $values = array_map(
        static fn (string $key): string => Coerce::toString($payload[$key] ?? null),
        [
            'success', 'invoice_id', 'invoice_status', 'message', 'tokenization_id',
            'external_reference', 'card_token', 'card_brand', 'card_last4',
            'card_exp_month', 'card_exp_year',
        ],
    );

    $payload['signature'] = Signature::build($values, $hashToken);

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

it('verifies a card_token.created webhook and exposes the saved card', function (): void {
    $event = makeClient(new FakeTransport)->webhooks->verify(cardTokenWebhook('secret'));

    expect($event->type())->toBe(WebhookEventType::CardTokenCreated)
        ->and($event->tokenizationId)->toBe('TKZ-4f21')
        ->and($event->cardToken)->toBe('CRD-9a07')
        ->and($event->cardBrand)->toBe('VISA')
        ->and($event->cardLast4)->toBe('4242')
        ->and($event->cardExpMonth)->toBe('12')
        ->and($event->cardExpYear)->toBe('2029')
        ->and($event->externalReference)->toBe('cust-42');
});

it('rejects a card_token webhook whose card was tampered with', function (): void {
    $payload = cardTokenWebhook('secret');
    $payload['card_last4'] = '1111';

    expect(makeClient(new FakeTransport)->webhooks->isValid($payload))->toBeFalse();
});

it('verifies a card_token.failed webhook, which carries no card at all', function (): void {
    $payload = [
        'event' => 'card_token.failed', 'success' => 0, 'invoice_id' => 7782,
        'invoice_status' => 'UNPAID', 'message' => 'The card could not be stored for future payments.',
        'tokenization_id' => 'TKZ-4f22', 'external_reference' => 'cust-43',
    ];
    $values = array_map(
        static fn (string $key): string => Coerce::toString($payload[$key] ?? null),
        ['success', 'invoice_id', 'invoice_status', 'message', 'tokenization_id', 'external_reference'],
    );
    $payload['signature'] = Signature::build($values, 'secret');

    $event = makeClient(new FakeTransport)->webhooks->verify($payload);

    expect($event->type())->toBe(WebhookEventType::CardTokenFailed)
        ->and($event->tokenizationId)->toBe('TKZ-4f22')
        ->and($event->cardToken)->toBeNull()
        ->and($event->success)->toBeFalse();
});

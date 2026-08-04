<?php

declare(strict_types=1);

use GetPayin\Paylink\Core\Http\Response;
use GetPayin\Paylink\Core\PaylinkClient;
use GetPayin\Paylink\Core\Signature;
use GetPayin\Paylink\Tests\Support\FakeTransport;

const HASH = 'test_hash_token_abc123';

/**
 * @param  array<string, mixed>  $data
 * @return array{0: PaylinkClient, 1: FakeTransport}
 */
function clientReturning(array $data): array
{
    $transport = new FakeTransport(FakeTransport::ok($data));

    return [makeClient($transport, HASH), $transport];
}

it('creates an invoice, signs the body to the server vector, and maps the result', function (): void {
    [$client, $transport] = clientReturning([
        'checkout_url' => 'https://pay.test/c/1', 'invoice_id' => 42, 'expires_at' => '2026-08-04T10:00:00Z',
    ]);

    $result = $client->invoices->create([
        'firstName' => 'John', 'lastName' => 'Doe', 'email' => 'john@example.com',
        'orderTitle' => 'Gold Plan', 'orderAmount' => 250, 'address' => '1 Main St',
        'city' => 'Cairo', 'country' => 'EG', 'state' => 'C', 'currency' => 'USD',
        'redirectionUrl' => 'https://shop.example.com/return',
        'webhookUrl' => 'https://shop.example.com/webhook', 'orderDetails' => 'note',
        'paymentMode' => 'authorize',
    ]);

    expect($result)->toBe([
        'checkoutUrl' => 'https://pay.test/c/1', 'invoiceId' => 42, 'expiresAt' => '2026-08-04T10:00:00Z',
    ]);

    $request = $transport->lastRequest();
    $body = $transport->lastBody();

    expect($body['signature'])->toBe('HpF+SI9LirGLrMLvVeFVbgxg4P+reftqQoBd3PEGc9M=')
        ->and($body['token'])->toBe('public_token')
        ->and($body['first_name'])->toBe('John')
        ->and($request['method'])->toBe('POST')
        ->and($request['url'])->toBe('https://api.test/api/v2/integration/init')
        ->and($request['headers']['Content-Type'] ?? null)->toBe('application/json');
});

it('voids an invoice and maps a null auth code', function (): void {
    [$client, $transport] = clientReturning(['invoice_id' => 7, 'paid_status' => 'voided', 'auth_code' => null]);

    expect($client->payments->void(['invoiceId' => 7]))
        ->toBe(['invoiceId' => 7, 'paidStatus' => 'voided', 'authCode' => null])
        ->and($transport->lastRequest()['url'])->toBe('https://api.test/api/integration/void');
});

it('settles an invoice, signing invoice_id then amount', function (): void {
    [$client, $transport] = clientReturning(['invoice_id' => 7, 'paid_status' => 'PAID', 'auth_code' => 'A2']);

    $result = $client->payments->settle(['invoiceId' => 7, 'amount' => '50.00']);
    $body = $transport->lastBody();

    expect($result)->toBe(['invoiceId' => 7, 'paidStatus' => 'PAID', 'authCode' => 'A2'])
        ->and($transport->lastRequest()['url'])->toBe('https://api.test/api/integration/settle')
        ->and($body['invoice_id'])->toBe('7')
        ->and($body['amount'])->toBe('50.00')
        ->and($body['signature'])->toBe(Signature::build(['7', '50.00'], HASH));
});

it('reverses an authorization on its own path', function (): void {
    [$client, $transport] = clientReturning(['invoice_id' => 7, 'paid_status' => 'UNPAID', 'auth_code' => null]);

    expect($client->payments->reverseAuthorization(['invoiceId' => 7])['paidStatus'])->toBe('UNPAID')
        ->and($transport->lastRequest()['url'])->toBe('https://api.test/api/integration/reverse-authorization');
});

it('reads the current gateway status', function (): void {
    [$client, $transport] = clientReturning(['invoice_id' => 7, 'paid_status' => 'PAID', 'auth_code' => 'A3']);

    expect($client->payments->checkStatus(['invoiceId' => 7]))
        ->toBe(['invoiceId' => 7, 'paidStatus' => 'PAID', 'authCode' => 'A3'])
        ->and($transport->lastRequest()['url'])->toBe('https://api.test/api/integration/check-status');
});

it('maps a partial refund and forwards the idempotency key', function (): void {
    [$client, $transport] = clientReturning([
        'invoice_id' => 12345, 'paid_status' => 'REFUNDED', 'auth_code' => null, 'refund_amount' => 1050,
    ]);

    $result = $client->payments->refund(['invoiceId' => 12345, 'amount' => '10.50'], 'refund-key-1');

    expect($result)->toBe([
        'invoiceId' => 12345, 'paidStatus' => 'REFUNDED', 'authCode' => null, 'refundAmount' => 1050,
    ])->and($transport->lastRequest()['headers']['Idempotency-Key'] ?? null)->toBe('refund-key-1');
});

it('tokenizes a card and maps the nested card object to camelCase', function (): void {
    [$client, $transport] = clientReturning([
        'token' => 'tok_1',
        'card' => ['brand' => 'visa', 'last4' => '1111', 'exp_month' => 12, 'exp_year' => 2030],
        'status' => 'active',
    ]);

    $result = $client->cards->tokenize([
        'firstName' => 'A', 'lastName' => 'B', 'cardNumber' => '4111111111111111',
        'cardExpiryMonth' => '12', 'cardExpiryYear' => '2030', 'country' => 'EG', 'address' => 'a', 'city' => 'c',
    ]);

    expect($transport->lastRequest()['url'])->toBe('https://api.test/api/v2/integration/tokens/card')
        ->and($result['token'])->toBe('tok_1')
        ->and($result['card'])->toBe(['brand' => 'visa', 'last4' => '1111', 'expMonth' => 12, 'expYear' => 2030]);
});

it('charges a stored token, signing card_token first', function (): void {
    [$client, $transport] = clientReturning([
        'invoice_id' => 11, 'invoice_number' => 'INV-11', 'amount' => 100,
        'currency' => 'USD', 'paid_status' => 'PAID', 'card_token' => 'tok_1',
    ]);

    $result = $client->cards->charge([
        'cardToken' => 'tok_1', 'initiator' => 'merchant', 'firstName' => 'A', 'lastName' => 'B',
        'currency' => 'USD', 'price' => '100.00', 'product' => 'Rebill', 'country' => 'EG', 'address' => 'a', 'city' => 'c',
    ]);

    $body = $transport->lastBody();

    expect($transport->lastRequest()['url'])->toBe('https://api.test/api/v2/integration/tokens/charge')
        ->and($body['card_token'])->toBe('tok_1')
        ->and($body['signature'])->toBe(Signature::build(
            ['tok_1', 'merchant', 'A', 'B', 'USD', '100.00', 'Rebill', 'EG', 'a', 'c'],
            HASH
        ))
        ->and($result)->toBe([
            'invoiceId' => 11, 'invoiceNumber' => 'INV-11', 'amount' => 100,
            'currency' => 'USD', 'paidStatus' => 'PAID', 'cardToken' => 'tok_1',
        ]);
});

it('returns the revoke message from a data-less envelope', function (): void {
    $transport = new FakeTransport(new Response(200, (string) json_encode(['success' => true, 'message' => 'Token revoked.'])));

    expect(makeClient($transport)->cards->revoke(['cardToken' => 'tok_1']))->toBe(['message' => 'Token revoked.']);
});

it('falls back to a default revoke message when the server sends none', function (): void {
    [$client] = clientReturning([]);

    expect($client->cards->revoke(['cardToken' => 'tok_1']))->toBe(['message' => 'Token revoked.']);
});

it('charges raw card data through vcc and maps the result', function (): void {
    [$client, $transport] = clientReturning([
        'invoice_id' => 9, 'invoice_number' => 'INV-9', 'amount' => 100, 'currency' => 'USD', 'paid_status' => 'PAID',
    ]);

    $result = $client->vcc->charge([
        'firstName' => 'S', 'lastName' => 'S', 'currencyId' => 1, 'price' => 100, 'product' => 'p',
        'cardNumber' => '4111111111111111', 'cardExpiryMonth' => '12', 'cardExpiryYear' => '2030',
        'country' => 'EG', 'address' => 'a', 'city' => 'c',
    ]);

    expect($transport->lastRequest()['url'])->toBe('https://api.test/api/v2/integration/vcc/charge')
        ->and($result)->toBe([
            'invoiceId' => 9, 'invoiceNumber' => 'INV-9', 'amount' => 100, 'currency' => 'USD', 'paidStatus' => 'PAID',
        ]);
});

it('creates a recurring mandate, forwarding the idempotency key', function (): void {
    [$client, $transport] = clientReturning([
        'checkout_url' => 'https://pay/checkout', 'mandate_id' => 'M9', 'invoice_id' => 90, 'expires_at' => '2026-08-04T10:00:00Z',
    ]);

    $result = $client->recurring->create([
        'firstName' => 'Sam', 'lastName' => 'Doe', 'email' => 's@x.com', 'orderTitle' => 'Gold',
        'orderAmount' => '250.00', 'currency' => 'USD', 'cadenceInterval' => 'month', 'cadenceCount' => 1,
        'totalCycles' => 12, 'consentText' => 'I authorise recurring monthly charges.',
    ], 'sub-42');

    expect($transport->lastRequest()['url'])->toBe('https://api.test/api/v2/integration/recurring/init')
        ->and($transport->lastRequest()['headers']['Idempotency-Key'] ?? null)->toBe('sub-42')
        ->and($transport->lastBody()['cadence_interval'])->toBe('month')
        ->and($result)->toBe([
            'checkoutUrl' => 'https://pay/checkout', 'mandateId' => 'M9', 'invoiceId' => 90, 'expiresAt' => '2026-08-04T10:00:00Z',
        ]);
});

it('reads a recurring mandate status over GET with a signed, body-less query', function (): void {
    [$client, $transport] = clientReturning([
        'mandate_id' => 'MANDATE_UID_123', 'status' => 'active', 'amount' => 1000,
        'completed_cycles' => 2, 'total_cycles' => 12, 'next_charge_at' => '2026-09-01',
    ]);

    $result = $client->recurring->status('MANDATE_UID_123');
    $request = $transport->lastRequest();

    expect($request['method'])->toBe('GET')
        ->and($request['body'])->toBeNull()
        ->and($request['url'])->toContain('/api/v2/integration/recurring/MANDATE_UID_123?')
        ->and($request['url'])->toContain('signature=' . rawurlencode(Signature::build(['MANDATE_UID_123'], HASH)))
        ->and($result)->toMatchArray(['mandateId' => 'MANDATE_UID_123', 'completedCycles' => 2, 'totalCycles' => 12, 'nextChargeAt' => '2026-09-01']);
});

it('cancels a mandate with a uid-signed body', function (): void {
    [$client, $transport] = clientReturning(['cancelled' => true, 'status' => 'cancelled']);

    $result = $client->recurring->cancel('M1');

    expect($transport->lastRequest()['method'])->toBe('POST')
        ->and($transport->lastRequest()['url'])->toBe('https://api.test/api/v2/integration/recurring/M1/cancel')
        ->and($transport->lastBody())->toBe(['token' => 'public_token', 'signature' => Signature::build(['M1'], HASH)])
        ->and($result)->toBe(['cancelled' => true, 'status' => 'cancelled']);
});

it('pauses and resumes on their action paths', function (): void {
    [$paused, $pausedTransport] = clientReturning(['paused' => true, 'status' => 'paused']);
    expect($paused->recurring->pause('M1'))->toBe(['paused' => true, 'status' => 'paused'])
        ->and($pausedTransport->lastRequest()['url'])->toBe('https://api.test/api/v2/integration/recurring/M1/pause');

    [$resumed, $resumedTransport] = clientReturning(['resumed' => true, 'status' => 'active']);
    expect($resumed->recurring->resume('M1'))->toBe(['resumed' => true, 'status' => 'active'])
        ->and($resumedTransport->lastRequest()['url'])->toBe('https://api.test/api/v2/integration/recurring/M1/resume');
});

it('URL-encodes a mandate id with path-unsafe characters', function (): void {
    [$client, $transport] = clientReturning(['cancelled' => true, 'status' => 'x']);

    $client->recurring->cancel('M/1 2');

    expect($transport->lastRequest()['url'])->toContain('/recurring/M%2F1%202/cancel');
});

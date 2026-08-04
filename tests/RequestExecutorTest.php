<?php

declare(strict_types=1);

use GetPayin\Paylink\Core\Exceptions\PaylinkApiException;
use GetPayin\Paylink\Core\Exceptions\PaylinkConnectionException;
use GetPayin\Paylink\Core\Http\RequestOptions;
use GetPayin\Paylink\Tests\Support\FakeTransport;

it('returns the data payload of a success envelope', function (): void {
    $http = makeExecutor(new FakeTransport(FakeTransport::ok(['invoice_id' => 7])));

    expect($http->execute(new RequestOptions('POST', '/x', body: ['a' => 'b'])))->toBe(['invoice_id' => 7]);
});

it('throws a typed API error carrying status, message, and field errors', function (): void {
    $http = makeExecutor(new FakeTransport(FakeTransport::fail(403, [
        'success' => false, 'message' => 'forbidden', 'errors' => ['product' => ['not enabled']],
    ])));

    $caught = null;

    try {
        $http->execute(new RequestOptions('POST', '/x', body: []));
    } catch (PaylinkApiException $error) {
        $caught = $error;
    }

    expect($caught)->toBeInstanceOf(PaylinkApiException::class)
        ->and($caught->getMessage())->toBe('forbidden')
        ->and($caught->status)->toBe(403)
        ->and($caught->isForbidden())->toBeTrue()
        ->and($caught->errors)->toBe(['product' => ['not enabled']]);
});

it('retries a transient 429 when the request is replay-safe', function (): void {
    $transport = new FakeTransport(
        FakeTransport::fail(429, ['success' => false, 'message' => 'slow down']),
        FakeTransport::ok(['ok' => 1]),
    );
    $http = makeExecutor($transport);

    expect($http->execute(new RequestOptions('POST', '/x', body: [], idempotencyKey: 'k')))->toBe(['ok' => 1])
        ->and($transport->requests)->toHaveCount(2);
});

it('never retries a bare POST that could double-charge', function (): void {
    $transport = new FakeTransport(
        FakeTransport::fail(429, ['success' => false]),
        FakeTransport::ok(['ok' => 1]),
    );
    $http = makeExecutor($transport);

    $caught = null;

    try {
        $http->execute(new RequestOptions('POST', '/x', body: []));
    } catch (PaylinkApiException $error) {
        $caught = $error;
    }

    expect($caught?->status)->toBe(429)
        ->and($transport->requests)->toHaveCount(1);
});

it('retries a connection error on a read', function (): void {
    $transport = new FakeTransport(
        new PaylinkConnectionException('network down'),
        FakeTransport::ok(['ok' => 1]),
    );
    $http = makeExecutor($transport);

    expect($http->execute(new RequestOptions('GET', '/x')))->toBe(['ok' => 1])
        ->and($transport->requests)->toHaveCount(2);
});

it('sends the Idempotency-Key header when provided', function (): void {
    $transport = new FakeTransport(FakeTransport::ok([]));

    makeExecutor($transport)->execute(new RequestOptions('POST', '/x', body: [], idempotencyKey: 'abc-123'));

    expect($transport->lastRequest()['headers']['Idempotency-Key'] ?? null)->toBe('abc-123');
});

it('appends url-encoded query parameters and omits the body for a GET', function (): void {
    $transport = new FakeTransport(FakeTransport::ok(['status' => 'active']));

    makeExecutor($transport)->execute(new RequestOptions('GET', '/r/1', query: ['token' => 'pub', 'signature' => 'sig+/=']));

    $request = $transport->lastRequest();

    expect($request['url'])->toContain('https://api.test/r/1?')
        ->and($request['url'])->toContain('token=pub')
        ->and($request['url'])->toContain('signature=sig%2B%2F%3D')
        ->and($request['body'])->toBeNull();
});

it('sends a JSON body and Content-Type for a POST', function (): void {
    $transport = new FakeTransport(FakeTransport::ok([]));

    makeExecutor($transport)->execute(new RequestOptions('POST', '/x', body: ['a' => '1']));

    $request = $transport->lastRequest();

    expect($request['headers']['Content-Type'] ?? null)->toBe('application/json')
        ->and(json_decode((string) $request['body'], true))->toBe(['a' => '1']);
});

it('sends a versioned User-Agent on every request', function (): void {
    $transport = new FakeTransport(FakeTransport::ok([]));

    makeExecutor($transport)->execute(new RequestOptions('POST', '/x', body: []));

    expect($transport->lastRequest()['headers']['User-Agent'] ?? '')->toStartWith('paylink-php/');
});

it('flags an idempotency conflict (409)', function (): void {
    $http = makeExecutor(new FakeTransport(FakeTransport::fail(409, ['data' => null, 'success' => false, 'message' => 'dup'])));

    $caught = null;

    try {
        $http->execute(new RequestOptions('POST', '/x', body: []));
    } catch (PaylinkApiException $error) {
        $caught = $error;
    }

    expect($caught?->isIdempotencyConflict())->toBeTrue()
        ->and($caught?->getMessage())->toBe('dup');
});

it('throws on a 200 body that carries success:false', function (): void {
    $http = makeExecutor(new FakeTransport(FakeTransport::fail(200, ['success' => false, 'message' => 'nope', 'errors' => ['x' => ['y']]])));

    $caught = null;

    try {
        $http->execute(new RequestOptions('POST', '/x', body: []));
    } catch (PaylinkApiException $error) {
        $caught = $error;
    }

    expect($caught?->status)->toBe(200)
        ->and($caught?->errors)->toBe(['x' => ['y']]);
});

it('falls back to data.message when the top-level message is absent', function (): void {
    $http = makeExecutor(new FakeTransport(FakeTransport::fail(403, [
        'success' => false, 'data' => ['message' => 'Recurring payments is disabled for this company.'],
    ])));

    $caught = null;

    try {
        $http->execute(new RequestOptions('POST', '/x', body: []));
    } catch (PaylinkApiException $error) {
        $caught = $error;
    }

    expect($caught?->isForbidden())->toBeTrue()
        ->and($caught?->getMessage())->toBe('Recurring payments is disabled for this company.');
});

it('never retries a bare POST that hits a connection error', function (): void {
    $transport = new FakeTransport(new PaylinkConnectionException('network down'), FakeTransport::ok(['ok' => 1]));

    expect(fn () => makeExecutor($transport)->execute(new RequestOptions('POST', '/x', body: [])))
        ->toThrow(PaylinkConnectionException::class)
        ->and($transport->requests)->toHaveCount(1);
});

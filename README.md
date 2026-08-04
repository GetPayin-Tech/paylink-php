# @getpayin-tech/paylink-php

[![CI](https://github.com/GetPayin-Tech/paylink-php/actions/workflows/ci.yml/badge.svg)](https://github.com/GetPayin-Tech/paylink-php/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/getpayin-tech/paylink-php.svg)](https://packagist.org/packages/getpayin-tech/paylink-php)
[![PHP](https://img.shields.io/packagist/php-v/getpayin-tech/paylink-php.svg)](composer.json)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Official server-side PHP SDK for the PayLink payment integration API — hosted checkouts, payment operations, card tokens, recurring mandates, and webhook verification.

The SDK signs every request exactly the way the PayLink servers expect, so you never reproduce the HMAC signing rules yourself. It is **framework-agnostic** (plain PHP, Symfony, Laravel, a queue worker) with **no required Composer dependencies** beyond `ext-curl`, `ext-json`, and PSR-3.

## Requirements

- PHP 8.2+
- `ext-curl`, `ext-json`

## Installation

```bash
composer require getpayin-tech/paylink-php
```

## Quick start

```php
use GetPayin\Paylink\Core\PaylinkClient;

$paylink = new PaylinkClient(
    publicToken: getenv('PAYLINK_PUBLIC_TOKEN'),
    hashToken: getenv('PAYLINK_HASH_TOKEN'), // secret — server-side only
);

$checkout = $paylink->invoices->create([
    'firstName'   => 'John',
    'lastName'    => 'Doe',
    'email'       => 'john@example.com',
    'orderTitle'  => 'Gold Plan',
    'orderAmount' => '250.00',   // pass money as strings for an exact wire form
    'currency'    => 'USD',
]);
// ['checkoutUrl' => ..., 'invoiceId' => ..., 'expiresAt' => ...]

// Redirect the payer to the returned checkout URL.
header('Location: '.$checkout['checkoutUrl']);
```

Request parameters are passed as `camelCase` arrays; results come back as `camelCase` arrays.

> Using Laravel? Don't construct the client by hand — **inject** the configured `PaylinkClient` from the container instead. See [Using it inside Laravel](#using-it-inside-laravel).

## Payment operations

```php
$paylink->payments->void(['invoiceId' => 12345]);
$paylink->payments->settle(['invoiceId' => 12345, 'amount' => '50.00']);
$paylink->payments->reverseAuthorization(['invoiceId' => 12345]);

$status = $paylink->payments->checkStatus(['invoiceId' => 12345]);
// ['invoiceId' => 12345, 'paidStatus' => '...', 'authCode' => '...']

// Refunds are idempotent when you pass an idempotency key — safe to retry:
$refund = $paylink->payments->refund(
    ['invoiceId' => 12345, 'amount' => '10.50'],
    idempotencyKey: 'refund-order-1234',
);
// ['invoiceId' => ..., 'paidStatus' => ..., 'authCode' => ..., 'refundAmount' => ...]
```

## Card tokenization

```php
$result = $paylink->cards->tokenize([
    'firstName'       => 'Jane',
    'lastName'        => 'Doe',
    'cardNumber'      => '4111111111111111',
    'cardExpiryMonth' => '12',
    'cardExpiryYear'  => '2030',
    'cardCvv'         => '123',
    'country'         => 'EG',
    'address'         => '1 Main St',
    'city'            => 'Cairo',
]);
$token = $result['token'];

$paylink->cards->charge([
    'cardToken' => $token,
    'initiator' => 'merchant',
    'firstName' => 'Jane',
    'lastName'  => 'Doe',
    'currency'  => 'USD',
    'price'     => '100.00',
    'product'   => 'Monthly rebill',
    'country'   => 'EG',
    'address'   => '1 Main St',
    'city'      => 'Cairo',
]);

$paylink->cards->revoke(['cardToken' => $token]);
```

For `US` and `CA` billing addresses, also pass the state fields the API requires: `usState` + `postalCode` (US) or `canadaState` + `postalCode` (CA).

## Recurring mandates

```php
$mandate = $paylink->recurring->create(
    [
        'firstName'       => 'Sam',
        'lastName'        => 'Doe',
        'email'           => 'sam@example.com',
        'orderTitle'      => 'Gold subscription',
        'orderAmount'     => '250.00',
        'currency'        => 'USD',
        'cadenceInterval' => 'month',
        'cadenceCount'    => 1,
        'totalCycles'     => 12,
        'consentText'     => 'I authorise recurring monthly charges.',
    ],
    idempotencyKey: 'sub-signup-42',
);

$paylink->recurring->status($mandate['mandateId']);
$paylink->recurring->pause($mandate['mandateId']);
$paylink->recurring->resume($mandate['mandateId']);
$paylink->recurring->cancel($mandate['mandateId']);
```

## Idempotency

Retrying a write after a network error or timeout risks performing it twice. To make that safe, pass an `idempotencyKey` — the SDK sends it as the `Idempotency-Key` header and the server returns the original result instead of charging, refunding, or creating a second time. Keys are scoped per integration and capped at 64 characters.

| Endpoint            | A replay with the same key returns     |
| ------------------- | -------------------------------------- |
| `invoices->create`  | the original invoice and `checkoutUrl` |
| `vcc->charge`       | the original charge                    |
| `cards->charge`     | the original charge                    |
| `payments->refund`  | the original refund                    |
| `recurring->create` | the original mandate                   |

```php
$paylink->vcc->charge([/* card + order fields */], idempotencyKey: 'vcc-order-1234');
$paylink->cards->charge([/* token + order fields */], idempotencyKey: 'tok-order-1234');
$paylink->invoices->create([/* customer + order fields */], idempotencyKey: 'order-1234');
```

Reusing a key with a _different_ request — for example `recurring->create` with changed terms, or `payments->refund` for a different amount — is rejected as a conflict: a `PaylinkApiException` whose `isIdempotencyConflict()` is true (HTTP 409). Only the endpoints above honor the header.

## Verifying webhooks

Pass the **raw request body** (or a decoded array) to `verify()`. It recomputes the signature with your `hashToken` and compares in constant time, throwing `PaylinkSignatureException` on a mismatch.

```php
use GetPayin\Paylink\Core\Exceptions\PaylinkSignatureException;
use GetPayin\Paylink\Core\Webhook\WebhookEventType;

try {
    $event = $paylink->webhooks->verify(file_get_contents('php://input'));
    // $event->event, $event->invoiceId, $event->success, $event->raw, ...
} catch (PaylinkSignatureException) {
    http_response_code(400);
    exit;
}

if ($event->type() === WebhookEventType::InvoicePaid) {
    // Fulfil the order.
}
```

> PayLink webhook signatures carry no timestamp, so verification does not protect against replay. Pair it with your own idempotency keyed on `invoice_id`.

## Error handling

Every failure extends `GetPayin\Paylink\Core\Exceptions\PaylinkException`:

| Error                       | When                                                                                                                                                 |
| --------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| `PaylinkConfigException`    | Invalid client configuration (missing tokens, non-positive timeout).                                                                                 |
| `PaylinkApiException`       | The API returned an error. Carries `status`, `errors`, `raw`, `retryAfterMs`, and `isIdempotencyConflict()` (409), `isRateLimited()` (429), `isForbidden()` (403 — e.g. card tokenization or recurring payments not enabled). |
| `PaylinkSignatureException` | A webhook signature did not verify.                                                                                                                  |
| `PaylinkConnectionException`| Network failure or timeout (no HTTP response).                                                                                                       |

```php
use GetPayin\Paylink\Core\Exceptions\PaylinkApiException;

try {
    $paylink->payments->refund(['invoiceId' => 12345, 'amount' => '10.00']);
} catch (PaylinkApiException $error) {
    if ($error->isIdempotencyConflict()) {
        // a refund with this idempotency key already exists
    }
}
```

## Retries and rate limiting

Every integration endpoint is rate limited server-side, so 429s are an expected condition under burst traffic rather than an edge case. The SDK retries transient failures — **429, 5xx, connection errors, and timeouts** — with exponential backoff and full jitter, honoring the server's `Retry-After` header when present.

**A request is only ever replayed when replaying it cannot double-charge:**

| Replayed                                   | Not replayed                                          |
| ------------------------------------------ | ----------------------------------------------------- |
| All `GET`s (`recurring->status`)           | `vcc->charge`, `cards->charge`, `cards->tokenize`      |
| Any call you pass an `idempotencyKey` to   | `invoices->create`, `recurring->create` without a key |
| `payments->checkStatus` (a pure read)      | `recurring->cancel` / `pause` / `resume`              |

So to make a refund safely retryable, pass an idempotency key — otherwise a failed refund surfaces immediately and is yours to handle:

```php
$paylink->payments->refund(
    ['invoiceId' => 12345, 'amount' => '10.50'],
    idempotencyKey: 'refund-order-1234', // now retried on 429/5xx
);
```

Tune or disable retries per client:

```php
new PaylinkClient(publicToken: $pub, hashToken: $secret, maxRetries: 0); // off
```

`timeoutMs` applies to **each attempt**, so worst-case wall time is roughly `(maxRetries + 1) × timeoutMs` plus backoff. For requests the SDK will not replay, `PaylinkApiException::$retryAfterMs` exposes the server's backoff hint (milliseconds) so you can schedule your own retry:

```php
} catch (PaylinkApiException $error) {
    if ($error->isRateLimited()) {
        // reschedule using $error->retryAfterMs
    }
}
```

## Amounts and precision

Signatures are computed over the exact bytes sent on the wire. To avoid any floating-point ambiguity, **pass monetary amounts as strings** (e.g. `'10.50'`). Integers are accepted and stringified, but strings give you full control.

## Logging

The SDK logs to any [PSR-3](https://www.php-fig.org/psr/psr-3/) logger you pass — retries at `warning`, exhausted retries at `error`, rejected webhooks at `warning`, and a timed `info` line per request. Every context array is masked, so a token, secret, signature, or card field never reaches the log. With no logger, output is silently discarded.

In Laravel, point it at a dedicated channel:

```php
// config/logging.php
'channels' => [
    'paylink' => [
        'driver' => 'daily',
        'path' => storage_path('logs/paylink.log'),
        'level' => 'debug',
        'days' => 14,
    ],
],
```

```php
use Illuminate\Support\Facades\Log;

$paylink = new PaylinkClient(
    publicToken: config('services.paylink.public_token'),
    hashToken: config('services.paylink.hash_token'),
    logger: Log::channel('paylink'),
);
```

Outside Laravel, pass a Monolog logger (or any PSR-3 implementation) the same way.

## Using it inside Laravel

The package ships an **auto-discovered** service provider and a `Paylink` facade — no manual wiring. Set the credentials in your environment:

```dotenv
PAYLINK_PUBLIC_TOKEN=pub_...
PAYLINK_HASH_TOKEN=secret_...
PAYLINK_LOG_CHANNEL=paylink
```

Then inject the configured client — **constructor injection** is the idiomatic way, and the container resolves the singleton from your config:

```php
use GetPayin\Paylink\Core\PaylinkClient;
use Illuminate\Http\RedirectResponse;

final class CheckoutController
{
    public function __construct(
        private readonly PaylinkClient $paylink,
    ) {}

    public function store(): RedirectResponse
    {
        $checkout = $this->paylink->invoices->create([
            'firstName'   => 'John',
            'lastName'    => 'Doe',
            'email'       => 'john@example.com',
            'orderTitle'  => 'Gold Plan',
            'orderAmount' => '250.00',
            'currency'    => 'USD',
        ]);

        return redirect()->away($checkout['checkoutUrl']);
    }
}
```

Method injection (route actions, jobs, listeners) and the facade resolve the same singleton:

```php
use GetPayin\Paylink\Core\PaylinkClient;
use GetPayin\Paylink\Laravel\Facades\Paylink;

public function checkout(PaylinkClient $paylink)
{
    return $paylink->invoices->create([/* ... */]);
}

Paylink::invoices()->create([/* ... */]);
$event = Paylink::webhooks()->verify(request()->getContent());
```

The provider binds the client through `LaravelHttpTransport` (Laravel's HTTP client, so `Http::fake()` works in tests) and logs to your configured channel. Publish the config to tune it:

```bash
php artisan vendor:publish --tag=paylink-config
```

Outside auto-discovery (or to run more than one integration), construct the client directly and pass any transport — implement `GetPayin\Paylink\Core\Http\Transport` to plug in Guzzle, a PSR-18 client, or a fake:

```php
use GetPayin\Paylink\Laravel\LaravelHttpTransport;

$paylink = new PaylinkClient(
    publicToken: config('paylink.public_token'),
    hashToken: config('paylink.hash_token'),
    transport: new LaravelHttpTransport(),
    logger: Log::channel('paylink'),
);
```

## Security

- `hashToken` is a signing secret. Never ship it to a browser, a mobile app, or a client bundle. It is redacted from `var_dump()` and `json_encode()` on the client, but load it from an environment variable or secret manager and never log it.
- `vcc->charge` and `cards->tokenize` accept raw PAN/CVV, which puts your server in PCI scope. Prefer the hosted checkout (`invoices->create`) or card tokens where possible.

See [SECURITY.md](SECURITY.md) for the full security policy and how to report a vulnerability privately.

## API reference

The full HTTP API — endpoints, fields, error codes, and test cards — is documented in the PayLink API reference: <https://pay.getpayin.com/docs/payment_integration/index.html>

## Development

```bash
composer install
composer check   # pint (lint) + phpstan (level 7) + pest
```

See [CONTRIBUTING.md](CONTRIBUTING.md) — the signing contract and how to keep it in sync with the server.

## License

MIT — see [LICENSE](LICENSE).

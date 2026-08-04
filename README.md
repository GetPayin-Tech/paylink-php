# getpayin-tech/paylink-php

[![CI](https://github.com/GetPayin-Tech/paylink-php/actions/workflows/ci.yml/badge.svg)](https://github.com/GetPayin-Tech/paylink-php/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/getpayin-tech/paylink-php.svg)](https://packagist.org/packages/getpayin-tech/paylink-php)
[![PHP](https://img.shields.io/packagist/php-v/getpayin-tech/paylink-php.svg)](composer.json)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Official server-side PHP SDK for the PayLink payment integration API — hosted checkouts, payment operations, card tokens, recurring mandates, and webhook verification.

The SDK signs every request exactly the way the PayLink servers expect, so you never reproduce the HMAC signing rules yourself. It is **framework-agnostic** (plain PHP, Symfony, Laravel, a queue worker) with **zero required Composer dependencies** — just `ext-curl` and `ext-json`.

## Requirements

- PHP 8.1+
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

// Redirect the payer to the returned checkout URL.
header('Location: '.$checkout['checkoutUrl']);
```

## Resources

| Namespace              | Methods                                                             |
| ---------------------- | ------------------------------------------------------------------ |
| `$paylink->invoices`   | `create`                                                           |
| `$paylink->payments`   | `void`, `refund`, `settle`, `reverseAuthorization`, `checkStatus`  |
| `$paylink->vcc`        | `charge` (raw card data — requires PCI scope)                       |
| `$paylink->cards`      | `tokenize`, `charge`, `revoke`                                      |
| `$paylink->recurring`  | `create`, `status`, `cancel`, `pause`, `resume`                    |
| `$paylink->webhooks`   | `verify`, `isValid`                                                 |

Request parameters are passed as `camelCase` arrays and results are returned as `camelCase` arrays. Pass an idempotency key to make a write safely retryable:

```php
$paylink->payments->refund(['invoiceId' => 12345, 'amount' => '10.50'], idempotencyKey: 'refund-12345-1');
```

## Webhooks

`verify()` recomputes the signature and throws `PaylinkSignatureException` on a mismatch. It accepts the raw request body or a decoded array.

```php
use GetPayin\Paylink\Core\Exceptions\PaylinkSignatureException;
use GetPayin\Paylink\Core\Webhook\WebhookEventType;

try {
    $event = $paylink->webhooks->verify(file_get_contents('php://input'));
} catch (PaylinkSignatureException) {
    http_response_code(400);
    exit;
}

if ($event->type() === WebhookEventType::InvoicePaid) {
    // Fulfil the order. Signatures carry no timestamp, so key your own
    // idempotency on $event->invoiceId to guard against replays.
}
```

## Error handling

Every failure extends `GetPayin\Paylink\Core\Exceptions\PaylinkException`:

- `PaylinkApiException` — a non-success response. Carries `status`, `errors`, `raw`, and helpers `isForbidden()`, `isRateLimited()`, `isIdempotencyConflict()`.
- `PaylinkConnectionException` — network failure or timeout.
- `PaylinkSignatureException` — a webhook signature mismatch.
- `PaylinkConfigException` — invalid client configuration.

Transient failures (429, 5xx, connection errors) are retried with exponential backoff and jitter, honoring `Retry-After`. A request is only ever replayed when it cannot double-charge: GETs, requests with an `Idempotency-Key`, and `payments->checkStatus`. A bare `vcc->charge` or `cards->charge` is never retried.

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

Then inject the configured client, or use the facade:

```php
use GetPayin\Paylink\Core\PaylinkClient;

public function checkout(PaylinkClient $paylink)   // resolved from config
{
    return $paylink->invoices->create([...]);
}

// or, via the facade:
use GetPayin\Paylink\Laravel\Facades\Paylink;

Paylink::invoices()->create([...]);
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

## Development

```bash
composer install
composer check   # pint (lint) + phpstan (level 7) + pest
```

See [CONTRIBUTING.md](CONTRIBUTING.md) — the signing contract and how to keep it in sync with the server.

## License

MIT — see [LICENSE](LICENSE).


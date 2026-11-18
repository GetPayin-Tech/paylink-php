# Changelog

All notable changes to this package are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.3.0] - 2026-11-18

### Fixed

- `webhooks->verify()` now accepts `card_token.created` and `card_token.failed`. Both carry the
  tokenisation and the card it produced, and the server signs all of those fields; this SDK signed
  none of them, so every card-token webhook failed verification. Both events are on by default for
  every integration, so any integrator saving cards was receiving webhooks it could not verify.

### Added

- `CardTokenCreated` and `CardTokenFailed` on `WebhookEventType`.
- The saved card on `WebhookEvent`: `tokenizationId`, `cardToken`, `cardBrand`, `cardLast4`,
  `cardExpMonth` and `cardExpYear`. They are appended after `raw`, so positional construction of an
  event written before they existed still works.

## [0.2.0] - 2026-08-06

### Added

- Optional `iframe` param on `invoices->create` — pass `'iframe' => true` to enable
  embedded (iframe) checkout. Like `payment_mode`, it is sent in the request body but
  excluded from the HMAC signature.

## [0.1.0] - 2026-08-04

### Added

- Initial PayLink PHP SDK: `invoices`, `payments`, `vcc`, `cards`, and `recurring`
  resources, plus `webhooks` signature verification.
- Framework-agnostic HTTP layer with a pluggable `Transport` (cURL by default) and
  an optional `LaravelHttpTransport` adapter for the `Http` facade.
- Byte-exact request signing verified against the shared golden-signature vectors.
- Transient-failure retries with exponential backoff, jitter, `Retry-After`, and
  replay-safety (never retries a bare charge).
- PSR-3 logging with secret masking, and optional auto-discovered Laravel
  integration (service provider, `Paylink` facade, publishable config).

[0.2.0]: https://github.com/GetPayin-Tech/paylink-php/releases/tag/v0.2.0
[0.1.0]: https://github.com/GetPayin-Tech/paylink-php/releases/tag/v0.1.0

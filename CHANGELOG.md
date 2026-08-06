# Changelog

All notable changes to this package are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

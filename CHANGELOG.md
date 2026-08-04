# Changelog

All notable changes to this package are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Initial PayLink PHP SDK: `invoices`, `payments`, `vcc`, `cards`, and `recurring`
  resources, plus `webhooks` signature verification.
- Framework-agnostic HTTP layer with a pluggable `Transport` (cURL by default) and
  an optional `LaravelHttpTransport` adapter for the `Http` facade.
- Byte-exact request signing verified against the shared golden-signature vectors.
- Transient-failure retries with exponential backoff, jitter, `Retry-After`, and
  replay-safety (never retries a bare charge).

[Unreleased]: https://github.com/GetPayin-Tech/paylink-php/commits/main

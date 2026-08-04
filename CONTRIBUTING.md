# Contributing

## Setup

```bash
composer install
composer check   # pint (lint) + phpstan (level 7) + pest
```

`composer check` runs everything CI runs. Run it before opening a PR.

| Script            | What it does                          |
| ----------------- | ------------------------------------- |
| `composer test`   | Run the Pest suite                    |
| `composer stan`   | PHPStan static analysis (level 7)     |
| `composer lint`   | Pint in `--test` mode (no changes)    |
| `composer format` | Pint (apply fixes)                    |

## The one thing to get right: signature parity

The SDK's core job is reproducing a byte-exact, order-sensitive HMAC that the
server rebuilds independently. The server signs
`implode('', Arr::except($request->validated(), [...]))`, and Laravel's
`validated()` returns fields in **`rules()` order**, skipping absent ones.

That means **the order of fields in `src/Core/Internal/FieldOrders.php` must match
the order of keys in the endpoint's FormRequest `rules()` method**, minus
`token`/`signature` (and `payment_mode`, which is sent but not signed).

`tests/SignatureParityTest.php` runs the shared `tests/fixtures/golden-signatures.json`
vectors — the same file the JS SDK verifies against, generated from the PHP
server — so any drift in field order or coercion fails the suite.

## Conventions

- `declare(strict_types=1)` in every file; explicit return types on every method.
- `final` classes, guard clauses over `if/else`, no fully-qualified class names inline.
- Add or update tests for every change; keep `composer check` green.

# Changelog

All notable changes to `laravel-square` will be documented in this file.

## 1.0.1 - 2026-10-05

- An empty `SQUARE_ENVIRONMENT`, `SQUARE_CURRENCY` or `SQUARE_TIMEOUT`, as `square:install` writes them, now falls back to the default; an empty environment used to throw instead of using sandbox.

## 1.0.0 - 2026-10-05

First release, for Laravel 12 and 13 (PHP 8.2+) on the official `square/square` SDK (v45–v47).

- The SDK's `SquareClient`, configured from `.env` (access token, sandbox/production, `Square-Version`), bound as a singleton and exposed through the `Square` facade: `Square::payments()`, `Square::customers()` and every other API client.
- SDK requests go through Laravel's HTTP client, so `Http::fake()` and `Http::preventStrayRequests()` work in tests.
- `Square::forMerchant($token)` for acting on behalf of another seller.
- Webhooks: `VerifyWebhookSignature` middleware (`square.webhook`) checking the `x-square-hmacsha256-signature` HMAC in constant time, and an opt-in route that dispatches `WebhookReceived` and a `square.{type}` event, with `event_id` de-duplication.
- `Square::idempotencyKey()`: random v4 UUIDs, or deterministic keys from parts for safe retries.
- `Money` helpers: decimal amounts to and from minor units with ISO 4217 precision, without floating-point rounding.
- `Square::oauth()`: authorization URL (code and PKCE flows), code exchange, token refresh and revocation.
- `<x-square::card-form />`: the Web Payments SDK card field, tokenizing into a hidden `source_id` input.
- `square:install` (config + `.env` keys) and `square:status` (connection check) commands.
- Test suite and GitHub Actions CI.

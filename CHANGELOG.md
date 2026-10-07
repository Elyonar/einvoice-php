# Changelog

All notable changes to this project will be documented in this file. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

The Yona SDKs are versioned independently of each other and of `@useyona/einvoice-js`; the
einvoice-js commit (or tag) pinned in `scripts/sync.sh` records which JS release this one tracks.

## 0.1.0 (2026-10-07)

The first release of the PHP SDK, at parity with `@useyona/einvoice-js` 0.8.1 (einvoice-js
`feat/codegen-emitters`, commit `95dff8f`): 99 methods in 23 modules over the 106 operations an API
key may call, 8 documented exclusions.

### Added

- `new EInvoice(['api_key' => …])`: the mode follows the key prefix (`sk_test_` sandbox, `sk_live_`
  live) on one permanent host; `assert_mode`, `timeout` (seconds), `retry` (`max_retries`,
  `base_delay`, `max_delay`, `max_retry_after`), `headers`, and the PSR-18/PSR-17 `http_client`,
  `request_factory`, `stream_factory` (discovered with `php-http/discovery` when omitted; a Guzzle
  client is built with the configured timeout).
- Modules `invoices`, `submissions`, `output`, `shareLinks`, `items`, `reference`, `buyers`, `sellers`,
  `inboundInvoices`, `issuedHistory`, `invoiceSettings`, `taxConnection`, `organization`,
  `billing->{accounts, payments, sandbox, statements, subscriptions, transactions}`,
  `webhooks->{endpoints, deliveries, events, eventTypes}`; `EInvoice::paginate()` / `Paginate::all()`.
- The exception hierarchy (`ApiException` and its subclasses by status, `TimeoutException`,
  `ConnectionException`, `ConfigException`, `WebhookException`; all extend `EInvoiceException`).
- Retries with backoff and `Retry-After` for GET/PUT/DELETE and keyed writes; generated
  `Idempotency-Key`s on the routes that accept one.
- `Webhooks::verifyWebhook`, `signWebhookPayload`, `computeWebhookSignature`,
  `parseSignatureHeader`, verified against the backend's test vectors; headers from a plain array,
  `$_SERVER` or a PSR-7 request.
- Generated phpstan array shapes for every request and response (`Useyona\EInvoice\Generated\Types`).
- The five guide recipes in `examples/` and their export to `guides/guides.json`.

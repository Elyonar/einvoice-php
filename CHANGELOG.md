# Changelog

All notable changes to this project will be documented in this file. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

The Yona SDKs are versioned independently of each other and of `@useyona/einvoice-js`; the
einvoice-js commit (or tag) pinned in `scripts/sync.sh` records which JS release this one tracks.

## 0.2.1 (2026-11-11)

In line with `@useyona/einvoice-js` 0.9.1: the 138 operations an API key may call (134 before), 13
documented exclusions; 126 methods in 27 modules.

### Added

- `$client->organization->listSubOrganizations()`, `listSubOrganizationsReadiness()` and
  `createSubOrganization()`: a parent organisation lists its sub-organisations (a page, newest first;
  `search`, `ids`), reads how ready each is for live, and creates one. The capabilities are
  `organization.children.read` and `organization.children.create`, which no key preset includes.
  Creating needs a live key (a sandbox key is refused with 403 `BIZ003`), sends no `Idempotency-Key`
  and is not retried: a repeat answers 409 `RES002`. 402 `BIZ016` when the plan allows no more
  organisations.
- `$client->taxConnection->listForSubOrganizations(['ids' => […]])`: the state of the connection to
  the tax authority of up to 100 sub-organisations, and whether a submission would pass.
- `ids` may be given as a list; it reaches the API as one comma-separated value.
- `$client->billing->partner->get(['page' => …, 'limit' => …])` pages the partner's businesses; the
  answer carries `businessesPage`. `get($options)` is unchanged.
- `$client->reference->getResource('states' | 'lgas' | 'countries')` has typed items for phpstan
  (`StateOption`, `LgaOption`, `CountryOption` of `ReferenceService`). A buyer's Nigerian address
  takes those codes: 400 `VAL202` (state) or `VAL203` (LGA) otherwise.
- `OrganizationChildCreatedData` (a phpstan shape of `WebhookEventType`): `organization.child.created`
  carries `keyId` when an API key created the sub-organisation.
- Two guide recipes: `states-and-lgas` and `sub-organisations` (seven in all).

### Changed

- The generated models follow the API: the organisation address gains `lga`, the organisation
  `onboardingProfile`, readiness `profile` and `liveRejection`, the billing plan `coveredBy`, payments
  `netMinor`, `vatMinor`, `serviceFeeItem` and the kind `service_fee`, and plan-change quotes the
  reason `renewal_in_progress`.

### Fixed

- `make sync` with `EINVOICE_JS_DIR` resolves the local einvoice-js checkout with `pwd -P` too, so
  the generator runs when the folder is reached through a symlink.

## 0.2.0 (2026-11-04)

In line with `@useyona/einvoice-js` 0.9.0: the 134 operations an API key may call, 13 documented
exclusions.

### Added

- `$client->collections`: the bank accounts that collect an invoice's payment and what they received (accounts:
  list, create, get, status, deactivate; the fee quote; payments; buyer credits; exceptions: list,
  resolve; settlement accounts; banks). They answer only where collections are switched on for the
  key's mode; elsewhere 409 `BIZ120`.
- `$client->billing->usage` (`getInvoices`, `quoteOverage`), `$client->billing->plans` (`getRecommendation`), `$client->billing->partner` (`get`, `getOffer`, `quoteSeat`, `quoteBasket`, `quotePlanChange`), and `$client->billing->subscriptions->getDealOffer()` and `quoteSubOrgSlot()`. Answers that can be empty are `null`.
- `$client->taxConnection->get()` takes the query `['identifier' => 'true']` / `['fresh' => 'true']`: which number the tax authority holds for the organisation, its TIN or its 13-digit Tax ID. `get($options)` is unchanged.
- `guides/operations.json` (`make operations`): per API operation, the method that calls it and a
  call template for the portal's API playground, derived from the same recorded calls as the parity
  test; `make test` compiles every template (`php -l` and phpstan) and fails when the file is stale.

### Changed

- **Breaking:** the generated models follow the API. Plan-change quotes carry `daysLeft` and `periodDays`,
  and no longer `creditMinor`, `windowsInPeriod` or `windowsUnissued`. Payment, ledger and plan-change
  reason enums gain values. The organisation gains `einvoicingTaxId`, the billing account `plan`, invoices
  `createdViaClientId`, and the tax connection `einvoicingIdentifier`, `identifierSource` and
  `identifierNextAction`.
- The payments service's reads (bank account, transfers) are excluded until payments launches in
  production.

### Fixed

- `make sync` regenerates the models on macOS (the einvoice-js checkout is resolved with `pwd -P`).

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

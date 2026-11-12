# useyona/einvoice-php

The official PHP SDK for the [Yona](https://useyona.com) e-invoicing API: create and manage
invoices, report them to the tax authority, and work with buyers, items, received invoices, billing,
collections and webhooks. PHP 8.1+, built on PSR-18, so it works with the HTTP client you already have.

Every Yona SDK has the same modules and methods; in PHP the names are `camelCase`
(`$client->invoices->issueCreditNote()`).

## Install

```bash
composer require useyona/einvoice-php
```

The SDK needs a PSR-18 HTTP client. If your project has none, `composer require guzzlehttp/guzzle`
installs one that the SDK finds automatically.

## Quick start

```php
<?php

require 'vendor/autoload.php';

use Useyona\EInvoice\EInvoice;

$client = new EInvoice(['api_key' => getenv('YONA_API_KEY')]);

// 1. A buyer
$buyer = $client->buyers->create([
    'name' => 'Acme Nigeria Ltd',
    'taxId' => '12345678-0001',
    'email' => 'accounts@acme.ng',
    'partyType' => 'company',
    'address' => ['line1' => '1 Marina', 'city' => 'Lagos', 'country' => 'NG'],
]);

// 2. A saved item
$item = $client->items->create([
    'name' => 'Laptop',
    'itemType' => 'goods',
    'hsnCode' => '8471.30',
    'productCategory' => 'Machinery',
    'unitCode' => 'EA',
    'unitPriceMinor' => '45000000',
    'currency' => 'NGN',
    'taxCategory' => 'STANDARD_VAT',
]);

// 3. A draft invoice
$invoice = $client->invoices->create([
    'invoiceKind' => 'B2B',
    'invoiceDate' => '2026-10-05',
    'currency' => 'NGN',
    'buyerId' => $buyer['id'],
    'lineItems' => [['itemId' => $item['id'], 'quantity' => 2]],
]);

// 4. Finalise it and report it to the tax authority
$client->invoices->finalise($invoice['id']);
$client->submissions->submit($invoice['id']);
$status = $client->submissions->getStatus($invoice['id']);
```

## Sandbox and live

The key is the only configuration. An `sk_test_…` key works in the sandbox and an `sk_live_…` key
in live, on the same host; `$client->mode()` tells you which. Going live means deploying a live
key, nothing else changes.

To make a deployment refuse a key of the wrong kind:

```php
new EInvoice(['api_key' => $key, 'assert_mode' => 'live']);
```

## Responses and pagination

Methods return the API's answer as an array with the API's field names (`$invoice['invoiceNumber']`);
the shapes are documented for phpstan and your IDE. List methods return a page:

```php
$page = $client->buyers->list(['limit' => 50]);
$page->data;       // the buyers
$page->pagination; // total, page, pageSize, totalPages, hasNext, hasPrevious
```

`EInvoice::paginate` walks every page for you:

```php
foreach (EInvoice::paginate(fn (array $q) => $client->buyers->list($q), ['limit' => 100]) as $buyer) {
    echo $buyer['name'], "\n";
}
```

Received invoices and issued history return a `Paginated` object with the items under
`$res->data['items']`. PDF downloads return a `BinaryResponse` (`data`, `contentType`, `fileName`).

## Errors

A refused request throws an exception chosen by status:

| Status | Exception | Typical `errorCode` |
|---|---|---|
| 400, 422 | `ValidationException` | `VAL001`; on a buyer's address `VAL202` (state not on the list) and `VAL203` (LGA not on the list, or of another state) |
| 401 | `AuthenticationException` | `AUTH041` (key revoked, expired or unknown), `AUTH004` (no credential) |
| 402 | `InsufficientCreditsException` | `BIZ001`; `BIZ016` from `organization->createSubOrganization` (the plan allows no more organisations) |
| 403 | `PermissionException` | `AUTH018` (route not for API keys), `AUTH019` (capability missing), `BIZ108` (other mode), `BIZ003` (a sandbox key creating a sub-organisation) |
| 404 | `NotFoundException` | `RES001` |
| 409 | `ConflictException` | `BIZ004`, `BIZ201`, `BIZ205`, `RES002` (it already exists) … |
| 429 | `RateLimitException` | `SYS005` (`retryAfter` set) |
| 5xx | `ServerException` | |

All extend `ApiException`, which carries `status`, `errorCode`, `errors` (per field), `requestId`
and `retryAfter`:

```php
use Useyona\EInvoice\Exception\ApiException;
use Useyona\EInvoice\Exception\ValidationException;

try {
    $client->invoices->create($params);
} catch (ValidationException $e) {
    foreach ($e->errors as $error) {
        echo $error->field, ': ', $error->message, "\n";
    }
} catch (ApiException $e) {
    echo $e->status, ' ', $e->errorCode, ' ', $e->requestId; // quote requestId to support
}
```

Branch on `errorCode`, not on the message. Problems before the API answers are `TimeoutException`,
`ConnectionException` and `ConfigException`; all SDK exceptions extend `EInvoiceException`.

## Retries and idempotency

Reads, and writes that carry an `Idempotency-Key`, are retried automatically on network errors,
timeouts, 408, 429 and 5xx, with backoff and respecting `Retry-After`. Other writes are never retried.

Where the API accepts an `Idempotency-Key` (creating an invoice, submitting, sending, downloading…)
the SDK generates one per call, so a retry is never applied or charged twice. Pass your own to make a
retry safe across restarts:

```php
use Useyona\EInvoice\RequestOptions;

$client->invoices->create($params, new RequestOptions(idempotencyKey: "order-{$orderId}"));
```

Tune the defaults with `timeout` and `retry` in the configuration, or per call with `RequestOptions`
(`headers`, `maxRetries`).

## Webhooks

```php
use Useyona\EInvoice\Exception\WebhookException;
use Useyona\EInvoice\Webhooks;

// Plain PHP
try {
    $event = Webhooks::verifyWebhook(file_get_contents('php://input'), $_SERVER, getenv('YONA_WEBHOOK_SECRET'));
} catch (WebhookException $e) {
    http_response_code(400);
    exit;
}
if ($event['type'] === 'invoice.accepted') {
    // …
}

// PSR-7 (Laravel, Symfony, Slim…)
$event = Webhooks::verifyWebhook($request->getBody(), $request, $secret);
```

`verifyWebhook` checks the `Yona-Signature` header over the raw body and returns the event. Always
pass the raw body, and deduplicate on `$event['id']`. `Webhooks::signWebhookPayload` signs a payload
the way Yona does, so you can test your handler locally. Endpoints are registered in the dashboard;
the SDK can list and test them and inspect deliveries.

## Guides

[`examples/`](examples/) holds seven complete, runnable walkthroughs. Run them against your sandbox
key with `YONA_API_KEY=sk_test_… make examples`.

| Recipe | What it shows |
|---|---|
| [`first-invoice.php`](examples/first-invoice.php) | buyer → item → draft → finalise → submit → status → PDF and QR |
| [`webhooks.php`](examples/webhooks.php) | verifying a delivery, reading the event, listing deliveries and redelivering |
| [`errors-and-retries.php`](examples/errors-and-retries.php) | `errorCode`, `status`, `requestId`, `retryAfter`; idempotency keys; a safe retry helper |
| [`sandbox-and-live.php`](examples/sandbox-and-live.php) | the mode from the key, `assert_mode`, and why going live changes only the key |
| [`received-invoices.php`](examples/received-invoices.php) | received invoices: filters and walking every page with `EInvoice::paginate` |
| [`states-and-lgas.php`](examples/states-and-lgas.php) | the state and LGA lists, an LGA picker filtered by state, a buyer saved with the codes, `VAL202` and `VAL203` |
| [`sub-organisations.php`](examples/sub-organisations.php) | listing sub-organisations with a key, their readiness for live and their tax connections, creating one with a live key |

## API reference

Every method takes an optional trailing `?RequestOptions $options`. Request and query arrays have
shapes named after the operation (`CreateInvoiceBody`, `ListInvoicesQuery`) in
`Useyona\EInvoice\Generated\Types`. 126 methods in 27 modules; a test proves every operation an API
key may call is a method or a documented exclusion (`ExcludedOperations`).

| Module | Methods |
|---|---|
| `$client->invoices` | `create`, `list`, `get`, `update`, `delete`, `finalise`, `reopen`, `revise`, `cancel`, `issueCreditNote`, `issueDebitNote`, `getOverview`, `getStatistics`, `getSummary` |
| `$client->submissions` | `submit`, `issue`, `createAndSubmit`, `batchSubmit`, `retry`, `renumber`, `queryStatus`, `getStatus`, `recordPayment`, `getAuthorityCopy` |
| `$client->output` | `getDownloadLink`, `downloadPdf`, `send` |
| `$client->shareLinks` | `create`, `list`, `revoke` |
| `$client->items` | `list`, `create`, `get`, `update`, `delete`, `archive`, `unarchive`, `listUsedCodes` |
| `$client->reference` | `listHsCodes`, `listHsCodeCategories`, `listResources`, `getResource`, `lookupTaxId`, `validateInvoice` |
| `$client->buyers` | `create`, `list`, `get`, `update`, `delete`, `bulkDelete`, `verifyTaxNumber`, `getVerificationStatus`, `search`, `checkReachability`, `checkWithTaxAuthority` |
| `$client->sellers` | `list`, `get`, `getVerificationStatus`, `search` |
| `$client->inboundInvoices` | `list`, `get`, `getAnalytics`, `listHistoryRuns`, `getHistoryRun`, `loadOlder` |
| `$client->issuedHistory` | `list`, `get`, `downloadPdf` |
| `$client->invoiceSettings` | `get` |
| `$client->taxConnection` | `get`, `listForSubOrganizations` |
| `$client->organization` | `get`, `getReadiness`, `listSubOrganizations`, `listSubOrganizationsReadiness`, `createSubOrganization` |
| `$client->billing->accounts` | `getMine`, `getStats`, `checkBalance` |
| `$client->billing->payments` | `list`, `get` |
| `$client->billing->sandbox` | `listTransactions`, `getUsage` |
| `$client->billing->statements` | `get` |
| `$client->billing->subscriptions` | `getActive`, `list`, `get`, `listRenewals`, `previewPlanChange`, `getDealOffer`, `quoteSubOrgSlot` |
| `$client->billing->transactions` | `list`, `get`, `getUsageAnalytics`, `getUsageByCostCode` |
| `$client->billing->usage` | `getInvoices`, `quoteOverage` |
| `$client->billing->plans` | `getRecommendation` |
| `$client->billing->partner` | `get`, `getOffer`, `quoteSeat`, `quoteBasket`, `quotePlanChange` |
| `$client->collections` | `listAccounts`, `createAccount`, `getAccount`, `getAccountStatus`, `deactivateAccount`, `quoteFee`, `listPayments`, `listBuyerCredits`, `listExceptions`, `resolveException`, `listSettlementAccounts`, `getSettlementAccount`, `listBanks` |
| `$client->webhooks->endpoints` | `list`, `get`, `test` |
| `$client->webhooks->deliveries` | `list`, `get`, `redeliver` |
| `$client->webhooks->events` | `list`, `get`, `redeliver` |
| `$client->webhooks->eventTypes` | `list` |
| `Webhooks` | `verifyWebhook`, `signWebhookPayload`, `computeWebhookSignature`, `parseSignatureHeader` |

`$client->taxConnection->get(['identifier' => 'true'])` also says which number the tax authority
holds for the organisation (its TIN or its 13-digit Tax ID): `einvoicingIdentifier`, or
`identifierNextAction` when it holds neither. `['fresh' => 'true']` asks the authority again. Both
are rationed; pass them only when you need that answer. `get($options)` still works.

`state` can be `awaiting_authority_listing`: the business asked to connect and the tax authority
does not list it under any of its numbers yet. Yona asks the authority again every 6 hours and
carries on by itself once the business is listed. `listing` then holds `since`, `checkedAt`,
`nextCheckAt` and the last `answer` (`unknown`, `elsewhere`, `unanswered` or `held_elsewhere`); it is
`null` in every other state. `connectWaits` says whether connecting now would wait like this instead
of being refused. The `tax_connection.action_required` webhook is sent for this state too, and
`invoice.finalised` can carry `reporting` (the phpstan shape `InvoiceFinalisedData` of
`Useyona\EInvoice\WebhookEventType`), so a handler can tell a held invoice from one being reported.

### Held invoices

While an organisation's connection to the tax authority is still in progress, an invoice it issues
is not refused: `$client->submissions->issue()` answers `reporting` `held` with `notReportedReason`
`awaiting_connection`, the invoice is sent to the buyer, and Yona reports it automatically once the
connection completes. Until then the invoice's `reporting.state` is `held`;
`$client->invoices->list(['reporting' => 'held'])` lists them and `$client->invoices->getSummary()`
counts them in `held`. If one cannot be reported when the connection completes, its
`reporting.reason` is `release_refused`: report it yourself with `$client->submissions->submit()`.

`$client->taxConnection->listForSubOrganizations(['ids' => […]])` is for a parent organisation: the
state of each named sub-organisation's connection in the key's mode, and whether a submission would
pass (`tax_connection.read`). `ids` is a list (or a comma-separated string) of at most 100 ids, each
once. An id that is not one of the organisation's sub-organisations is left out of the answer.

### Sub-organisations

An organisation that runs several businesses holds them as sub-organisations. No key preset includes
`organization.children.read` or `organization.children.create`: an Owner or Admin adds them to a key
by name, under Sub-organisations in the dashboard's Create API Key dialog.

| Method | Route | Capability |
|---|---|---|
| `organization->listSubOrganizations` | `GET /a/v1/organizations/me/children` | `organization.children.read` |
| `organization->listSubOrganizationsReadiness` | `GET /a/v1/organizations/me/children/readiness` | `organization.children.read` |
| `organization->createSubOrganization` | `POST /a/v1/organizations` | `organization.children.create` |
| `taxConnection->listForSubOrganizations` | `GET /i/v1/tax-connection/children?ids=` | `tax_connection.read` |

- `listSubOrganizations(['page' => …, 'limit' => …, 'search' => …, 'ids' => […]])` answers a page,
  newest first, with the total in `pagination`; 100 a page at most. `ids` (a list, at most 100) keeps
  only the ones named. It works in sandbox and live, and with `EInvoice::paginate`. A list of ids
  reaches the API as one comma-separated value.
- `listSubOrganizationsReadiness` answers the same page with each sub-organisation's checklist,
  `liveStatus` and `nextStep`.
- `createSubOrganization(['businessName' => …, 'email' => …, 'phoneNumber' => …, 'website' => …])`
  (the last two optional; the country is the parent's) needs a live key: a sandbox key is refused
  with 403 `BIZ003`. The new organisation is recorded under the parent's longest-standing Owner and
  is `pending_verification` until it completes its own onboarding. No `Idempotency-Key` is sent and
  the SDK does not retry it: a name is unique under its parent, so a repeat answers 409 `RES002`.
  402 `BIZ016` when the plan allows no more organisations; 60 calls a minute for each key.
- The parent's `organization.child.created` webhook carries `childOrganizationId`, `actorUserId`
  and, only when an API key created the sub-organisation, `keyId` (the phpstan shape
  `OrganizationChildCreatedData` of `Useyona\EInvoice\WebhookEventType`).

### States and LGAs

`$client->reference->getResource('states')` and `getResource('lgas')` have typed items: a state
has `code` and `name` (`NG-LA` Lagos) and an LGA has `code`, `name` and `stateCode` (`NG-LA-IKE`
Ikeja, in `NG-LA`). For a State and LGA picker, read both once and filter the LGAs by the chosen
state's `code`. An item of `getResource('countries')` has `code` and `name`. The phpstan shapes are
`StateOption`, `LgaOption` and `CountryOption` of `Useyona\EInvoice\Service\ReferenceService`.

For a Nigerian address, a buyer's `address.state` and `address.lga` are a state and an LGA from
those lists: the `code`, or the exact `name`, which is stored as the code. A value that is not on
the list is refused with 400 `VAL202` (state) or `VAL203` (LGA, also when it belongs to another
state). Both are optional, and free text for other countries.

### Billing and collections

`$client->billing->partner->get(['page' => 2, 'limit' => 50])` pages the businesses the partner pays
for (`businesses`, oldest first; `businessesPage` carries `page`, `limit` and `total`; 100 a page,
the most, when `limit` is left out). `get()` and `get($options)` still work.

`billing->plans->getRecommendation()`, `billing->usage->getInvoices()`,
`billing->partner->getOffer()` and `billing->subscriptions->getDealOffer()` return `null` when there
is nothing to show. `$client->collections` answers only where collections are switched on for the
key's mode; elsewhere every call is 409 `BIZ120` (`ConflictException`). Its writes take no
`Idempotency-Key` and are not retried.

Account management (users, roles, API keys, purchases, webhook endpoint settings) is done in the
Yona dashboard, not through the API key. So is managing a sub-organisation (its profile, members,
onboarding and tax connection), signed in to that sub-organisation: a key lists sub-organisations
and creates one. The payments service's bank-account and transfer reads join the SDK when payments
launches in production.

## Configuration

`new EInvoice([...])` accepts:

| Option | Purpose |
|---|---|
| `timeout` | timeout in seconds for the HTTP client the SDK creates (default 30) |
| `retry` | `max_retries` (2), `base_delay` (0.5 s), `max_delay` (8 s), `max_retry_after` (60 s) |
| `headers` | headers sent on every request |
| `http_client`, `request_factory`, `stream_factory` | your own PSR-18 client and PSR-17 factories (proxy, TLS) |
| `base_url` | another gateway, for local development only; the key decides sandbox or live |

## Development

```bash
make test        # PHPUnit
make lint        # phpstan level 8 + php-cs-fixer
make examples    # run the examples against your sandbox key (YONA_API_KEY)
make sync        # regenerate the typed models from the API definition
make release     # prepare a release: version, CHANGELOG, guides, checks (DRY=1 to preview)
```

## License

MIT

# useyona/einvoice-php

The official PHP SDK for the [Yona](https://useyona.com) e-invoicing API: create and manage
invoices, report them to the tax authority, and work with buyers, items, received invoices, billing
and webhooks. PHP 8.1+, built on PSR-18, so it works with the HTTP client you already have.

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

| Status | Exception |
|---|---|
| 400, 422 | `ValidationException` |
| 401 | `AuthenticationException` |
| 402 | `InsufficientCreditsException` |
| 403 | `PermissionException` |
| 404 | `NotFoundException` |
| 409 | `ConflictException` |
| 429 | `RateLimitException` |
| 5xx | `ServerException` |

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

[`examples/`](examples/) holds complete, runnable walkthroughs: your first invoice, webhooks, errors
and retries, sandbox and live, received invoices. Run them against your sandbox key with
`YONA_API_KEY=sk_test_… make examples`.

## API reference

Every method takes an optional trailing `?RequestOptions $options`. Request and query arrays have
shapes named after the operation (`CreateInvoiceBody`, `ListInvoicesQuery`) in
`Useyona\EInvoice\Generated\Types`.

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
| `$client->inboundInvoices` | `list`, `get`, `getAnalytics`, `listHistoryRuns`, `getHistoryRun` |
| `$client->issuedHistory` | `list`, `get`, `downloadPdf` |
| `$client->invoiceSettings`, `$client->taxConnection` | `get` |
| `$client->organization` | `get`, `getReadiness` |
| `$client->billing->accounts` | `getMine`, `getStats`, `checkBalance` |
| `$client->billing->payments` | `list`, `get` |
| `$client->billing->sandbox` | `listTransactions`, `getUsage` |
| `$client->billing->statements` | `get` |
| `$client->billing->subscriptions` | `getActive`, `list`, `get`, `listRenewals`, `previewPlanChange` |
| `$client->billing->transactions` | `list`, `get`, `getUsageAnalytics`, `getUsageByCostCode` |
| `$client->webhooks->endpoints` | `list`, `get`, `test` |
| `$client->webhooks->deliveries` | `list`, `get`, `redeliver` |
| `$client->webhooks->events` | `list`, `get`, `redeliver` |
| `$client->webhooks->eventTypes` | `list` |
| `Webhooks` | `verifyWebhook`, `signWebhookPayload`, `computeWebhookSignature`, `parseSignatureHeader` |

Account management (users, roles, API keys, purchases, webhook endpoint settings) is done in the
Yona dashboard, not through the API key.

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
```

## License

MIT

# useyona/einvoice-php

Official PHP SDK for the Yona e-invoicing API. It covers exactly what an API key may call:
invoicing, submissions to the tax authority, output and share links, items, reference data, buyers,
the read-only seller, received invoices and issued history, invoice settings, the tax connection, the
organisation (read), billing reads and webhooks (read, test, redeliver).

Every Yona SDK exposes the same modules and methods; in PHP the names are `camelCase`
(`$client->invoices->issueCreditNote()`), so the portal guides read the same in every language.

## Features

- **One key, nothing else to configure.** `sk_test_…` is the sandbox, `sk_live_…` is live, on the same host.
- **Typed for phpstan and your IDE.** Every request and response shape is a generated array shape
  (`Useyona\EInvoice\Generated\Types`); bodies and answers are plain arrays at runtime.
- **Safe retries.** GET, PUT, DELETE and writes carrying an `Idempotency-Key` are retried on network
  errors, timeouts, 408, 429 and 5xx, honouring `Retry-After`. The SDK generates the key on the
  routes that accept one, so its own retry is never charged twice.
- **Typed exceptions.** `ValidationException`, `NotFoundException`, `RateLimitException`… all carry
  `status`, `errorCode`, `errors`, `requestId` and `retryAfter`.
- **Webhooks.** `Webhooks::verifyWebhook` checks the `Yona-Signature` over the raw body in constant time.
- **Bring your own HTTP client.** PSR-18 and PSR-17: Guzzle is discovered when installed, any other
  implementation can be injected. PHP 8.1+.

## Installation

```bash
composer require useyona/einvoice-php
```

The SDK needs a PSR-18 client and PSR-17 factories. If your project has none,
`composer require guzzlehttp/guzzle` installs one the SDK discovers automatically (Guzzle ships its
own PSR-17 factories). Any other PSR-18 client works through `http_client` (see below).

## Quick start

```php
<?php

require 'vendor/autoload.php';

use Useyona\EInvoice\EInvoice;

$client = new EInvoice(['api_key' => getenv('YONA_API_KEY')]);
$client->mode(); // 'sandbox' for sk_test_… keys, 'live' for sk_live_… keys

// 1. A buyer
$buyer = $client->buyers->create([
    'name' => 'Acme Nigeria Ltd',
    'taxId' => '12345678-0001',
    'email' => 'accounts@acme.ng',
    'partyType' => 'company',
    'address' => ['line1' => '1 Marina', 'city' => 'Lagos', 'country' => 'NG'],
]);

// 2. A saved item (optional: a line can also carry its own description, unit code and codes)
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

// 3. A draft invoice: invoiceKind and taxCategory, never taxPercent
$invoice = $client->invoices->create([
    'invoiceKind' => 'B2B',
    'invoiceDate' => '2026-10-05',
    'currency' => 'NGN',
    'buyerId' => $buyer['id'],
    'lineItems' => [['itemId' => $item['id'], 'quantity' => 2]],
]);

// 4. Finalise and report it to the tax authority
$client->invoices->finalise($invoice['id']);
$client->submissions->submit($invoice['id']); // 202: queued
$status = $client->submissions->getStatus($invoice['id']);
```

That is all the configuration an integration needs: the key. The SDK talks to the production
gateway for both modes; the key's prefix decides whether you are in the sandbox or live.

To fail fast when a deployment is given the wrong key:

```php
new EInvoice(['api_key' => $key, 'assert_mode' => 'live']); // throws ConfigException for an sk_test_ key
```

A malformed key is refused at construction with `ConfigException` (the key is never echoed). An
unknown configuration key is refused too.

## Guides

The recipes in [`examples/`](examples/) are the developer guides of the Yona portal (Developers,
Overview): your first invoice, webhooks, errors and retries, sandbox and live, received invoices.
Each runs end to end against a sandbox key (`YONA_API_KEY=sk_test_… make examples`).

## Verifying your setup

```bash
YONA_API_KEY=sk_test_… make smoke-remote
```

runs free reads across every module and then the first-invoice example against the real API, with
your own sandbox key, and prints a table method → OK/FAIL with the error code and request id of any
failure. It refuses a live key and never prints the key. The example creates sandbox data in your
organisation: a buyer, an item and an invoice submitted to the tax authority's sandbox.

## Responses and pagination

Methods return the API's `data` as an array with the wire names (`$invoice['invoiceNumber']`).
List methods return a `Page`:

```php
$page = $client->buyers->list(['limit' => 50]);
$page->data;       // list<BuyerViewDto>
$page->pagination; // ['total', 'page', 'pageSize', 'totalPages', 'hasNext', 'hasPrevious']
$page->requestId;
```

`EInvoice::paginate` walks every page for you:

```php
foreach (EInvoice::paginate(fn (array $q) => $client->buyers->list($q), ['limit' => 100]) as $buyer) {
    // …
}
```

Received invoices and issued history answer an object with `items`, so they return a `Paginated`:
`$res->data['items']` and `$res->pagination`.

PDF downloads return a `BinaryResponse`: `data` (the bytes, a string), `contentType`, `fileName`, `requestId`.

## Errors

Every refusal of the API throws a subclass of `ApiException`, chosen by status:

| Status | Class | Typical `errorCode` |
|---|---|---|
| 400, 422 | `ValidationException` | `VAL…` |
| 401 | `AuthenticationException` | `AUTH…` (a revoked, expired or malformed key) |
| 402 | `InsufficientCreditsException` | `BIZ001` |
| 403 | `PermissionException` | `AUTH019` (capability), `AUTH018` (user-only route) |
| 404 | `NotFoundException` | `RES001` |
| 409 | `ConflictException` | `BIZ…` (state), `RES002` (duplicate) |
| 429 | `RateLimitException` | `SYS005`, with `retryAfter` |
| 5xx | `ServerException` | `SYS001` |

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

Branch on `errorCode`, never on the message. Outside the API: `TimeoutException`,
`ConnectionException` (`getPrevious()` is the PSR-18 exception), `ConfigException`,
`WebhookException`; all extend `EInvoiceException` (a `RuntimeException`). The names map to
einvoice-js's `EInvoice…Error` classes one for one (`EInvoiceApiError` → `ApiException`, and so on).

## Retries and idempotency

```php
$client = new EInvoice([
    'api_key' => $key,
    'timeout' => 30,
    'retry' => ['max_retries' => 3, 'base_delay' => 0.5, 'max_delay' => 8, 'max_retry_after' => 30],
]);
```

The SDK retries GET, PUT and DELETE, and writes that carry an `Idempotency-Key`, on network errors,
timeouts, 408, 429 and 5xx, with exponential backoff and jitter; a `Retry-After` on 429/503 is
waited out up to `max_retry_after` seconds (a longer one is thrown at once, with `retryAfter` set).
Other writes are never retried for you.

`timeout` (seconds) is a property of the HTTP client, because PSR-18 has no per-request deadline:
when the SDK creates the client itself (Guzzle, through discovery) it is built with this timeout; an
injected client keeps the timeout it was configured with. A PSR-18 network failure whose message
mentions a timeout is thrown as `TimeoutException`, any other as `ConnectionException`.

On routes that accept an `Idempotency-Key` (`invoices->create`, `submissions->submit`, `output->send`,
the downloads…) the SDK generates one per call, so its own retries are never applied or charged
twice. Pass your own to make a retry across process restarts safe:

```php
use Useyona\EInvoice\RequestOptions;

$client->invoices->create($params, new RequestOptions(idempotencyKey: "order-{$orderId}"));
```

`RequestOptions` also takes `headers` and `maxRetries` for one call (and `timeout`, honoured only by
a client the SDK built).

## Your own HTTP client

```php
use GuzzleHttp\Client;
use Nyholm\Psr7\Factory\Psr17Factory;

$factory = new Psr17Factory();
$client = new EInvoice([
    'api_key' => $key,
    'http_client' => new Client(['timeout' => 20, 'proxy' => 'http://proxy.internal:3128']),
    'request_factory' => $factory,
    'stream_factory' => $factory,
]);
```

Any `Psr\Http\Client\ClientInterface` works; a client that throws on 4xx/5xx (Guzzle's `http_errors`)
must be configured not to, as PSR-18 requires (Guzzle's `sendRequest()` already does). The transport
is `$client->http` for advanced use.

## Webhooks

```php
use Useyona\EInvoice\Exception\WebhookException;
use Useyona\EInvoice\Webhooks;

// Plain PHP: the raw body and $_SERVER (HTTP_YONA_SIGNATURE)
try {
    $event = Webhooks::verifyWebhook(file_get_contents('php://input'), $_SERVER, getenv('YONA_WEBHOOK_SECRET'));
} catch (WebhookException $e) {
    http_response_code(400);
    exit;
}
if ($event['type'] === 'invoice.accepted') {
    // …
}

// PSR-7 (Laravel, Symfony, Slim, Mezzio…): pass the request; the body stream is the raw payload
$event = Webhooks::verifyWebhook($request->getBody(), $request, $secret);
```

Yona signs `t + "." + raw body` with HMAC-SHA256 and sends `Yona-Signature: t=…,v1=…` (a second
`v1=` while a rotated secret overlaps). `verifyWebhook` accepts the delivery when a signature
matches in constant time and `t` is within ±300 s, and returns the parsed event. Pass the raw body,
never a re-serialised array (one is refused). Deduplicate on `$event['id']`.
`Webhooks::signWebhookPayload` signs a payload exactly as Yona does, for testing your handler.
`WebhookEventType` lists the event catalogue as constants. Endpoints are registered in the dashboard;
the key can list and test them (`$client->webhooks->endpoints`), read deliveries and redeliver.

## What an API key cannot do

Users, invitations, roles, API keys, organisation management, collections, purchases and webhook
endpoint writes are done by a signed-in user in the dashboard; the API answers 403 `AUTH018` to a
key. The seller is the organisation itself (read-only; `sellers->create/update/delete` are 409
`BIZ205` and have no method). `ExcludedOperations::OPERATIONS` lists the API-key operations the SDK
deliberately has no method for, with the reason.

## API reference

Every method takes an optional trailing `?RequestOptions $options`. Query and body parameters are
arrays whose shapes are named after the operation (`ListInvoicesQuery`, `CreateInvoiceBody`), all in
`Useyona\EInvoice\Generated\Types` (import one with
`@phpstan-import-type CreateInvoiceBody from \Useyona\EInvoice\Generated\Types`).

#### `$client->invoices`
`create($params)`, `list($query)`, `get($id)`, `update($id, $params)`, `delete($id)`, `finalise($id)`, `reopen($id)`, `revise($id)`, `cancel($id, $params)`, `issueCreditNote($id, $params)`, `issueDebitNote($id, $params)`, `getOverview($query)`, `getStatistics($query)`, `getSummary($query)`

#### `$client->submissions`
`submit($id, $query)`, `issue($id)`, `createAndSubmit($params)`, `batchSubmit($params)`, `retry($id)`, `renumber($id)`, `queryStatus($id)`, `getStatus($id)`, `recordPayment($id, $params)`, `getAuthorityCopy($id)`

#### `$client->output`
`getDownloadLink($id)`, `downloadPdf($id)` → `BinaryResponse`, `send($id, $params)`

#### `$client->shareLinks`
`create($invoiceId, $params)`, `list($invoiceId)`, `revoke($invoiceId, $linkId)`

#### `$client->items`
`list($query)`, `create($params)`, `get($id)`, `update($id, $params)`, `delete($id)`, `archive($id)`, `unarchive($id)`, `listUsedCodes($query)`

#### `$client->reference`
`listHsCodes($query)`, `listHsCodeCategories()`, `listResources()`, `getResource($type)`, `lookupTaxId($value, $query)`, `validateInvoice($params)`

#### `$client->buyers`
`create($params)`, `list($query)`, `get($id)`, `update($id, $params)`, `delete($id)`, `bulkDelete($params)`, `verifyTaxNumber($id)`, `getVerificationStatus($id)`, `search($query)`, `checkReachability($query)`, `checkWithTaxAuthority($params)`

#### `$client->sellers`
`list($query)`, `get($id)`, `getVerificationStatus($id)`, `search($query)`

#### `$client->inboundInvoices`
`list($query)` → `Paginated`, `get($id)`, `getAnalytics($query)`, `listHistoryRuns($query)` → `Paginated`, `getHistoryRun($runId)`, `loadOlder()` (deprecated)

#### `$client->issuedHistory`
`list($query)` → `Paginated`, `get($id)`, `downloadPdf($id)` → `BinaryResponse`

#### `$client->invoiceSettings` · `$client->taxConnection`
`get()`

#### `$client->organization`
`get($orgId)`, `getReadiness()`

#### `$client->billing->accounts`
`getMine($query)`, `getStats($accountId = 'me', $query)`, `checkBalance($accountId, $query)`

#### `$client->billing->payments`
`list($query)`, `get($id)`

#### `$client->billing->sandbox`
`listTransactions($query)`, `getUsage()`

#### `$client->billing->statements`
`get($period, $query)`

#### `$client->billing->subscriptions`
`getActive()`, `list($query)`, `get($id)`, `listRenewals($query)`, `previewPlanChange($id, $query)`

#### `$client->billing->transactions`
`list($query)`, `get($id)`, `getUsageAnalytics($query)`, `getUsageByCostCode($query)`

#### `$client->webhooks->endpoints`
`list()`, `get($id)`, `test($id, $params = [])`

#### `$client->webhooks->deliveries`
`list($query)`, `get($id)`, `redeliver($id)`

#### `$client->webhooks->events`
`list($query)`, `get($id)`, `redeliver($id, $params)`

#### `$client->webhooks->eventTypes`
`list()`

#### `Useyona\EInvoice\Webhooks`
`verifyWebhook($payload, $headers, $secret, ?int $toleranceSeconds = null, ?int $now = null)`, `signWebhookPayload($payload, $secrets, ?int $timestamp = null)`, `computeWebhookSignature($payload, $secret, $timestamp)`, `parseSignatureHeader($header)`

#### `EInvoice`
`mode()`, `baseUrl()`, `http` (the `HttpClient`), `EInvoice::paginate($list, $query)` (also `Paginate::all`)

## Advanced: `base_url`

`base_url` points the client at another gateway (a local one in development). It never changes
the mode: the key does. Never switch hosts by mode in your own code.

## Development

```bash
make install        # composer install
make test           # PHPUnit, parity included (coverage with pcov or xdebug)
make lint           # phpstan level 8 + php-cs-fixer (PSR-12), dry run
make fix            # php-cs-fixer
make sync           # copy the snapshot and vectors from einvoice-js and regenerate the models (needs Node 22)
make sync-check     # CI: the three must match the pinned einvoice-js commit
make guides         # examples/ → guides/guides.json (a test fails when it is stale)
make examples       # run the examples (YONA_API_KEY; YONA_BASE_URL to point elsewhere)
make smoke-remote   # verify your own sandbox key (see "Verifying your setup")
```

The models (`src/Generated/Types.php`), the API snapshot and the webhook test vectors come from
[`einvoice-js`](https://github.com/Elyonar/einvoice-js), the source of truth for every Yona SDK, at
the einvoice-js commit (or tag) pinned in `scripts/sync.sh`; `tests/ParityTest.php` fails when a
method and the snapshot disagree. The SDKs are versioned independently (this one is
`Version::VERSION`); releases are tags `v<version>` that Packagist picks up (PUBLISHING.md).

## License

MIT

<?php

// @recipe errors-and-retries Errors and safe retries
// @summary Catch the SDK's typed exceptions, branch on errorCode, quote the requestId to support, honour retryAfter, and use idempotency keys so a retried write is never applied or charged twice.

declare(strict_types=1);

require 'vendor/autoload.php';

use Useyona\EInvoice\EInvoice;
use Useyona\EInvoice\Exception\ApiException;
use Useyona\EInvoice\Exception\ConnectionException;
use Useyona\EInvoice\Exception\NotFoundException;
use Useyona\EInvoice\Exception\RateLimitException;
use Useyona\EInvoice\Exception\ServerException;
use Useyona\EInvoice\Exception\TimeoutException;
use Useyona\EInvoice\Exception\ValidationException;
use Useyona\EInvoice\Http\HttpClient;
use Useyona\EInvoice\RequestOptions;

// @step client Configure retries
// @text The SDK retries GET, PUT and DELETE, and writes that carry an Idempotency-Key, on network errors, timeouts, 408, 429 and 5xx. Other writes are never retried for you.
$yona = new EInvoice([
    'api_key' => (string) getenv('YONA_API_KEY'),
    'timeout' => 30,
    'retry' => ['max_retries' => 3, 'base_delay' => 0.5, 'max_retry_after' => 30],
]);

// @step validation Read a validation error
// @text A 400 or 422 is a ValidationException. Branch on errorCode, show errors per field, and keep requestId for support.
// @op createInvoice
try {
    $yona->invoices->create(['invoiceKind' => 'B2B', 'invoiceDate' => '2026-10-05', 'currency' => 'NGN', 'lineItems' => []]);
    throw new RuntimeException('expected a validation error');
} catch (ValidationException $err) {
    echo $err->status, ' ', $err->errorCode, ' ', $err->requestId, "\n";
    foreach ($err->errors as $e) {
        echo "  {$e->field}: {$e->message}\n";
    }
}

// @step not-found Handle a missing resource
// @text Every API error extends ApiException, so one catch can handle them all and narrow by class.
// @op getInvoice
try {
    $yona->invoices->get('0192f3a0-0000-7000-8000-000000000000');
    throw new RuntimeException('expected a not-found error');
} catch (NotFoundException $err) {
    echo 'not found ', $err->errorCode, ' ', $err->requestId, "\n";
} catch (ApiException $err) {
    echo 'refused ', $err->status, ' ', $err->errorCode, "\n";
}

// @step idempotency Make a write safe to retry
// @text Pass your own idempotencyKey (stored with your order, for example) so a retry after a crash or a timeout returns the first result instead of creating a second invoice.
// @op createInvoice
$idempotencyKey = 'order-' . HttpClient::generateIdempotencyKey();
$params = [
    'invoiceKind' => 'B2C',
    'invoiceDate' => date('Y-m-d'),
    'currency' => 'NGN',
    'lineItems' => [[
        'itemName' => 'Consulting hour',
        'description' => 'Consulting hour',
        'unitCode' => 'EA',
        'quantity' => 1,
        'unitPrice' => '25000.00',
        'taxCategory' => 'STANDARD_VAT',
        'isicCode' => '6201',
        'serviceCategory' => 'Professional services',
    ]],
];
$first = $yona->invoices->create($params, new RequestOptions(idempotencyKey: $idempotencyKey));
$again = $yona->invoices->create($params, new RequestOptions(idempotencyKey: $idempotencyKey));
echo 'same invoice on retry: ', var_export($first['id'] === $again['id'], true), "\n";
if ($first['id'] !== $again['id']) {
    throw new RuntimeException('an idempotent retry created a second invoice');
}

// @step retry-after Wait out a rate limit
// @text When the SDK's own retries are exhausted, a 429 or 503 carries retryAfter (seconds). Retry only what is safe: reads, or writes with an idempotency key.
function withRetry(callable $call, int $attempts = 3): mixed
{
    for ($attempt = 1; ; ++$attempt) {
        try {
            return $call();
        } catch (RateLimitException|ServerException|TimeoutException|ConnectionException $err) {
            if ($attempt >= $attempts) {
                throw $err;
            }
            $seconds = $err instanceof ApiException && $err->retryAfter !== null ? $err->retryAfter : 2 ** $attempt;
            sleep($seconds);
        }
    }
}
$page = withRetry(fn () => $yona->invoices->list(['limit' => 5]));
echo 'invoices ', $page->pagination['total'], ' request ', $page->requestId, "\n";

// @step cleanup Delete the draft
// @text Drafts can be deleted; their number is never reused.
// @op deleteInvoice
$yona->invoices->delete($first['id']);

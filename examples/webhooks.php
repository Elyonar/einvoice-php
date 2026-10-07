<?php

// @recipe webhooks Receive webhooks
// @summary Verify the Yona-Signature of each delivery over the raw body, dispatch by event type, and inspect deliveries with the SDK. Endpoints are registered in the dashboard: an API key can read and test them, not create them.

declare(strict_types=1);

require 'vendor/autoload.php';

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Useyona\EInvoice\EInvoice;
use Useyona\EInvoice\Exception\WebhookException;
use Useyona\EInvoice\Webhooks;

// @step secret Keep the endpoint secret
// @text Register your endpoint URL in the dashboard (Developers, Webhooks); it shows the signing secret (whsec_…) once. Keep it in your environment.
$secret = getenv('YONA_WEBHOOK_SECRET') ?: 'whsec_local_example_secret';

// @step read-raw-body Read the raw body
// @text The signature covers the exact bytes Yona sent. Read the body as a string (the PSR-7 body stream, or file_get_contents('php://input') without a framework); never verify a re-serialised array.
function readRawBody(ServerRequestInterface $request): string
{
    return (string) $request->getBody();
}

// @step dispatch Dispatch by event type
// @text Deduplicate on $event['id']: a redelivery carries the same id. Answer 2xx quickly and do slow work afterwards.
$seen = [];
/** @param array<string, mixed> $event */
function handleEvent(array $event): void
{
    global $seen;
    if (isset($seen[$event['id']])) {
        return;
    }
    $seen[$event['id']] = true;
    switch ($event['type']) {
        case 'invoice.accepted':
            echo 'accepted ', $event['data']['invoiceId'] ?? '', "\n";
            break;
        case 'invoice.rejected':
            echo 'rejected ', $event['data']['invoiceId'] ?? '', "\n";
            break;
        case 'invoice.received':
            echo "a supplier sent you an invoice\n";
            break;
        default:
            echo 'ignored ', $event['type'], "\n";
    }
}

// @step handler Verify every delivery
// @text Webhooks::verifyWebhook checks the HMAC-SHA256 signature and the timestamp (±300 s) and returns the parsed event. Refuse anything it rejects with 400. The handler is a PSR-15-style function: pass it the request your framework gives you.
$factory = new Psr17Factory();
function handleDelivery(ServerRequestInterface $request, string $secret, Psr17Factory $factory): ResponseInterface
{
    if ($request->getMethod() !== 'POST' || $request->getUri()->getPath() !== '/webhooks/yona') {
        return $factory->createResponse(404);
    }
    $raw = readRawBody($request);
    try {
        $event = Webhooks::verifyWebhook($raw, $request, $secret);
    } catch (WebhookException) {
        return $factory->createResponse(400)->withBody($factory->createStream('invalid signature'));
    }
    handleEvent($event);

    return $factory->createResponse(200)->withBody($factory->createStream('ok'));
}

// @step test-locally Test your handler locally
// @text Webhooks::signWebhookPayload signs a payload exactly as Yona does, so you can exercise the handler before going live, here with a PSR-7 request built in-process. A tampered body is refused.
$body = json_encode([
    'id' => 'evt_local_1',
    'type' => 'invoice.accepted',
    'version' => 1,
    'createdAt' => gmdate('Y-m-d\TH:i:s\Z'),
    'mode' => 'sandbox',
    'organizationId' => 'org_local',
    'data' => ['invoiceId' => 'inv_local'],
    'test' => true,
], JSON_THROW_ON_ERROR);
$delivery = fn (string $payload): ServerRequestInterface => $factory->createServerRequest('POST', 'https://example.com/webhooks/yona')
    ->withHeader(Webhooks::SIGNATURE_HEADER, Webhooks::signWebhookPayload($body, $secret))
    ->withBody($factory->createStream($payload));
$signed = handleDelivery($delivery($body), $secret, $factory);
$tampered = handleDelivery($delivery(str_replace('accepted', 'rejected', $body)), $secret, $factory);
echo 'signed delivery ', $signed->getStatusCode(), ' | tampered delivery ', $tampered->getStatusCode(), "\n";
if ($signed->getStatusCode() !== 200 || $tampered->getStatusCode() !== 400) {
    throw new RuntimeException('the handler did not verify as expected');
}

// @step list-endpoints List your endpoints
// @text The key reads the endpoints registered for its organisation and mode.
// @op listWebhookEndpoints
$yona = new EInvoice(['api_key' => (string) getenv('YONA_API_KEY')]);
$endpoints = $yona->webhooks->endpoints->list();
echo 'endpoints ', count($endpoints), "\n";

// @step list-deliveries Inspect deliveries
// @text Every delivery and its status: delivered, retrying or failed with the reason.
// @op listWebhookDeliveries
$deliveries = $yona->webhooks->deliveries->list(['limit' => 10]);
foreach ($deliveries->data as $d) {
    echo $d['type'], ' ', $d['status'], ' ', $d['lastHttpStatus'] ?? '-', "\n";
}

// @step redeliver Redeliver a failed delivery
// @text After fixing your endpoint, ask for one more attempt.
// @op redeliverWebhookDelivery
$failed = null;
foreach ($deliveries->data as $d) {
    if ($d['status'] === 'failed') {
        $failed = $d;
        break;
    }
}
if ($failed !== null) {
    $yona->webhooks->deliveries->redeliver($failed['id']);
}
echo 'redelivered ', $failed !== null ? $failed['id'] : 'nothing to redeliver', "\n";

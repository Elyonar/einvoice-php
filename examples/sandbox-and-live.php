<?php

// @recipe sandbox-and-live Sandbox and live
// @summary The key decides the mode: an sk_test_ key works in sandbox and an sk_live_ key in live, on the same host. Going live changes only the key.

declare(strict_types=1);

require 'vendor/autoload.php';

use Useyona\EInvoice\EInvoice;
use Useyona\EInvoice\Exception\ConfigException;

// @step client Create the client and read its mode
// @text There is no host or mode to configure. The SDK reads the mode from the key prefix.
// @quickstart
$yona = new EInvoice(['api_key' => (string) getenv('YONA_API_KEY')]);
echo $yona->mode(), "\n"; // 'sandbox' for sk_test_, 'live' for sk_live_

// @step first-call Make your first call
// @text The readiness check lists what your organisation still needs before it can invoice in this mode.
// @op getOrganizationReadiness
// @quickstart
$readiness = $yona->organization->getReadiness();
echo json_encode($readiness, JSON_PRETTY_PRINT), "\n";

// @step assert-mode Guard against the wrong key
// @text Pass assert_mode so a deployment refuses to start with a key of the other mode. The error never echoes the key.
$refused = false;
try {
    new EInvoice(['api_key' => (string) getenv('YONA_API_KEY'), 'assert_mode' => $yona->mode() === 'sandbox' ? 'live' : 'sandbox']);
} catch (ConfigException $err) {
    $refused = true;
    echo 'refused: ', $err->getMessage(), "\n";
}
if (!$refused) {
    throw new RuntimeException('assert_mode did not refuse a key of the other mode');
}

// @step sandbox-allowance See your sandbox allowance
// @text Sandbox invoices are free within an allowance; submissions go to the tax authority's sandbox, never to the live register.
// @op getSandboxUsage
if ($yona->mode() === 'sandbox') {
    echo json_encode($yona->billing->sandbox->getUsage(), JSON_PRETTY_PRINT), "\n";
}

// @step tax-connection Check the tax connection
// @text Each mode has its own connection to the tax authority. Live needs the organisation approved for live and its live connection made in the dashboard.
// @op getTaxConnection
$connection = $yona->taxConnection->get();
echo json_encode($connection, JSON_PRETTY_PRINT), "\n";

// @step go-live Go live
// @text Create a live key in the dashboard and deploy it as YONA_API_KEY in place of the test key. Nothing else in your code changes: the same calls, the same host.
$client = new EInvoice(['api_key' => (string) getenv('YONA_API_KEY')]);
echo $client->mode() === 'live' ? 'live: submissions are reported to the tax authority' : 'sandbox: deploy an sk_live_ key to go live', "\n";

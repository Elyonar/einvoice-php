<?php

declare(strict_types=1);

/*
 * REMOTE SMOKE: verify your own sandbox setup against the real API. Not shipped. Run it yourself:
 *
 *   YONA_API_KEY=sk_test_… make smoke-remote
 *
 * - Refuses any key that is not a sandbox key (sk_test_): nothing here ever runs in live.
 * - Talks to the SDK's default host (https://gp.useyona.com). Another host only when YONA_BASE_URL is
 *   set explicitly in the environment; there is no other way to change it.
 * - Runs only sandbox-safe calls: mode detection, free reads across every module, then the
 *   first-invoice example end to end, which CREATES SANDBOX DATA in your organisation (a buyer, an
 *   item and an invoice submitted to the tax authority's sandbox).
 * - Prints a table method → OK/FAIL with the error code and request id of a failure. The key is never
 *   printed (nor passed on the command line: the example gets it through its environment).
 */

namespace Useyona\EInvoice\Scripts;

use Useyona\EInvoice\EInvoice;
use Useyona\EInvoice\Exception\ApiException;
use Useyona\EInvoice\Exception\ConfigException;

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/run_examples.php';

const DEFAULT_BASE_URL = 'https://gp.useyona.com';

$apiKey = trim((string) getenv('YONA_API_KEY'));
if ($apiKey === '') {
    fwrite(STDERR, "Set YONA_API_KEY to a sandbox key: YONA_API_KEY=sk_test_… make smoke-remote\n");
    exit(2);
}
if (!str_starts_with($apiKey, 'sk_test_')) {
    fwrite(STDERR, "Refused: smoke-remote runs only with a sandbox key (sk_test_…). The key was not used.\n");
    exit(2);
}
$explicitBase = trim((string) getenv('YONA_BASE_URL'));
$baseUrl = $explicitBase !== '' ? rtrim($explicitBase, '/') : DEFAULT_BASE_URL;
if (preg_match('#^https://#', $baseUrl) !== 1 && preg_match('#^http://(localhost|127\.0\.0\.1)(:\d+)?$#', $baseUrl) !== 1) {
    fwrite(STDERR, "Refused: YONA_BASE_URL must be https:// (or http://localhost).\n");
    exit(2);
}

try {
    $yona = new EInvoice(['api_key' => $apiKey, 'base_url' => $baseUrl, 'assert_mode' => 'sandbox']);
} catch (ConfigException $err) {
    fwrite(STDERR, "Refused: {$err->getMessage()}\n");
    exit(2);
}

echo "smoke-remote → {$baseUrl}" . ($explicitBase !== '' ? ' (YONA_BASE_URL)' : '') . ", sandbox key (not printed)\n";
echo "Reads first; then the first-invoice example, which CREATES SANDBOX DATA in your organisation:\n";
echo "a buyer, an item and an invoice submitted to the tax authority's sandbox.\n\n";

/** @var list<array{name: string, result: string, note: string}> $rows */
$rows = [];

/**
 * `$known`: error codes that are a correct answer for a fresh sandbox (OK, with the code noted).
 *
 * @param list<string> $known
 */
function check(string $name, callable $fn, array $known = []): mixed
{
    global $rows;
    try {
        $value = $fn();
        $rows[] = ['name' => $name, 'result' => 'OK', 'note' => ''];

        return $value;
    } catch (ApiException $err) {
        if (in_array($err->errorCode, $known, true)) {
            $rows[] = ['name' => $name, 'result' => 'OK', 'note' => "{$err->status} {$err->errorCode} (expected in a sandbox)"];
        } else {
            $rows[] = ['name' => $name, 'result' => 'FAIL', 'note' => "{$err->status} " . ($err->errorCode ?? '') . ' requestId ' . ($err->requestId ?? '-')];
        }
    } catch (\Throwable $err) {
        $rows[] = ['name' => $name, 'result' => 'FAIL', 'note' => (new \ReflectionClass($err))->getShortName() . ': ' . substr($err->getMessage(), 0, 140)];
    }

    return null;
}

$period = gmdate('Y-m');

// ── mode ──
check('mode (sk_test_ → sandbox)', function () use ($yona): void {
    if ($yona->mode() !== 'sandbox') {
        throw new \RuntimeException("mode is {$yona->mode()}");
    }
});

// ── reads across every module (free; nothing is written) ──
check('organization.getReadiness', fn () => $yona->organization->getReadiness());
check('invoiceSettings.get', fn () => $yona->invoiceSettings->get());
check('taxConnection.get', fn () => $yona->taxConnection->get());
check('reference.listResources', fn () => $yona->reference->listResources());
check('reference.listHsCodes', fn () => $yona->reference->listHsCodes(['limit' => 5]));
check('reference.listHsCodeCategories', fn () => $yona->reference->listHsCodeCategories());
$sellers = check('sellers.list', fn () => $yona->sellers->list());
if (isset($sellers->data[0]['id'])) {
    check('sellers.get', fn () => $yona->sellers->get($sellers->data[0]['id']));
}
$buyers = check('buyers.list', fn () => $yona->buyers->list(['limit' => 5]));
if (isset($buyers->data[0]['id'])) {
    check('buyers.get', fn () => $yona->buyers->get($buyers->data[0]['id']));
}
$items = check('items.list', fn () => $yona->items->list(['limit' => 5]));
if (isset($items->data[0]['id'])) {
    check('items.get', fn () => $yona->items->get($items->data[0]['id']));
}
$invoices = check('invoices.list', fn () => $yona->invoices->list(['limit' => 5]));
if (isset($invoices->data[0]['id'])) {
    $firstId = $invoices->data[0]['id'];
    check('invoices.get', fn () => $yona->invoices->get($firstId));
    check('submissions.getStatus', fn () => $yona->submissions->getStatus($firstId));
    check('shareLinks.list', fn () => $yona->shareLinks->list($firstId));
}
check('invoices.getOverview', fn () => $yona->invoices->getOverview());
check('invoices.getStatistics', fn () => $yona->invoices->getStatistics());
check('invoices.getSummary', fn () => $yona->invoices->getSummary());
check('inboundInvoices.list', fn () => $yona->inboundInvoices->list(['limit' => 5]));
check('inboundInvoices.getAnalytics', fn () => $yona->inboundInvoices->getAnalytics());
// BIZ221: retrieving invoice history is not available yet on this deployment.
check('inboundInvoices.listHistoryRuns', fn () => $yona->inboundInvoices->listHistoryRuns(), ['BIZ221']);
check('issuedHistory.list', fn () => $yona->issuedHistory->list(['limit' => 5]));
$account = check('billing.accounts.getMine', fn () => $yona->billing->accounts->getMine());
if (is_array($account) && isset($account['organizationId'])) {
    check('organization.get', fn () => $yona->organization->get($account['organizationId']));
}
check('billing.accounts.getStats', fn () => $yona->billing->accounts->getStats());
if (is_array($account) && isset($account['id'])) {
    check('billing.accounts.checkBalance', fn () => $yona->billing->accounts->checkBalance($account['id'], ['credits' => 1]));
}
check('billing.payments.list', fn () => $yona->billing->payments->list());
check('billing.sandbox.getUsage', fn () => $yona->billing->sandbox->getUsage());
check('billing.sandbox.listTransactions', fn () => $yona->billing->sandbox->listTransactions(['limit' => 5]));
check('billing.statements.get', fn () => $yona->billing->statements->get($period));
check('billing.subscriptions.list', fn () => $yona->billing->subscriptions->list());
check('billing.subscriptions.listRenewals', fn () => $yona->billing->subscriptions->listRenewals());
check('billing.transactions.list', fn () => $yona->billing->transactions->list(['limit' => 5]));
check('billing.transactions.getUsageAnalytics', fn () => $yona->billing->transactions->getUsageAnalytics());
check('billing.transactions.getUsageByCostCode', fn () => $yona->billing->transactions->getUsageByCostCode());
check('webhooks.endpoints.list', fn () => $yona->webhooks->endpoints->list());
check('webhooks.deliveries.list', fn () => $yona->webhooks->deliveries->list(['limit' => 5]));
check('webhooks.events.list', fn () => $yona->webhooks->events->list(['limit' => 5]));
check('webhooks.eventTypes.list', fn () => $yona->webhooks->eventTypes->list());

// ── the first-invoice example, end to end (creates sandbox data) ──
$ex = runExample('first-invoice', $apiKey, $explicitBase !== '' ? $baseUrl : null);
$tail = substr(implode(' | ', array_filter(explode("\n", trim($ex['output'])), fn (string $l): bool => $l !== '')), -240);
$rows[] = ['name' => 'example first-invoice (creates sandbox data)', 'result' => $ex['ok'] ? 'OK' : 'FAIL', 'note' => $ex['ok'] ? round($ex['ms'] / 1000) . ' s' : $tail];

// ── report ──
$width = max(array_map(fn (array $r): int => strlen($r['name']), $rows));
echo str_pad('method', $width), "  result  detail\n";
foreach ($rows as $r) {
    echo str_pad($r['name'], $width), '  ', str_pad($r['result'], 6), '  ', $r['note'], "\n";
}
$failed = count(array_filter($rows, fn (array $r): bool => $r['result'] === 'FAIL'));
echo "\n", count($rows) - $failed, " OK, {$failed} FAIL. Quote a requestId to Yona support for any FAIL.\n";
exit($failed > 0 ? 1 : 0);

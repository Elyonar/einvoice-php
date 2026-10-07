<?php

// @recipe received-invoices Received invoices
// @summary List the invoices other businesses issued to you, filter them, and walk every page with paginate.

declare(strict_types=1);

require 'vendor/autoload.php';

use Useyona\EInvoice\EInvoice;
use Useyona\EInvoice\Page;

// @step client Create the client
$yona = new EInvoice(['api_key' => (string) getenv('YONA_API_KEY')]);

// @step connection Check the tax connection
// @text Received invoices arrive through your connection to the tax authority; until it is connected the list is empty.
// @op getTaxConnection
$connection = $yona->taxConnection->get();
echo 'tax connection ', json_encode($connection), "\n";

// @step list Read the first page
// @text Newest first. sync tells you whether the mirror is still filling (syncing) and when it last read the tax authority.
// @op listInboundInvoices
$first = $yona->inboundInvoices->list(['limit' => 20]);
echo 'received ', $first->pagination['total'] ?? count($first->data['items']), ' syncing ', var_export($first->data['sync']['syncing'], true), "\n";
foreach ($first->data['items'] as $inv) {
    echo $inv['irn'], ' ', $inv['supplierName'] ?? $inv['supplierTin'], ' ', $inv['payableAmountMinor'], ' ', $inv['currency'], ' ', $inv['paymentStatus'], "\n";
}

// @step filter Filter
// @text Filter by seller name or TIN, payment status, issue date, amount or currency.
// @op listInboundInvoices
$unpaid = $yona->inboundInvoices->list(['paymentStatus' => 'unpaid', 'sort' => '-issueDate', 'limit' => 20]);
echo 'unpaid on this page ', count($unpaid->data['items']), "\n";

// @step paginate Walk every page
// @text EInvoice::paginate fetches page after page for you. Received invoices return an object with items, so map each page to a Page of its items.
// @op listInboundInvoices
$count = 0;
$pages = EInvoice::paginate(function (array $query) use ($yona): Page {
    $res = $yona->inboundInvoices->list($query);
    $items = $res->data['items'];
    $pagination = $res->pagination ?? ['total' => count($items), 'page' => $query['page'], 'pageSize' => $query['limit'], 'totalPages' => 1, 'hasNext' => false, 'hasPrevious' => false];

    return new Page($items, $pagination);
}, ['limit' => 50]);
foreach ($pages as $inv) {
    ++$count;
    if ($count <= 3) {
        echo $inv['irn'], "\n";
    }
}
echo 'walked ', $count, " received invoices\n";

// @step detail Open one
// @op getInboundInvoice
$one = $first->data['items'][0] ?? null;
if ($one !== null) {
    echo json_encode($yona->inboundInvoices->get($one['id']), JSON_PRETTY_PRINT), "\n";
}

// @step analytics Summarise
// @text Totals by status and seller for a period.
// @op getInboundInvoiceAnalytics
echo json_encode($yona->inboundInvoices->getAnalytics(), JSON_PRETTY_PRINT), "\n";

// @step paginate-buyers The same for any list
// @text Every list method returns a Page with data and pagination, so paginate works on it directly.
// @op listBuyers
$buyers = 0;
foreach (EInvoice::paginate(fn (array $q) => $yona->buyers->list($q), ['limit' => 100]) as $buyer) {
    if ($buyer['id'] !== '') {
        ++$buyers;
    }
}
echo 'buyers ', $buyers, "\n";

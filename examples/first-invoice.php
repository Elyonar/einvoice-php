<?php

// @recipe first-invoice Your first invoice
// @summary Create a buyer and an item, draft an invoice, finalise it, submit it to the tax authority, follow its status and download the PDF and the QR payload.

declare(strict_types=1);

require 'vendor/autoload.php';

use Useyona\EInvoice\EInvoice;
use Useyona\EInvoice\Exception\ConflictException;

// @step client Create the client
// @text Pass only your API key. An sk_test_ key works in sandbox and an sk_live_ key in live, on the same host.
$yona = new EInvoice(['api_key' => (string) getenv('YONA_API_KEY')]);
$ref = bin2hex(random_bytes(4));

// @step create-buyer Create a buyer
// @text A buyer is the party you invoice. A B2B buyer carries its tax id (TIN), which the tax authority checks on submission. A buyer can never carry your own organisation's TIN (the authority refuses SAME_PARTY_TIN); the sandbox accepts any well-formed TIN. A TIN is unique per organisation: on 409 RES002, reuse the buyer you already have.
// @op createBuyer
$taxId = '12345678-0001';
$findOrCreateBuyer = function () use ($yona, $ref, $taxId): array {
    try {
        return $yona->buyers->create([
            'name' => "Acme Nigeria Ltd {$ref}",
            'partyType' => 'company',
            'taxId' => $taxId,
            'email' => "accounts.{$ref}@example.com",
            'address' => ['line1' => '1 Marina', 'city' => 'Lagos', 'country' => 'NG'],
        ]);
    } catch (ConflictException $err) {
        if ($err->errorCode !== 'RES002') {
            throw $err;
        }
        $found = $yona->buyers->list(['search' => $taxId, 'limit' => 1]);
        if ($found->data === []) {
            throw $err;
        }

        return $found->data[0];
    }
};
$buyer = $findOrCreateBuyer();
echo 'buyer ', $buyer['id'], "\n";

// @step find-hs-code Find an HS code
// @text Goods carry an HS code. Search the reference list for one that fits your product.
// @op listHsCodes
$hsCodes = $yona->reference->listHsCodes(['limit' => 1]);
$hsnCode = $hsCodes->data[0]['code'] ?? '8471.30';

// @step create-item Create an item
// @text Save what you sell once and reuse it on every invoice. Amounts are integer minor units (kobo for NGN), sent as strings.
// @op createItem
$item = $yona->items->create([
    'name' => "Laptop {$ref}",
    'itemType' => 'goods',
    'hsnCode' => $hsnCode,
    'productCategory' => 'Machinery',
    'unitCode' => 'EA',
    'unitPriceMinor' => '150000000',
    'currency' => 'NGN',
    'taxCategory' => 'STANDARD_VAT',
    'sku' => "LAPTOP-{$ref}",
]);
echo 'item ', $item['id'], "\n";

// @step create-invoice Draft the invoice
// @text A draft can be edited freely. The SDK sends an Idempotency-Key, so a retried create is never charged twice.
// @op createInvoice
$draft = $yona->invoices->create([
    'invoiceKind' => 'B2B',
    'invoiceDate' => date('Y-m-d'),
    'currency' => 'NGN',
    'buyerId' => $buyer['id'],
    'lineItems' => [['itemId' => $item['id'], 'quantity' => 2]],
]);
echo 'draft ', $draft['invoiceNumber'], ' ', $draft['totals']['payableMinor'], "\n";

// @step finalise Finalise it
// @text Finalising freezes the seller and buyer onto the invoice and runs the jurisdiction's checks.
// @op finaliseInvoice
$finalised = $yona->invoices->finalise($draft['id']);
echo 'finalised ', $finalised['status'], "\n";

// @step submit Submit it to the tax authority
// @text Submission is asynchronous: the answer is the queued submission. Webhooks tell you when it settles.
// @op submitInvoice
$submitted = $yona->submissions->submit($draft['id']);
echo 'submitted ', $submitted['invoice']['status'], "\n";

// @step wait Wait for the outcome
// @text Poll the status as last recorded (or listen for invoice.accepted / invoice.rejected webhooks). A rejection carries the authority's reasons in submissions[].rejectReasons: print them and stop, since a rejected invoice cannot be queried or downloaded.
// @op getInvoiceStatus
$settled = ['signed', 'transmitted', 'accepted', 'rejected', 'failed'];
$status = $yona->submissions->getStatus($draft['id']);
for ($i = 0; $i < 45 && !in_array($status['status'], $settled, true); ++$i) {
    sleep(2);
    $status = $yona->submissions->getStatus($draft['id']);
}
echo 'status ', $status['status'], ' ', $status['authorityReference'] ?? '', "\n";
if ($status['status'] === 'rejected') {
    foreach ($status['submissions'] as $submission) {
        foreach ($submission['rejectReasons'] as $reason) {
            echo '  refused: ', $reason['code'], ' ', $reason['field'] ?? '', ' ', $reason['message'], "\n";
        }
    }
    throw new \RuntimeException('the tax authority rejected the invoice; fix what it named and submit again');
}

// @step query-status Ask the tax authority directly
// @text queryStatus asks the authority now instead of reading the last recorded state.
// @op queryInvoiceStatus
$live = $yona->submissions->queryStatus($draft['id']);
echo 'authority says ', $live['status'], ' ', $live['authorityState'], "\n";

// @step download-pdf Download the PDF
// @text The PDF bytes come back as a string in a BinaryResponse. getDownloadLink returns a short-lived URL instead.
// @op downloadInvoice
$pdf = $yona->output->downloadPdf($draft['id']);
$file = sys_get_temp_dir() . '/' . ($pdf->fileName ?? "{$draft['invoiceNumber']}.pdf");
file_put_contents($file, $pdf->data);
echo 'pdf ', $file, ' ', strlen($pdf->data), " bytes\n";

// @step qr Read the QR payload
// @text Once registered, the invoice carries the verification payload to print as its QR code.
// @op getInvoice
$invoice = $yona->invoices->get($draft['id']);
$verification = $invoice['verification'] ?? null;
echo 'qr ', $verification !== null ? strlen($verification['payload']) . ' chars' : 'not registered yet', "\n";

<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Service;

use Useyona\EInvoice\BinaryResponse;
use Useyona\EInvoice\RequestOptions;

/**
 * Output (`/i/v1/invoices/:id/download|send`): the invoice PDF and sending it to the buyer.
 *
 * @phpstan-import-type DownloadInvoiceData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type SendInvoiceBody from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type SendInvoiceData from \Useyona\EInvoice\Generated\Types
 */
final class OutputService extends BaseService
{
    /**
     * A short-lived download link to the invoice PDF (`invoice.download_pdf`; charged as one
     * download). Sends an `Idempotency-Key`, so an SDK retry is not charged twice; the backend binds
     * a key to the invoice it first downloaded (reusing it for another invoice is 409 `BIZ107`).
     *
     * @return DownloadInvoiceData
     */
    public function getDownloadLink(string $id, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/i/v1/invoices/' . self::seg($id) . '/download', ['idempotent' => true, 'options' => $options]);
    }

    /**
     * The invoice PDF itself (`invoice.download_pdf`; same route with `Accept: application/pdf`;
     * charged as one download). Sends an `Idempotency-Key`, like {@see getDownloadLink()}.
     */
    public function downloadPdf(string $id, ?RequestOptions $options = null): BinaryResponse
    {
        return $this->binary('GET', '/i/v1/invoices/' . self::seg($id) . '/download', ['query' => ['format' => 'pdf'], 'idempotent' => true, 'options' => $options]);
    }

    /**
     * Emails the invoice to the buyer's address on record (`invoice.send_to_buyer`; 202 = queued).
     * In sandbox no email leaves; `shareUrl` previews the PDF. Sends an `Idempotency-Key`.
     *
     * @param SendInvoiceBody $params
     *
     * @return SendInvoiceData
     */
    public function send(string $id, array $params, ?RequestOptions $options = null): array
    {
        return $this->call('POST', '/i/v1/invoices/' . self::seg($id) . '/send', ['body' => $params, 'idempotent' => true, 'options' => $options]);
    }
}

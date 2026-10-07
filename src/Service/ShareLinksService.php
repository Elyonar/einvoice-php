<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Service;

use Useyona\EInvoice\RequestOptions;

/**
 * Share links (`/i/v1/invoices/:id/share-links`): public, revocable links to an invoice.
 *
 * @phpstan-import-type CreateInvoiceShareLinkBody from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type CreateInvoiceShareLinkData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListInvoiceShareLinksItem from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type RevokeInvoiceShareLinkData from \Useyona\EInvoice\Generated\Types
 */
final class ShareLinksService extends BaseService
{
    /**
     * Creates a share link (`invoice.share`).
     *
     * @param CreateInvoiceShareLinkBody $params
     *
     * @return CreateInvoiceShareLinkData
     */
    public function create(string $invoiceId, array $params, ?RequestOptions $options = null): array
    {
        return $this->call('POST', '/i/v1/invoices/' . self::seg($invoiceId) . '/share-links', ['body' => $params, 'options' => $options]);
    }

    /**
     * Lists an invoice's share links (`invoice.share`).
     *
     * @return list<ListInvoiceShareLinksItem>
     */
    public function list(string $invoiceId, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/i/v1/invoices/' . self::seg($invoiceId) . '/share-links', ['options' => $options]);
    }

    /**
     * Revokes a share link (`invoice.share`).
     *
     * @return RevokeInvoiceShareLinkData
     */
    public function revoke(string $invoiceId, string $linkId, ?RequestOptions $options = null): array
    {
        return $this->call('DELETE', '/i/v1/invoices/' . self::seg($invoiceId) . '/share-links/' . self::seg($linkId), ['options' => $options]);
    }
}

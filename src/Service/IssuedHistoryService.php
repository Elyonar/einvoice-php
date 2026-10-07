<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Service;

use Useyona\EInvoice\BinaryResponse;
use Useyona\EInvoice\Paginated;
use Useyona\EInvoice\RequestOptions;

/**
 * Issued history (`/i/v1/issued-history`): invoices the organisation issued that NRS holds, from before Yona.
 *
 * @phpstan-import-type ListIssuedHistoryQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListIssuedHistoryData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetIssuedHistoryInvoiceData from \Useyona\EInvoice\Generated\Types
 */
final class IssuedHistoryService extends BaseService
{
    /**
     * Lists invoices issued outside Yona that the tax authority holds (`invoice.inbound.read`; free).
     *
     * @param ListIssuedHistoryQuery|null $query
     *
     * @return Paginated<ListIssuedHistoryData>
     */
    public function list(?array $query = null, ?RequestOptions $options = null): Paginated
    {
        return $this->paginated('GET', '/i/v1/issued-history', ['query' => $query, 'options' => $options]);
    }

    /**
     * Gets one; `document` is read and charged once per invoice on first open (`invoice.inbound.read`).
     *
     * @return GetIssuedHistoryInvoiceData
     */
    public function get(string $id, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/i/v1/issued-history/' . self::seg($id), ['options' => $options]);
    }

    /**
     * The PDF printed from the authority copy (`invoice.inbound.read`; charged once per invoice if not yet read).
     */
    public function downloadPdf(string $id, ?RequestOptions $options = null): BinaryResponse
    {
        return $this->binary('GET', '/i/v1/issued-history/' . self::seg($id) . '/pdf', ['options' => $options]);
    }
}

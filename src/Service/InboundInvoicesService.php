<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Service;

use Useyona\EInvoice\Paginated;
use Useyona\EInvoice\RequestOptions;

/**
 * Received invoices (`/i/v1/inbound-invoices`): what other businesses issued to the organisation.
 *
 * @phpstan-import-type ListInboundInvoicesQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListInboundInvoicesData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetInboundInvoiceData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetInboundInvoiceAnalyticsQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetInboundInvoiceAnalyticsData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListInboundHistoryRunsQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListInboundHistoryRunsData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetInboundHistoryRunData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type LoadOlderInboundInvoicesData from \Useyona\EInvoice\Generated\Types
 */
final class InboundInvoicesService extends BaseService
{
    /**
     * Lists received invoices (`invoice.inbound.read`; free). `tier: 'history'` lists those
     * retrieved by a history run. `$res->data['items']` holds the page; `$res->pagination` is `meta.pagination`.
     *
     * @param ListInboundInvoicesQuery|null $query
     *
     * @return Paginated<ListInboundInvoicesData>
     */
    public function list(?array $query = null, ?RequestOptions $options = null): Paginated
    {
        return $this->paginated('GET', '/i/v1/inbound-invoices', ['query' => $query, 'options' => $options]);
    }

    /**
     * Gets a received invoice; `document` is read from the gateway on first view (`invoice.inbound.read`).
     *
     * @return GetInboundInvoiceData
     */
    public function get(string $id, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/i/v1/inbound-invoices/' . self::seg($id), ['options' => $options]);
    }

    /**
     * Analytics over received invoices, with the list's filters (`invoice.inbound.read`; free).
     *
     * @param GetInboundInvoiceAnalyticsQuery|null $query
     *
     * @return GetInboundInvoiceAnalyticsData
     */
    public function getAnalytics(?array $query = null, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/i/v1/inbound-invoices/analytics', ['query' => $query, 'options' => $options]);
    }

    /**
     * The history retrievals, newest first, and the active one (`invoice.inbound.read`; free).
     *
     * @param ListInboundHistoryRunsQuery|null $query
     *
     * @return Paginated<ListInboundHistoryRunsData>
     */
    public function listHistoryRuns(?array $query = null, ?RequestOptions $options = null): Paginated
    {
        return $this->paginated('GET', '/i/v1/inbound-invoices/history-runs', ['query' => $query, 'options' => $options]);
    }

    /**
     * One history retrieval, for polling its progress (`invoice.inbound.read`; free).
     *
     * @return GetInboundHistoryRunData
     */
    public function getHistoryRun(string $runId, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/i/v1/inbound-invoices/history-runs/' . self::seg($runId), ['options' => $options]);
    }

    /**
     * Kept for older clients: reads and charges nothing and answers that nothing older belongs in
     * the Received tab. Older invoices come from history runs (started in the dashboard).
     *
     * @return LoadOlderInboundInvoicesData
     *
     * @deprecated Use {@see listHistoryRuns()} and `list(['tier' => 'history'])`.
     */
    public function loadOlder(?RequestOptions $options = null): array
    {
        return $this->call('POST', '/i/v1/inbound-invoices/load-older', ['options' => $options]);
    }
}

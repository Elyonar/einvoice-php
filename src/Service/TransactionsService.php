<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Service;

use Useyona\EInvoice\Page;
use Useyona\EInvoice\RequestOptions;

/**
 * The credit ledger (`/b/v1/transactions`).
 *
 * @phpstan-import-type ListLedgerEntriesQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListLedgerEntriesItem from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetLedgerEntryData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetUsageAnalyticsQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetUsageAnalyticsItem from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetUsageByCostCodeQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetUsageByCostCodeItem from \Useyona\EInvoice\Generated\Types
 */
final class TransactionsService extends BaseService
{
    /**
     * Ledger entries, newest first (`billing.ledger.read`).
     *
     * @param ListLedgerEntriesQuery|null $query
     *
     * @return Page<ListLedgerEntriesItem>
     */
    public function list(?array $query = null, ?RequestOptions $options = null): Page
    {
        return $this->page('GET', '/b/v1/transactions', ['query' => $query, 'options' => $options]);
    }

    /**
     * One ledger entry (`billing.ledger.read`).
     *
     * @return GetLedgerEntryData
     */
    public function get(string $id, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/b/v1/transactions/' . self::seg($id), ['options' => $options]);
    }

    /**
     * Credits spent per day, week or month (`billing.usage.read`).
     *
     * @param GetUsageAnalyticsQuery|null $query
     *
     * @return list<GetUsageAnalyticsItem>
     */
    public function getUsageAnalytics(?array $query = null, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/b/v1/transactions/analytics/usage', ['query' => $query, 'options' => $options]);
    }

    /**
     * Credits spent per cost code, net of refunds (`billing.usage.read`).
     *
     * @param GetUsageByCostCodeQuery|null $query
     *
     * @return list<GetUsageByCostCodeItem>
     */
    public function getUsageByCostCode(?array $query = null, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/b/v1/transactions/breakdown/by-endpoint', ['query' => $query, 'options' => $options]);
    }
}

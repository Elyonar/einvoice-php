<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Service;

use Useyona\EInvoice\Page;
use Useyona\EInvoice\RequestOptions;

/**
 * The sandbox ledger (`/b/v1/sandbox`). A live key is refused with 403 `BIZ108`.
 *
 * @phpstan-import-type ListSandboxTransactionsQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListSandboxTransactionsItem from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetSandboxUsageData from \Useyona\EInvoice\Generated\Types
 */
final class SandboxService extends BaseService
{
    /**
     * The sandbox ledger's entries, newest first (`billing.ledger.read`).
     *
     * @param ListSandboxTransactionsQuery|null $query
     *
     * @return Page<ListSandboxTransactionsItem>
     */
    public function listTransactions(?array $query = null, ?RequestOptions $options = null): Page
    {
        return $this->page('GET', '/b/v1/sandbox/transactions', ['query' => $query, 'options' => $options]);
    }

    /**
     * This UTC month's free sandbox allowance: granted, used, remaining (`billing.usage.read`).
     *
     * @return GetSandboxUsageData
     */
    public function getUsage(?RequestOptions $options = null): array
    {
        return $this->call('GET', '/b/v1/sandbox/usage', ['options' => $options]);
    }
}

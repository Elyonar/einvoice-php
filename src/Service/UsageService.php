<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Service;

use Useyona\EInvoice\RequestOptions;

/**
 * Invoice usage (`/b/v1/usage/invoices`): this month's invoices against the plan's allowance.
 *
 * @phpstan-import-type GetInvoiceUsageData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetOverageQuoteData from \Useyona\EInvoice\Generated\Types
 */
final class UsageService extends BaseService
{
    /**
     * This month's invoice usage, the allowance and its thresholds (`billing.subscription.read`);
     * `null` when the organisation has none.
     *
     * @return GetInvoiceUsageData
     */
    public function getInvoices(?RequestOptions $options = null): ?array
    {
        return $this->call('GET', '/b/v1/usage/invoices/current', ['options' => $options]);
    }

    /**
     * What an overage pack would cost now; writes nothing (`billing.subscription.read`).
     *
     * @return GetOverageQuoteData
     */
    public function quoteOverage(?RequestOptions $options = null): array
    {
        return $this->call('GET', '/b/v1/usage/invoices/overage/quote', ['options' => $options]);
    }
}

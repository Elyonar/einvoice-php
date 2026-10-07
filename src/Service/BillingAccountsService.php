<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Service;

use Useyona\EInvoice\RequestOptions;

/**
 * Billing accounts (`/b/v1/billing-accounts`): the credit balance.
 *
 * @phpstan-import-type GetBillingAccountQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetBillingAccountData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetBillingAccountStatsQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetBillingAccountStatsData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type CheckBillingAccountBalanceQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type CheckBillingAccountBalanceData from \Useyona\EInvoice\Generated\Types
 */
final class BillingAccountsService extends BaseService
{
    /**
     * The organisation's billing account; an API key sees its own mode's balance (`billing.account.read`).
     *
     * @param GetBillingAccountQuery|null $query
     *
     * @return GetBillingAccountData
     */
    public function getMine(?array $query = null, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/b/v1/billing-accounts/me', ['query' => $query, 'options' => $options]);
    }

    /**
     * Balance, lifetime totals and 30-day consumption; `$accountId` is the account's id or `'me'` (`billing.account.read`).
     *
     * @param GetBillingAccountStatsQuery|null $query
     *
     * @return GetBillingAccountStatsData
     */
    public function getStats(string $accountId = 'me', ?array $query = null, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/b/v1/billing-accounts/' . self::seg($accountId) . '/stats', ['query' => $query, 'options' => $options]);
    }

    /**
     * Whether the balance covers `credits` (advisory; `billing.account.read`). `$accountId` is the id or `'me'`.
     *
     * @param CheckBillingAccountBalanceQuery $query
     *
     * @return CheckBillingAccountBalanceData
     */
    public function checkBalance(string $accountId, array $query, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/b/v1/billing-accounts/' . self::seg($accountId) . '/check-balance', ['query' => $query, 'options' => $options]);
    }
}

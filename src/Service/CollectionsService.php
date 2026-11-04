<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Service;

use Useyona\EInvoice\Page;
use Useyona\EInvoice\RequestOptions;

/**
 * Collections (`/b/v1/collection`): accounts that collect an invoice's payment by bank transfer,
 * the payments they received and how each was reconciled, buyer credit, the exceptions a person
 * must resolve, and the settlement account the money is paid out to. Each answer is the key's own
 * mode. Answers only where collections are switched on for the key's mode; elsewhere every call is
 * 409 `BIZ120` (`ConflictException`).
 *
 * @phpstan-import-type ListCollectionAccountsQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListCollectionAccountsItem from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type CreateCollectionAccountBody from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type CreateCollectionAccountData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetCollectionAccountData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetCollectionAccountStatusData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type DeactivateCollectionAccountBody from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type DeactivateCollectionAccountData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type QuoteCollectionFeeQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type QuoteCollectionFeeItem from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListCollectionPaymentsQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListCollectionPaymentsItem from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListCollectionBuyerCreditsQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListCollectionBuyerCreditsItem from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListCollectionExceptionsQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListCollectionExceptionsItem from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ResolveCollectionExceptionBody from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ResolveCollectionExceptionData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListCollectionSettlementAccountsQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListCollectionSettlementAccountsItem from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetCollectionSettlementAccountData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListCollectionBanksQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListCollectionBanksItem from \Useyona\EInvoice\Generated\Types
 */
final class CollectionsService extends BaseService
{
    /**
     * The organisation's collection accounts, newest first (`collection.account.read`).
     *
     * @param ListCollectionAccountsQuery|null $query
     *
     * @return Page<ListCollectionAccountsItem>
     */
    public function listAccounts(?array $query = null, ?RequestOptions $options = null): Page
    {
        return $this->page('GET', '/b/v1/collection/accounts', ['query' => $query, 'options' => $options]);
    }

    /**
     * Opens a collection account for an invoice (`collection.account.create`). Not retried: the
     * route takes no `Idempotency-Key`.
     *
     * @param CreateCollectionAccountBody $params
     *
     * @return CreateCollectionAccountData
     */
    public function createAccount(array $params, ?RequestOptions $options = null): array
    {
        return $this->call('POST', '/b/v1/collection/accounts', ['body' => $params, 'options' => $options]);
    }

    /**
     * One collection account (`collection.account.read`).
     *
     * @return GetCollectionAccountData
     */
    public function getAccount(string $id, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/b/v1/collection/accounts/' . self::seg($id), ['options' => $options]);
    }

    /**
     * The provider's state of a collection account (`collection.account.read`).
     *
     * @return GetCollectionAccountStatusData
     */
    public function getAccountStatus(string $id, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/b/v1/collection/accounts/' . self::seg($id) . '/status', ['options' => $options]);
    }

    /**
     * Deactivates a collection account; it stops taking payments (`collection.account.deactivate`).
     * Not retried: the route takes no `Idempotency-Key`.
     *
     * @param DeactivateCollectionAccountBody $params
     *
     * @return DeactivateCollectionAccountData
     */
    public function deactivateAccount(string $id, array $params, ?RequestOptions $options = null): array
    {
        return $this->call('POST', '/b/v1/collection/accounts/' . self::seg($id) . '/deactivate', ['body' => $params, 'options' => $options]);
    }

    /**
     * Estimates the provider's fee on a collection before the account is opened
     * (`collection.account.create`).
     *
     * @param QuoteCollectionFeeQuery $query
     *
     * @return list<QuoteCollectionFeeItem>
     */
    public function quoteFee(array $query, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/b/v1/collection/quote', ['query' => $query, 'options' => $options]);
    }

    /**
     * The organisation's collection payments, newest first (`collection.payment.read`).
     *
     * @param ListCollectionPaymentsQuery|null $query
     *
     * @return Page<ListCollectionPaymentsItem>
     */
    public function listPayments(?array $query = null, ?RequestOptions $options = null): Page
    {
        return $this->page('GET', '/b/v1/collection/payments', ['query' => $query, 'options' => $options]);
    }

    /**
     * Buyer credit: each buyer's paid remainder not yet applied or refunded (`collection.payment.read`).
     *
     * @param ListCollectionBuyerCreditsQuery|null $query
     *
     * @return Page<ListCollectionBuyerCreditsItem>
     */
    public function listBuyerCredits(?array $query = null, ?RequestOptions $options = null): Page
    {
        return $this->page('GET', '/b/v1/collection/credits', ['query' => $query, 'options' => $options]);
    }

    /**
     * Payments a person must resolve (`collection.exception.read`).
     *
     * @param ListCollectionExceptionsQuery|null $query
     *
     * @return Page<ListCollectionExceptionsItem>
     */
    public function listExceptions(?array $query = null, ?RequestOptions $options = null): Page
    {
        return $this->page('GET', '/b/v1/collection/exceptions', ['query' => $query, 'options' => $options]);
    }

    /**
     * Resolves an exception: points its remainder at an invoice or a buyer, or records it as handled
     * (`collection.exception.resolve`). `$paymentId` is the exception's payment. Not retried: the
     * route takes no `Idempotency-Key`.
     *
     * @param ResolveCollectionExceptionBody $params
     *
     * @return ResolveCollectionExceptionData
     */
    public function resolveException(string $paymentId, array $params, ?RequestOptions $options = null): array
    {
        return $this->call('POST', '/b/v1/collection/exceptions/' . self::seg($paymentId) . '/resolve', ['body' => $params, 'options' => $options]);
    }

    /**
     * The active settlement account of this mode, as a page of zero or one
     * (`collection.settlement_account.read`).
     *
     * @param ListCollectionSettlementAccountsQuery|null $query
     *
     * @return Page<ListCollectionSettlementAccountsItem>
     */
    public function listSettlementAccounts(?array $query = null, ?RequestOptions $options = null): Page
    {
        return $this->page('GET', '/b/v1/collection/settlement-accounts', ['query' => $query, 'options' => $options]);
    }

    /**
     * The active settlement account, by the organisation's seller id (`collection.settlement_account.read`).
     *
     * @return GetCollectionSettlementAccountData
     */
    public function getSettlementAccount(string $sellerId, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/b/v1/collection/settlement-accounts/' . self::seg($sellerId), ['options' => $options]);
    }

    /**
     * The rail's bank list for this mode, for choosing a settlement bank
     * (`collection.settlement_account.read`).
     *
     * @param ListCollectionBanksQuery $query
     *
     * @return list<ListCollectionBanksItem>
     */
    public function listBanks(array $query, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/b/v1/collection/banks', ['query' => $query, 'options' => $options]);
    }
}

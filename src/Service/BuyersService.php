<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Service;

use Useyona\EInvoice\Page;
use Useyona\EInvoice\RequestOptions;

/**
 * Buyers (`/i/v1/buyers`): the parties the organisation invoices.
 *
 * @phpstan-import-type CreateBuyerBody from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type CreateBuyerData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListBuyersQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListBuyersItem from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetBuyerData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type UpdateBuyerBody from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type UpdateBuyerData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type DeleteBuyerData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type BulkDeleteBuyersBody from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type BulkDeleteBuyersData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type VerifyBuyerTaxNumberData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetBuyerVerificationStatusData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type SearchBuyersQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type SearchBuyersItem from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type CheckBuyerReachabilityQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type CheckBuyerReachabilityData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type CheckBuyerWithTaxAuthorityBody from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type CheckBuyerWithTaxAuthorityData from \Useyona\EInvoice\Generated\Types
 */
final class BuyersService extends BaseService
{
    /**
     * Creates a buyer (`buyer.create`; charged per buyer).
     *
     * @param CreateBuyerBody $params
     *
     * @return CreateBuyerData
     */
    public function create(array $params, ?RequestOptions $options = null): array
    {
        return $this->call('POST', '/i/v1/buyers', ['body' => $params, 'options' => $options]);
    }

    /**
     * Lists buyers, filtered by `search` and `taxIdStatus` (`buyer.read`).
     *
     * @param ListBuyersQuery|null $query
     *
     * @return Page<ListBuyersItem>
     */
    public function list(?array $query = null, ?RequestOptions $options = null): Page
    {
        return $this->page('GET', '/i/v1/buyers', ['query' => $query, 'options' => $options]);
    }

    /**
     * Gets a buyer, archived ones included (`buyer.read`).
     *
     * @return GetBuyerData
     */
    public function get(string $id, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/i/v1/buyers/' . self::seg($id), ['options' => $options]);
    }

    /**
     * Updates a buyer; `null` clears an optional field (`buyer.update`; charged when the edit
     * changes something, free for a no-op). Retried only with `$options->idempotencyKey`.
     *
     * @param UpdateBuyerBody $params
     *
     * @return UpdateBuyerData
     */
    public function update(string $id, array $params, ?RequestOptions $options = null): array
    {
        return $this->call('PATCH', '/i/v1/buyers/' . self::seg($id), [
            'body' => $params,
            'idempotent' => $options?->idempotencyKey !== null,
            'options' => $options,
        ]);
    }

    /**
     * Deletes a buyer, or archives it when an issued invoice names it (`outcome` says which; `buyer.delete`).
     *
     * @return DeleteBuyerData
     */
    public function delete(string $id, ?RequestOptions $options = null): array
    {
        return $this->call('DELETE', '/i/v1/buyers/' . self::seg($id), ['options' => $options]);
    }

    /**
     * Deletes up to 100 buyers, each like a single delete (`buyer.delete`).
     *
     * @param BulkDeleteBuyersBody $params
     *
     * @return BulkDeleteBuyersData
     */
    public function bulkDelete(array $params, ?RequestOptions $options = null): array
    {
        return $this->call('DELETE', '/i/v1/buyers/bulk', ['body' => $params, 'options' => $options]);
    }

    /**
     * Starts a tax-number verification (`buyer.verify_tax_id`; 202, or 200 with the one pending).
     *
     * @return VerifyBuyerTaxNumberData
     */
    public function verifyTaxNumber(string $id, ?RequestOptions $options = null): array
    {
        return $this->call('POST', '/i/v1/buyers/' . self::seg($id) . '/verify-tax-number', ['options' => $options]);
    }

    /**
     * The latest verification and the buyer's tax-id status (`buyer.read`).
     *
     * @return GetBuyerVerificationStatusData
     */
    public function getVerificationStatus(string $id, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/i/v1/buyers/' . self::seg($id) . '/verification-status', ['options' => $options]);
    }

    /**
     * Searches buyers by name, legal name or tax id (`q` ≥ 2 characters; `buyer.read`).
     *
     * @param SearchBuyersQuery $query
     *
     * @return list<SearchBuyersItem>
     */
    public function search(array $query, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/i/v1/buyers/search', ['query' => $query, 'options' => $options]);
    }

    /**
     * Whether the e-invoicing network reaches a TIN, from the cache (`buyer.read`; free).
     *
     * @param CheckBuyerReachabilityQuery $query
     *
     * @return CheckBuyerReachabilityData
     */
    public function checkReachability(array $query, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/i/v1/buyers/reachability', ['query' => $query, 'options' => $options]);
    }

    /**
     * Asks the tax authority about a TIN now (`buyer.read`; charged, refunded when unanswered).
     * Sends an `Idempotency-Key`.
     *
     * @param CheckBuyerWithTaxAuthorityBody $params
     *
     * @return CheckBuyerWithTaxAuthorityData
     */
    public function checkWithTaxAuthority(array $params, ?RequestOptions $options = null): array
    {
        return $this->call('POST', '/i/v1/buyers/reachability/check', ['body' => $params, 'idempotent' => true, 'options' => $options]);
    }
}

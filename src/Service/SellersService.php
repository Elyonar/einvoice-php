<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Service;

use Useyona\EInvoice\Page;
use Useyona\EInvoice\RequestOptions;

/**
 * Sellers (`/i/v1/sellers`): read-only. The organisation is its only seller; creating, editing,
 * deleting or verifying a seller is always 409 `BIZ205`, so the SDK has no method for it.
 *
 * @phpstan-import-type ListSellersQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListSellersItem from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetSellerData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetSellerVerificationStatusData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type SearchSellersQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type SearchSellersItem from \Useyona\EInvoice\Generated\Types
 */
final class SellersService extends BaseService
{
    /**
     * The organisation's seller, as a list of one (`seller.read`).
     *
     * @param ListSellersQuery|null $query
     *
     * @return Page<ListSellersItem>
     */
    public function list(?array $query = null, ?RequestOptions $options = null): Page
    {
        return $this->page('GET', '/i/v1/sellers', ['query' => $query, 'options' => $options]);
    }

    /**
     * Gets the seller by id (`seller.read`).
     *
     * @return GetSellerData
     */
    public function get(string $id, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/i/v1/sellers/' . self::seg($id), ['options' => $options]);
    }

    /**
     * The organisation's TIN status (`seller.read`).
     *
     * @return GetSellerVerificationStatusData
     */
    public function getVerificationStatus(string $id, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/i/v1/sellers/' . self::seg($id) . '/verification-status', ['options' => $options]);
    }

    /**
     * The seller when it matches `q`, else an empty list (`seller.read`).
     *
     * @param SearchSellersQuery $query
     *
     * @return list<SearchSellersItem>
     */
    public function search(array $query, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/i/v1/sellers/search', ['query' => $query, 'options' => $options]);
    }
}

<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Service;

use Useyona\EInvoice\Page;
use Useyona\EInvoice\RequestOptions;

/**
 * Invoice reference (`/i/v1/invoices/hsn-codes|resources|lookup|validate`): code lists, tax-id
 * lookup and the dry-run validation with the tax authority.
 *
 * @phpstan-import-type ListHsCodesQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListHsCodesItem from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListHsCodeCategoriesItem from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListInvoiceReferenceListsData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetInvoiceReferenceListData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type LookupTaxIdQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type LookupTaxIdData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ValidateInvoiceBody from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ValidateInvoiceData from \Useyona\EInvoice\Generated\Types
 */
final class ReferenceService extends BaseService
{
    /**
     * Lists HS codes of the jurisdiction (`reference.read`; free).
     *
     * @param ListHsCodesQuery|null $query
     *
     * @return Page<ListHsCodesItem>
     */
    public function listHsCodes(?array $query = null, ?RequestOptions $options = null): Page
    {
        return $this->page('GET', '/i/v1/invoices/hsn-codes', ['query' => $query, 'options' => $options]);
    }

    /**
     * Lists the HS code category names (`reference.read`; free).
     *
     * @return list<ListHsCodeCategoriesItem>
     */
    public function listHsCodeCategories(?RequestOptions $options = null): array
    {
        return $this->call('GET', '/i/v1/invoices/hsn-codes/categories', ['options' => $options]);
    }

    /**
     * Lists the jurisdiction's reference code lists (`reference.read`; free).
     *
     * @return ListInvoiceReferenceListsData
     */
    public function listResources(?RequestOptions $options = null): array
    {
        return $this->call('GET', '/i/v1/invoices/resources', ['options' => $options]);
    }

    /**
     * One code list's items, e.g. `currencies`, `tax-categories` (`reference.read`; free; 404 for a
     * type not served).
     *
     * @return GetInvoiceReferenceListData
     */
    public function getResource(string $type, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/i/v1/invoices/resources/' . self::seg($type), ['options' => $options]);
    }

    /**
     * Looks up a tax id with the provider (`tax_id.lookup`; charged, refunded when it could not
     * answer). Sends an `Idempotency-Key`, so an SDK retry is not charged twice.
     *
     * @param LookupTaxIdQuery|null $query
     *
     * @return LookupTaxIdData
     */
    public function lookupTaxId(string $value, ?array $query = null, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/i/v1/invoices/lookup/tax-id/' . self::seg($value), ['query' => $query, 'idempotent' => true, 'options' => $options]);
    }

    /**
     * Validates a document with the tax authority without storing it (`invoice.validate`; charged,
     * refunded when it could not answer). 200 whether valid or not. Sends an `Idempotency-Key`.
     *
     * @param ValidateInvoiceBody $params
     *
     * @return ValidateInvoiceData
     */
    public function validateInvoice(array $params, ?RequestOptions $options = null): array
    {
        return $this->call('POST', '/i/v1/invoices/validate', ['body' => $params, 'idempotent' => true, 'options' => $options]);
    }
}

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
 *
 * The item shapes of the three code lists whose items are fixed (`getResource`):
 *
 * @phpstan-type CountryOption array{code: string, name: string}
 * @phpstan-type StateOption array{code: string, name: string}
 * @phpstan-type LgaOption array{code: string, name: string, stateCode: string}
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
     * type not served). Three lists have a fixed item shape: `countries` `{code, name}` (ISO 3166-1
     * alpha-2), `states` `{code, name}` (Nigeria: `NG-LA` Lagos) and `lgas` `{code, name, stateCode}`
     * (Nigeria: `NG-LA-IKE` Ikeja, in `NG-LA`). For a State and LGA picker, read both once and
     * filter `lgas` by the chosen state's `code`; a buyer's address takes these codes.
     *
     * @return (
     *     $type is 'states' ? array{type: string, version: float, items: list<StateOption>, fetchedAt: string, stale: bool} : (
     *     $type is 'lgas' ? array{type: string, version: float, items: list<LgaOption>, fetchedAt: string, stale: bool} : (
     *     $type is 'countries' ? array{type: string, version: float, items: list<CountryOption>, fetchedAt: string, stale: bool} :
     *     GetInvoiceReferenceListData
     * )))
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

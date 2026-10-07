<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Service;

use Useyona\EInvoice\Page;
use Useyona\EInvoice\RequestOptions;

/**
 * Saved items (`/i/v1/items`): the goods and services an invoice line can reference by `itemId`.
 *
 * @phpstan-import-type ListItemsQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListItemsItem from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type CreateItemBody from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type CreateItemData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetItemData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type UpdateItemBody from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type UpdateItemData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type DeleteItemData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ArchiveItemData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type UnarchiveItemData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListUsedItemCodesQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListUsedItemCodesItem from \Useyona\EInvoice\Generated\Types
 */
final class ItemsService extends BaseService
{
    /**
     * Lists saved items; non-archived by default (`item.read`).
     *
     * @param ListItemsQuery|null $query
     *
     * @return Page<ListItemsItem>
     */
    public function list(?array $query = null, ?RequestOptions $options = null): Page
    {
        return $this->page('GET', '/i/v1/items', ['query' => $query, 'options' => $options]);
    }

    /**
     * Saves an item (`item.create`; free). Goods need `hsnCode`, services `serviceCode`.
     *
     * @param CreateItemBody $params
     *
     * @return CreateItemData
     */
    public function create(array $params, ?RequestOptions $options = null): array
    {
        return $this->call('POST', '/i/v1/items', ['body' => $params, 'options' => $options]);
    }

    /**
     * Gets a saved item, archived ones included (`item.read`).
     *
     * @return GetItemData
     */
    public function get(string $id, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/i/v1/items/' . self::seg($id), ['options' => $options]);
    }

    /**
     * Updates a saved item; `itemType` is immutable (`item.update`; charged). Retried only with
     * `$options->idempotencyKey`.
     *
     * @param UpdateItemBody $params
     *
     * @return UpdateItemData
     */
    public function update(string $id, array $params, ?RequestOptions $options = null): array
    {
        return $this->call('PATCH', '/i/v1/items/' . self::seg($id), [
            'body' => $params,
            'idempotent' => $options?->idempotencyKey !== null,
            'options' => $options,
        ]);
    }

    /**
     * Deletes an item no invoice line references; otherwise 409 `BIZ004`: archive it (`item.archive`).
     *
     * @return DeleteItemData
     */
    public function delete(string $id, ?RequestOptions $options = null): array
    {
        return $this->call('DELETE', '/i/v1/items/' . self::seg($id), ['options' => $options]);
    }

    /**
     * Archives an item (`item.archive`; idempotent).
     *
     * @return ArchiveItemData
     */
    public function archive(string $id, ?RequestOptions $options = null): array
    {
        return $this->call('POST', '/i/v1/items/' . self::seg($id) . '/archive', ['options' => $options]);
    }

    /**
     * Unarchives an item (`item.archive`; idempotent).
     *
     * @return UnarchiveItemData
     */
    public function unarchive(string $id, ?RequestOptions $options = null): array
    {
        return $this->call('POST', '/i/v1/items/' . self::seg($id) . '/unarchive', ['options' => $options]);
    }

    /**
     * The HS (`type=goods`) or service (`type=service`) codes already used on the organisation's
     * invoices (`item.read`).
     *
     * @param ListUsedItemCodesQuery|null $query
     *
     * @return list<ListUsedItemCodesItem>
     */
    public function listUsedCodes(?array $query = null, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/i/v1/invoices/codes/used', ['query' => $query, 'options' => $options]);
    }
}

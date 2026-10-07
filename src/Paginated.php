<?php

declare(strict_types=1);

namespace Useyona\EInvoice;

/**
 * An answer whose `data` is an object that also carries `meta.pagination` (received invoices,
 * issued history): `$res->data['items']` holds the page.
 *
 * @phpstan-import-type PaginationMeta from Page
 *
 * @template D
 */
final class Paginated
{
    /**
     * @param D                   $data
     * @param PaginationMeta|null $pagination `meta.pagination`, when the answer carried one
     * @param string|null         $requestId  the request id of the answer
     */
    public function __construct(
        public readonly mixed $data,
        public readonly ?array $pagination = null,
        public readonly ?string $requestId = null,
    ) {
    }
}

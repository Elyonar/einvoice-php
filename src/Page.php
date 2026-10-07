<?php

declare(strict_types=1);

namespace Useyona\EInvoice;

/**
 * A list answer: the items and `meta.pagination`.
 *
 * @phpstan-type PaginationMeta array{
 *     total: int|float,
 *     page: int|float,
 *     pageSize: int|float,
 *     totalPages: int|float,
 *     hasNext: bool,
 *     hasPrevious: bool,
 *     nextPage?: string,
 *     previousPage?: string,
 *     currentPage?: string
 * }
 *
 * @template T
 */
final class Page
{
    /** `meta.pagination` of an empty or envelope-less answer. */
    public const EMPTY_PAGINATION = ['total' => 0, 'page' => 1, 'pageSize' => 0, 'totalPages' => 0, 'hasNext' => false, 'hasPrevious' => false];

    /**
     * @param list<T>        $data       the items of this page
     * @param PaginationMeta $pagination `meta.pagination`: `total`, `page` (1-based), `pageSize`, `totalPages`, `hasNext`, `hasPrevious`
     * @param string|null    $requestId  the request id of the answer
     */
    public function __construct(
        public readonly array $data,
        public readonly array $pagination = self::EMPTY_PAGINATION,
        public readonly ?string $requestId = null,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Useyona\EInvoice;

/**
 * Iterates every item of a paged list, fetching page after page.
 *
 * ```php
 * foreach (Paginate::all(fn (array $q) => $client->buyers->list($q), ['limit' => 100]) as $buyer) { … }
 * ```
 *
 * `EInvoice::paginate()` is the same function.
 */
final class Paginate
{
    private function __construct()
    {
    }

    /**
     * @template T
     *
     * @param callable(array<string, mixed>): Page<T> $list  called with the query plus `page`, once per page
     * @param array<string, mixed>                    $query the list's query; `page` is the first page to fetch (default 1)
     *
     * @return \Generator<int, T, mixed, void>
     */
    public static function all(callable $list, array $query = []): \Generator
    {
        $page = $query['page'] ?? 1;
        if (!is_int($page)) {
            $page = (int) $page;
        }
        while (true) {
            $result = $list([...$query, 'page' => $page]);
            foreach ($result->data as $item) {
                yield $item;
            }
            if ($result->pagination['hasNext'] !== true || $result->data === []) {
                return;
            }
            ++$page;
        }
    }
}

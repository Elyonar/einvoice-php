<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Service;

use Useyona\EInvoice\BinaryResponse;
use Useyona\EInvoice\Http\HttpClient;
use Useyona\EInvoice\Page;
use Useyona\EInvoice\Paginated;
use Useyona\EInvoice\RequestOptions;

/**
 * Shared plumbing of every service: unwraps the `{ meta, data }` envelope.
 *
 * A call spec is an array with `query` (array|null), `body` (mixed), `idempotent` (bool: the route
 * honours `Idempotency-Key`, so the SDK sends one), `headers` (array<string, string>) and `options`
 * (`RequestOptions|null`).
 *
 * @phpstan-import-type PaginationMeta from Page
 * @phpstan-type CallSpec array{
 *     query?: array<string, mixed>|null,
 *     body?: mixed,
 *     idempotent?: bool,
 *     headers?: array<string, string>,
 *     options?: RequestOptions|null
 * }
 */
abstract class BaseService
{
    public function __construct(protected readonly HttpClient $http)
    {
    }

    /** Encodes one path segment. */
    protected static function seg(string|int $value): string
    {
        return rawurlencode((string) $value);
    }

    /**
     * One call; returns `data`.
     *
     * @param CallSpec $spec
     */
    protected function call(string $method, string $path, array $spec = []): mixed
    {
        return $this->http->request($method, $path, [
            'query' => $spec['query'] ?? null,
            'body' => $spec['body'] ?? null,
            'idempotent' => $spec['idempotent'] ?? false,
            'headers' => $spec['headers'] ?? [],
            'options' => $spec['options'] ?? null,
        ])->data;
    }

    /**
     * A list call; returns the items and `meta.pagination`.
     *
     * @param CallSpec $spec
     *
     * @return Page<mixed>
     */
    protected function page(string $method, string $path, array $spec = []): Page
    {
        $res = $this->http->request($method, $path, [
            'query' => $spec['query'] ?? null,
            'body' => $spec['body'] ?? null,
            'headers' => $spec['headers'] ?? [],
            'options' => $spec['options'] ?? null,
        ]);
        /** @var list<mixed> $data */
        $data = is_array($res->data) ? array_values($res->data) : [];

        return new Page($data, self::paginationOf($res->meta) ?? Page::EMPTY_PAGINATION, $res->requestId);
    }

    /**
     * `meta.pagination` of an answer, when the envelope carried one. The JSON boundary: the wire
     * shape is what the API documents (`PaginationMeta`).
     *
     * @param array<string, mixed>|null $meta
     *
     * @return PaginationMeta|null
     */
    private static function paginationOf(?array $meta): ?array
    {
        $pagination = $meta['pagination'] ?? null;
        if (!is_array($pagination)) {
            return null;
        }

        /** @var PaginationMeta $pagination */
        return $pagination;
    }

    /**
     * A call whose `data` is an object and whose `meta` also carries pagination.
     *
     * @param CallSpec $spec
     *
     * @return Paginated<mixed>
     */
    protected function paginated(string $method, string $path, array $spec = []): Paginated
    {
        $res = $this->http->request($method, $path, [
            'query' => $spec['query'] ?? null,
            'headers' => $spec['headers'] ?? [],
            'options' => $spec['options'] ?? null,
        ]);
        return new Paginated($res->data, self::paginationOf($res->meta), $res->requestId);
    }

    /**
     * A binary download.
     *
     * @param CallSpec $spec
     */
    protected function binary(string $method, string $path, array $spec = []): BinaryResponse
    {
        $res = $this->http->request($method, $path, [
            'query' => $spec['query'] ?? null,
            'idempotent' => $spec['idempotent'] ?? false,
            'binary' => true,
            'headers' => ['Accept' => 'application/pdf', ...($spec['headers'] ?? [])],
            'options' => $spec['options'] ?? null,
        ]);
        if (!$res->data instanceof BinaryResponse) {
            // The route answered JSON (a download link instead of the file): hand it over as bytes of JSON.
            return new BinaryResponse(json_encode($res->data, JSON_THROW_ON_ERROR), 'application/json', null, $res->requestId);
        }

        return $res->data;
    }
}

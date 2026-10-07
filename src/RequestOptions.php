<?php

declare(strict_types=1);

namespace Useyona\EInvoice;

/**
 * Per-request options; the last parameter of every service method.
 *
 * ```php
 * $client->invoices->create($params, new RequestOptions(idempotencyKey: "order-{$orderId}"));
 * ```
 */
final class RequestOptions
{
    /**
     * @param float|null                $timeout        timeout for this request, in seconds. PSR-18 has no per-request
     *                                                  deadline, so this is honoured only when the SDK created the HTTP
     *                                                  client itself (Guzzle); an injected client keeps its own timeout.
     * @param array<string, string>     $headers        extra headers for this request. `Authorization` cannot be overridden.
     * @param string|null               $idempotencyKey the `Idempotency-Key` for a write that accepts one (8–128 of
     *                                                  `A–Z a–z 0–9 _ -`). When omitted on such a write the SDK generates
     *                                                  one, so its own retries are never charged twice; pass your own to
     *                                                  make a retry across process restarts safe. Ignored by routes that
     *                                                  do not accept a key.
     * @param int|null                  $maxRetries     maximum retries for this request (overrides the client's `retry.max_retries`)
     */
    public function __construct(
        public readonly ?float $timeout = null,
        public readonly array $headers = [],
        public readonly ?string $idempotencyKey = null,
        public readonly ?int $maxRetries = null,
    ) {
    }
}

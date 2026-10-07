<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Http;

/** A successful answer, unwrapped from the envelope: `data`, `meta`, status and headers. */
final class RawResponse
{
    /**
     * @param array<string, mixed>|null    $meta    the envelope's `meta`, when the answer was the envelope
     * @param array<string, array<string>> $headers the response headers, as PSR-7 `getHeaders()` gives them
     */
    public function __construct(
        public readonly int $status,
        public readonly mixed $data,
        public readonly ?array $meta = null,
        public readonly ?string $requestId = null,
        public readonly array $headers = [],
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Useyona\EInvoice;

/** A binary download (a PDF). */
final class BinaryResponse
{
    public function __construct(
        /** The file's bytes. */
        public readonly string $data,
        /** `Content-Type`, e.g. `application/pdf`. */
        public readonly string $contentType,
        /** The file name from `Content-Disposition`, when sent. */
        public readonly ?string $fileName = null,
        /** The request id of the answer. */
        public readonly ?string $requestId = null,
    ) {
    }
}

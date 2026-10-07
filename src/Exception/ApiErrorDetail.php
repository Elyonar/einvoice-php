<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Exception;

/**
 * One entry of the API's `meta.errors`: the wire shape is a single-key object
 * `{ "<field-or-code>": "<message>" }`; the SDK also gives it as `field` + `message`.
 */
final class ApiErrorDetail
{
    public function __construct(
        /** The field (or code) the message is about: the single key of the wire object. */
        public readonly string $field,
        /** The human-readable message. */
        public readonly string $message,
    ) {
    }
}

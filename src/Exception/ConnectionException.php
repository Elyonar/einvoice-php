<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Exception;

/** The request never reached the API (DNS, TLS, connection reset…). `getPrevious()` is the PSR-18 exception. */
class ConnectionException extends EInvoiceException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}

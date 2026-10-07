<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Exception;

/**
 * The request did not finish within the timeout.
 *
 * PSR-18 does not tell a timeout from another network failure: the SDK maps a
 * `NetworkExceptionInterface` whose message mentions a timeout ("timed out", "timeout") to this
 * class and every other one to {@see ConnectionException}.
 */
class TimeoutException extends EInvoiceException
{
    public function __construct(
        /** The timeout that elapsed, in seconds. */
        public readonly float $timeoutSeconds,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(sprintf('Request timed out after %ss', rtrim(rtrim(number_format($timeoutSeconds, 3, '.', ''), '0'), '.')), 0, $previous);
    }
}

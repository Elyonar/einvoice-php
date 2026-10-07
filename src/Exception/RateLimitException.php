<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Exception;

/** 429 — rate limited (`SYS005`). `retryAfter` holds the seconds to wait. */
class RateLimitException extends ApiException
{
}

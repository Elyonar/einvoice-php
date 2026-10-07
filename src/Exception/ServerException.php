<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Exception;

/** 5xx — the API or a provider behind it failed (`SYS001`). 503 may carry `retryAfter`. */
class ServerException extends ApiException
{
}

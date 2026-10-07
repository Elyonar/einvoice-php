<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Exception;

/** 401 — the key is missing, malformed, revoked or expired (`AUTH…`). */
class AuthenticationException extends ApiException
{
}

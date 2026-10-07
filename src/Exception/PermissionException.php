<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Exception;

/**
 * 403 — the key lacks a capability (`AUTH019`), the route does not accept an API key
 * (`AUTH018`), or a business rule forbids it.
 */
class PermissionException extends ApiException
{
}

<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Exception;

/** 409 — the resource's state does not allow it (e.g. `BIZ004`, `BIZ201`, `BIZ205`). */
class ConflictException extends ApiException
{
}

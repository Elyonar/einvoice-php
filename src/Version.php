<?php

declare(strict_types=1);

namespace Useyona\EInvoice;

/** The SDK version, as sent in the `User-Agent` header (`einvoice-php/<version>`). */
final class Version
{
    public const VERSION = '0.1.0';

    public const USER_AGENT = 'einvoice-php/' . self::VERSION;

    private function __construct()
    {
    }
}

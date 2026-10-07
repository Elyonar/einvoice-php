<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Tests\Support;

use Psr\Http\Client\ClientExceptionInterface;

/** A PSR-18 failure that is not a network one (the client refused to send the request). */
final class FakeClientException extends \RuntimeException implements ClientExceptionInterface
{
}

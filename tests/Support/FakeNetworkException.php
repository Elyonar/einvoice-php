<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Tests\Support;

use Nyholm\Psr7\Request;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

/**
 * What a PSR-18 client throws when the request never reached the server: a DNS failure, a reset, a
 * timeout. PSR-18 does not tell them apart; the SDK reads the message.
 */
final class FakeNetworkException extends \RuntimeException implements NetworkExceptionInterface
{
    public function __construct(string $message, private readonly ?RequestInterface $request = null)
    {
        parent::__construct($message);
    }

    public function getRequest(): RequestInterface
    {
        return $this->request ?? new Request('GET', 'https://gp.useyona.com/');
    }
}

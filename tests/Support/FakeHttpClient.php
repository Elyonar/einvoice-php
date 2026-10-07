<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Tests\Support;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A PSR-18 client that records every request and answers from a script: a list of responses or
 * exceptions (the last one repeats), or a callable `(RequestInterface): ResponseInterface`.
 */
final class FakeHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var list<ResponseInterface|\Throwable> */
    private array $responses;

    /** @var (callable(RequestInterface): ResponseInterface)|null */
    private $handler;

    /**
     * @param list<ResponseInterface|\Throwable>|callable(RequestInterface): ResponseInterface $script
     */
    public function __construct(array|callable $script)
    {
        if (is_callable($script)) {
            $this->handler = $script;
            $this->responses = [];
        } else {
            $this->handler = null;
            $this->responses = array_values($script);
        }
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        if ($this->handler !== null) {
            return ($this->handler)($request);
        }
        if ($this->responses === []) {
            throw new \LogicException('The fake client has no response scripted');
        }
        $answer = count($this->responses) > 1 ? array_shift($this->responses) : $this->responses[0];
        if ($answer instanceof \Throwable) {
            throw $answer;
        }

        return $answer;
    }

    public function calls(): int
    {
        return count($this->requests);
    }

    public function last(): RequestInterface
    {
        return $this->requests[count($this->requests) - 1];
    }
}

<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Tests\Support;

use Useyona\EInvoice\Http\HttpClient;

/** The transport with `sleep()` recording the waits instead of sleeping. */
final class RecordingHttpClient extends HttpClient
{
    /** @var list<float> */
    public array $sleeps = [];

    protected function sleep(float $seconds): void
    {
        $this->sleeps[] = $seconds;
    }
}

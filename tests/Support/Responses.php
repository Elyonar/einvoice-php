<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Tests\Support;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Useyona\EInvoice\EInvoice;
use Useyona\EInvoice\Http\HttpClient;

/**
 * Shared fixtures: the keys, envelope builders and clients wired to a {@see FakeHttpClient}.
 *
 * @phpstan-import-type RetryConfig from HttpClient
 * @phpstan-type PartialConfig array{
 *     api_key?: string,
 *     base_url?: string,
 *     assert_mode?: 'sandbox'|'live',
 *     timeout?: float|int,
 *     retry?: RetryConfig,
 *     headers?: array<string, string>
 * }
 */
final class Responses
{
    public const TEST_KEY = 'sk_test_abcdefghijklmnop_' . 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';
    public const LIVE_KEY = 'sk_live_qrstuvwxyz234567_' . 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    private function __construct()
    {
    }

    /** @param array<string, string> $headers */
    public static function raw(int $status, ?string $body, array $headers = []): ResponseInterface
    {
        return new Response($status, $headers, $body);
    }

    /** @param array<string, string> $headers */
    public static function json(int $status, mixed $body, array $headers = []): ResponseInterface
    {
        return new Response(
            $status,
            ['content-type' => 'application/json', ...$headers],
            $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR),
        );
    }

    /** @param array<string, mixed> $meta */
    public static function ok(mixed $data, array $meta = []): ResponseInterface
    {
        return self::json(200, [
            'meta' => ['statusCode' => 200, 'success' => true, 'message' => 'Success', 'errors' => [], 'timestamp' => 't', 'requestId' => 'req-ok', ...$meta],
            'data' => $data,
        ]);
    }

    /**
     * @param array<string, mixed>  $extra
     * @param array<string, string> $headers
     */
    public static function refusal(int $status, string $errorCode, array $extra = [], array $headers = []): ResponseInterface
    {
        return self::json($status, [
            'meta' => [
                'statusCode' => $status,
                'success' => false,
                'message' => "refused {$errorCode}",
                'errorCode' => $errorCode,
                'errors' => [['name' => 'is required']],
                'timestamp' => 't',
                'requestId' => 'req-err',
                ...$extra,
            ],
            'data' => null,
        ], $headers);
    }

    /**
     * A transport over the fake, with a tiny backoff and `sleep()` recorded.
     *
     * @param PartialConfig $extra
     */
    public static function http(FakeHttpClient $fake, array $extra = []): RecordingHttpClient
    {
        $factory = new Psr17Factory();

        return new RecordingHttpClient([
            'api_key' => self::TEST_KEY,
            'http_client' => $fake,
            'request_factory' => $factory,
            'stream_factory' => $factory,
            'retry' => ['base_delay' => 0.001],
            ...$extra,
        ]);
    }

    /**
     * A client over the fake (its transport is a plain {@see HttpClient}; retries wait 1 ms).
     *
     * @param PartialConfig $extra
     */
    public static function client(FakeHttpClient $fake, array $extra = []): EInvoice
    {
        $factory = new Psr17Factory();

        return new EInvoice([
            'api_key' => self::TEST_KEY,
            'http_client' => $fake,
            'request_factory' => $factory,
            'stream_factory' => $factory,
            'retry' => ['base_delay' => 0.001],
            ...$extra,
        ]);
    }
}

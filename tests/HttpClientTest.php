<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Useyona\EInvoice\BinaryResponse;
use Useyona\EInvoice\Exception\ApiException;
use Useyona\EInvoice\Exception\AuthenticationException;
use Useyona\EInvoice\Exception\ConfigException;
use Useyona\EInvoice\Exception\ConflictException;
use Useyona\EInvoice\Exception\ConnectionException;
use Useyona\EInvoice\Exception\InsufficientCreditsException;
use Useyona\EInvoice\Exception\NotFoundException;
use Useyona\EInvoice\Exception\PermissionException;
use Useyona\EInvoice\Exception\RateLimitException;
use Useyona\EInvoice\Exception\ServerException;
use Useyona\EInvoice\Exception\TimeoutException;
use Useyona\EInvoice\Exception\ValidationException;
use Useyona\EInvoice\Http\HttpClient;
use Useyona\EInvoice\RequestOptions;
use Useyona\EInvoice\Tests\Support\FakeClientException;
use Useyona\EInvoice\Tests\Support\FakeHttpClient;
use Useyona\EInvoice\Tests\Support\FakeNetworkException;
use Useyona\EInvoice\Tests\Support\Responses;
use Useyona\EInvoice\Version;

/** The port of einvoice-js tests/client/http-client.test.ts over a fake PSR-18 client. */
final class HttpClientTest extends TestCase
{
    // ── configuration ──

    public function testDefaultsToTheProductionGatewayForBothModes(): void
    {
        self::assertSame('https://gp.useyona.com', HttpClient::DEFAULT_BASE_URL);
        self::assertSame('https://gp.useyona.com', (new HttpClient(['api_key' => Responses::TEST_KEY]))->baseUrl);
        self::assertSame('https://gp.useyona.com', (new HttpClient(['api_key' => Responses::LIVE_KEY]))->baseUrl);
        self::assertSame('https://gp.useyona.com', HttpClient::defaultBaseUrl());
    }

    public function testReadsTheModeFromTheKeyPrefix(): void
    {
        self::assertSame('sandbox', (new HttpClient(['api_key' => Responses::TEST_KEY]))->mode);
        self::assertSame('live', (new HttpClient(['api_key' => Responses::LIVE_KEY]))->mode);
        self::assertSame('live', HttpClient::modeOfApiKey(' ' . Responses::LIVE_KEY . ' '));
    }

    public function testAcceptsABaseUrlOverrideAndStripsTrailingSlashes(): void
    {
        self::assertSame('http://127.0.0.1:3000', (new HttpClient(['api_key' => Responses::TEST_KEY, 'base_url' => 'http://127.0.0.1:3000//']))->baseUrl);
        $this->expectException(ConfigException::class);
        new HttpClient(['api_key' => Responses::TEST_KEY, 'base_url' => 'not a url']);
    }

    public function testRejectsAMissingOrMalformedKeyEarlyWithoutEchoingIt(): void
    {
        foreach ([['api_key' => ''], []] as $config) {
            try {
                new HttpClient($config); // @phpstan-ignore argument.type (a missing key is the case under test)
                self::fail('accepted');
            } catch (ConfigException $e) {
                self::assertStringContainsString('API key is required', $e->getMessage());
            }
        }
        $bad = [
            'sk_prod_abcdefghijklmnop_' . str_repeat('A', 43),
            'sk_test_short',
            'pk_test_x',
            Responses::TEST_KEY . 'x',
            'sk_test_ABCDEFGHIJKLMNOP_' . str_repeat('A', 43),
        ];
        foreach ($bad as $key) {
            try {
                new HttpClient(['api_key' => $key]);
                self::fail('accepted');
            } catch (ConfigException $e) {
                self::assertStringNotContainsString($key, $e->getMessage());
            }
        }
        $this->expectException(ConfigException::class);
        HttpClient::modeOfApiKey(42);
    }

    public function testAssertModeThrowsOnAMismatchAndPassesOnAMatch(): void
    {
        try {
            new HttpClient(['api_key' => Responses::TEST_KEY, 'assert_mode' => 'live']);
            self::fail('accepted');
        } catch (ConfigException $e) {
            self::assertMatchesRegularExpression('/asserted to be live/', $e->getMessage());
        }
        try {
            new HttpClient(['api_key' => Responses::LIVE_KEY, 'assert_mode' => 'sandbox']);
            self::fail('accepted');
        } catch (ConfigException $e) {
            self::assertMatchesRegularExpression('/sk_live_/', $e->getMessage());
        }
        self::assertSame('live', (new HttpClient(['api_key' => Responses::LIVE_KEY, 'assert_mode' => 'live']))->mode);
    }

    public function testRefusesUnknownOptions(): void
    {
        // The PHP counterpart of "needs a fetch implementation": the configuration is an array, so a typo must not pass silently.
        try {
            new HttpClient(['api_key' => Responses::TEST_KEY, 'apiKey' => 'x']);
            self::fail('accepted');
        } catch (ConfigException $e) {
            self::assertStringContainsString("Unknown option 'apiKey'", $e->getMessage());
        }
        try {
            new HttpClient(['api_key' => Responses::TEST_KEY, 'retry' => ['maxRetries' => 1]]);
            self::fail('accepted');
        } catch (ConfigException $e) {
            self::assertStringContainsString("Unknown retry option 'maxRetries'", $e->getMessage());
        }
        $this->expectException(ConfigException::class);
        Responses::http(new FakeHttpClient([Responses::ok([])]))->request('GET', '/x', ['requestOptions' => null]);
    }

    public function testCreatesAGuzzleClientWithTheConfiguredTimeoutWhenNoneIsInjected(): void
    {
        $http = new HttpClient(['api_key' => Responses::TEST_KEY, 'timeout' => 7.5]);
        $property = new \ReflectionProperty(HttpClient::class, 'client');
        $client = $property->getValue($http);
        self::assertInstanceOf(\GuzzleHttp\Client::class, $client);
        self::assertSame(7.5, $client->getConfig('timeout'));
        self::assertFalse($client->getConfig('http_errors'));
    }

    #[RunInSeparateProcess]
    public function testTheBaseUrlOverrideConstantIsConsultedOnlyAsTheDefault(): void
    {
        define('USEYONA_EINVOICE_BASE_URL_OVERRIDE', 'http://127.0.0.1:4000/');
        self::assertSame('http://127.0.0.1:4000/', HttpClient::defaultBaseUrl());
        self::assertSame('http://127.0.0.1:4000', (new HttpClient(['api_key' => Responses::TEST_KEY]))->baseUrl);
        self::assertSame('http://localhost:3000', (new HttpClient(['api_key' => Responses::TEST_KEY, 'base_url' => 'http://localhost:3000']))->baseUrl);
    }

    // ── requests ──

    public function testSendsBearerAuthJsonAndTheQueryAndUnwrapsTheEnvelope(): void
    {
        $fake = new FakeHttpClient([Responses::ok(['id' => 'x'])]);
        $c = Responses::http($fake, ['headers' => ['X-Extra' => '1', 'Authorization' => 'Bearer nope']]);
        $res = $c->request('POST', '/i/v1/buyers', [
            'body' => ['name' => 'A'],
            'query' => ['page' => 2, 'empty' => '', 'nil' => null, 'flag' => true, 'ids' => ['a', 'b']],
            'options' => new RequestOptions(headers: ['authorization' => 'Bearer override', 'x-api-key' => 'legacy']),
        ]);
        self::assertSame(['id' => 'x'], $res->data);
        self::assertSame('req-ok', $res->requestId);
        self::assertSame(200, $res->status);
        $req = $fake->last();
        self::assertSame('POST', $req->getMethod());
        self::assertSame('https://gp.useyona.com/i/v1/buyers?page=2&flag=true&ids=a&ids=b', (string) $req->getUri());
        self::assertSame(['Bearer ' . Responses::TEST_KEY], $req->getHeader('Authorization'));
        self::assertFalse($req->hasHeader('x-api-key'));
        self::assertSame('application/json', $req->getHeaderLine('Content-Type'));
        self::assertSame('application/json', $req->getHeaderLine('Accept'));
        self::assertSame('1', $req->getHeaderLine('X-Extra'));
        self::assertSame('einvoice-php/0.1.0', $req->getHeaderLine('User-Agent'));
        self::assertSame(Version::USER_AGENT, $req->getHeaderLine('User-Agent'));
        self::assertSame('{"name":"A"}', (string) $req->getBody());
    }

    public function testAnswersANonEnvelopeJsonBodyAndA204AsTheyAre(): void
    {
        $fake = new FakeHttpClient([
            Responses::json(200, [1, 2]),
            Responses::raw(204, null, ['x-request-id' => 'r204']),
            Responses::raw(200, ''),
        ]);
        $c = Responses::http($fake);
        self::assertSame([1, 2], $c->request('GET', '/x')->data);
        $r = $c->request('DELETE', '/x');
        self::assertNull($r->data);
        self::assertSame('r204', $r->requestId);
        self::assertNull($c->request('GET', '/x')->data);
    }

    public function testASuccessBodyThatIsNotJsonIsAnError(): void
    {
        $c = Responses::http(new FakeHttpClient([Responses::raw(200, '<html>')]));
        $this->expectException(ApiException::class);
        $this->expectExceptionMessageMatches('/not JSON/');
        $c->request('GET', '/x');
    }

    public function testReturnsABinaryDownloadWithItsTypeAndFileName(): void
    {
        $fake = new FakeHttpClient([
            Responses::raw(200, '%PDF', ['content-type' => 'application/pdf', 'content-disposition' => 'attachment; filename="INV-1.pdf"', 'x-request-id' => 'rpdf']),
            Responses::raw(200, "\x01"),
        ]);
        $c = Responses::http($fake);
        $res = $c->request('GET', '/pdf', ['binary' => true]);
        self::assertInstanceOf(BinaryResponse::class, $res->data);
        self::assertSame('%PDF', $res->data->data);
        self::assertSame('application/pdf', $res->data->contentType);
        self::assertSame('INV-1.pdf', $res->data->fileName);
        self::assertSame('rpdf', $res->data->requestId);
        $bare = $c->request('GET', '/pdf', ['binary' => true]);
        self::assertInstanceOf(BinaryResponse::class, $bare->data);
        self::assertSame('application/octet-stream', $bare->data->contentType);
        self::assertNull($bare->data->fileName);
    }

    public function testABinaryRouteAnsweringJsonIsParsedAsTheEnvelope(): void
    {
        $c = Responses::http(new FakeHttpClient([Responses::ok(['downloadUrl' => 'u'])]));
        self::assertSame(['downloadUrl' => 'u'], $c->request('GET', '/pdf', ['binary' => true])->data);
    }

    public function testAnEmptyBodyIsSentAsAnEmptyObject(): void
    {
        $fake = new FakeHttpClient([Responses::ok([])]);
        $c = Responses::http($fake);
        $c->request('POST', '/x', ['body' => []]);
        $c->request('POST', '/x', ['body' => new \stdClass()]);
        $c->request('POST', '/x');
        self::assertSame('{}', (string) $fake->requests[0]->getBody());
        self::assertSame('{}', (string) $fake->requests[1]->getBody());
        self::assertSame('', (string) $fake->requests[2]->getBody());
        self::assertTrue($fake->requests[1]->hasHeader('Content-Type'));
        self::assertFalse($fake->requests[2]->hasHeader('Content-Type'));
    }

    // ── errors ──

    /** @return iterable<string, array{int, string, class-string<ApiException>}> */
    public static function statuses(): iterable
    {
        yield '400 VAL001' => [400, 'VAL001', ValidationException::class];
        yield '422 VAL001' => [422, 'VAL001', ValidationException::class];
        yield '401 AUTH004' => [401, 'AUTH004', AuthenticationException::class];
        yield '402 BIZ001' => [402, 'BIZ001', InsufficientCreditsException::class];
        yield '403 AUTH019' => [403, 'AUTH019', PermissionException::class];
        yield '404 RES001' => [404, 'RES001', NotFoundException::class];
        yield '409 BIZ205' => [409, 'BIZ205', ConflictException::class];
        yield '418 BIZ999' => [418, 'BIZ999', ApiException::class];
    }

    /** @param class-string<ApiException> $class */
    #[DataProvider('statuses')]
    public function testMapsAStatusToItsClassWithErrorCodeErrorsAndRequestId(int $status, string $code, string $class): void
    {
        $c = Responses::http(new FakeHttpClient([Responses::refusal($status, $code)]));
        try {
            $c->request('POST', '/x', ['body' => ['a' => 1]]);
            self::fail('accepted');
        } catch (ApiException $e) {
            self::assertSame($class, $e::class);
            self::assertSame($status, $e->status);
            self::assertSame($status, $e->getStatusCode());
            self::assertSame($code, $e->errorCode);
            self::assertSame("refused {$code}", $e->getMessage());
            self::assertCount(1, $e->errors);
            self::assertSame('name', $e->errors[0]->field);
            self::assertSame('is required', $e->errors[0]->message);
            self::assertSame('req-err', $e->requestId);
            self::assertIsArray($e->body);
        }
    }

    public function testFallsBackToTheXRequestIdHeaderAndHttpStatusWhenTheBodyIsNotTheEnvelope(): void
    {
        $c = Responses::http(new FakeHttpClient([Responses::raw(502, 'Bad Gateway', ['x-request-id' => 'hdr-id'])]));
        try {
            $c->request('POST', '/x');
            self::fail('accepted');
        } catch (ServerException $e) {
            self::assertSame('HTTP 502', $e->getMessage());
            self::assertSame('hdr-id', $e->requestId);
            self::assertNull($e->errorCode);
            self::assertSame([], $e->errors);
        }
        $c = Responses::http(new FakeHttpClient([Responses::json(404, ['meta' => ['errors' => 'odd', 'message' => '']])]));
        try {
            $c->request('POST', '/x');
            self::fail('accepted');
        } catch (NotFoundException $e) {
            self::assertSame('HTTP 404', $e->getMessage());
        }
        $c = Responses::http(new FakeHttpClient([Responses::json(404, ['meta' => ['errors' => [null, 'x', ['a' => 1]]]])]));
        try {
            $c->request('POST', '/x');
            self::fail('accepted');
        } catch (NotFoundException $e) {
            self::assertCount(1, $e->errors);
            self::assertSame('a', $e->errors[0]->field);
            self::assertSame('1', $e->errors[0]->message);
        }
    }

    public function testA429ExposesRetryAfter(): void
    {
        $c = Responses::http(new FakeHttpClient([Responses::refusal(429, 'SYS005', [], ['retry-after' => '7'])]), ['retry' => ['max_retries' => 0]]);
        try {
            $c->request('GET', '/x');
            self::fail('accepted');
        } catch (RateLimitException $e) {
            self::assertSame(7, $e->retryAfter);
        }
    }

    // ── retries ──

    public function testRetriesAGetOn503HonouringRetryAfterThenSucceeds(): void
    {
        $fake = new FakeHttpClient([Responses::refusal(503, 'SYS001', [], ['retry-after' => '3']), Responses::ok(['n' => 1])]);
        $c = Responses::http($fake);
        self::assertSame(['n' => 1], $c->request('GET', '/x')->data);
        self::assertSame(2, $fake->calls());
        self::assertSame([3.0], $c->sleeps);
    }

    public function testRetries429OnAGetWithRetryAfterAndGivesUpAfterMaxRetries(): void
    {
        $fake = new FakeHttpClient([Responses::refusal(429, 'SYS005', [], ['retry-after' => '1'])]);
        $c = Responses::http($fake, ['retry' => ['max_retries' => 2, 'base_delay' => 0.001]]);
        try {
            $c->request('GET', '/x');
            self::fail('accepted');
        } catch (RateLimitException) {
            self::assertSame(3, $fake->calls());
            self::assertSame([1.0, 1.0], $c->sleeps);
        }
    }

    public function testThrowsAtOnceWhenRetryAfterExceedsMaxRetryAfter(): void
    {
        $fake = new FakeHttpClient([Responses::refusal(429, 'SYS005', [], ['retry-after' => '3600'])]);
        try {
            Responses::http($fake)->request('GET', '/x');
            self::fail('accepted');
        } catch (RateLimitException $e) {
            self::assertSame(3600, $e->retryAfter);
            self::assertSame(1, $fake->calls());
        }
    }

    public function testBacksOffOnA500WithoutRetryAfter(): void
    {
        $fake = new FakeHttpClient([Responses::refusal(500, 'SYS001'), Responses::ok([])]);
        $c = Responses::http($fake);
        $c->request('DELETE', '/x');
        self::assertSame(2, $fake->calls());
        self::assertCount(1, $c->sleeps);
        self::assertGreaterThanOrEqual(0.0005, $c->sleeps[0]);
        self::assertLessThanOrEqual(0.001, $c->sleeps[0]);
    }

    public function testNeverRetriesAPostWithoutAnIdempotencyKey(): void
    {
        $fake = new FakeHttpClient([Responses::refusal(503, 'SYS001')]);
        try {
            Responses::http($fake)->request('POST', '/x', ['body' => ['a' => 1]]);
            self::fail('accepted');
        } catch (ServerException) {
            self::assertSame(1, $fake->calls());
        }
        $g = new FakeHttpClient([new FakeNetworkException('fetch failed')]);
        try {
            Responses::http($g)->request('PATCH', '/x', ['body' => ['a' => 1]]);
            self::fail('accepted');
        } catch (ConnectionException) {
            self::assertSame(1, $g->calls());
        }
    }

    public function testGeneratesOneIdempotencyKeyForAKeyedPostAndReusesItOnEveryRetry(): void
    {
        $fake = new FakeHttpClient([new FakeNetworkException('fetch failed'), Responses::refusal(502, 'SYS001'), Responses::ok(['id' => 'inv'])]);
        Responses::http($fake)->request('POST', '/i/v1/invoices', ['body' => ['a' => 1], 'idempotent' => true]);
        self::assertSame(3, $fake->calls());
        $keys = array_map(fn ($r) => $r->getHeaderLine('Idempotency-Key'), $fake->requests);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{8,128}$/', $keys[0]);
        self::assertCount(1, array_unique($keys));
    }

    public function testUsesTheCallerIdempotencyKeyRefusesAMalformedOneAndDropsARawHeaderCopy(): void
    {
        $fake = new FakeHttpClient([Responses::ok([])]);
        $c = Responses::http($fake);
        $c->request('POST', '/x', ['idempotent' => true, 'options' => new RequestOptions(idempotencyKey: 'order-42-create', headers: ['idempotency-key' => 'raw'])]);
        self::assertSame(['order-42-create'], $fake->last()->getHeader('Idempotency-Key'));
        try {
            $c->request('POST', '/x', ['idempotent' => true, 'options' => new RequestOptions(idempotencyKey: 'bad key!')]);
            self::fail('accepted');
        } catch (ConfigException) {
            self::assertSame(1, $fake->calls());
        }
        $c->request('POST', '/x', ['options' => new RequestOptions(idempotencyKey: 'ignored-here')]);
        self::assertFalse($fake->last()->hasHeader('Idempotency-Key'));
    }

    public function testPerRequestMaxRetriesOverridesTheClient(): void
    {
        $fake = new FakeHttpClient([Responses::refusal(503, 'SYS001')]);
        try {
            Responses::http($fake)->request('GET', '/x', ['options' => new RequestOptions(maxRetries: 0)]);
            self::fail('accepted');
        } catch (ServerException) {
            self::assertSame(1, $fake->calls());
        }
    }

    public function testTimesOutAndRetriesAGetAndTheFinalFailureIsATimeoutException(): void
    {
        // PSR-18 reports a timeout as a NetworkExceptionInterface; the SDK tells it from a connection failure by the message.
        $fake = new FakeHttpClient([new FakeNetworkException('cURL error 28: Operation timed out after 5000 milliseconds with 0 bytes received')]);
        $c = Responses::http($fake, ['timeout' => 5, 'retry' => ['max_retries' => 1, 'base_delay' => 0.001]]);
        try {
            $c->request('GET', '/x');
            self::fail('accepted');
        } catch (TimeoutException $e) {
            self::assertSame(2, $fake->calls());
            self::assertSame(5.0, $e->timeoutSeconds);
            self::assertSame('Request timed out after 5s', $e->getMessage());
            self::assertInstanceOf(FakeNetworkException::class, $e->getPrevious());
        }
    }

    public function testAConnectionErrorOnAGetIsRetriedThenThrownAsAConnectionException(): void
    {
        $fake = new FakeHttpClient([new FakeNetworkException('getaddrinfo ENOTFOUND')]);
        try {
            Responses::http($fake, ['retry' => ['max_retries' => 1, 'base_delay' => 0.001]])->request('GET', '/x');
            self::fail('accepted');
        } catch (ConnectionException $e) {
            self::assertStringContainsString('ENOTFOUND', $e->getMessage());
            self::assertStringContainsString('https://gp.useyona.com', $e->getMessage());
            self::assertSame(2, $fake->calls());
            self::assertInstanceOf(FakeNetworkException::class, $e->getPrevious());
        }
    }

    /** @return iterable<string, array{\Throwable, class-string}> */
    public static function networkFailures(): iterable
    {
        yield 'cURL 28' => [new FakeNetworkException('cURL error 28: Operation timed out after 30001 milliseconds'), TimeoutException::class];
        yield 'timeout word' => [new FakeNetworkException('Connection timeout'), TimeoutException::class];
        yield 'timed out' => [new FakeNetworkException('Read timed out'), TimeoutException::class];
        yield 'cURL 6' => [new FakeNetworkException('cURL error 6: Could not resolve host: gp.useyona.com'), ConnectionException::class];
        yield 'reset' => [new FakeNetworkException('Connection reset by peer'), ConnectionException::class];
        yield 'client exception' => [new FakeClientException('the client refused to send the request'), ConnectionException::class];
    }

    /** @param class-string $class */
    #[DataProvider('networkFailures')]
    public function testMapsPsr18FailuresToTimeoutOrConnection(\Throwable $thrown, string $class): void
    {
        $fake = new FakeHttpClient([$thrown]);
        try {
            Responses::http($fake, ['retry' => ['max_retries' => 0]])->request('GET', '/x');
            self::fail('accepted');
        } catch (TimeoutException|ConnectionException $e) {
            self::assertSame($class, $e::class);
            self::assertSame($thrown, $e->getPrevious());
        }
    }

    public function testANonJsonSuccessBodyIsAnApiExceptionNeverRetriedAsAConnectionError(): void
    {
        $fake = new FakeHttpClient([Responses::raw(200, '<html>', ['content-type' => 'text/html'])]);
        try {
            Responses::http($fake)->request('GET', '/x');
            self::fail('accepted');
        } catch (ApiException $e) {
            self::assertSame(ApiException::class, $e::class);
            self::assertSame(1, $fake->calls());
        }
    }

    public function testTheRealSleepWaits(): void
    {
        $factory = new Psr17Factory();
        $c = new HttpClient(['api_key' => Responses::TEST_KEY, 'http_client' => new FakeHttpClient([Responses::ok([])]), 'request_factory' => $factory, 'stream_factory' => $factory]);
        $sleep = new \ReflectionMethod(HttpClient::class, 'sleep');
        $started = hrtime(true);
        $sleep->invoke($c, 0.005);
        self::assertGreaterThanOrEqual(4_000_000, hrtime(true) - $started);
    }

    // ── helpers ──

    public function testParseRetryAfterReadsSecondsAndHttpDates(): void
    {
        self::assertNull(HttpClient::parseRetryAfter(null));
        self::assertNull(HttpClient::parseRetryAfter(''));
        self::assertSame(12, HttpClient::parseRetryAfter('12'));
        self::assertNull(HttpClient::parseRetryAfter('garbage'));
        $now = (int) (new \DateTimeImmutable('2026-10-05T10:00:00Z'))->getTimestamp();
        self::assertSame(30, HttpClient::parseRetryAfter('Mon, 05 Oct 2026 10:00:30 GMT', $now));
        self::assertSame(0, HttpClient::parseRetryAfter('Mon, 05 Oct 2026 09:00:00 GMT', $now));
    }

    public function testGenerateIdempotencyKeyMakesValidUniqueUuids(): void
    {
        $a = HttpClient::generateIdempotencyKey();
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $a);
        self::assertMatchesRegularExpression(HttpClient::IDEMPOTENCY_KEY_PATTERN, $a);
        self::assertNotSame($a, HttpClient::generateIdempotencyKey());
    }
}

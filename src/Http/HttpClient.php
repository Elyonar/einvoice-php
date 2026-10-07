<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Http;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Useyona\EInvoice\BinaryResponse;
use Useyona\EInvoice\Exception\ApiErrorDetail;
use Useyona\EInvoice\Exception\ApiException;
use Useyona\EInvoice\Exception\ConfigException;
use Useyona\EInvoice\Exception\ConnectionException;
use Useyona\EInvoice\Exception\TimeoutException;
use Useyona\EInvoice\RequestOptions;
use Useyona\EInvoice\Version;

/**
 * The transport: authentication, the envelope, errors, idempotency keys, retries and timeouts,
 * over any PSR-18 client and PSR-17 factories (discovered, or injected). Services call
 * {@see HttpClient::request()}; integrators normally never touch it.
 *
 * Configuration (an array; unknown keys throw `ConfigException`):
 *  - `api_key` (string, required): `sk_test_…` (sandbox) or `sk_live_…` (live).
 *  - `base_url` (string): advanced: override the API host (e.g. a local gateway in development).
 *    The default is the production gateway for both modes; never switch hosts by mode.
 *  - `assert_mode` ('sandbox'|'live'): throw at construction unless the key is of this mode.
 *  - `timeout` (float, seconds, default 30): PSR-18 has no per-request deadline, so the timeout is a
 *    property of the HTTP client. When the SDK creates the client itself (Guzzle, through discovery)
 *    it is constructed with this timeout; an injected `http_client` keeps the timeout it was built with.
 *  - `retry` (array): `max_retries` (default 2), `base_delay` (seconds, default 0.5), `max_delay`
 *    (seconds, default 8), `max_retry_after` (seconds, default 60). Only requests that are safe to
 *    repeat are retried: GET, PUT and DELETE, and writes that carry an `Idempotency-Key`; on a network
 *    error, a timeout, 408, 429 and 5xx. A `Retry-After` longer than `max_retry_after` is thrown at once.
 *  - `headers` (array<string, string>): headers sent on every request.
 *  - `http_client` (`ClientInterface`), `request_factory` (`RequestFactoryInterface`),
 *    `stream_factory` (`StreamFactoryInterface`): the PSR-18/17 implementations (discovered when omitted).
 *
 * @phpstan-type RetryConfig array{max_retries?: int, base_delay?: float, max_delay?: float, max_retry_after?: int}
 * @phpstan-type HttpClientConfig array{
 *     api_key: string,
 *     base_url?: string,
 *     assert_mode?: 'sandbox'|'live',
 *     timeout?: float|int,
 *     retry?: RetryConfig,
 *     headers?: array<string, string>,
 *     http_client?: ClientInterface,
 *     request_factory?: RequestFactoryInterface,
 *     stream_factory?: StreamFactoryInterface
 * }
 * @phpstan-type InternalRequestOptions array{
 *     query?: array<string, mixed>|null,
 *     body?: mixed,
 *     idempotent?: bool,
 *     binary?: bool,
 *     headers?: array<string, string>,
 *     options?: RequestOptions|null
 * }
 */
class HttpClient
{
    /**
     * The production gateway, for sandbox and live keys alike (the key's prefix picks the mode).
     *
     * THIS NAME IS PERMANENT. It is compiled into every installed copy of the SDK, so infrastructure
     * moves by repointing DNS behind it, never by changing it here: 0.7.0 broke for everyone when its
     * built-in `gateway.useyona.com` stopped serving.
     */
    public const DEFAULT_BASE_URL = 'https://gp.useyona.com';

    /**
     * A Yona API key: `sk_test_` (sandbox) or `sk_live_` (live), a 16-character public id
     * (base32, lower case), then a 43-character secret (base64url).
     */
    public const API_KEY_PATTERN = '/^sk_(test|live)_([a-z2-7]{16})_([A-Za-z0-9_-]{43})$/';

    /** What the API accepts in `Idempotency-Key`. */
    public const IDEMPOTENCY_KEY_PATTERN = '/^[A-Za-z0-9_-]{8,128}$/';

    public const MODE_SANDBOX = 'sandbox';
    public const MODE_LIVE = 'live';

    private const RETRYABLE_STATUS = [408, 429, 500, 502, 503, 504];
    private const IDEMPOTENT_METHODS = ['GET', 'HEAD', 'PUT', 'DELETE'];
    private const CONFIG_KEYS = ['api_key', 'base_url', 'assert_mode', 'timeout', 'retry', 'headers', 'http_client', 'request_factory', 'stream_factory'];
    private const RETRY_KEYS = ['max_retries', 'base_delay', 'max_delay', 'max_retry_after'];
    private const REQUEST_KEYS = ['query', 'body', 'idempotent', 'binary', 'headers', 'options'];

    /** The host requests go to. */
    public readonly string $baseUrl;

    /** The key's mode, from its prefix: `sandbox` or `live`. */
    public readonly string $mode;

    private readonly string $apiKey;
    private readonly float $timeout;
    private readonly int $maxRetries;
    private readonly float $baseDelay;
    private readonly float $maxDelay;
    private readonly int $maxRetryAfter;
    /** @var array<string, string> */
    private readonly array $defaultHeaders;
    private readonly ClientInterface $client;
    private readonly RequestFactoryInterface $requestFactory;
    private readonly StreamFactoryInterface $streamFactory;

    /**
     * @param HttpClientConfig $config
     *
     * @throws ConfigException for a missing or malformed key, a mode mismatch or a bad option
     */
    public function __construct(array $config)
    {
        foreach (array_keys($config) as $key) {
            if (!in_array($key, self::CONFIG_KEYS, true)) {
                throw new ConfigException(sprintf("Unknown option '%s'; the options are %s.", $key, implode(', ', self::CONFIG_KEYS)));
            }
        }
        $apiKey = $config['api_key'] ?? '';
        if (!is_string($apiKey) || $apiKey === '') {
            throw new ConfigException("An API key is required: new EInvoice(['api_key' => 'sk_test_…']).");
        }
        $this->mode = self::modeOfApiKey($apiKey);
        $assert = $config['assert_mode'] ?? null;
        if ($assert !== null && $assert !== $this->mode) {
            if (!in_array($assert, [self::MODE_SANDBOX, self::MODE_LIVE], true)) {
                throw new ConfigException("assert_mode must be 'sandbox' or 'live'.");
            }
            throw new ConfigException(sprintf(
                'This client was asserted to be %s, but the API key is a %s key (%s…).',
                $assert,
                $this->mode,
                $this->mode === self::MODE_LIVE ? 'sk_live_' : 'sk_test_',
            ));
        }
        $this->apiKey = trim($apiKey);

        $base = $config['base_url'] ?? self::defaultBaseUrl();
        if (!is_string($base) || filter_var($base, FILTER_VALIDATE_URL) === false || !preg_match('#^https?://#i', $base)) {
            throw new ConfigException(sprintf('base_url is not a URL: %s', is_scalar($base) ? (string) $base : gettype($base)));
        }
        $this->baseUrl = rtrim($base, '/');

        $this->timeout = (float) ($config['timeout'] ?? 30.0);
        $retry = $config['retry'] ?? [];
        foreach (array_keys($retry) as $key) {
            if (!in_array($key, self::RETRY_KEYS, true)) {
                throw new ConfigException(sprintf("Unknown retry option '%s'; the options are %s.", $key, implode(', ', self::RETRY_KEYS)));
            }
        }
        $this->maxRetries = $retry['max_retries'] ?? 2;
        $this->baseDelay = (float) ($retry['base_delay'] ?? 0.5);
        $this->maxDelay = (float) ($retry['max_delay'] ?? 8.0);
        $this->maxRetryAfter = $retry['max_retry_after'] ?? 60;
        $this->defaultHeaders = $config['headers'] ?? [];

        $this->client = $config['http_client'] ?? self::discoverClient($this->timeout);
        $this->requestFactory = $config['request_factory'] ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory = $config['stream_factory'] ?? Psr17FactoryDiscovery::findStreamFactory();
    }

    /**
     * The default host: {@see DEFAULT_BASE_URL}, unless the constant `USEYONA_EINVOICE_BASE_URL_OVERRIDE`
     * is defined. Only `scripts/examples-preload.php` defines it (to run the examples against another
     * gateway without changing a line of them); the SDK itself never reads an environment variable.
     */
    public static function defaultBaseUrl(): string
    {
        if (defined('USEYONA_EINVOICE_BASE_URL_OVERRIDE')) {
            $override = constant('USEYONA_EINVOICE_BASE_URL_OVERRIDE');
            if (is_string($override) && $override !== '') {
                return $override;
            }
        }

        return self::DEFAULT_BASE_URL;
    }

    /** The mode a key belongs to, or throws `ConfigException` for a malformed key (never echoing it). */
    public static function modeOfApiKey(mixed $apiKey): string
    {
        $matched = is_string($apiKey) && preg_match(self::API_KEY_PATTERN, trim($apiKey), $m) === 1;
        if (!$matched) {
            throw new ConfigException(
                'The API key is malformed: expected sk_test_<16 characters>_<43 characters> (sandbox) or '
                . 'sk_live_… (live). Copy it again from the dashboard (API keys).',
            );
        }

        return $m[1] === 'live' ? self::MODE_LIVE : self::MODE_SANDBOX;
    }

    /** A fresh idempotency key (a UUID v4). */
    public static function generateIdempotencyKey(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }

    /** Seconds from a `Retry-After` header (delta-seconds or an HTTP date). */
    public static function parseRetryAfter(?string $value, ?int $now = null): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $trimmed = trim($value);
        if (preg_match('/^\d+$/', $trimmed) === 1) {
            return (int) $trimmed;
        }
        $date = \DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC7231, $trimmed, new \DateTimeZone('UTC'));
        if ($date === false) {
            $ts = strtotime($trimmed);
            if ($ts === false) {
                return null;
            }
        } else {
            $ts = $date->getTimestamp();
        }

        return max(0, $ts - ($now ?? time()));
    }

    /**
     * Sends one API call and returns the parsed success, or throws an `ApiException` subclass
     * (`TimeoutException` / `ConnectionException` when the API could not be reached).
     *
     * @param InternalRequestOptions $options `query`, `body`, `idempotent` (the route honours `Idempotency-Key`:
     *                                        the SDK sends one, generated unless the caller gave one), `binary`
     *                                        (expect a PDF instead of the JSON envelope), `headers` (set by the
     *                                        service), `options` (the caller's `RequestOptions`)
     */
    public function request(string $method, string $path, array $options = []): RawResponse
    {
        foreach (array_keys($options) as $key) {
            if (!in_array($key, self::REQUEST_KEYS, true)) {
                throw new ConfigException(sprintf("Unknown request option '%s'.", $key));
            }
        }
        $method = strtoupper($method);
        $ro = $options['options'] ?? null;
        $url = $this->buildUrl($path, $options['query'] ?? null);
        $timeout = $ro->timeout ?? $this->timeout;
        $hasBody = array_key_exists('body', $options) && $options['body'] !== null;
        $headers = $this->buildHeaders($options['headers'] ?? [], $ro, $hasBody, (bool) ($options['idempotent'] ?? false));
        $keyed = array_key_exists('Idempotency-Key', $headers);
        $retryable = in_array($method, self::IDEMPOTENT_METHODS, true) || $keyed;
        $maxRetries = $retryable ? ($ro->maxRetries ?? $this->maxRetries) : 0;
        $body = $hasBody ? self::encodeBody($options['body']) : null;
        $binary = (bool) ($options['binary'] ?? false);

        for ($attempt = 0; ; ++$attempt) {
            try {
                $response = $this->attemptOnce($method, $url, $headers, $body);
            } catch (NetworkExceptionInterface|ClientExceptionInterface $e) {
                $failure = self::isTimeout($e)
                    ? new TimeoutException($timeout, $e)
                    : new ConnectionException(sprintf('Could not reach %s: %s', $this->baseUrl, $e->getMessage()), $e);
                if ($attempt < $maxRetries) {
                    $this->sleep($this->backoff($attempt));
                    continue;
                }
                throw $failure;
            }

            $status = $response->getStatusCode();
            if ($status >= 200 && $status < 300) {
                return $this->parseSuccess($response, $binary);
            }

            $error = $this->parseError($response);
            if ($attempt < $maxRetries && in_array($status, self::RETRYABLE_STATUS, true)) {
                $wait = $error->retryAfter;
                if ($wait !== null && ($status === 429 || $status === 503)) {
                    if ($wait > $this->maxRetryAfter) {
                        throw $error;
                    }
                    $this->sleep((float) $wait);
                } else {
                    $this->sleep($this->backoff($attempt));
                }
                continue;
            }
            throw $error;
        }
    }

    /**
     * Waits `$seconds`.
     *
     * @internal Overridable in tests.
     */
    protected function sleep(float $seconds): void
    {
        if ($seconds > 0) {
            usleep((int) round($seconds * 1_000_000));
        }
    }

    /**
     * @param array<string, string> $headers
     */
    private function attemptOnce(string $method, string $url, array $headers, ?string $body): ResponseInterface
    {
        $request = $this->requestFactory->createRequest($method, $url);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        if ($body !== null) {
            $request = $request->withBody($this->streamFactory->createStream($body));
        }

        return $this->client->sendRequest($request);
    }

    /**
     * @param array<string, string> $serviceHeaders
     *
     * @return array<string, string>
     */
    private function buildHeaders(array $serviceHeaders, ?RequestOptions $ro, bool $hasBody, bool $idempotent): array
    {
        $headers = [];
        foreach ([['Accept' => 'application/json', 'User-Agent' => Version::USER_AGENT], $this->defaultHeaders, $serviceHeaders, $ro->headers ?? []] as $layer) {
            foreach ($layer as $name => $value) {
                // Later layers win, whatever the case the name was written in (PSR-7 headers are case-insensitive).
                foreach (array_keys($headers) as $existing) {
                    if (strcasecmp($existing, $name) === 0) {
                        unset($headers[$existing]);
                    }
                }
                $headers[$name] = $value;
            }
        }
        foreach (array_keys($headers) as $name) {
            $lower = strtolower($name);
            if ($lower === 'authorization' || $lower === 'x-api-key' || $lower === 'idempotency-key') {
                unset($headers[$name]);
            }
        }
        $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        if ($hasBody) {
            $headers['Content-Type'] = 'application/json';
        }
        if ($idempotent) {
            $key = $ro->idempotencyKey ?? self::generateIdempotencyKey();
            if (preg_match(self::IDEMPOTENCY_KEY_PATTERN, $key) !== 1) {
                throw new ConfigException('idempotencyKey must be 8–128 characters of A–Z, a–z, 0–9, _ or -.');
            }
            $headers['Idempotency-Key'] = $key;
        }

        return $headers;
    }

    private function parseSuccess(ResponseInterface $response, bool $binary): RawResponse
    {
        $status = $response->getStatusCode();
        $headerRequestId = self::headerOrNull($response, 'x-request-id');
        $contentType = $response->getHeaderLine('content-type');
        if ($binary && !str_contains($contentType, 'json')) {
            $disposition = $response->getHeaderLine('content-disposition');
            $name = preg_match('/filename\*?=(?:UTF-8\'\')?"?([^";]+)"?/i', $disposition, $m) === 1 ? rawurldecode($m[1]) : null;
            $file = new BinaryResponse((string) $response->getBody(), $contentType !== '' ? $contentType : 'application/octet-stream', $name, $headerRequestId);

            return new RawResponse($status, $file, null, $headerRequestId, $response->getHeaders());
        }
        if ($status === 204) {
            return new RawResponse(204, null, null, $headerRequestId, $response->getHeaders());
        }
        $text = (string) $response->getBody();
        try {
            $parsed = $text === '' ? null : json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ApiException($status, sprintf('The API answered %d with a body that is not JSON', $status), null, [], $headerRequestId, null, $text);
        }
        if (is_array($parsed) && array_key_exists('meta', $parsed) && array_key_exists('data', $parsed)) {
            $meta = is_array($parsed['meta']) ? $parsed['meta'] : null;
            $requestId = is_string($meta['requestId'] ?? null) ? $meta['requestId'] : $headerRequestId;

            return new RawResponse($status, $parsed['data'], $meta, $requestId, $response->getHeaders());
        }

        return new RawResponse($status, $parsed, null, $headerRequestId, $response->getHeaders());
    }

    private function parseError(ResponseInterface $response): ApiException
    {
        $status = $response->getStatusCode();
        $retryAfter = self::parseRetryAfter(self::headerOrNull($response, 'retry-after'));
        $headerRequestId = self::headerOrNull($response, 'x-request-id');
        // Only a body that is not JSON is tolerated; a failure reading it propagates.
        $text = (string) $response->getBody();
        $parsed = $text === '' ? null : json_decode($text, true);
        $meta = is_array($parsed) && is_array($parsed['meta'] ?? null) ? $parsed['meta'] : [];
        $message = $meta['message'] ?? null;
        $errorCode = $meta['errorCode'] ?? null;
        $requestId = $meta['requestId'] ?? null;

        return ApiException::forStatus(
            $status,
            is_string($message) && $message !== '' ? $message : sprintf('HTTP %d', $status),
            is_string($errorCode) ? $errorCode : null,
            self::unpackErrors($meta['errors'] ?? null),
            is_string($requestId) ? $requestId : $headerRequestId,
            $retryAfter,
            $parsed,
        );
    }

    /**
     * `{ field: message }` wire entries → `ApiErrorDetail(field, message)`.
     *
     * @return list<ApiErrorDetail>
     */
    private static function unpackErrors(mixed $errors): array
    {
        $out = [];
        if (!is_array($errors)) {
            return $out;
        }
        foreach ($errors as $entry) {
            if (is_array($entry)) {
                foreach ($entry as $field => $message) {
                    $out[] = new ApiErrorDetail((string) $field, is_scalar($message) || $message === null ? var_export($message, true) === 'NULL' ? 'null' : (is_bool($message) ? ($message ? 'true' : 'false') : (string) $message) : json_encode($message, JSON_THROW_ON_ERROR));
                }
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed>|null $query
     */
    private function buildUrl(string $path, ?array $query): string
    {
        $url = $this->baseUrl . $path;
        if ($query === null || $query === []) {
            return $url;
        }
        $pairs = [];
        foreach ($query as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            foreach (is_array($value) ? $value : [$value] as $v) {
                if ($v === null) {
                    continue;
                }
                $pairs[] = rawurlencode((string) $key) . '=' . rawurlencode(self::scalarToString($v));
            }
        }
        if ($pairs === []) {
            return $url;
        }

        return $url . (str_contains($path, '?') ? '&' : '?') . implode('&', $pairs);
    }

    private static function scalarToString(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    /** JSON for the wire; an empty array is an empty object (`{}`), never `[]`. */
    private static function encodeBody(mixed $body): string
    {
        if ($body === [] || $body instanceof \stdClass && (array) $body === []) {
            return '{}';
        }

        return json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function isTimeout(\Throwable $e): bool
    {
        return $e instanceof NetworkExceptionInterface && preg_match('/timed?[ _-]?out|timeout/i', $e->getMessage()) === 1;
    }

    private static function headerOrNull(ResponseInterface $response, string $name): ?string
    {
        $value = $response->getHeaderLine($name);

        return $value === '' ? null : $value;
    }

    private function backoff(int $attempt): float
    {
        $base = min($this->maxDelay, $this->baseDelay * (2 ** $attempt));

        return $base / 2 + (mt_rand() / mt_getrandmax()) * ($base / 2);
    }

    /**
     * The PSR-18 client the SDK uses when none is injected: Guzzle built with the configured
     * timeout when it is installed, else whatever `php-http/discovery` finds (with its own timeout).
     */
    private static function discoverClient(float $timeout): ClientInterface
    {
        if (class_exists(\GuzzleHttp\Client::class)) {
            return new \GuzzleHttp\Client(['timeout' => $timeout, 'connect_timeout' => $timeout, 'http_errors' => false]);
        }

        return Psr18ClientDiscovery::find();
    }
}

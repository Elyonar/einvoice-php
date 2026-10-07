<?php

declare(strict_types=1);

namespace Useyona\EInvoice;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;
use Useyona\EInvoice\Exception\WebhookException;

/**
 * `Yona-Signature` verification: the port of einvoice-js's `webhooks/verify.ts`.
 *
 * ```php
 * use Useyona\EInvoice\Webhooks;
 *
 * $event = Webhooks::verifyWebhook(file_get_contents('php://input'), $_SERVER, getenv('YONA_WEBHOOK_SECRET'));
 * if ($event['type'] === 'invoice.accepted') { … }
 * ```
 *
 * @phpstan-import-type WebhookEvent from WebhookEventType
 * @phpstan-type SignatureHeader array{timestamp: int, signatures: list<string>}
 */
final class Webhooks
{
    /** The signature header Yona sends on every webhook delivery. */
    public const SIGNATURE_HEADER = 'Yona-Signature';

    /** A delivery whose `t` is further than this from the receiver's clock is refused (seconds). */
    public const DEFAULT_TOLERANCE_SECONDS = 300;

    private function __construct()
    {
    }

    /**
     * Computes one `v1` value: hex HMAC-SHA256, keyed with the endpoint secret as UTF-8, over
     * `<t> + "." + <raw body>`.
     */
    public static function computeWebhookSignature(string $payload, string $secret, int $timestamp): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    }

    /**
     * Builds a `Yona-Signature` header for a payload, for testing your own webhook handler.
     * Pass two secrets to imitate a rotation overlap (the current one first).
     *
     * @param string|list<string> $secrets
     */
    public static function signWebhookPayload(string $payload, string|array $secrets, ?int $timestamp = null): string
    {
        $t = $timestamp ?? time();
        $list = is_string($secrets) ? [$secrets] : array_values($secrets);
        $parts = ['t=' . $t];
        foreach ($list as $secret) {
            $parts[] = 'v1=' . self::computeWebhookSignature($payload, $secret, $t);
        }

        return implode(',', $parts);
    }

    /**
     * The `t` and every `v1` of a `Yona-Signature` header.
     *
     * @return SignatureHeader
     *
     * @throws WebhookException when the header has no `t`, an invalid `t` or no `v1`
     */
    public static function parseSignatureHeader(string $header): array
    {
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            $eq = strpos($part, '=');
            if ($eq === false) {
                continue;
            }
            $key = trim(substr($part, 0, $eq));
            $value = trim(substr($part, $eq + 1));
            if ($key === 't') {
                if (preg_match('/^\d+$/', $value) !== 1) {
                    throw new WebhookException('Yona-Signature has an invalid t');
                }
                $timestamp = (int) $value;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }
        if ($timestamp === null) {
            throw new WebhookException('Yona-Signature has no t');
        }
        if ($signatures === []) {
            throw new WebhookException('Yona-Signature has no v1 signature');
        }

        return ['timestamp' => $timestamp, 'signatures' => $signatures];
    }

    /**
     * Verifies a webhook delivery and returns the parsed event.
     *
     * Yona signs `t + "." + <the exact bytes of the body>` with HMAC-SHA256 and the endpoint secret,
     * and sends `Yona-Signature: t=<unix seconds>,v1=<hex>`; while a rotated secret overlaps the header
     * carries a second `v1=`. This accepts the delivery when any `v1` matches (constant-time compare)
     * and `t` is within the tolerance. Pass the RAW body (`file_get_contents('php://input')`, or the
     * PSR-7 request body stream): a re-serialised array will not verify. Deduplicate on
     * `$event['id']`: a redelivery carries the same id.
     *
     * @param string|StreamInterface                                                $payload          the raw request body
     * @param array<string, string|list<string>|null>|\ArrayAccess<string, mixed>|MessageInterface|null $headers the request
     *        headers: a plain array with any header-name case (`Yona-Signature`, `yona-signature`), `$_SERVER`
     *        (`HTTP_YONA_SIGNATURE`), a PSR-7 request or message (`getHeaderLine`), or an `ArrayAccess`
     * @param string                                                                $secret           the endpoint's signing secret (`whsec_…`), from the dashboard
     * @param int|null                                                              $toleranceSeconds maximum distance (seconds) between the signature's `t` and now; default 300
     * @param int|null                                                              $now              "now" in unix seconds (for tests)
     *
     * @return WebhookEvent the parsed event
     *
     * @throws WebhookException when the header is missing or malformed, `t` is outside the tolerance,
     *                          no signature matches, or the body is not JSON
     */
    public static function verifyWebhook(
        mixed $payload,
        mixed $headers,
        string $secret,
        ?int $toleranceSeconds = null,
        ?int $now = null,
    ): array {
        if ($secret === '') {
            throw new WebhookException('A webhook secret is required');
        }
        if ($payload instanceof StreamInterface) {
            $payload = (string) $payload;
        }
        if (!is_string($payload)) {
            throw new WebhookException('Pass the raw request body (a string), not a parsed array');
        }
        $header = self::readHeader($headers, self::SIGNATURE_HEADER);
        if ($header === null || $header === '') {
            throw new WebhookException('Missing Yona-Signature header');
        }
        ['timestamp' => $timestamp, 'signatures' => $signatures] = self::parseSignatureHeader($header);

        $tolerance = $toleranceSeconds ?? self::DEFAULT_TOLERANCE_SECONDS;
        $current = $now ?? time();
        if (abs($current - $timestamp) > $tolerance) {
            throw new WebhookException(sprintf('Yona-Signature timestamp is outside the %ds tolerance', $tolerance));
        }

        $expected = self::computeWebhookSignature($payload, $secret, $timestamp);
        $matched = false;
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                $matched = true;
            }
        }
        if (!$matched) {
            throw new WebhookException('Webhook signature verification failed');
        }

        try {
            $event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new WebhookException('The webhook body is not JSON');
        }
        if (!is_array($event)) {
            throw new WebhookException('The webhook body is not JSON');
        }

        /** @var WebhookEvent $event */
        return $event;
    }

    /** Reads one header, whatever the shape of `$headers` (see {@see verifyWebhook}). */
    private static function readHeader(mixed $headers, string $name): ?string
    {
        if ($headers === null) {
            return null;
        }
        if ($headers instanceof MessageInterface) {
            $line = $headers->getHeaderLine($name);

            return $line === '' ? null : $line;
        }
        $lower = strtolower($name);
        $server = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (is_array($headers)) {
            foreach ($headers as $key => $value) {
                if (strtolower((string) $key) === $lower || $key === $server) {
                    return self::firstValue($value);
                }
            }

            return null;
        }
        if ($headers instanceof \ArrayAccess) {
            foreach ([$name, $lower, strtoupper($name), $server] as $candidate) {
                if ($headers->offsetExists($candidate)) {
                    return self::firstValue($headers->offsetGet($candidate));
                }
            }
        }

        return null;
    }

    private static function firstValue(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value === [] ? null : reset($value);
        }
        if ($value === null) {
            return null;
        }

        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : null;
    }
}

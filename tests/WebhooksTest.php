<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Useyona\EInvoice\Exception\WebhookException;
use Useyona\EInvoice\Webhooks;

/**
 * Yona-Signature verification: the shared vectors (tests/vectors/webhook-signature.json, produced by
 * the backend signer) that every Yona SDK verifies, plus the PHP-specific input handling.
 */
final class WebhooksTest extends TestCase
{
    private const MESSAGES = [
        'signature' => '/verification failed/',
        'tolerance' => '/tolerance/',
        'parse' => '/has (no|an invalid)/',
        'missing' => '/Missing Yona-Signature/',
        'secret' => '/secret is required/',
    ];

    /** @var array<string, mixed>|null */
    private static ?array $vectors = null;

    /** @return array<string, mixed> */
    private static function vectors(): array
    {
        if (self::$vectors === null) {
            $raw = file_get_contents(__DIR__ . '/vectors/webhook-signature.json');
            self::assertNotFalse($raw);
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            self::$vectors = $decoded;
        }

        return self::$vectors;
    }

    private static function body(): string
    {
        $body = base64_decode(self::str('bodyBase64'), true);
        self::assertNotFalse($body);

        return $body;
    }

    private static function str(string $key): string
    {
        $value = self::vectors()[$key];
        self::assertIsString($value);

        return $value;
    }

    private static function int(string $key): int
    {
        $value = self::vectors()[$key];
        self::assertIsInt($value);

        return $value;
    }

    /** The receiver recipe of the API's webhook documentation, reimplemented independently. */
    private static function backendV1(string $secret, int $t, string $raw): string
    {
        return hash_hmac('sha256', "{$t}.{$raw}", $secret);
    }

    // ── the shared vectors ──

    public function testTheVectorsAreConsistentWithThemselves(): void
    {
        $body = self::body();
        $t = self::int('t');
        self::assertSame(self::str('bodyUtf8'), $body);
        self::assertSame(self::int('bodyBytes'), strlen($body));
        self::assertSame(self::str('v1'), self::backendV1(self::str('secret'), $t, $body));
        self::assertSame(self::str('v1Previous'), self::backendV1(self::str('previousSecret'), $t, $body));
        self::assertSame("t={$t},v1=" . self::str('v1'), self::str('header'));
        self::assertSame("t={$t},v1=" . self::str('v1') . ',v1=' . self::str('v1Previous'), self::str('headerRotating'));
    }

    public function testMatchesTheBackendSignerByteForByte(): void
    {
        $body = self::body();
        $t = self::int('t');
        self::assertSame(self::str('header'), Webhooks::signWebhookPayload($body, self::str('secret'), $t));
        self::assertSame(self::str('headerRotating'), Webhooks::signWebhookPayload($body, [self::str('secret'), self::str('previousSecret')], $t));
        self::assertSame(self::str('v1'), Webhooks::computeWebhookSignature($body, self::str('secret'), $t));
        self::assertSame(self::str('v1'), Webhooks::computeWebhookSignature(self::str('bodyUtf8'), self::str('secret'), $t));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function cases(): iterable
    {
        $raw = file_get_contents(__DIR__ . '/vectors/webhook-signature.json');
        if ($raw === false) {
            throw new \RuntimeException('vectors missing');
        }
        /** @var array{cases: list<array<string, mixed>>} $vectors */
        $vectors = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        foreach ($vectors['cases'] as $case) {
            $name = $case['name'];
            yield (is_string($name) ? $name : 'case') => [$case];
        }
    }

    /** @param array<string, mixed> $case */
    #[DataProvider('cases')]
    public function testCase(array $case): void
    {
        $secrets = ['current' => self::str('secret'), 'previous' => self::str('previousSecret'), 'other' => self::str('otherSecret'), 'empty' => ''];
        $headers = ['header' => self::str('header'), 'rotating' => self::str('headerRotating')];
        $headerKey = $case['header'];
        self::assertIsString($headerKey);
        $header = $headers[$headerKey] ?? $headerKey;
        $body = ($case['body'] ?? null) === 'reserialised' ? self::str('bodyReserialised') : self::body();
        $secretKey = $case['secret'];
        self::assertIsString($secretKey);
        $now = $case['now'] ?? self::int('t');
        self::assertIsInt($now);
        $tolerance = $case['toleranceSeconds'] ?? null;
        self::assertTrue($tolerance === null || is_int($tolerance));

        $run = fn (): array => Webhooks::verifyWebhook($body, $header !== '' ? ['yona-signature' => $header] : [], $secrets[$secretKey], $tolerance, $now);

        $expect = $case['expect'];
        self::assertIsString($expect);
        if ($expect === 'ok') {
            $event = $run();
            /** @var array{id: string, type: string, mode: string} $expected */
            $expected = self::vectors()['event'];
            self::assertSame($expected['id'], $event['id']);
            self::assertSame($expected['type'], $event['type']);
            self::assertSame($expected['mode'], $event['mode']);
        } else {
            $this->expectException(WebhookException::class);
            $this->expectExceptionMessageMatches(self::MESSAGES[$expect]);
            $run();
        }
    }

    public function testParsesTheHeaderIgnoringUnknownSchemesAndJunkParts(): void
    {
        /** @var list<array{header: string, timestamp: int, signatures: list<string>}> $parse */
        $parse = self::vectors()['parseHeader'];
        self::assertNotEmpty($parse);
        foreach ($parse as $vector) {
            self::assertSame(['timestamp' => $vector['timestamp'], 'signatures' => $vector['signatures']], Webhooks::parseSignatureHeader($vector['header']));
        }
    }

    // ── PHP inputs ──

    public function testAcceptsAnyHeaderNameCaseAListValueAPsr7RequestServerVarsAndArrayAccess(): void
    {
        $body = self::body();
        $t = self::int('t');
        $header = self::str('header');
        $secret = self::str('secret');
        self::assertNotEmpty(Webhooks::verifyWebhook($body, ['YONA-SIGNATURE' => $header], $secret, null, $t)['id']);
        self::assertNotEmpty(Webhooks::verifyWebhook($body, ['Yona-Signature' => [$header]], $secret, null, $t)['id']);
        self::assertNotEmpty(Webhooks::verifyWebhook($body, ['HTTP_YONA_SIGNATURE' => $header, 'REQUEST_METHOD' => 'POST'], $secret, null, $t)['id']);
        self::assertNotEmpty(Webhooks::verifyWebhook($body, new \ArrayObject(['yona-signature' => $header]), $secret, null, $t)['id']);

        $factory = new Psr17Factory();
        $request = $factory->createServerRequest('POST', '/webhooks/yona')
            ->withHeader(Webhooks::SIGNATURE_HEADER, $header)
            ->withBody($factory->createStream($body));
        self::assertNotEmpty(Webhooks::verifyWebhook((string) $request->getBody(), $request, $secret, null, $t)['id']);
        // The PSR-7 body stream itself is accepted as the raw payload.
        self::assertNotEmpty(Webhooks::verifyWebhook($request->getBody(), $request, $secret, null, $t)['id']);
    }

    public function testRefusesAParsedArrayInsteadOfTheRawBody(): void
    {
        $this->expectException(WebhookException::class);
        $this->expectExceptionMessageMatches('/raw request body/');
        Webhooks::verifyWebhook(json_decode(self::body(), true), ['yona-signature' => self::str('header')], self::str('secret'), null, self::int('t'));
    }

    public function testRefusesANonJsonBodyAndAMissingHeadersObject(): void
    {
        $raw = 'not json';
        $t = self::int('t');
        try {
            Webhooks::verifyWebhook($raw, ['yona-signature' => Webhooks::signWebhookPayload($raw, self::str('secret'), $t)], self::str('secret'), null, $t);
            self::fail('accepted');
        } catch (WebhookException $e) {
            self::assertStringContainsString('not JSON', $e->getMessage());
        }
        $scalar = '42';
        try {
            Webhooks::verifyWebhook($scalar, ['yona-signature' => Webhooks::signWebhookPayload($scalar, self::str('secret'), $t)], self::str('secret'), null, $t);
            self::fail('accepted');
        } catch (WebhookException $e) {
            self::assertStringContainsString('not JSON', $e->getMessage());
        }
        $this->expectException(WebhookException::class);
        $this->expectExceptionMessageMatches('/Missing/');
        Webhooks::verifyWebhook(self::body(), null, self::str('secret'), null, $t);
    }

    public function testUsesTheCurrentClockByDefault(): void
    {
        $body = self::body();
        $now = time();
        $header = Webhooks::signWebhookPayload($body, self::str('secret'), $now);
        self::assertNotEmpty(Webhooks::verifyWebhook($body, ['yona-signature' => $header], self::str('secret'))['id']);
        self::assertMatchesRegularExpression('/^t=\d+,v1=[0-9a-f]{64}$/', Webhooks::signWebhookPayload($body, self::str('secret')));
        self::assertSame(300, Webhooks::DEFAULT_TOLERANCE_SECONDS);
        self::assertSame('Yona-Signature', Webhooks::SIGNATURE_HEADER);
    }
}

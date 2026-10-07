<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Tests;

use PHPUnit\Framework\TestCase;
use Useyona\EInvoice\Version;

/**
 * GUIDES: guides/guides.json is what the portal's Developers Overview renders (ruling R78), with the
 * same schema as einvoice-js's. This pins the schema, ties every step to a snapshot operation, and
 * fails when the committed file is not what `make guides` produces from examples/ today.
 *
 * @phpstan-type Step array{id: string, title: string, text: string|null, sdk: string, curl: string|null, operationId: string|null}
 * @phpstan-type Recipe array{id: string, title: string, summary: string, steps: list<Step>}
 * @phpstan-type Guides array{sdkVersion: string, generatedFrom: string, install: string, quickStart: string, recipes: list<Recipe>}
 */
final class GuidesTest extends TestCase
{
    private static string $root;

    /** @var Guides */
    private static array $guides;

    /** @var array<string, array{method: string, path: string}> */
    private static array $ops;

    /** @var list<string> */
    private static array $examples;

    public static function setUpBeforeClass(): void
    {
        self::$root = dirname(__DIR__);
        /** @var Guides $guides */
        $guides = self::read('guides/guides.json');
        self::$guides = $guides;
        /** @var array{operations: list<array{operationId: string, method: string, path: string}>} $snapshot */
        $snapshot = self::read('scripts/openapi-api-key-ops.json');
        self::$ops = [];
        foreach ($snapshot['operations'] as $op) {
            self::$ops[$op['operationId']] = $op;
        }
        $files = glob(self::$root . '/examples/*.php');
        self::assertNotFalse($files);
        self::$examples = array_map(fn (string $f): string => basename($f, '.php'), $files);
        sort(self::$examples);
    }

    private static function read(string $path): mixed
    {
        $raw = file_get_contents(self::$root . '/' . $path);
        self::assertNotFalse($raw);

        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }

    private static function nonEmpty(mixed $v): bool
    {
        return is_string($v) && trim($v) !== '';
    }

    /**
     * @param array<string, mixed> $o
     *
     * @return list<string>
     */
    private static function keysOf(array $o): array
    {
        $keys = array_keys($o);
        sort($keys);

        return $keys;
    }

    public function testHasExactlyTheTopLevelSchemaThePortalReads(): void
    {
        self::assertSame(['generatedFrom', 'install', 'quickStart', 'recipes', 'sdkVersion'], self::keysOf(self::$guides));
        self::assertSame(Version::VERSION, self::$guides['sdkVersion']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{7,40}$/', self::$guides['generatedFrom']);
        self::assertSame('composer require useyona/einvoice-php', self::$guides['install']);
        self::assertTrue(self::nonEmpty(self::$guides['quickStart']));
        self::assertStringContainsString('Useyona\EInvoice', self::$guides['quickStart']);
        self::assertStringContainsString('<?php', self::$guides['quickStart']);
        self::assertNotEmpty(self::$guides['recipes']);
    }

    public function testHasOneRecipePerExampleEachWithTheExactRecipeAndStepSchema(): void
    {
        $ids = array_map(fn (array $r): string => $r['id'], self::$guides['recipes']);
        sort($ids);
        self::assertSame(self::$examples, $ids);
        foreach (self::$guides['recipes'] as $r) {
            self::assertSame(['id', 'steps', 'summary', 'title'], self::keysOf($r));
            self::assertMatchesRegularExpression('/^[a-z0-9-]+$/', $r['id']);
            self::assertTrue(self::nonEmpty($r['title']) && self::nonEmpty($r['summary']));
            self::assertNotEmpty($r['steps']);
            self::assertCount(count($r['steps']), array_unique(array_map(fn (array $s): string => $s['id'], $r['steps'])));
            foreach ($r['steps'] as $s) {
                self::assertSame(['curl', 'id', 'operationId', 'sdk', 'text', 'title'], self::keysOf($s));
                self::assertMatchesRegularExpression('/^[a-z0-9][a-z0-9-]*$/', $s['id']);
                self::assertTrue(self::nonEmpty($s['title']) && self::nonEmpty($s['sdk']));
                self::assertTrue($s['text'] === null || self::nonEmpty($s['text']));
                self::assertTrue($s['curl'] === null || self::nonEmpty($s['curl']));
                self::assertSame($s['curl'] === null, $s['operationId'] === null);
                self::assertDoesNotMatchRegularExpression('/@(step|text|op|recipe|summary|quickstart)\b/', $s['sdk']);
            }
            // The header (<?php, require, use) is hoisted into the first step only.
            self::assertStringContainsString('use Useyona\EInvoice', $r['steps'][0]['sdk']);
            self::assertStringStartsWith('<?php', $r['steps'][0]['sdk']);
            foreach (array_slice($r['steps'], 1) as $s) {
                self::assertDoesNotMatchRegularExpression('/^(use |require |<\?php)/m', $s['sdk']);
            }
        }
    }

    public function testNamesOnlyOperationsOfTheApiKeySnapshotAndCurlCallsThatOperation(): void
    {
        foreach (self::$guides['recipes'] as $r) {
            foreach ($r['steps'] as $s) {
                if ($s['operationId'] === null) {
                    continue;
                }
                self::assertArrayHasKey($s['operationId'], self::$ops);
                $op = self::$ops[$s['operationId']];
                self::assertNotNull($s['curl']);
                $first = explode("\n", $s['curl'])[0];
                self::assertStringContainsString("https://gp.useyona.com{$op['path']}", $first);
                if ($op['method'] !== 'GET') {
                    self::assertStringContainsString("-X {$op['method']} ", $first);
                }
            }
        }
    }

    public function testNeverCarriesAKey(): void
    {
        $everything = json_encode(self::$guides, JSON_THROW_ON_ERROR);
        self::assertDoesNotMatchRegularExpression('/sk_(test|live)_[a-z2-7]{16}_/', $everything);
        foreach (self::$guides['recipes'] as $r) {
            foreach ($r['steps'] as $s) {
                if ($s['curl'] !== null) {
                    self::assertStringContainsString('Authorization: Bearer $YONA_API_KEY', $s['curl']);
                }
            }
        }
        // The code reads the key only from YONA_API_KEY.
        foreach (self::$examples as $example) {
            $src = file_get_contents(self::$root . "/examples/{$example}.php");
            self::assertNotFalse($src);
            self::assertDoesNotMatchRegularExpression('/sk_(test|live)_[a-z2-7]{16}_/', $src);
            preg_match_all("/'api_key'\s*=>\s*([^,\]\n]+)/", $src, $matches);
            self::assertNotEmpty($matches[1], "{$example}: no api_key");
            foreach ($matches[1] as $value) {
                self::assertMatchesRegularExpression("/^(\(string\) )?getenv\('YONA_API_KEY'\)\s*$/", $value, $example);
            }
        }
    }

    public function testIsCurrentAFreshExportOfExamplesEqualsTheCommittedFile(): void
    {
        exec(sprintf('cd %s && %s scripts/export_guides.php --check 2>&1', escapeshellarg(self::$root), escapeshellarg(PHP_BINARY)), $output, $status);
        self::assertSame(0, $status, implode("\n", $output));
    }
}

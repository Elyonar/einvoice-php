<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Tests;

use PHPUnit\Framework\TestCase;
use Useyona\EInvoice\ExcludedOperations;
use Useyona\EInvoice\Scripts\Guides;
use Useyona\EInvoice\Scripts\Operations;
use Useyona\EInvoice\Tests\Support\SdkSurface;
use Useyona\EInvoice\Version;

require_once dirname(__DIR__) . '/scripts/export_operations.php';

/**
 * OPERATIONS: guides/operations.json tells the portal's API playground, per API operation, how this SDK
 * calls it. This pins the contract, ties every entry to the method tests/ParityTest.php records for
 * that operation, fails when the committed file is not what `make operations` produces today, and
 * COMPILES every template: rendered with sample values after `setup`, each snippet must pass `php -l`
 * and phpstan at the repository's level against the SDK's own types.
 *
 * @phpstan-type Entry array{module: string, method: string, template: string}
 * @phpstan-type Map array{language: string, package: string, sdkVersion: string, generatedFrom: string, setup: string, literal: array{string: string, body: string}, operations: array<string, Entry>}
 */
final class OperationsTest extends TestCase
{
    private static string $root;

    /** @var Map */
    private static array $map;

    /** @var array<string, array{method: string, path: string, operationId: string}> */
    private static array $ops;

    public static function setUpBeforeClass(): void
    {
        self::$root = dirname(__DIR__);
        $raw = file_get_contents(self::$root . '/guides/operations.json');
        self::assertNotFalse($raw);
        /** @var Map $map */
        $map = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        self::$map = $map;
        self::$ops = [];
        foreach (Operations::snapshot()['operations'] as $op) {
            self::$ops[$op['operationId']] = $op;
        }
        Guides::load();
    }

    public function testHasExactlyTheContractThePortalReads(): void
    {
        $keys = array_keys(self::$map);
        sort($keys);
        self::assertSame(['generatedFrom', 'language', 'literal', 'operations', 'package', 'sdkVersion', 'setup'], $keys);
        self::assertSame('php', self::$map['language']);
        self::assertSame('useyona/einvoice-php', self::$map['package']);
        self::assertSame(Version::VERSION, self::$map['sdkVersion']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{7,40}$/', self::$map['generatedFrom']);
        self::assertSame(['string' => 'single', 'body' => 'php-array'], self::$map['literal']);
        self::assertStringContainsString("getenv('YONA_API_KEY')", self::$map['setup']);
        self::assertStringContainsString('$yona = new EInvoice(', self::$map['setup']);
        self::assertDoesNotMatchRegularExpression('/sk_(test|live)_/', json_encode(self::$map, JSON_THROW_ON_ERROR));
        foreach (self::$map['operations'] as $id => $entry) {
            $entryKeys = array_keys($entry);
            sort($entryKeys);
            self::assertSame(['method', 'module', 'template'], $entryKeys, $id);
            self::assertStringStartsWith('$result = $yona->', $entry['template'], $id);
        }
    }

    public function testCoversExactlyTheOperationsThatAreNotExcluded(): void
    {
        $excluded = [];
        foreach (ExcludedOperations::OPERATIONS as $e) {
            $excluded["{$e['method']} {$e['path']}"] = true;
        }
        $expected = [];
        foreach (self::$ops as $id => $op) {
            if (!isset($excluded["{$op['method']} {$op['path']}"])) {
                $expected[] = $id;
            }
        }
        sort($expected);
        $actual = array_keys(self::$map['operations']);
        sort($actual);
        self::assertSame($expected, $actual);
        self::assertCount(count(self::$ops) - count(ExcludedOperations::OPERATIONS), $actual);
    }

    public function testEveryMethodIsOneTheParitySourceRecordsForThatOperation(): void
    {
        $recorded = [];
        foreach (SdkSurface::recordCalls() as $call) {
            $op = SdkSurface::match(array_values(self::$ops), $call['httpMethod'], $call['path']);
            self::assertNotNull($op, $call['label']);
            $recorded[$op['operationId']][] = $call['label'];
        }
        foreach (self::$map['operations'] as $id => $entry) {
            self::assertContains("{$entry['module']}.{$entry['method']}", $recorded[$id] ?? [], $id);
            self::assertStringContainsString('$yona->' . str_replace('.', '->', $entry['module']) . "->{$entry['method']}(", $entry['template'], $id);
        }
    }

    public function testPathPlaceholdersAreTheSnapshotParameterNames(): void
    {
        foreach (self::$map['operations'] as $id => $entry) {
            preg_match_all('/\{\{path:(\w+)\}\}/', $entry['template'], $m);
            preg_match_all('/\{(\w+)\}/', self::$ops[$id]['path'], $p);
            $inPath = $p[1];
            self::assertSame([], array_values(array_diff($m[1], $inPath)), $id);
            self::assertDoesNotMatchRegularExpression('/\{\{(?!path:\w+\}\}|query\}\}|body\}\})/', $entry['template'], $id);
        }
    }

    public function testIsCurrentAFreshExportEqualsTheCommittedFile(): void
    {
        exec(sprintf('cd %s && %s scripts/export_operations.php --check 2>&1', escapeshellarg(self::$root), escapeshellarg(PHP_BINARY)), $output, $status);
        self::assertSame(0, $status, implode("\n", $output));
    }

    public function testEveryTemplateCompilesAndTypeChecksAfterTheSetup(): void
    {
        $dir = sys_get_temp_dir() . '/einvoice-php-operations';
        self::assertSame([], self::compileErrors(self::$map, $dir));
    }

    public function testTheCompileCheckCatchesABrokenTemplate(): void
    {
        $map = self::$map;
        $map['operations']['createInvoice'] = ['module' => 'invoices', 'method' => 'creat', 'template' => '$result = $yona->invoices->creat({{body}});'];
        $map['operations']['getInvoice'] = ['module' => 'invoices', 'method' => 'get', 'template' => '$result = $yona->invoices->get({{path:id}}'];
        $errors = self::compileErrors($map, sys_get_temp_dir() . '/einvoice-php-operations-broken');
        self::assertCount(2, $errors);
        self::assertStringContainsString('createInvoice', $errors[0] . $errors[1]);
        self::assertStringContainsString('getInvoice', $errors[0] . $errors[1]);
    }

    /**
     * Renders every operation as `setup` + its template (path parameters "id-1", the required query
     * parameters, the example body from the request schema), then runs `php -l` on each file and
     * phpstan (the repository's level) over all of them.
     *
     * @param Map $map
     *
     * @return list<string> one line per failing operation
     */
    private static function compileErrors(array $map, string $dir): array
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0o777, true);
        }
        array_map('unlink', glob($dir . '/*.php') ?: []);
        $errors = [];
        $files = [];
        foreach ($map['operations'] as $id => $entry) {
            $sample = Operations::sample($id);
            $code = $map['setup'] . "\n\n" . Operations::render($entry['template'], $sample['path'], $sample['query'], $sample['body']) . "\n";
            $file = "{$dir}/{$id}.php";
            file_put_contents($file, $code);
            exec(sprintf('%s -l %s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($file)), $out, $status);
            if ($status !== 0) {
                $errors[] = "{$id}: php -l: " . implode(' ', $out);
            } else {
                $files[] = $file;
            }
            $out = [];
        }
        $level = preg_match('/^\s*level:\s*(\w+)/m', (string) file_get_contents(self::$root . '/phpstan.neon'), $m) === 1 ? $m[1] : 'max';
        $neon = "{$dir}/phpstan.neon";
        file_put_contents($neon, "parameters:\n    level: {$level}\n    treatPhpDocTypesAsCertain: false\n    tmpDir: {$dir}/.phpstan\n");
        $command = sprintf(
            'cd %s && %s %s analyse --no-progress --error-format=json --memory-limit=1G -c %s --autoload-file %s %s 2>/dev/null',
            escapeshellarg(self::$root),
            escapeshellarg(PHP_BINARY),
            escapeshellarg(self::$root . '/vendor/bin/phpstan'),
            escapeshellarg($neon),
            escapeshellarg(self::$root . '/vendor/autoload.php'),
            implode(' ', array_map('escapeshellarg', $files)),
        );
        exec($command, $out, $status);
        /** @var array{totals?: array{errors: int, file_errors: int}, files?: array<string, array{messages: list<array{message: string, line: int}>}>, errors?: list<string>}|null $report */
        $report = json_decode(implode("\n", $out), true);
        if (!is_array($report) || !isset($report['totals'])) {
            return [...$errors, 'phpstan did not report: ' . implode("\n", $out)];
        }
        foreach ($report['errors'] ?? [] as $e) {
            $errors[] = "phpstan: {$e}";
        }
        foreach ($report['files'] ?? [] as $file => $result) {
            foreach ($result['messages'] as $msg) {
                $errors[] = basename($file, '.php') . ":{$msg['line']}: {$msg['message']}";
            }
        }
        sort($errors);

        return $errors;
    }
}

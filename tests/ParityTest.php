<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Tests;

use PHPUnit\Framework\TestCase;
use Useyona\EInvoice\EInvoice;
use Useyona\EInvoice\ExcludedOperations;
use Useyona\EInvoice\RequestOptions;
use Useyona\EInvoice\Service\BaseService;
use Useyona\EInvoice\Tests\Support\FakeHttpClient;
use Useyona\EInvoice\Tests\Support\Responses;

/**
 * PARITY: the SDK's methods and the API-key operations of the gateway's OpenAPI
 * (scripts/openapi-api-key-ops.json, copied from einvoice-js by `make sync`) must agree both ways:
 *   - every SDK method calls exactly one snapshot operation;
 *   - every snapshot operation is called by at least one method, or is in ExcludedOperations with a
 *     reason, and every exclusion names a snapshot operation no method calls;
 *   - an Idempotency-Key is generated exactly on the POST/GET routes that accept one;
 *   - the generated models are what the snapshot generates (`make sync-check`, run by CI).
 * The same five assertions as einvoice-js tests/parity.test.ts.
 *
 * @phpstan-type Op array{method: string, path: string, operationId: string, description?: string|null, parameters: list<array{in: string, name: string}>}
 * @phpstan-type Call array{label: string, method: string, path: string, idempotent: bool}
 */
final class ParityTest extends TestCase
{
    /**
     * Routes whose backend controller reads `Idempotency-Key` although the OpenAPI does not say so yet
     * (mirrors einvoice-js: GET /i/v1/invoices/{id}/download is charged per download; the key dedupes the charge).
     */
    private const KEYED_BUT_UNDOCUMENTED = ['GET /i/v1/invoices/{id}/download'];

    /** @var array{count: int, operations: list<Op>} */
    private static array $snapshot;

    /** @var list<Call>|null */
    private static ?array $calls = null;

    public static function setUpBeforeClass(): void
    {
        $raw = file_get_contents(dirname(__DIR__) . '/scripts/openapi-api-key-ops.json');
        if ($raw === false) {
            throw new \RuntimeException('snapshot missing');
        }
        /** @var array{count: int, operations: list<Op>} $snapshot */
        $snapshot = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        self::$snapshot = $snapshot;
    }

    /**
     * The recorded calls, made on first use from inside a test (not in setUpBeforeClass) so the
     * 99 service methods they exercise count towards coverage.
     *
     * @return list<Call>
     */
    private static function calls(): array
    {
        return self::$calls ??= self::recordCalls();
    }

    private static function opKey(string $method, string $path): string
    {
        return "{$method} {$path}";
    }

    /**
     * The snapshot operation a concrete request matches: the template with the fewest placeholders wins.
     *
     * @return Op|null
     */
    private static function match(string $method, string $path): ?array
    {
        $hits = [];
        foreach (self::$snapshot['operations'] as $op) {
            if ($op['method'] !== $method) {
                continue;
            }
            $regex = '#^' . preg_replace('/\\\\\{[^}]+\\\\\}/', '[^/]+', preg_quote($op['path'], '#')) . '$#';
            if (preg_match($regex, $path) === 1) {
                $hits[] = [substr_count($op['path'], '{'), $op];
            }
        }
        usort($hits, fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return $hits[0][1] ?? null;
    }

    /**
     * Every (label, service instance) reachable from the client: the properties typed as a service,
     * and the groups (billing, webhooks) one level down.
     *
     * @return array<string, BaseService>
     */
    private static function services(EInvoice $client): array
    {
        $out = [];
        foreach ((new \ReflectionClass($client))->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getName() === 'http') {
                continue;
            }
            $value = $property->getValue($client);
            if ($value instanceof BaseService) {
                $out[$property->getName()] = $value;
                continue;
            }
            self::assertIsObject($value);
            foreach ((new \ReflectionClass($value))->getProperties(\ReflectionProperty::IS_PUBLIC) as $inner) {
                $service = $inner->getValue($value);
                self::assertInstanceOf(BaseService::class, $service, "{$property->getName()}.{$inner->getName()}");
                $out["{$property->getName()}.{$inner->getName()}"] = $service;
            }
        }

        return $out;
    }

    /** @return list<\ReflectionMethod> */
    private static function publicMethods(BaseService $service): array
    {
        return array_values(array_filter(
            (new \ReflectionClass($service))->getMethods(\ReflectionMethod::IS_PUBLIC),
            fn (\ReflectionMethod $m): bool => !$m->isConstructor() && !$m->isStatic(),
        ));
    }

    /**
     * Positional placeholders from the parameter types: `string` → 'ID1', 'ID2'…; `array` → [];
     * a nullable or `RequestOptions` parameter → null; the first optional parameter ends the list.
     *
     * @return list<mixed>
     */
    private static function placeholderArgs(\ReflectionMethod $method): array
    {
        $args = [];
        $ids = 0;
        foreach ($method->getParameters() as $p) {
            if ($p->isOptional()) {
                break;
            }
            $type = $p->getType();
            self::assertInstanceOf(\ReflectionNamedType::class, $type, "{$method->getName()}({$p->getName()})");
            if ($type->allowsNull() || $type->getName() === RequestOptions::class) {
                $args[] = null;
            } elseif ($type->getName() === 'array') {
                $args[] = [];
            } elseif ($type->getName() === 'string') {
                $args[] = 'ID' . (++$ids);
            } else {
                self::fail("{$method->getName()}: no placeholder for a {$type->getName()} parameter");
            }
        }

        return $args;
    }

    /**
     * Calls every SDK method once with placeholder arguments and records the request it builds.
     *
     * @return list<Call>
     */
    private static function recordCalls(): array
    {
        $calls = [];
        foreach (self::services(new EInvoice(['api_key' => Responses::TEST_KEY])) as $label => $service) {
            foreach (self::publicMethods($service) as $method) {
                $fake = new FakeHttpClient([Responses::ok([])]);
                $target = self::services(Responses::client($fake))[$label];
                $method->invokeArgs($target, self::placeholderArgs($method));
                self::assertSame(1, $fake->calls(), "{$label}.{$method->getName()} made {$fake->calls()} requests");
                $req = $fake->last();
                $calls[] = [
                    'label' => "{$label}.{$method->getName()}",
                    'method' => $req->getMethod(),
                    'path' => $req->getUri()->getPath(),
                    'idempotent' => $req->hasHeader('Idempotency-Key'),
                ];
            }
        }

        return $calls;
    }

    public function testTheSnapshotIsTheApiKeySurface(): void
    {
        self::assertCount(self::$snapshot['count'], self::$snapshot['operations']);
        self::assertGreaterThan(0, self::$snapshot['count']);
    }

    public function testEverySdkMethodCallsExactlyOneSnapshotOperation(): void
    {
        $orphans = [];
        foreach (self::calls() as $call) {
            if (self::match($call['method'], $call['path']) === null) {
                $orphans[] = "{$call['label']} → {$call['method']} {$call['path']}";
            }
        }
        self::assertSame([], $orphans);
    }

    public function testEverySnapshotOperationHasAMethodOrADocumentedExclusion(): void
    {
        $covered = [];
        foreach (self::calls() as $call) {
            $op = self::match($call['method'], $call['path']);
            self::assertNotNull($op);
            $covered[self::opKey($op['method'], $op['path'])] = true;
        }
        $excluded = [];
        foreach (ExcludedOperations::OPERATIONS as $e) {
            $excluded[self::opKey($e['method'], $e['path'])] = true;
        }
        $missing = [];
        $everything = [];
        foreach (self::$snapshot['operations'] as $op) {
            $key = self::opKey($op['method'], $op['path']);
            $everything[$key] = true;
            if (!isset($covered[$key]) && !isset($excluded[$key])) {
                $missing[] = $key;
            }
        }
        self::assertSame([], $missing);
        // An exclusion is a real operation, has a reason, and no method calls it.
        foreach (ExcludedOperations::OPERATIONS as $e) {
            $key = self::opKey($e['method'], $e['path']);
            self::assertArrayHasKey($key, $everything);
            self::assertArrayNotHasKey($key, $covered);
            self::assertGreaterThan(10, strlen($e['reason']));
        }
    }

    /** @param Op $op */
    private static function acceptsIdempotencyKey(array $op): bool
    {
        $description = $op['description'] ?? '';
        foreach ($op['parameters'] as $p) {
            if ($p['in'] === 'header' && preg_match('/idempotency-key/i', $p['name']) === 1) {
                return true;
            }
        }

        return str_contains($description, 'Idempotency-Key') && !str_contains($description, 'Idempotency-Key` is ignored');
    }

    public function testTheSdkGeneratesAnIdempotencyKeyExactlyOnThePostGetRoutesThatAcceptOne(): void
    {
        $expected = [];
        foreach (self::$snapshot['operations'] as $op) {
            if (self::acceptsIdempotencyKey($op) && in_array($op['method'], ['POST', 'GET'], true)) {
                $expected[] = self::opKey($op['method'], $op['path']);
            }
        }
        $expected = array_values(array_unique([...$expected, ...self::KEYED_BUT_UNDOCUMENTED]));
        $actual = [];
        foreach (self::calls() as $call) {
            if ($call['idempotent']) {
                $op = self::match($call['method'], $call['path']);
                self::assertNotNull($op);
                $actual[] = self::opKey($op['method'], $op['path']);
            }
        }
        $actual = array_values(array_unique($actual));
        sort($expected);
        sort($actual);
        self::assertSame($expected, $actual);
    }

    public function testPatchRoutesThatAcceptAKeySendItOnlyWhenTheCallerGivesOne(): void
    {
        $fake = new FakeHttpClient([Responses::ok([])]);
        $yona = Responses::client($fake);
        $yona->buyers->update('b1', ['name' => 'X']);
        self::assertFalse($fake->requests[0]->hasHeader('Idempotency-Key'));
        $yona->buyers->update('b1', ['name' => 'X'], new RequestOptions(idempotencyKey: 'caller-key-1'));
        self::assertSame('caller-key-1', $fake->requests[1]->getHeaderLine('Idempotency-Key'));
        $yona->items->update('i1', [], new RequestOptions(idempotencyKey: 'caller-key-2'));
        self::assertSame('caller-key-2', $fake->requests[2]->getHeaderLine('Idempotency-Key'));
    }

    public function testTheMethodCountIsTheDocumentedSurface(): void
    {
        self::assertCount(99, self::calls(), '99 methods in 23 modules (update CLAUDE.md and README when this changes)');
        $modules = array_unique(array_map(fn (array $c): string => substr($c['label'], 0, (int) strrpos($c['label'], '.')), self::calls()));
        self::assertCount(23, $modules);
    }

    public function testTheGeneratedTypesAreWhatTheSnapshotGenerates(): void
    {
        // `make sync-check` (CI) fetches the pinned einvoice-js commit and compares; here the generator
        // runs only when a local einvoice-js checkout is at hand: EINVOICE_JS_DIR, else a sibling folder
        // (its clone name first; the folder may be called anything).
        $root = dirname(__DIR__);
        $candidates = array_filter([getenv('EINVOICE_JS_DIR') ?: null, dirname($root) . '/einvoice-js', dirname($root) . '/elyonar-sdk']);
        $generator = '';
        foreach ($candidates as $dir) {
            if (is_file($dir . '/scripts/gen-types.mjs')) {
                $generator = $dir . '/scripts/gen-types.mjs';
                break;
            }
        }
        if ($generator === '') {
            self::markTestSkipped('no local einvoice-js checkout: `make sync-check` covers this in CI');
        }
        $command = sprintf(
            'node %s --lang php --snapshot %s --out %s --check 2>&1',
            escapeshellarg($generator),
            escapeshellarg($root . '/scripts/openapi-api-key-ops.json'),
            escapeshellarg($root . '/src/Generated/Types.php'),
        );
        exec($command, $output, $status);
        self::assertSame(0, $status, implode("\n", $output));
    }
}

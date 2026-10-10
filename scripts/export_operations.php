<?php

declare(strict_types=1);

/*
 * The SDK surface → guides/operations.json: per API operation, how this SDK calls it, so the portal's
 * API playground can show the request a developer built as PHP. Not shipped.
 *
 *   make operations          write guides/operations.json
 *   make operations-check    exit 1 unless the committed file equals a fresh export
 *                            (every field but generatedFrom, the commit it was made at)
 *
 * Derived, never hand-written: the method of each operation is the one tests/ParityTest.php records
 * for it (tests/Support/SdkSurface.php calls every SDK method once against a fake HTTP client and
 * matches the request to a snapshot operation). The arguments come from calling the method again
 * with one probe per parameter: a string that lands in a path segment is that `{placeholder}`; an
 * array that lands in the query string is `{{query}}`, in the JSON body `{{body}}`. When several
 * methods call one operation, the one answering the JSON envelope wins over one answering a
 * BinaryResponse (output.getDownloadLink over output.downloadPdf); any other tie fails the export.
 */

namespace Useyona\EInvoice\Scripts;

use Useyona\EInvoice\BinaryResponse;
use Useyona\EInvoice\EInvoice;
use Useyona\EInvoice\ExcludedOperations;
use Useyona\EInvoice\Tests\Support\Responses;
use Useyona\EInvoice\Tests\Support\SdkSurface;
use Useyona\EInvoice\Version;

require_once __DIR__ . '/export_guides.php';

const OPERATIONS_OUT = ROOT . '/guides/operations.json';
const SETUP = <<<'PHP'
    <?php

    require 'vendor/autoload.php';

    use Useyona\EInvoice\EInvoice;

    $yona = new EInvoice(['api_key' => (string) getenv('YONA_API_KEY')]);
    PHP;

/**
 * @phpstan-import-type Arg from SdkSurface
 *
 * @phpstan-type Schema array<string, mixed>
 * @phpstan-type SnapshotOp array{method: string, path: string, operationId: string, parameters: list<array{in: string, name: string, required?: bool, schema?: Schema}>, requestBody?: array{content?: array<string, array{schema?: Schema}>}|null}
 * @phpstan-type Entry array{module: string, method: string, template: string}
 */
final class Operations
{
    private function __construct()
    {
    }

    /** @return array{operations: list<SnapshotOp>} */
    public static function snapshot(): array
    {
        $raw = file_get_contents(ROOT . '/scripts/openapi-api-key-ops.json');
        if ($raw === false) {
            throw new \RuntimeException('scripts/openapi-api-key-ops.json is missing');
        }
        /** @var array{operations: list<SnapshotOp>} $snapshot */
        $snapshot = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        return $snapshot;
    }

    /** @param SnapshotOp $op */
    private static function takesQuery(array $op): bool
    {
        foreach ($op['parameters'] as $p) {
            if ($p['in'] === 'query') {
                return true;
            }
        }

        return false;
    }

    /** @param SnapshotOp $op */
    private static function takesBody(array $op): bool
    {
        return ($op['requestBody'] ?? null) !== null;
    }

    /**
     * @param list<Arg> $roles
     * @param SnapshotOp $op
     */
    private static function template(string $module, string $method, array $roles, array $op): string
    {
        $args = [];
        foreach ($roles as $role) {
            $args[] = match ($role['kind']) {
                'path' => "{{path:{$role['name']}}}",
                'query' => '{{query}}',
                'body' => '{{body}}',
            };
        }
        // An optional trailing query/body the operation does not take is left out.
        while ($roles !== []) {
            $last = $roles[array_key_last($roles)];
            $unused = ($last['kind'] === 'query' && !self::takesQuery($op)) || ($last['kind'] === 'body' && !self::takesBody($op));
            if (!$last['optional'] || !$unused) {
                break;
            }
            array_pop($roles);
            array_pop($args);
        }
        foreach ($roles as $role) {
            if ($role['kind'] === 'path' && !str_contains($op['path'], "{{$role['name']}}")) {
                throw new \RuntimeException("{$module}.{$method}: {$role['name']} is not a path parameter of {$op['operationId']}");
            }
        }
        $chain = implode('->', explode('.', $module));

        return "\$result = \$yona->{$chain}->{$method}(" . implode(', ', $args) . ');';
    }

    /** @return array<string, mixed> */
    public static function build(string $sha): array
    {
        $snapshot = self::snapshot();
        $excluded = [];
        foreach (ExcludedOperations::OPERATIONS as $e) {
            $excluded["{$e['method']} {$e['path']}"] = true;
        }
        /** @var array<string, list<array{module: string, method: string}>> $candidates */
        $candidates = [];
        foreach (SdkSurface::recordCalls() as $call) {
            $op = SdkSurface::match($snapshot['operations'], $call['httpMethod'], $call['path']);
            if ($op === null) {
                throw new \RuntimeException("{$call['label']} calls no snapshot operation (see tests/ParityTest.php)");
            }
            $candidates[$op['operationId']][] = ['module' => $call['module'], 'method' => $call['method']];
        }
        $services = SdkSurface::services(new EInvoice(['api_key' => Responses::TEST_KEY]));
        $operations = [];
        foreach ($snapshot['operations'] as $op) {
            $id = $op['operationId'];
            if (isset($excluded["{$op['method']} {$op['path']}"])) {
                continue;
            }
            $found = $candidates[$id] ?? [];
            if (count($found) > 1) {
                $found = array_values(array_filter($found, function (array $c) use ($services): bool {
                    $type = (new \ReflectionMethod($services[$c['module']], $c['method']))->getReturnType();

                    return !($type instanceof \ReflectionNamedType && $type->getName() === BinaryResponse::class);
                }));
            }
            if (count($found) !== 1) {
                throw new \RuntimeException("{$id}: " . count($found) . ' candidate methods (expected exactly one; see tests/ParityTest.php)');
            }
            ['module' => $module, 'method' => $method] = $found[0];
            $roles = SdkSurface::argumentRoles($module, new \ReflectionMethod($services[$module], $method), $op['path']);
            $operations[$id] = ['module' => $module, 'method' => $method, 'template' => self::template($module, $method, $roles, $op)];
        }
        ksort($operations);
        $composer = json_decode((string) file_get_contents(ROOT . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);

        return [
            'language' => 'php',
            'package' => is_array($composer) ? $composer['name'] : '',
            'sdkVersion' => Version::VERSION,
            'generatedFrom' => $sha,
            'setup' => SETUP,
            'literal' => ['string' => 'single', 'body' => 'php-array'],
            'operations' => $operations,
        ];
    }

    // ── rendering a template with literals (what the portal does; used by the compile check) ──

    /** A PHP literal for a JSON value: single-quoted strings, short arrays, `[]` for an empty object. */
    public static function phpLiteral(mixed $value, int $indent = 0): string
    {
        if ($value instanceof \stdClass) {
            $value = (array) $value;
        }
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            return var_export($value, true);
        }
        if (is_string($value)) {
            return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
        }
        if (!is_array($value)) {
            throw new \RuntimeException('not a JSON value: ' . get_debug_type($value));
        }
        if ($value === []) {
            return '[]';
        }
        $pad = str_repeat('    ', $indent + 1);
        $lines = [];
        foreach ($value as $k => $v) {
            $lines[] = $pad . (array_is_list($value) ? '' : self::phpLiteral((string) $k) . ' => ') . self::phpLiteral($v, $indent + 1) . ',';
        }

        return "[\n" . implode("\n", $lines) . "\n" . str_repeat('    ', $indent) . ']';
    }

    /**
     * The template with literals in place of its placeholders.
     *
     * @param array<string, string> $path  parameter name → value
     * @param mixed                 $query the query object
     * @param mixed                 $body  the body
     */
    public static function render(string $template, array $path, mixed $query, mixed $body): string
    {
        $out = (string) preg_replace_callback('/\{\{path:(\w+)\}\}/', function (array $m) use ($path): string {
            if (!isset($path[$m[1]])) {
                throw new \RuntimeException("no value for path parameter {$m[1]}");
            }

            return self::phpLiteral($path[$m[1]]);
        }, $template);

        return str_replace(['{{query}}', '{{body}}'], [self::phpLiteral($query), self::phpLiteral($body)], $out);
    }

    /**
     * Sample values for an operation: every path parameter "id-1", the query of its required
     * parameters (each its schema example), the example body built from the request schema.
     *
     * @return array{path: array<string, string>, query: array<string, mixed>, body: mixed}
     */
    public static function sample(string $operationId): array
    {
        foreach (self::snapshot()['operations'] as $op) {
            if ($op['operationId'] !== $operationId) {
                continue;
            }
            $path = [];
            $query = [];
            foreach ($op['parameters'] as $p) {
                if ($p['in'] === 'path') {
                    $path[$p['name']] = 'id-1';
                } elseif ($p['in'] === 'query' && ($p['required'] ?? false)) {
                    $query[$p['name']] = Guides::exampleOf($p['schema'] ?? null) ?? 'string';
                }
            }
            $schema = $op['requestBody']['content']['application/json']['schema'] ?? null;

            return ['path' => $path, 'query' => $query, 'body' => $schema === null ? [] : Guides::exampleOf($schema)];
        }
        throw new \RuntimeException("{$operationId} is not in the snapshot");
    }

    /** @param array<string, mixed> $operations */
    public static function serialise(array $operations): string
    {
        $json = json_encode($operations, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return preg_replace_callback('/^(?: {4})+/m', fn (array $m): string => str_repeat('  ', intdiv(strlen($m[0]), 4)), $json) . "\n";
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    Guides::load();
    if (in_array('--check', $argv ?? [], true)) {
        $raw = file_get_contents(OPERATIONS_OUT);
        if ($raw === false) {
            fwrite(STDERR, "guides/operations.json is missing: run `make operations`\n");
            exit(1);
        }
        /** @var array<string, mixed> $committed */
        $committed = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $sha = $committed['generatedFrom'] ?? '';
        if (Operations::serialise(Operations::build(is_string($sha) ? $sha : '')) !== Operations::serialise($committed)) {
            fwrite(STDERR, "guides/operations.json is stale: run `make operations` and commit it\n");
            exit(1);
        }
        echo "guides/operations.json is current\n";
    } else {
        $sha = trim((string) shell_exec('cd ' . escapeshellarg(ROOT) . ' && git rev-parse --short HEAD 2>/dev/null'));
        if ($sha === '') {
            $sha = '0000000';
        }
        $operations = Operations::build($sha);
        file_put_contents(OPERATIONS_OUT, Operations::serialise($operations));
        /** @var array<string, mixed> $ops */
        $ops = $operations['operations'];
        echo 'guides/operations.json: ', count($ops), " operations (from {$sha})\n";
    }
}

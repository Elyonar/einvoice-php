<?php

declare(strict_types=1);

/*
 * examples/*.php → guides/guides.json, the developer guides the portal shows (ruling R78). Not shipped.
 *
 *   make guides          write guides/guides.json
 *   make guides-check    exit 1 unless the committed file equals a fresh export
 *                        (every field but generatedFrom, the commit it was made at)
 *
 * An example is annotated with line comments (the same directives as einvoice-js):
 *   // @recipe <id> <Title>       header: id = the file name
 *   // @summary <text>            header
 *   // @step <id> <Title>         a step: its code runs until the next @step
 *   // @text <guidance>           optional, after @step (may repeat; joined with a space)
 *   // @op <operationId>          optional: the API operation of the step (scripts/openapi-api-key-ops.json)
 *   // @quickstart                optional: the step is part of the README-sized quick start
 * The header before the first @step (`<?php`, `declare`, `require`, `use`) is hoisted into the first
 * step's code. The curl of a step is generated from its @op: method, path with its {placeholders}, the
 * required query parameters, and an example body built from the request schema. It never carries a
 * real key: $YONA_API_KEY.
 */

namespace Useyona\EInvoice\Scripts;

use Useyona\EInvoice\Version;

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/run_examples.php';

const HOST = 'https://gp.useyona.com';
const OUT = ROOT . '/guides/guides.json';
const INSTALL = 'composer require useyona/einvoice-php';
const DIRECTIVE = '~^\s*//\s*@(recipe|summary|step|text|op|quickstart)\b\s*(.*)$~';

/**
 * @phpstan-type Schema array<string, mixed>
 * @phpstan-type Op array{method: string, path: string, operationId: string, description?: string|null, parameters: list<array{in: string, name: string, required?: bool, schema?: Schema}>, requestBody?: array{content?: array<string, array{schema?: Schema}>}}
 */
final class Guides
{
    /** @var array{operations: list<Op>, schemas: array<string, Schema>} */
    private static array $snapshot;

    /** @var array<string, Op> */
    private static array $opsById = [];

    private function __construct()
    {
    }

    public static function load(): void
    {
        $raw = file_get_contents(ROOT . '/scripts/openapi-api-key-ops.json');
        if ($raw === false) {
            throw new \RuntimeException('scripts/openapi-api-key-ops.json is missing');
        }
        /** @var array{operations: list<Op>, schemas: array<string, Schema>} $snapshot */
        $snapshot = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        self::$snapshot = $snapshot;
        foreach ($snapshot['operations'] as $op) {
            self::$opsById[$op['operationId']] = $op;
        }
    }

    // ── example bodies from the schema ──

    /** @return Schema */
    private static function resolveRef(string $ref): array
    {
        $name = str_replace('#/components/schemas/', '', $ref);
        if (!isset(self::$snapshot['schemas'][$name])) {
            throw new \RuntimeException("schema {$name} is not in the snapshot");
        }

        return self::$snapshot['schemas'][$name];
    }

    /** @param Schema|null $schema */
    public static function exampleOf(?array $schema, int $depth = 0): mixed
    {
        if ($schema === null || $schema === [] || $depth > 8) {
            return null;
        }
        if (isset($schema['$ref']) && is_string($schema['$ref'])) {
            return self::exampleOf(self::resolveRef($schema['$ref']), $depth + 1);
        }
        if (array_key_exists('example', $schema)) {
            return $schema['example'];
        }
        if (array_key_exists('default', $schema)) {
            return $schema['default'];
        }
        if (isset($schema['enum']) && is_array($schema['enum']) && $schema['enum'] !== []) {
            return $schema['enum'][array_key_first($schema['enum'])];
        }
        foreach (['oneOf', 'anyOf'] as $union) {
            if (isset($schema[$union]) && is_array($schema[$union]) && $schema[$union] !== []) {
                /** @var Schema $first */
                $first = $schema[$union][array_key_first($schema[$union])];

                return self::exampleOf($first, $depth + 1);
            }
        }
        if (isset($schema['allOf']) && is_array($schema['allOf']) && $schema['allOf'] !== []) {
            /** @var list<Schema> $all */
            $all = array_values($schema['allOf']);
            $parts = array_map(fn (array $s): mixed => self::exampleOf($s, $depth + 1), $all);
            $objects = array_filter($parts, fn (mixed $p): bool => is_array($p) || $p instanceof \stdClass);
            if (count($objects) === count($parts)) {
                $merged = [];
                foreach ($parts as $p) {
                    $merged = [...$merged, ...(array) $p];
                }

                return $merged === [] ? new \stdClass() : $merged;
            }

            return $parts[0];
        }
        $type = $schema['type'] ?? null;
        if ($type === 'object' || ($type === null && isset($schema['properties']))) {
            $out = [];
            /** @var array<string, Schema> $properties */
            $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
            /** @var list<string> $required */
            $required = is_array($schema['required'] ?? null) ? $schema['required'] : [];
            foreach ($required as $name) {
                $out[$name] = self::exampleOf($properties[$name] ?? null, $depth + 1);
            }

            return $out === [] ? new \stdClass() : $out;
        }
        if ($type === 'array') {
            /** @var Schema|null $items */
            $items = is_array($schema['items'] ?? null) ? $schema['items'] : null;

            return [self::exampleOf($items, $depth + 1)];
        }
        if ($type === 'integer' || $type === 'number') {
            return $schema['minimum'] ?? 1;
        }
        if ($type === 'boolean') {
            return false;
        }
        if ($type === 'string') {
            return match ($schema['format'] ?? null) {
                'uuid' => '00000000-0000-7000-8000-000000000000',
                'date' => '2026-10-05',
                'date-time' => '2026-10-05T12:00:00Z',
                default => 'string',
            };
        }

        return null;
    }

    /**
     * Mirrors tests/ParityTest.php: the route reads an Idempotency-Key.
     *
     * @param Op $op
     */
    private static function acceptsIdempotencyKey(array $op): bool
    {
        if (!in_array($op['method'], ['POST', 'GET'], true)) {
            return false;
        }
        foreach ($op['parameters'] as $p) {
            if ($p['in'] === 'header' && preg_match('/idempotency-key/i', $p['name']) === 1) {
                return true;
            }
        }
        $description = $op['description'] ?? '';

        return str_contains($description, 'Idempotency-Key') && !str_contains($description, 'Idempotency-Key` is ignored');
    }

    private static function shellQuote(string $s): string
    {
        return "'" . str_replace("'", "'\\''", $s) . "'";
    }

    /** JSON with two-space indentation (as every SDK's guides render it). */
    private static function prettyJson(mixed $value): string
    {
        $json = json_encode($value, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return (string) preg_replace_callback('/^(?: {4})+/m', fn (array $m): string => str_repeat('  ', intdiv(strlen($m[0]), 4)), $json);
    }

    public static function curlFor(string $operationId): string
    {
        $op = self::$opsById[$operationId] ?? null;
        if ($op === null) {
            throw new \RuntimeException("@op {$operationId} is not an API-key operation of the snapshot");
        }
        $query = [];
        foreach ($op['parameters'] as $p) {
            if ($p['in'] === 'query' && ($p['required'] ?? false)) {
                $example = self::exampleOf($p['schema'] ?? null);
                $value = is_scalar($example) ? (string) $example : $p['name'];
                $query[] = rawurlencode($p['name']) . '=' . rawurlencode($value === '' ? $p['name'] : $value);
            }
        }
        $url = HOST . $op['path'] . ($query !== [] ? '?' . implode('&', $query) : '');
        $lines = [$op['method'] === 'GET' ? "curl \"{$url}\"" : "curl -X {$op['method']} \"{$url}\"", '  -H "Authorization: Bearer $YONA_API_KEY"'];
        $bodySchema = $op['requestBody']['content']['application/json']['schema'] ?? null;
        if (self::acceptsIdempotencyKey($op)) {
            $lines[] = '  -H "Idempotency-Key: $(uuidgen)"';
        }
        if ($bodySchema !== null) {
            $lines[] = '  -H "Content-Type: application/json"';
            $lines[] = '  -d ' . self::shellQuote(self::prettyJson(self::exampleOf($bodySchema)));
        }

        return implode(" \\\n", $lines);
    }

    // ── parsing ──

    /**
     * @param list<string> $lines
     *
     * @return list<string>
     */
    private static function trimBlank(array $lines): array
    {
        $a = 0;
        $b = count($lines);
        while ($a < $b && trim($lines[$a]) === '') {
            ++$a;
        }
        while ($b > $a && trim($lines[$b - 1]) === '') {
            --$b;
        }

        return array_slice($lines, $a, $b - $a);
    }

    /**
     * @return array{recipe: array{id: string, title: string, summary: string}, imports: string, steps: list<array{id: string, title: string, text: list<string>, op: string|null, quickstart: bool, code: list<string>}>}
     */
    public static function parseExample(string $id, string $source): array
    {
        $recipe = ['id' => null, 'title' => null, 'summary' => null];
        $header = [];
        $steps = [];
        $current = null;
        foreach (explode("\n", str_replace("\r\n", "\n", $source)) as $line) {
            if (preg_match(DIRECTIVE, $line, $m) === 1) {
                [, $kind, $rest] = $m;
                $value = trim($rest);
                if ($kind === 'recipe' || $kind === 'summary') {
                    if ($current !== null) {
                        throw new \RuntimeException("{$id}: @{$kind} must come before the first @step");
                    }
                    if ($kind === 'recipe') {
                        $parts = preg_split('/\s+/', $value, 2) ?: [''];
                        $recipe['id'] = $parts[0];
                        $recipe['title'] = trim($parts[1] ?? '');
                    } else {
                        $recipe['summary'] = $value;
                    }
                } elseif ($kind === 'step') {
                    $parts = preg_split('/\s+/', $value, 2) ?: [''];
                    if ($current !== null) {
                        $steps[] = $current;
                    }
                    $current = ['id' => $parts[0], 'title' => trim($parts[1] ?? ''), 'text' => [], 'op' => null, 'quickstart' => false, 'code' => [], 'started' => false];
                } else {
                    if ($current === null) {
                        throw new \RuntimeException("{$id}: @{$kind} outside a step");
                    }
                    if ($current['started']) {
                        throw new \RuntimeException("{$id}/{$current['id']}: @{$kind} must directly follow @step");
                    }
                    if ($kind === 'text') {
                        $current['text'][] = $value;
                    } elseif ($kind === 'op') {
                        $current['op'] = $value;
                    } else {
                        $current['quickstart'] = true;
                    }
                }
                continue;
            }
            if ($current !== null) {
                $current['started'] = true;
                $current['code'][] = $line;
            } else {
                $header[] = $line;
            }
        }
        if ($current !== null) {
            $steps[] = $current;
        }
        if ($recipe['id'] !== $id) {
            throw new \RuntimeException("{$id}: @recipe id must be the file name (got " . ($recipe['id'] ?? 'nothing') . ')');
        }
        if ($recipe['title'] === null || $recipe['title'] === '' || $recipe['summary'] === null || $recipe['summary'] === '') {
            throw new \RuntimeException("{$id}: @recipe needs a title and @summary");
        }
        if ($steps === []) {
            throw new \RuntimeException("{$id}: no @step");
        }
        $ids = [];
        $out = [];
        foreach ($steps as $s) {
            if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $s['id']) !== 1 || $s['title'] === '') {
                throw new \RuntimeException("{$id}: a @step needs a kebab-case id and a title");
            }
            if (isset($ids[$s['id']])) {
                throw new \RuntimeException("{$id}: duplicate step id {$s['id']}");
            }
            $ids[$s['id']] = true;
            if ($s['op'] !== null && !isset(self::$opsById[$s['op']])) {
                throw new \RuntimeException("{$id}/{$s['id']}: @op {$s['op']} is not in the snapshot");
            }
            if (self::trimBlank($s['code']) === []) {
                throw new \RuntimeException("{$id}/{$s['id']}: the step has no code");
            }
            unset($s['started']);
            $out[] = $s;
        }

        return [
            'recipe' => ['id' => $recipe['id'], 'title' => $recipe['title'], 'summary' => $recipe['summary']],
            'imports' => (string) preg_replace("/\n{3,}/", "\n\n", implode("\n", self::trimBlank($header))),
            'steps' => $out,
        ];
    }

    /** Drops the `use` imports a code string does not use; keeps `<?php`, `declare` and `require`. */
    private static function pruneImports(string $imports, string $code): string
    {
        $kept = [];
        foreach (explode("\n", $imports) as $line) {
            if (preg_match('/^use\s+(?:function\s+|const\s+)?([^;\s]+)(?:\s+as\s+(\w+))?;/', $line, $m) === 1) {
                $name = $m[2] ?? substr($m[1], (int) strrpos('\\' . $m[1], '\\'));
                if (preg_match('/\b' . preg_quote($name, '/') . '\b/', $code) !== 1) {
                    continue;
                }
            }
            $kept[] = $line;
        }

        return (string) preg_replace("/\n{3,}/", "\n\n", trim(implode("\n", $kept)));
    }

    /** @return array<string, mixed> */
    public static function build(string $sha): array
    {
        $recipes = [];
        $quick = [];
        foreach (exampleIds() as $id) {
            $source = file_get_contents(ROOT . "/examples/{$id}.php");
            if ($source === false) {
                throw new \RuntimeException("examples/{$id}.php is unreadable");
            }
            ['recipe' => $recipe, 'imports' => $imports, 'steps' => $steps] = self::parseExample($id, $source);
            $rendered = [];
            foreach ($steps as $i => $s) {
                $code = implode("\n", self::trimBlank($s['code']));
                if ($s['quickstart']) {
                    $quick[] = [$imports, $code];
                }
                $rendered[] = [
                    'id' => $s['id'],
                    'title' => $s['title'],
                    'text' => $s['text'] !== [] ? implode(' ', $s['text']) : null,
                    'sdk' => $i === 0 && $imports !== '' ? "{$imports}\n\n{$code}" : $code,
                    'curl' => $s['op'] !== null ? self::curlFor($s['op']) : null,
                    'operationId' => $s['op'],
                ];
            }
            $recipes[] = ['id' => $recipe['id'], 'title' => $recipe['title'], 'summary' => $recipe['summary'], 'steps' => $rendered];
        }
        if ($quick === []) {
            throw new \RuntimeException('no step is marked @quickstart');
        }
        if (count(array_unique(array_map(fn (array $q): string => $q[0], $quick))) > 1) {
            throw new \RuntimeException('@quickstart steps must come from one example');
        }
        $quickCode = implode("\n\n", array_map(fn (array $q): string => $q[1], $quick));

        return [
            'sdkVersion' => Version::VERSION,
            'generatedFrom' => $sha,
            'install' => INSTALL,
            'quickStart' => self::pruneImports($quick[0][0], $quickCode) . "\n\n" . $quickCode,
            'recipes' => $recipes,
        ];
    }

    /** @param array<string, mixed> $guides */
    public static function serialise(array $guides): string
    {
        return self::prettyJson($guides) . "\n";
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    Guides::load();
    if (in_array('--check', $argv ?? [], true)) {
        $raw = file_get_contents(OUT);
        if ($raw === false) {
            fwrite(STDERR, "guides/guides.json is missing: run `make guides`\n");
            exit(1);
        }
        /** @var array<string, mixed> $committed */
        $committed = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $sha = $committed['generatedFrom'] ?? '';
        $fresh = Guides::build(is_string($sha) ? $sha : '');
        if (Guides::serialise($fresh) !== Guides::serialise($committed)) {
            fwrite(STDERR, "guides/guides.json is stale: run `make guides` and commit it\n");
            exit(1);
        }
        echo "guides/guides.json is current\n";
    } else {
        $sha = trim((string) shell_exec('cd ' . escapeshellarg(ROOT) . ' && git rev-parse --short HEAD 2>/dev/null'));
        if ($sha === '') {
            $sha = '0000000';
        }
        if (!is_dir(dirname(OUT))) {
            mkdir(dirname(OUT), 0o777, true);
        }
        $guides = Guides::build($sha);
        file_put_contents(OUT, Guides::serialise($guides));
        /** @var list<array{steps: list<mixed>}> $recipes */
        $recipes = $guides['recipes'];
        $steps = array_sum(array_map(fn (array $r): int => count($r['steps']), $recipes));
        echo 'guides/guides.json: ', count($recipes), " recipes, {$steps} steps (from {$sha})\n";
    }
}

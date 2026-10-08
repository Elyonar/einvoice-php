<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Tests\Support;

use Useyona\EInvoice\EInvoice;
use Useyona\EInvoice\RequestOptions;
use Useyona\EInvoice\Service\BaseService;

/**
 * The SDK's method surface, read by reflection and by calling every method once against a
 * {@see FakeHttpClient}: the one source both tests/ParityTest.php (method ↔ operation parity) and
 * scripts/export_operations.php (guides/operations.json) are built from, so the two cannot disagree.
 *
 * @phpstan-type Call array{label: string, module: string, method: string, httpMethod: string, path: string, idempotent: bool}
 * @phpstan-type Arg array{kind: 'path', name: string, optional: bool}|array{kind: 'query'|'body', optional: bool}
 */
final class SdkSurface
{
    private function __construct()
    {
    }

    /**
     * Every (module, service instance) reachable from the client: the properties typed as a service,
     * and the groups (billing, webhooks) one level down. Modules are dotted: `invoices`, `billing.accounts`.
     *
     * @return array<string, BaseService>
     */
    public static function services(EInvoice $client): array
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
            if (!is_object($value)) {
                throw new \LogicException("EInvoice::\${$property->getName()} is neither a service nor a group");
            }
            foreach ((new \ReflectionClass($value))->getProperties(\ReflectionProperty::IS_PUBLIC) as $inner) {
                $service = $inner->getValue($value);
                if (!$service instanceof BaseService) {
                    throw new \LogicException("{$property->getName()}.{$inner->getName()} is not a service");
                }
                $out["{$property->getName()}.{$inner->getName()}"] = $service;
            }
        }

        return $out;
    }

    /** @return list<\ReflectionMethod> */
    public static function publicMethods(BaseService $service): array
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
    public static function placeholderArgs(\ReflectionMethod $method): array
    {
        $args = [];
        $ids = 0;
        foreach ($method->getParameters() as $p) {
            if ($p->isOptional()) {
                break;
            }
            $type = $p->getType();
            if (!$type instanceof \ReflectionNamedType) {
                throw new \LogicException("{$method->getName()}(\${$p->getName()}) has no single named type");
            }
            if ($type->allowsNull() || $type->getName() === RequestOptions::class) {
                $args[] = null;
            } elseif ($type->getName() === 'array') {
                $args[] = [];
            } elseif ($type->getName() === 'string') {
                $args[] = 'ID' . (++$ids);
            } else {
                throw new \LogicException("{$method->getName()}: no placeholder for a {$type->getName()} parameter");
            }
        }

        return $args;
    }

    /**
     * Calls every SDK method once with placeholder arguments and records the request it builds.
     *
     * @return list<Call>
     */
    public static function recordCalls(): array
    {
        $calls = [];
        foreach (self::services(new EInvoice(['api_key' => Responses::TEST_KEY])) as $module => $service) {
            foreach (self::publicMethods($service) as $method) {
                $fake = new FakeHttpClient([Responses::ok([])]);
                $target = self::services(Responses::client($fake))[$module];
                $method->invokeArgs($target, self::placeholderArgs($method));
                if ($fake->calls() !== 1) {
                    throw new \LogicException("{$module}.{$method->getName()} made {$fake->calls()} requests");
                }
                $req = $fake->last();
                $calls[] = [
                    'label' => "{$module}.{$method->getName()}",
                    'module' => $module,
                    'method' => $method->getName(),
                    'httpMethod' => $req->getMethod(),
                    'path' => $req->getUri()->getPath(),
                    'idempotent' => $req->hasHeader('Idempotency-Key'),
                ];
            }
        }

        return $calls;
    }

    /**
     * The snapshot operation a concrete request matches: the template with the fewest placeholders wins.
     *
     * @template T of array{method: string, path: string}
     *
     * @param list<T> $operations
     *
     * @return T|null
     */
    public static function match(array $operations, string $method, string $path): ?array
    {
        $hits = [];
        foreach ($operations as $op) {
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
     * What each parameter of a method (but the trailing `RequestOptions`) carries into the request,
     * found by calling it with a distinct probe per parameter: a string that lands in a path segment
     * is that `{placeholder}` of the operation's path; an array whose probe key lands in the query
     * string is the query, in the JSON body the body.
     *
     * @param string $opPath the matched operation's path template
     *
     * @return list<Arg>
     */
    public static function argumentRoles(string $module, \ReflectionMethod $method, string $opPath): array
    {
        $args = [];
        $probes = [];
        foreach ($method->getParameters() as $i => $p) {
            $type = $p->getType();
            if (!$type instanceof \ReflectionNamedType) {
                throw new \LogicException("{$module}.{$method->getName()}(\${$p->getName()}) has no single named type");
            }
            if ($type->getName() === RequestOptions::class) {
                break;
            }
            if ($type->getName() === 'string') {
                $args[] = "zprobe{$i}z";
            } elseif ($type->getName() === 'array') {
                $args[] = ["zprobe{$i}z" => 'v'];
            } else {
                throw new \LogicException("{$module}.{$method->getName()}: no probe for a {$type->getName()} parameter");
            }
            $probes[] = [$i, $type->getName(), $p->isOptional()];
        }
        $fake = new FakeHttpClient([Responses::ok([])]);
        $method->invokeArgs(self::services(Responses::client($fake))[$module], $args);
        $req = $fake->last();
        $segments = explode('/', $req->getUri()->getPath());
        $template = explode('/', $opPath);
        $query = $req->getUri()->getQuery();
        $body = (string) $req->getBody();
        $roles = [];
        foreach ($probes as [$i, $type, $optional]) {
            $probe = "zprobe{$i}z";
            if ($type === 'string') {
                $at = array_search($probe, $segments, true);
                if ($at === false || !isset($template[$at]) || preg_match('/^\{(\w+)\}$/', $template[$at], $m) !== 1) {
                    throw new \LogicException("{$module}.{$method->getName()}: string parameter {$i} is not a path parameter of {$opPath}");
                }
                $roles[] = ['kind' => 'path', 'name' => $m[1], 'optional' => $optional];
            } elseif (str_contains($query, $probe)) {
                $roles[] = ['kind' => 'query', 'optional' => $optional];
            } elseif (str_contains($body, $probe)) {
                $roles[] = ['kind' => 'body', 'optional' => $optional];
            } else {
                throw new \LogicException("{$module}.{$method->getName()}: array parameter {$i} reaches neither the query nor the body");
            }
        }

        return $roles;
    }
}

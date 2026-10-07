<?php

declare(strict_types=1);

/*
 * Runs examples/*.php as an integrator would: the key comes only from YONA_API_KEY. Not shipped. The key is passed through the child's environment and never printed.
 *
 *   php scripts/run_examples.php            (YONA_API_KEY, optional YONA_BASE_URL) every example
 *   php scripts/run_examples.php webhooks   one example
 *
 * The examples are the code the guides show: they pass only the API key, so they talk to the default
 * host. To run them against another gateway without changing a line of them, YONA_BASE_URL makes the
 * child process repoint the SDK's default host before the example runs, through
 * `-d auto_prepend_file=scripts/examples-preload.php` (the SDK itself never reads the variable).
 */

namespace Useyona\EInvoice\Scripts;

const ROOT = __DIR__ . '/..';

/** The example ids, in the order the guides list them. */
const EXAMPLE_ORDER = ['first-invoice', 'webhooks', 'errors-and-retries', 'sandbox-and-live', 'received-invoices'];

/** @return list<string> */
function exampleIds(): array
{
    $files = glob(ROOT . '/examples/*.php') ?: [];
    $present = array_map(fn (string $f): string => basename($f, '.php'), $files);
    sort($present);
    $ordered = array_values(array_filter(EXAMPLE_ORDER, fn (string $id): bool => in_array($id, $present, true)));
    $rest = array_values(array_filter($present, fn (string $id): bool => !in_array($id, EXAMPLE_ORDER, true)));

    return [...$ordered, ...$rest];
}

/**
 * Runs one example; never throws.
 *
 * @return array{id: string, ok: bool, ms: int, output: string}
 */
function runExample(string $id, string $apiKey, ?string $baseUrl = null, int $timeoutSeconds = 180): array
{
    $env = array_filter(getenv(), 'is_string');
    $env['YONA_API_KEY'] = $apiKey;
    if ($baseUrl !== null && $baseUrl !== '') {
        $env['YONA_BASE_URL'] = $baseUrl;
    } else {
        unset($env['YONA_BASE_URL']);
    }
    $command = [PHP_BINARY, '-d', 'auto_prepend_file=' . ROOT . '/scripts/examples-preload.php', ROOT . "/examples/{$id}.php"];
    $started = hrtime(true);
    $output = '';
    $ok = false;
    $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, realpath(ROOT) ?: ROOT, $env);
    if (is_resource($process)) {
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $deadline = $started + $timeoutSeconds * 1_000_000_000;
        while (true) {
            $output .= (string) stream_get_contents($pipes[1]);
            $output .= (string) stream_get_contents($pipes[2]);
            $status = proc_get_status($process);
            if (!$status['running']) {
                $ok = $status['exitcode'] === 0;
                break;
            }
            if (hrtime(true) > $deadline) {
                proc_terminate($process, 9);
                $output .= "\nkilled after {$timeoutSeconds}s";
                break;
            }
            usleep(50_000);
        }
        $output .= (string) stream_get_contents($pipes[1]);
        $output .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
    } else {
        $output = 'could not start php';
    }
    // Defence in depth: an example never prints the key, but scrub it if one ever did.
    if ($apiKey !== '') {
        $output = str_replace($apiKey, 'sk_***', $output);
    }

    return ['id' => $id, 'ok' => $ok, 'ms' => (int) ((hrtime(true) - $started) / 1_000_000), 'output' => $output];
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $key = (string) getenv('YONA_API_KEY');
    if ($key === '') {
        fwrite(STDERR, "Set YONA_API_KEY\n");
        exit(2);
    }
    $ids = array_slice($argv ?? [], 1) ?: exampleIds();
    $failed = 0;
    foreach ($ids as $id) {
        $r = runExample($id, $key, getenv('YONA_BASE_URL') ?: null);
        echo $r['ok'] ? 'OK  ' : 'FAIL', " example {$id} ({$r['ms']} ms)\n";
        if (!$r['ok']) {
            ++$failed;
            $lines = array_slice(explode("\n", trim($r['output'])), -20);
            echo implode("\n", array_map(fn (string $l): string => "     {$l}", $lines)), "\n";
        }
    }
    exit($failed > 0 ? 1 : 0);
}

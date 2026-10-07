<?php

declare(strict_types=1);

/*
 * Prepended (`php -d auto_prepend_file=scripts/examples-preload.php`) by scripts/run_examples.php. Not shipped.
 *
 * The examples are the code the guides show: they pass only the API key, so they talk to the
 * default host https://gp.useyona.com. To run them against another gateway (a local stack, or an
 * explicit YONA_BASE_URL) without changing a line of them, this defines the constant the transport
 * consults for its default host (HttpClient::defaultBaseUrl()). The SDK itself never reads an
 * environment variable: this file is the only place YONA_BASE_URL is read.
 */
$target = rtrim((string) getenv('YONA_BASE_URL'), '/');
if ($target !== '' && !defined('USEYONA_EINVOICE_BASE_URL_OVERRIDE')) {
    define('USEYONA_EINVOICE_BASE_URL_OVERRIDE', $target);
}
unset($target);

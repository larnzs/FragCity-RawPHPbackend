<?php
declare(strict_types=1);

/**
 * CLI-only read-only test for the signed Server Browser API.
 * Run from the website repository root after configuring settings/settings.json.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once dirname(__DIR__) . '/class/ServerBrowserRepository.php';

try {
    $snapshot = (new ServerBrowserRepository())->getSnapshot();
    $servers = $snapshot['servers'];

    echo "PASS: signed Server Browser API request succeeded.\n";
    echo 'Generated at: ' . ($snapshot['generated_at'] ?? 'unknown') . "\n";
    echo 'Cache age: ' . ($snapshot['cache_age_seconds'] === null
        ? 'unknown'
        : $snapshot['cache_age_seconds'] . ' seconds') . "\n";
    echo 'Cache stale: ' . ($snapshot['stale'] ? 'yes' : 'no') . "\n";
    echo 'Servers: ' . count($servers) . "\n";

    foreach ($servers as $server) {
        if (!is_array($server)) {
            continue;
        }
        $address = (string)($server['host'] ?? '') . ':' . (string)($server['game_port'] ?? '');
        $players = (int)($server['players'] ?? 0) . '/' . (int)($server['max_players'] ?? 0);
        $rtt = isset($server['query_rtt_ms']) && is_numeric($server['query_rtt_ms'])
            ? number_format((float)$server['query_rtt_ms'], 2) . ' ms'
            : 'n/a';

        echo '- ' . (string)($server['name'] ?? 'Unknown server')
            . ' | ' . $address
            . ' | ' . (string)($server['status'] ?? 'unknown')
            . ' | ' . (string)($server['map'] ?? 'Unknown')
            . ' | ' . $players
            . ' | Query RTT ' . $rtt
            . "\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: Server Browser API request failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

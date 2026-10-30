<?php
declare(strict_types=1);

/**
 * CLI-only A2S network preflight for the shared-hosting PHP runtime.
 *
 * Usage:
 *   php tools/test-public-server-browser-udp.php 85.185.40.85 27085 29000 29001
 *
 * The host and every port must already belong to an enabled configured target.
 * This script sends only to the explicit ports supplied; it never scans.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$host = trim((string)($argv[1] ?? ''));
$portArguments = array_slice($argv, 2);

if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false
    || $portArguments === []
    || count($portArguments) > 10) {
    fwrite(STDERR, "Usage: php tools/test-public-server-browser-udp.php IPV4 PORT [PORT ...] (1-10 explicit ports)\n");
    exit(2);
}

$ports = [];
foreach ($portArguments as $portText) {
    if (!is_string($portText) || !preg_match('/^[0-9]{1,5}$/D', $portText)) {
        fwrite(STDERR, "FAIL: invalid port argument.\n");
        exit(2);
    }
    $port = (int)$portText;
    if ($port < 1 || $port > 65535) {
        fwrite(STDERR, "FAIL: port is out of range.\n");
        exit(2);
    }
    $ports[] = $port;
}
$ports = array_values(array_unique($ports));

require_once dirname(__DIR__) . '/settings/loader.php';
require_once dirname(__DIR__) . '/class/PublicServerBrowserA2SScanner.php';

$indexSettings = fragcity_settings('files.index', []);
$defaultProfile = is_array($indexSettings)
    ? trim((string)($indexSettings['database_profile'] ?? 'main'))
    : 'main';
$environmentProfile = getenv('FRAGCITY_SERVER_BROWSER_DB_PROFILE');
$profile = is_string($environmentProfile) && trim($environmentProfile) !== ''
    ? trim($environmentProfile)
    : $defaultProfile;
$pdo = fragcity_create_pdo($profile !== '' ? $profile : 'main');

if (!$pdo instanceof PDO) {
    fwrite(STDERR, "FAIL: could not connect to the configured website database.\n");
    exit(2);
}
if (!function_exists('stream_socket_client') || !function_exists('stream_select')) {
    fwrite(STDERR, "FAIL: PHP UDP stream functions are unavailable.\n");
    exit(2);
}

$targetQuery = $pdo->prepare(
    'SELECT id, host, port_start, port_end, query_timeout_ms
     FROM public_server_scan_targets
     WHERE host = :host AND enabled = 1'
);
$targetQuery->execute([':host' => $host]);
$targets = $targetQuery->fetchAll(PDO::FETCH_ASSOC);

if ($targets === []) {
    fwrite(STDERR, "FAIL: no enabled target matches this IP in public_server_scan_targets.\n");
    exit(2);
}

$timeoutMs = 1500;
$allowed = [];
foreach ($targets as $target) {
    $timeoutMs = (int)$target['query_timeout_ms'];
    foreach ($ports as $port) {
        if ($port >= (int)$target['port_start'] && $port <= (int)$target['port_end']) {
            $allowed[$port] = true;
        }
    }
}

foreach ($ports as $port) {
    if (!isset($allowed[$port])) {
        fwrite(STDERR, 'FAIL: port ' . $port . " is outside the configured range for this target.\n");
        exit(2);
    }
}

try {
    $scanner = new PublicServerBrowserA2SScanner($timeoutMs);
    $results = $scanner->queryBatch($host, $ports);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: UDP preflight could not run: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$failed = 0;
foreach ($ports as $port) {
    $result = $results[$port] ?? ['status' => 'error', 'error' => 'no_result'];
    $info = is_array($result['info'] ?? null) ? $result['info'] : [];

    if (($result['status'] ?? '') === 'online' && (int)($info['app_id'] ?? 0) === 730) {
        echo '[' . $port . "] PASS: "
            . (string)($info['name'] ?? 'CS2')
            . ' | map=' . (string)($info['map'] ?? 'unknown')
            . ' | players=' . (int)($info['players'] ?? 0)
            . '/' . (int)($info['max_players'] ?? 0)
            . ' | query=' . number_format((float)($info['query_rtt_ms'] ?? 0), 2) . " ms\n";
    } else {
        ++$failed;
        echo '[' . $port . '] FAIL: '
            . (string)($result['error'] ?? $result['status'] ?? 'query_failed')
            . ' | status=' . (string)($result['status'] ?? 'unknown') . "\n";
    }
}

if ($failed > 0) {
    fwrite(STDERR, $failed . " A2S test(s) failed.\n");
    exit(1);
}

echo 'PASS: all ' . count($ports) . " explicit UDP A2S tests succeeded from this PHP runtime.\n";

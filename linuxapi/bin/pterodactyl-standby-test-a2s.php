<?php
declare(strict_types=1);

/**
 * CLI-only test for the PHP A2S implementation.
 *
 * Uses only the internal query_host from runtime config and explicit ports
 * passed by the operator. It never scans a port range.
 *
 * Usage:
 *   php linuxapi/bin/test-a2s.php 27085 29000 29001
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$ports = array_slice($argv, 1);
if ($ports === [] || count($ports) > 10) {
    fwrite(STDERR, "Usage: php linuxapi/bin/test-a2s.php PORT [PORT ...] (1-10 explicit ports)\n");
    exit(2);
}

$validatedPorts = [];
foreach ($ports as $portText) {
    if (!is_string($portText) || !preg_match('/^[0-9]{1,5}$/D', $portText)) {
        fwrite(STDERR, "FAIL: every port must be an explicit integer between 1 and 65535.\n");
        exit(2);
    }

    $port = (int)$portText;
    if ($port < 1 || $port > 65535) {
        fwrite(STDERR, "FAIL: port is out of range: " . $portText . "\n");
        exit(2);
    }

    $validatedPorts[] = $port;
}

$validatedPorts = array_values(array_unique($validatedPorts));
$configPath = getenv('FRAGCITY_API_CONFIG') ?: '/etc/fragcity-api/config.php';
if (!is_file($configPath) || !is_readable($configPath)) {
    fwrite(STDERR, "FAIL: runtime config is missing or unreadable.\n");
    exit(2);
}

try {
    $config = require $configPath;
    $serverConfig = is_array($config) && is_array($config['pterodactyl_standby'] ?? null)
        ? $config['pterodactyl_standby']
        : [];
    $host = trim((string)($serverConfig['query_host'] ?? ''));

    if (filter_var($host, FILTER_VALIDATE_IP) === false) {
        throw new RuntimeException('pterodactyl_standby.query_host must be a valid internal IP address.');
    }

    require_once __DIR__ . '/../src/PterodactylStandby/A2SInfoQuery.php';
    $query = new FragCityA2SInfoQuery((int)($serverConfig['query_timeout_ms'] ?? 1500));
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . PHP_EOL);
    exit(2);
}

$failed = 0;
foreach ($validatedPorts as $port) {
    try {
        $info = $query->query($host, $port);
        if ((int)($info['app_id'] ?? 0) !== 730) {
            throw new RuntimeException('The A2S response AppID is not 730.');
        }

        echo '[ ' . $port . " ] A2S PASS\n";
        echo '  Name: ' . (string)$info['name'] . "\n";
        echo '  Map: ' . (string)$info['map'] . "\n";
        echo '  Players: ' . (int)$info['players'] . '/' . (int)$info['max_players'] . "\n";
        echo '  AppID: ' . (int)$info['app_id'] . "\n";
        echo '  Query RTT: ' . number_format((float)$info['query_rtt_ms'], 2) . " ms\n";
    } catch (Throwable $e) {
        ++$failed;
        echo '[ ' . $port . ' ] A2S FAIL: ' . $e->getMessage() . "\n";
    }
}

if ($failed > 0) {
    fwrite(STDERR, $failed . ' A2S query(s) failed.' . PHP_EOL);
    exit(1);
}

echo 'PASS: all ' . count($validatedPorts) . ' explicit A2S query(s) succeeded.' . PHP_EOL;

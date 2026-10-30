<?php
declare(strict_types=1);

/**
 * Explicit A2S batch preflight for configured Pterodactyl allocations.
 * Run on the Linux API host:
 * php linuxapi/bin/pterodactyl-standby-test-a2s.php 27085 29000 29001
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$configPath = getenv('FRAGCITY_API_CONFIG') ?: '/etc/fragcity-api/config.php';
if (!is_file($configPath) || !is_readable($configPath)) {
    fwrite(STDERR, "FAIL: runtime config is missing or unreadable.\n");
    exit(2);
}

try {
    $config = require $configPath;
    if (!is_array($config)) {
        throw new RuntimeException('Runtime config must return an array.');
    }

    $serverConfig = is_array($config['pterodactyl_standby'] ?? null)
        ? $config['pterodactyl_standby']
        : [];
    $host = trim((string)($serverConfig['query_host'] ?? ''));
    if (filter_var($host, FILTER_VALIDATE_IP) === false) {
        throw new RuntimeException('pterodactyl_standby.query_host must be a valid internal IP address.');
    }

    $arguments = array_slice($argv, 1);
    if ($arguments === [] || count($arguments) > 32) {
        throw new InvalidArgumentException(
            'Usage: php linuxapi/bin/pterodactyl-standby-test-a2s.php PORT [PORT ...] (1-32 explicit ports)'
        );
    }

    $ports = [];
    foreach ($arguments as $text) {
        if (!is_string($text) || !preg_match('/^[0-9]{1,5}$/D', $text)) {
            throw new InvalidArgumentException('Every port must be an explicit integer.');
        }
        $port = (int)$text;
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('Port is out of range: ' . $text);
        }
        $ports[] = $port;
    }
    if (count(array_unique($ports)) !== count($ports)) {
        throw new InvalidArgumentException('Duplicate ports are not allowed.');
    }

    require_once __DIR__ . '/../src/PterodactylStandby/A2SInfoQuery.php';
    $query = new FragCityA2SInfoQuery((int)($serverConfig['query_timeout_ms'] ?? 1500));
    $started = microtime(true);
    $results = $query->queryBatch($host, $ports);
    $failed = 0;

    foreach ($ports as $port) {
        $result = $results[$port] ?? [];
        $info = is_array($result['info'] ?? null) ? $result['info'] : null;
        if ($info === null || (int)($info['app_id'] ?? 0) !== 730) {
            ++$failed;
            $reason = is_string($result['error'] ?? null)
                ? $result['error']
                : 'A2S response is not a valid CS2 AppID 730 response.';
            echo '[ ' . $port . ' ] A2S FAIL: ' . $reason . PHP_EOL;
            continue;
        }

        echo '[ ' . $port . ' ] A2S PASS' . PHP_EOL;
        echo '  Name: ' . (string)($info['name'] ?? '') . PHP_EOL;
        echo '  Map: ' . (string)($info['map'] ?? '') . PHP_EOL;
        echo '  Players: ' . (int)($info['players'] ?? 0) . '/' . (int)($info['max_players'] ?? 0) . PHP_EOL;
        echo '  AppID: 730' . PHP_EOL;
        echo '  Query RTT: ' . number_format((float)($info['query_rtt_ms'] ?? 0), 2) . ' ms' . PHP_EOL;
    }

    $elapsed = round((microtime(true) - $started) * 1000, 2);
    if ($failed > 0) {
        fwrite(STDERR, $failed . ' of ' . count($ports) . ' A2S query(s) failed.' . PHP_EOL);
        exit(1);
    }

    echo 'PASS: all ' . count($ports) . ' explicit allocations succeeded in '
        . number_format($elapsed, 2) . ' ms total.' . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . PHP_EOL);
    exit(2);
}

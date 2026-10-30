<?php
declare(strict_types=1);

/**
 * Refresh the public CS2 server snapshot.
 * Run this only on the Linux API host, preferably as www-data via systemd.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

set_time_limit(90);

require_once __DIR__ . '/../src/ServerBrowser/ServerBrowserDiscovery.php';

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

    $serverConfig = is_array($config['server_browser'] ?? null)
        ? $config['server_browser']
        : [];
    $cacheFile = trim((string)($serverConfig['cache_file'] ?? ''));

    if ($cacheFile === '' || $cacheFile[0] !== '/') {
        throw new RuntimeException('server_browser.cache_file must be an absolute path.');
    }

    $cacheDirectory = dirname($cacheFile);
    if (!is_dir($cacheDirectory) && !mkdir($cacheDirectory, 0750, true) && !is_dir($cacheDirectory)) {
        throw new RuntimeException('Could not create the server-browser cache directory.');
    }

    $lock = @fopen($cacheFile . '.lock', 'c');
    if (!is_resource($lock)) {
        throw new RuntimeException('Could not open the discovery lock file.');
    }

    if (!flock($lock, LOCK_EX | LOCK_NB)) {
        fclose($lock);
        echo "SKIP: another server-browser refresh is already running.\n";
        exit(0);
    }

    try {
        $discovery = new FragCityServerBrowserDiscovery($config);
        $servers = $discovery->discover();

        $snapshot = [
            'schema_version' => 1,
            'generated_at' => gmdate('c'),
            'servers' => $servers,
        ];

        $json = json_encode(
            $snapshot,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        );

        $temporaryFile = $cacheFile . '.tmp.' . getmypid();
        if (file_put_contents($temporaryFile, $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Could not write temporary server-browser cache.');
        }

        @chmod($temporaryFile, 0640);
        if (!rename($temporaryFile, $cacheFile)) {
            @unlink($temporaryFile);
            throw new RuntimeException('Could not atomically replace server-browser cache.');
        }

        echo 'PASS: refreshed ' . count($servers) . ' CS2 allocation(s) at ' . $snapshot['generated_at'] . PHP_EOL;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

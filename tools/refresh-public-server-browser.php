<?php
declare(strict_types=1);

/**
 * Portable CLI entrypoint for the public A2S scanner.
 *
 * Requirements: PHP CLI, PDO MySQL, UDP stream support, stream_select and
 * network access to the configured website database and target UDP endpoints.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once dirname(__DIR__) . '/settings/loader.php';
require_once dirname(__DIR__) . '/class/PublicServerBrowserA2SScanner.php';
require_once dirname(__DIR__) . '/class/PublicServerBrowserScanRepository.php';
require_once dirname(__DIR__) . '/class/PublicServerBrowserScanWorker.php';

/**
 * Resolve a bounded integer setting, preferring an environment override.
 *
 * @param array<string, mixed> $settings
 */
function fragcity_server_browser_int_setting(
    string $environmentKey,
    array $settings,
    string $settingKey,
    int $default,
    int $minimum,
    int $maximum
): int {
    $environmentValue = getenv($environmentKey);
    $value = $environmentValue !== false && trim($environmentValue) !== ''
        ? $environmentValue
        : ($settings[$settingKey] ?? $default);

    if (filter_var($value, FILTER_VALIDATE_INT) === false) {
        throw new InvalidArgumentException('Invalid integer setting: ' . $environmentKey);
    }

    return max($minimum, min($maximum, (int)$value));
}

$scannerSettings = fragcity_settings('public_server_scanner', []);
if (!is_array($scannerSettings)) {
    $scannerSettings = [];
}

$indexSettings = fragcity_settings('files.index', []);
$defaultProfile = is_array($indexSettings)
    ? trim((string)($indexSettings['database_profile'] ?? 'main'))
    : 'main';

$environmentProfile = getenv('FRAGCITY_SERVER_BROWSER_DB_PROFILE');
$configuredProfile = $scannerSettings['database_profile'] ?? $defaultProfile;
$profile = is_string($environmentProfile) && trim($environmentProfile) !== ''
    ? trim($environmentProfile)
    : trim((string)$configuredProfile);
$profile = $profile !== '' ? $profile : 'main';

try {
    $maxPortsPerRun = fragcity_server_browser_int_setting(
        'FRAGCITY_SERVER_BROWSER_MAX_PER_RUN',
        $scannerSettings,
        'max_ports_per_run',
        256,
        1,
        4096
    );
    $batchSize = fragcity_server_browser_int_setting(
        'FRAGCITY_SERVER_BROWSER_BATCH_SIZE',
        $scannerSettings,
        'batch_size',
        32,
        1,
        128
    );
    $timeLimitSeconds = fragcity_server_browser_int_setting(
        'FRAGCITY_SERVER_BROWSER_TIME_LIMIT',
        $scannerSettings,
        'time_limit_seconds',
        25,
        0,
        300
    );
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . PHP_EOL);
    exit(2);
}

if ($timeLimitSeconds > 0) {
    @set_time_limit($timeLimitSeconds);
}

if (!class_exists(PDO::class)
    || !in_array('mysql', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "FAIL: this PHP CLI runtime requires the PDO MySQL driver.\n");
    exit(2);
}

if (!function_exists('stream_socket_client')
    || !function_exists('stream_select')
    || !function_exists('hrtime')) {
    fwrite(STDERR, "FAIL: PHP CLI requires stream_socket_client, stream_select and hrtime for UDP A2S queries.\n");
    exit(2);
}

$pdo = fragcity_create_pdo($profile);
if (!$pdo instanceof PDO) {
    fwrite(STDERR, 'FAIL: could not connect to website database profile "' . $profile . '".' . PHP_EOL);
    exit(2);
}

try {
    $repository = new PublicServerBrowserScanRepository($pdo);
    $worker = new PublicServerBrowserScanWorker($repository);
    $summary = $worker->run($maxPortsPerRun, $batchSize);

    if ($summary['skipped']) {
        echo "SKIP: another public server scan is already running.\n";
        exit(0);
    }

    echo 'PASS: scanned ' . $summary['processed']
        . ' port(s); online=' . $summary['online']
        . ', offline=' . $summary['offline']
        . ', non_cs2=' . $summary['not_cs2']
        . ', errors=' . $summary['errors']
        . ', skipped_targets=' . $summary['skipped_targets']
        . '; elapsed=' . $summary['elapsed_seconds'] . "s\n";

    foreach ($summary['warnings'] as $warning) {
        fwrite(STDERR, 'WARN: ' . $warning . PHP_EOL);
    }
} catch (Throwable $e) {
    error_log('[FragCity Public Server Browser] ' . get_class($e) . ': ' . $e->getMessage());
    fwrite(STDERR, 'FAIL: public server scan stopped: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

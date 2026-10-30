<?php
declare(strict_types=1);

/**
 * Static architecture regression checks for the active website and API.
 * Run from the repository root: php linuxapi/tools/architecture-check.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$root = dirname(__DIR__, 2);
$checks = [];
$failures = [];

$read = static function (string $relative) use ($root): string {
    $path = $root . DIRECTORY_SEPARATOR . $relative;
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('Required source file missing: ' . $relative);
    }
    $contents = file_get_contents($path);
    if (!is_string($contents)) {
        throw new RuntimeException('Unable to read source file: ' . $relative);
    }
    return $contents;
};

$check = static function (string $name, bool $ok) use (&$checks, &$failures): void {
    $checks[$name] = $ok;
    if (!$ok) {
        $failures[] = $name;
    }
};

try {
    $index = $read('linuxapi/public/index.php');
    $linuxConfig = $read('linuxapi/config.example.php');
    $siteBridge = $read('api/internal_agent_bridge.php');
    $preflight = $read('tools/site-bridge-preflight.php');
    $weaponRepo = $read('class/WeaponPaintsRepository.php');
    $apiClient = $read('class/FragCityLinuxApiClient.php');
    $settingsLoader = $read('settings/loader.php');

    $standbySettingsRaw = $read('settings/pterodactyl-standby.integration.example.json');
    $standbySettings = json_decode($standbySettingsRaw, true, 512, JSON_THROW_ON_ERROR);
    $standbyApiClient = $read('class/PterodactylStandbyApiClient.php');
    $standbyRepository = $read('class/PterodactylStandbyRepository.php');
    $standbyPterodactylClient = $read('linuxapi/src/PterodactylStandby/PterodactylApplicationClient.php');
    $standbyDiscovery = $read('linuxapi/src/PterodactylStandby/PterodactylStandbyDiscovery.php');
    $standbyA2S = $read('linuxapi/src/PterodactylStandby/A2SInfoQuery.php');
    $standbyWorker = $read('linuxapi/bin/pterodactyl-standby-refresh.php');

    $publicPage = $read('pages/serverlist.php');
    $publicRepository = $read('class/PublicServerBrowserRepository.php');
    $publicScanner = $read('class/PublicServerBrowserA2SScanner.php');
    $publicWorker = $read('tools/refresh-public-server-browser.php');
    $publicUdpTest = $read('tools/test-public-server-browser-udp.php');
    $publicMigration = $read('database/migrations/20261030_create_public_server_browser_tables.sql');
    $localGrants = $read('tools/laragon-site-db-user.sql');
    $nginxApi = $read('linuxapi/nginx/fragcity-api-https.conf.example');
    $standbyDocs = $read('docs/PTERODACTYL-STANDBY-API.md');
    $publicDocs = $read('docs/PUBLIC-SERVER-SCANNER.md');
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . PHP_EOL);
    exit(2);
}

$check(
    'WeaponPaints remains on its signed API and Linux-local GLOBAL database',
    str_contains($index, "'/v1/weaponpaints'")
        && str_contains($linuxConfig, "'host' => '127.0.0.1'")
        && str_contains($linuxConfig, "'name' => 'GLOBAL_weaponpaints'")
        && str_contains($weaponRepo, 'new FragCityLinuxApiClient()')
        && str_contains($apiClient, "hash_hmac('sha256'")
        && str_contains($apiClient, 'CURLOPT_SSL_VERIFYPEER => true')
        && !str_contains($weaponRepo, 'new PDO')
);

$check(
    'Legacy Windows-Agent API remains retired',
    str_contains($index, 'legacy_agent_api_retired')
        && str_contains($siteBridge, 'http_response_code(410)')
        && str_contains($siteBridge, 'legacy_agent_api_retired')
        && !str_contains($siteBridge, 'fragcity_create_pdo')
);

$check(
    'Runtime settings remain fail-closed and never load the example file',
    str_contains($settingsLoader, "settings.json")
        && str_contains($settingsLoader, 'Required runtime settings file is missing')
        && !str_contains($settingsLoader, 'settings.example.json')
);

$check(
    'Active Server Browser reads the main database and never invokes a remote API',
    str_contains($publicPage, 'new PublicServerBrowserRepository($pdo)')
        && str_contains($publicRepository, 'public_server_scan_ports')
        && str_contains($publicRepository, 'public_server_scan_targets')
        && str_contains($publicRepository, "p.app_id = 730")
        && !str_contains($publicPage, 'ServerBrowserApiClient')
        && !str_contains($publicRepository, 'curl_init')
        && !str_contains($publicRepository, 'stream_socket_client')
        && !str_contains($publicRepository, 'Pterodactyl')
);

$check(
    'Public A2S scanner uses bounded concurrent UDP, handles challenges and validates AppID 730',
    str_contains($publicScanner, 'stream_socket_client')
        && str_contains($publicScanner, 'stream_select')
        && str_contains($publicScanner, 'Source Engine Query')
        && str_contains($publicScanner, '"\xFF\xFF\xFF\xFF\x41"')
        && str_contains($publicScanner, '"\xFF\xFF\xFF\xFF\x49"')
        && str_contains($publicScanner, 'count($ports) > 128')
        && str_contains($publicWorker, '$scanner->queryBatch($host, $ports)')
        && str_contains($publicWorker, '(int)$info[\'app_id\'] === 730')
);

$check(
    'Public scanner Cron is CLI-only, locked, resumable and limited to 256 ports per run',
    str_contains($publicWorker, "PHP_SAPI !== 'cli'")
        && str_contains($publicWorker, "GET_LOCK('fragcity_public_server_browser_scan', 0)")
        && str_contains($publicWorker, '$maxPerRun = 256')
        && str_contains($publicWorker, '$batchSize = 64')
        && str_contains($publicWorker, 'next_query_at')
        && str_contains($publicWorker, 'retry_offline_seconds')
        && str_contains($publicWorker, "RELEASE_LOCK('fragcity_public_server_browser_scan')")
);

$check(
    'Public scanner migration creates only its two tables and seeds the requested target range',
    str_contains($publicMigration, 'CREATE TABLE IF NOT EXISTS public_server_scan_targets')
        && str_contains($publicMigration, 'CREATE TABLE IF NOT EXISTS public_server_scan_ports')
        && str_contains($publicMigration, "'85.185.40.85', 27085, 29100")
        && str_contains($publicMigration, 'retry_offline_seconds')
        && str_contains($publicMigration, 'refresh_online_seconds')
);

$check(
    'Local DB user has explicit grants for both scanner tables',
    str_contains($localGrants, 'public_server_scan_targets')
        && str_contains($localGrants, 'public_server_scan_ports')
);

$check(
    'Shared-hosting UDP preflight uses only explicit configured ports',
    str_contains($publicUdpTest, 'count($portArguments) > 10')
        && str_contains($publicUdpTest, 'outside the configured range')
        && str_contains($publicUdpTest, '$scanner->queryBatch($host, $ports)')
        && str_contains($publicDocs, 'test-public-server-browser-udp.php')
);

$check(
    'Pterodactyl standby integration has an independent website secret',
    (($standbySettings['integrations']['pterodactyl_standby_api']['base_url'] ?? null) === 'https://api.fragcity.ir')
        && is_string($standbySettings['integrations']['pterodactyl_standby_api']['shared_secret'] ?? null)
        && str_contains($standbyApiClient, 'integrations.pterodactyl_standby_api.shared_secret')
        && str_contains($standbyApiClient, "hash_hmac('sha256'")
        && str_contains($standbyRepository, 'new PterodactylStandbyApiClient()')
);

$check(
    'Pterodactyl standby client is read-only and filters configured allocations',
    str_contains($standbyPterodactylClient, 'CURLOPT_HTTPGET => true')
        && str_contains($standbyPterodactylClient, 'Authorization: Bearer ')
        && str_contains($standbyPterodactylClient, 'allowed_egg_ids')
        && str_contains($standbyPterodactylClient, 'allowed_docker_images')
        && str_contains($standbyPterodactylClient, 'is_default')
        && !str_contains($standbyPterodactylClient, 'CURLOPT_POST => true')
        && str_contains($standbyDiscovery, '$this->a2s->query($queryHost, $port)')
        && str_contains($standbyA2S, 'Source Engine Query')
        && str_contains($standbyWorker, 'PterodactylStandbyDiscovery')
        && str_contains($standbyDocs, 'not the active public Server Browser')
);

$check(
    'Standby snapshot route and Nginx example use a distinct Pterodactyl Standby path',
    str_contains($index, "'/v1/pterodactyl-standby'")
        && str_contains($index, '$config[\'pterodactyl_standby\'][\'shared_secret\']')
        && str_contains($index, 'pterodactyl_standby_cache_unavailable')
        && str_contains($nginxApi, 'location = /v1/pterodactyl-standby')
        && str_contains($nginxApi, 'SCRIPT_FILENAME $document_root/index.php')
);

$check(
    'Website preflight remains read-only and scoped to the known site schema',
    str_contains($preflight, "PHP_SAPI !== 'cli'")
        && str_contains($preflight, 'INFORMATION_SCHEMA.COLUMNS')
        && str_contains($preflight, "'donations' =>")
        && str_contains($preflight, "'withdraw_requests' =>")
        && !preg_match('/\b(?:INSERT\s+INTO|UPDATE\s+[A-Za-z_][A-Za-z0-9_]*\s+SET|DELETE\s+FROM|CREATE\s+TABLE|ALTER\s+TABLE|DROP\s+TABLE|TRUNCATE\s+TABLE)\b/i', $preflight)
);

$retiredTables = [
    'agent_' . 'tokens',
    'agent_' . 'nodes',
    'pending_' . 'servers',
    'registered_' . 'servers',
    'game_' . 'servers',
    'server_runtime_' . 'stats',
    'rcon_command_' . 'logs',
    'server_' . 'configs',
    'server_' . 'ports',
    'agent_' . 'commands',
    'fragcity_api_bridge_' . 'nonces',
    'fragcity_api_bridge_login_' . 'attempts',
];

$runtimeClean = true;
$excludedDirectories = ['.git', 'vendor', 'node_modules'];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $fileInfo) {
    if (!($fileInfo instanceof SplFileInfo) || !$fileInfo->isFile()) {
        continue;
    }

    $relative = substr($fileInfo->getPathname(), strlen($root) + 1);
    $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
    if (strtolower(pathinfo($relative, PATHINFO_EXTENSION)) !== 'php'
        || $relative === 'linuxapi/tools/architecture-check.php') {
        continue;
    }

    $segments = explode('/', $relative);
    if (array_intersect($segments, $excludedDirectories) !== []) {
        continue;
    }

    $contents = file_get_contents($fileInfo->getPathname());
    if (!is_string($contents)) {
        $runtimeClean = false;
        fwrite(STDERR, 'FAIL: unable to read PHP source: ' . $relative . PHP_EOL);
        continue;
    }

    foreach ($retiredTables as $table) {
        if (preg_match('/\b' . preg_quote($table, '/') . '\b/i', $contents)) {
            $runtimeClean = false;
            fwrite(STDERR, 'FAIL: retired table reference remains in ' . $relative . PHP_EOL);
        }
    }
}
$check('All PHP files exclude retired Windows-Agent table dependencies', $runtimeClean);

$failed = 0;
foreach ($checks as $name => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . PHP_EOL;
    if (!$passed) {
        ++$failed;
    }
}

if ($failed > 0) {
    fwrite(STDERR, $failed . ' architecture check(s) failed.' . PHP_EOL);
    exit(1);
}

echo 'All ' . count($checks) . ' architecture checks passed.' . PHP_EOL;

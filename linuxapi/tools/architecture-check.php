<?php
declare(strict_types=1);

/**
 * Static architecture regression checks.
 *
 * This ensures the website no longer queries the retired Windows-Agent schema
 * and keeps WeaponPaints access behind the Linux API.
 * Run from the repository root: php linuxapi/tools/architecture-check.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$root = dirname(__DIR__, 2);
$failures = [];
$checks = [];

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
    $settingsRaw = $read('settings/settings.example.json');
    $settings = json_decode($settingsRaw, true, 512, JSON_THROW_ON_ERROR);
    $serverBrowserApiClient = $read('class/FragCityServerBrowserApiClient.php');
    $serverBrowserRepository = $read('class/ServerBrowserRepository.php');
    $serverBrowserPage = $read('pages/serverlist.php');
    $serverBrowserDiscovery = $read('linuxapi/src/ServerBrowser/ServerBrowserDiscovery.php');
    $pterodactylClient = $read('linuxapi/src/ServerBrowser/PterodactylApplicationClient.php');
    $a2sClient = $read('linuxapi/src/ServerBrowser/A2SInfoQuery.php');
    $serverBrowserWorker = $read('linuxapi/bin/refresh-server-browser.php');
    $nginxApi = $read('linuxapi/nginx/fragcity-api-https.conf.example');
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . PHP_EOL);
    exit(2);
}

$check(
    'Linux API remains pinned to the WeaponPaints route and local GLOBAL database',
    str_contains($index, "'/v1/weaponpaints'")
        && !str_contains($index, 'apiProxyLegacyAgent')
        && str_contains($linuxConfig, "'host' => '127.0.0.1'")
        && str_contains($linuxConfig, "'name' => 'GLOBAL_weaponpaints'")
        && !str_contains($linuxConfig, 'website_bridge')
);
$check(
    'Old Agent API route is retired with HTTP 410',
    str_contains($index, 'legacy_agent_api_retired')
        && !str_contains($index, 'apiProxyLegacyAgent')
);
$check(
    'Website WeaponPaints repository only uses the signed Linux API client',
    str_contains($weaponRepo, 'new FragCityLinuxApiClient()')
        && str_contains($weaponRepo, "transport !== 'api'")
        && !str_contains($weaponRepo, 'new PDO')
        && !preg_match('/\b(?:SELECT|INSERT\s+INTO|UPDATE|DELETE\s+FROM)\b/i', $weaponRepo)
);
$check(
    'WeaponPaints client verifies TLS, signs requests, disables redirects, and API verifies signatures',
    str_contains($apiClient, 'CURLOPT_SSL_VERIFYPEER => true')
        && str_contains($apiClient, 'CURLOPT_SSL_VERIFYHOST => 2')
        && str_contains($apiClient, 'CURLOPT_FOLLOWLOCATION => false')
        && str_contains($apiClient, "hash_hmac('sha256'")
        && str_contains($index, 'hash_equals(')
);
$check(
    'Runtime settings loader requires settings/settings.json and never falls back to example',
    str_contains($settingsLoader, "__DIR__.'/settings.json'")
        && str_contains($settingsLoader, 'Required runtime settings file is missing')
        && !str_contains($settingsLoader, 'settings.example.json')
);
$check(
    'Settings sample defaults WeaponPaints to API transport and contains no site bridge secret',
    (($settings['files']['database.weaponpaints_plugin']['transport'] ?? null) === 'api')
        && !isset($settings['integrations']['linuxapi_site_bridge'])
        && !isset($settings['databases']['weaponpaints'])
);
$check(
    'Server Browser website client uses a separate signed HTTPS integration',
    str_contains($serverBrowserApiClient, "integrations.server_browser_api.base_url")
        && str_contains($serverBrowserApiClient, "integrations.server_browser_api.shared_secret")
        && str_contains($serverBrowserApiClient, "hash_hmac('sha256'")
        && str_contains($serverBrowserApiClient, "CURLOPT_SSL_VERIFYPEER => true")
        && str_contains($serverBrowserApiClient, "CURLOPT_FOLLOWLOCATION => false")
);
$check(
    'Server Browser repository and page never query Pterodactyl or UDP directly',
    str_contains($serverBrowserRepository, 'new FragCityServerBrowserApiClient()')
        && !str_contains($serverBrowserRepository, 'new PDO')
        && !str_contains($serverBrowserRepository, 'stream_socket_client')
        && str_contains($serverBrowserPage, 'new ServerBrowserRepository()')
        && !str_contains($serverBrowserPage, 'settings/public-servers.php')
);
$check(
    'Linux Server Browser endpoint uses its own secret and reads the cached snapshot',
    str_contains($index, "'/v1/server-browser'")
        && str_contains($index, '$config[\'server_browser\'][\'shared_secret\']')
        && str_contains($index, 'server_browser_cache_unavailable')
        && str_contains($index, 'server_browser_cache_invalid')
);
$check(
    'Pterodactyl discovery is read-only and restricted to configured CS2 node/image/Egg allocations',
    str_contains($pterodactylClient, 'CURLOPT_HTTPGET => true')
        && str_contains($pterodactylClient, 'Authorization: Bearer ')
        && str_contains($pterodactylClient, 'allowed_egg_ids')
        && str_contains($pterodactylClient, 'allowed_docker_images')
        && str_contains($pterodactylClient, 'is_default')
        && !str_contains($pterodactylClient, 'CURLOPT_POST => true')
);
$check(
    'A2S queries use explicit allocations, handle challenge responses, and verify CS2 AppID',
    str_contains($a2sClient, 'Source Engine Query')
        && str_contains($a2sClient, '"\\xFF\\xFF\\xFF\\xFF\\x41"')
        && str_contains($serverBrowserDiscovery, '$this->a2s->query($queryHost, $port)')
        && str_contains($serverBrowserDiscovery, "['app_id'] ?? 0) !== 730")
        && !str_contains($serverBrowserDiscovery, 'range(')
);
$check(
    'Server Browser worker serializes refreshes and atomically replaces its cache',
    str_contains($serverBrowserWorker, 'LOCK_EX | LOCK_NB')
        && str_contains($serverBrowserWorker, 'rename($temporaryFile, $cacheFile)')
        && str_contains($serverBrowserWorker, "PHP_SAPI !== 'cli'")
);
$check(
    'Active Nginx TLS example routes the exact Server Browser endpoint to the fixed API front controller',
    str_contains($nginxApi, 'location = /v1/server-browser')
        && str_contains($nginxApi, 'SCRIPT_FILENAME $document_root/index.php')
);

$check(
    'Old website Agent connector fails closed without database access',
    str_contains($siteBridge, 'http_response_code(410)')
        && str_contains($siteBridge, 'legacy_agent_api_retired')
        && !str_contains($siteBridge, 'fragcity_create_pdo')
        && !str_contains($siteBridge, 'agent_tokens')
);
$check(
    'Schema preflight checks only the supplied website database schema',
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
        if (preg_match('/\\b' . preg_quote($table, '/') . '\\b/i', $contents)) {
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

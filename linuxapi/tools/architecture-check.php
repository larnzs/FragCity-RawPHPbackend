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
    $weaponPaintsUtils = $read('class/utils.php');
    $weaponPaintsPage = $read('pages/weaponpaints.php');
    $weaponPaintsJavascript = $read('js/weaponpaints.js');
    $weaponPaintsStylesheet = $read('css/weaponpaints.css');
    $pinCatalog = json_decode($read('data/collectibles_en.json'), true, 512, JSON_THROW_ON_ERROR);
    $apiClient = $read('class/FragCityLinuxApiClient.php');
    $settingsLoader = $read('settings/loader.php');

    // Runtime settings examples are tracked templates, never live credentials.
    $websiteSettingsRaw = $read('settings/settings.example.json');
    $websiteSettings = json_decode($websiteSettingsRaw, true, 512, JSON_THROW_ON_ERROR);
    $weaponPaintsSettingsRaw = $read('settings/weaponpaints-api.integration.example.json');
    $weaponPaintsSettings = json_decode($weaponPaintsSettingsRaw, true, 512, JSON_THROW_ON_ERROR);
    $standbySettingsRaw = $read('settings/pterodactyl-standby.integration.example.json');
    $standbySettings = json_decode($standbySettingsRaw, true, 512, JSON_THROW_ON_ERROR);
    $standbyApiClient = $read('class/PterodactylStandbyApiClient.php');
    $standbyRepository = $read('class/PterodactylStandbyRepository.php');
    $serverListPage = $read('pages/serverlist.php');
    $serverListStylesheet = $read('css/serverlist.css');
    $siteHeaderMarkup = $read('includes/header.php');
    $siteHeaderStylesheet = $read('css/header.css');
    $headerJavascript = $read('js/header.js');
    $websiteIndex = $read('index.php');
    $testHarnessIndex = $read('tst/index.php');
    $footerMarkup = $read('includes/footer.php');
    $footerStylesheet = $read('css/footer.css');
    $launcherPage = $read('pages/launcher.php');
    $launcherStylesheet = $read('css/launcher.css');
    $supportPage = $read('pages/support.php');
    $userManualPage = $read('pages/usermanual.php');
    $rulesPage = $read('pages/rules.php');
    $privacyPage = $read('pages/privacy.php');
    $aboutPage = $read('pages/about.php');
    $standbyPterodactylClient = $read('linuxapi/src/PterodactylStandby/PterodactylApplicationClient.php');
    $standbyDiscovery = $read('linuxapi/src/PterodactylStandby/PterodactylStandbyDiscovery.php');
    $standbyA2S = $read('linuxapi/src/PterodactylStandby/A2SInfoQuery.php');
    $standbyA2SPlayers = $read('linuxapi/src/PterodactylStandby/A2SPlayerQuery.php');
    $standbyA2SPlayersTest = $read('linuxapi/tests/a2s-player-query-test.php');
    $standbySitePlayersEndpoint = $read('api/public-server-players.php');
    $standbyBrowserJavascript = $read('js/serverlist.js');
    $standbyBrowserCss = $read('css/serverlist.css');
    $standbyCache = $read('linuxapi/src/PterodactylStandby/PterodactylStandbySnapshotCache.php');
    $standbyA2STest = $read('linuxapi/bin/pterodactyl-standby-test-a2s.php');
    $standbyAllocationTest = $read('linuxapi/tests/pterodactyl-primary-allocation-test.php');
    $standbyWorker = $read('linuxapi/bin/pterodactyl-standby-refresh.php');

    $monitoringPanelClient = $read('linuxapi/src/PterodactylMonitoring/PterodactylMonitoringPanelClient.php');
    $monitoringWingsClient = $read('linuxapi/src/PterodactylMonitoring/WingsMonitoringClient.php');
    $monitoringHostMetrics = $read('linuxapi/src/PterodactylMonitoring/PterodactylHostMetrics.php');
    $monitoringService = $read('linuxapi/src/PterodactylMonitoring/PterodactylMonitoringService.php');
    $monitoringSiteClient = $read('class/PterodactylMonitoringApiClient.php');
    $monitoringSiteRepository = $read('class/PterodactylMonitoringRepository.php');
    $monitoringSiteEndpoint = $read('api/admin-pterodactyl-monitoring.php');
    $monitoringSettingsRaw = $read('settings/pterodactyl-monitoring.integration.example.json');
    $monitoringSettings = json_decode($monitoringSettingsRaw, true, 512, JSON_THROW_ON_ERROR);
    $monitoringDocs = $read('docs/PTERODACTYL-MONITORING.md');
    $nginxApiBootstrap = $read('linuxapi/nginx/fragcity-api.conf.example');

    $publicPage = $read('pages/serverlist.php');
    $publicRepository = $read('class/PublicServerBrowserRepository.php');
    $publicScanner = $read('class/PublicServerBrowserA2SScanner.php');
    $publicScanStoreInterface = $read('class/PublicServerBrowserScanStoreInterface.php');
    $publicScanRepository = $read('class/PublicServerBrowserScanRepository.php');
    $publicScanWorker = $read('class/PublicServerBrowserScanWorker.php');
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
    'Pin catalog preserves each pin ID after pagination converts the catalog to a numeric list',
    str_contains($weaponPaintsUtils, '\'id\'        => (int)$id')
        && str_contains($weaponPaintsPage, "\$pin['id']")
        && is_array($pinCatalog)
        && count($pinCatalog) > 0
        && array_reduce($pinCatalog, static function (bool $valid, mixed $pin): bool {
            $id = is_array($pin) ? ($pin['id'] ?? $pin['pin_id'] ?? null) : null;
            return $valid && (is_int($id) || (is_string($id) && preg_match('/^\\d+$/D', $id) === 1))
                && (int)$id >= 0 && (int)$id <= 100000;
        }, true)
);

$check(
    'WeaponPaints skin, sticker and keychain modals expose separate mobile selection and feature tabs',
    str_contains($weaponPaintsJavascript, 'function setupMobileModalTabs(')
        && str_contains($weaponPaintsJavascript, "label: 'انتخاب Paint'")
        && str_contains($weaponPaintsJavascript, "label: 'افزودن ویژگی‌ها'")
        && str_contains($weaponPaintsJavascript, "key: 'select'")
        && str_contains($weaponPaintsJavascript, "setupMobileModalTabs(")
        && str_contains($weaponPaintsStylesheet, '.wp-mobile-modal-tabs')
        && str_contains($weaponPaintsStylesheet, '.wp-skin-list-pane.is-mobile-active')
        && str_contains($weaponPaintsStylesheet, '.wp-addon-list-pane.is-mobile-active')
        && str_contains($weaponPaintsStylesheet, '.wp-addon-properties.is-mobile-active')
);

$check(
    'WeaponPaints mobile category bar is sticky within the main content scrollport and compact controls fit small screens',
    str_contains($weaponPaintsStylesheet, '.wp-app .wp-header')
        && str_contains($weaponPaintsStylesheet, 'position: sticky !important;')
        && str_contains($weaponPaintsStylesheet, 'top: 72px !important;')
        && str_contains($weaponPaintsStylesheet, 'top: 66px !important;')
        && str_contains($weaponPaintsStylesheet, 'body:has(.wp-app) .main-content')
        && str_contains($weaponPaintsStylesheet, 'overflow-y: visible !important;')
        && str_contains($weaponPaintsStylesheet, 'grid-template-areas:')
        && str_contains($weaponPaintsStylesheet, 'scroll-snap-type: x proximity;')
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
    'Complete website settings example is valid and includes every active API integration',
    is_array($websiteSettings['app'] ?? null)
        && is_array($websiteSettings['databases']['main'] ?? null)
        && (($websiteSettings['files']['database.weaponpaints_plugin']['transport'] ?? null) === 'api')
        && (($websiteSettings['integrations']['weaponpaints_api']['base_url'] ?? null) === 'https://api.fragcity.ir')
        && (($websiteSettings['integrations']['pterodactyl_standby_api']['base_url'] ?? null) === 'https://api.fragcity.ir')
        && (($websiteSettings['integrations']['pterodactyl_monitoring_api']['base_url'] ?? null) === 'https://api.fragcity.ir')
        && is_string($websiteSettings['integrations']['weaponpaints_api']['shared_secret'] ?? null)
        && is_string($websiteSettings['integrations']['pterodactyl_standby_api']['shared_secret'] ?? null)
        && is_string($websiteSettings['integrations']['pterodactyl_monitoring_api']['shared_secret'] ?? null)
);

$check(
    'Standalone website Integration examples are valid and document the matching Linux secrets',
    (($weaponPaintsSettings['integrations']['weaponpaints_api']['base_url'] ?? null) === 'https://api.fragcity.ir')
        && str_contains((string)($weaponPaintsSettings['integrations']['weaponpaints_api']['_comment'] ?? ''), 'shared_secret')
        && (($standbySettings['integrations']['pterodactyl_standby_api']['base_url'] ?? null) === 'https://api.fragcity.ir')
        && str_contains((string)($standbySettings['integrations']['pterodactyl_standby_api']['_comment'] ?? ''), 'pterodactyl_standby.shared_secret')
        && (($monitoringSettings['integrations']['pterodactyl_monitoring_api']['base_url'] ?? null) === 'https://api.fragcity.ir')
        && str_contains((string)($monitoringSettings['integrations']['pterodactyl_monitoring_api']['_comment'] ?? ''), 'pterodactyl_monitoring.shared_secret')
);

$check(
    'Linux runtime example documents all three independent HMAC keys and the Pterodactyl feature settings',
    str_contains($linuxConfig, 'REPLACE_WITH_WEAPONPAINTS_64_HEX_SECRET')
        && str_contains($linuxConfig, 'REPLACE_WITH_STANDBY_64_HEX_SECRET')
        && str_contains($linuxConfig, 'REPLACE_WITH_MONITORING_64_HEX_SECRET')
        && str_contains($linuxConfig, "'cache_ttl_seconds' => 10")
        && str_contains($linuxConfig, "'cache_max_stale_seconds' => 180")
        && str_contains($linuxConfig, "'allowed_node_ids' => []")
        && str_contains($linuxConfig, "'node_id' => 0")
        && str_contains($linuxConfig, "'wings_base_urls' => []")
);

$check(
    'Active Server Browser uses the signed Pterodactyl API and no longer reads the range-scanner tables',
    str_contains($publicPage, 'new PterodactylStandbyRepository()')
        && str_contains($publicPage, 'Pterodactyl Server Browser API read failed')
        && str_contains($standbyRepository, 'new PterodactylStandbyApiClient()')
        && str_contains($standbyApiClient, "hash_hmac('sha256'")
        && str_contains($publicPage, '$serverSnapshot')
        && !str_contains($publicPage, 'PublicServerBrowserRepository')
);

$check(
    'Public A2S scanner uses bounded concurrent UDP, handles challenges and validates AppID 730',
    str_contains($publicScanner, 'stream_socket_client')
        && str_contains($publicScanner, 'stream_select')
        && str_contains($publicScanner, 'Source Engine Query')
        && str_contains($publicScanner, '"\\xFF\\xFF\\xFF\\xFF\\x41"')
        && str_contains($publicScanner, '"\\xFF\\xFF\\xFF\\xFF\\x49"')
        && str_contains($publicScanner, 'count($ports) > 128')
        && str_contains($publicScanWorker, '$scanner->queryBatch($host, $ports)')
        && str_contains($publicScanRepository, '=== 730')
);

$check(
    'Public scan protocol, storage contract, PDO repository and batch worker are modular',
    str_contains($publicScanStoreInterface, 'interface PublicServerBrowserScanStoreInterface')
        && str_contains($publicScanRepository, 'implements PublicServerBrowserScanStoreInterface')
        && str_contains($publicScanRepository, 'ensureTargetInventory')
        && str_contains($publicScanRepository, 'getDuePorts')
        && str_contains($publicScanRepository, 'saveResult')
        && str_contains($publicScanWorker, 'PublicServerBrowserScanStoreInterface $repository')
        && str_contains($publicScanWorker, 'ensureTargetInventory')
        && str_contains($publicScanWorker, 'getDuePorts')
        && str_contains($publicScanWorker, 'saveResult')
        && str_contains($publicScanWorker, 'array_chunk($dueRows, $batchSize)')
        && str_contains($publicScanRepository, 'GET_LOCK')
        && str_contains($publicScanRepository, 'RELEASE_LOCK')
        && !str_contains($publicWorker, 'INSERT IGNORE INTO public_server_scan_ports')
        && !str_contains($publicWorker, 'UPDATE public_server_scan_ports')
);

$check(
    'Portable CLI runner supports environment-specific settings and resource limits',
    str_contains($publicWorker, "PHP_SAPI !== 'cli'")
        && str_contains($settingsLoader, 'FRAGCITY_SETTINGS_FILE')
        && str_contains($publicWorker, 'FRAGCITY_SERVER_BROWSER_DB_PROFILE')
        && str_contains($publicWorker, 'FRAGCITY_SERVER_BROWSER_MAX_PER_RUN')
        && str_contains($publicWorker, 'FRAGCITY_SERVER_BROWSER_BATCH_SIZE')
        && str_contains($publicWorker, 'FRAGCITY_SERVER_BROWSER_TIME_LIMIT')
        && str_contains($publicWorker, 'PDO::getAvailableDrivers()')
        && str_contains($publicWorker, 'stream_socket_client')
        && str_contains($publicWorker, 'stream_select')
);

$check(
    'Public scanner Cron is CLI-only and delegates bounded scanning to the worker service',
    str_contains($publicWorker, "PHP_SAPI !== 'cli'")
        && str_contains($publicWorker, 'new PublicServerBrowserScanRepository($pdo)')
        && str_contains($publicWorker, 'new PublicServerBrowserScanWorker($repository)')
        && str_contains($publicWorker, '$worker->run($maxPortsPerRun, $batchSize)')
        && str_contains($publicScanWorker, '$maxPortsPerRun = max(1, min(4096, $maxPortsPerRun))')
        && str_contains($publicScanWorker, '$batchSize = max(1, min(128, $batchSize))')
        && str_contains($publicScanRepository, 'COALESCE(:server_name, server_name)')
        && str_contains($publicScanRepository, 'COALESCE(:app_id, app_id)')
        && str_contains($publicScanRepository, 'next_query_at')
        && str_contains($publicScanRepository, 'retry_offline_seconds')
);

$check(
    'Public scanner migration is MariaDB-compatible and seeds the requested target range',
    str_contains($publicMigration, 'CREATE TABLE IF NOT EXISTS public_server_scan_targets')
        && str_contains($publicMigration, 'CREATE TABLE IF NOT EXISTS public_server_scan_ports')
        && str_contains($publicMigration, "'85.185.40.85', 27085, 29100")
        && str_contains($publicMigration, 'retry_offline_seconds')
        && str_contains($publicMigration, 'refresh_online_seconds')
        && !preg_match('/\\bCHECK\\s*\\(/i', $publicMigration)
        && str_contains($publicScanWorker, 'FILTER_VALIDATE_IP')
        && str_contains($publicScanWorker, '($portEnd - $portStart) > 4095')
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
    'Pterodactyl Monitoring is a distinct signed, on-demand Linux API route',
    str_contains($index, "'/v1/pterodactyl-monitoring'")
        && str_contains($index, '$config[\'pterodactyl_monitoring\'][\'shared_secret\']')
        && str_contains($index, "action !== 'get_snapshot'")
        && str_contains($index, 'new PterodactylMonitoringService($config)')
        && str_contains($linuxConfig, "'pterodactyl_monitoring' =>")
        && str_contains($linuxConfig, "'enabled' => false")
        && str_contains($nginxApi, 'location = /v1/pterodactyl-monitoring')
);

$check(
    'Pterodactyl monitoring Application client is read-only and explicitly allowlists nodes and CS2 servers',
    str_contains($monitoringPanelClient, 'CURLOPT_HTTPGET => true')
        && str_contains($monitoringPanelClient, 'Authorization: Bearer ')
        && str_contains($monitoringPanelClient, '/api/application/nodes/')
        && str_contains($monitoringPanelClient, '/configuration')
        && str_contains($monitoringPanelClient, 'allowed_node_ids')
        && str_contains($monitoringPanelClient, 'allowed_egg_ids')
        && str_contains($monitoringPanelClient, 'allowed_docker_images')
        && !str_contains($monitoringPanelClient, 'CURLOPT_POST => true')
);

$check(
    'Wings monitoring client reads system and server resources with protected bearer auth',
    str_contains($monitoringWingsClient, 'Authorization: Bearer ')
        && str_contains($monitoringWingsClient, '/api/system?v=2')
        && str_contains($monitoringWingsClient, '/api/servers')
        && str_contains($monitoringWingsClient, 'isPrivateHost')
        && str_contains($monitoringWingsClient, 'CURLOPT_SSL_VERIFYPEER => true')
        && !str_contains($monitoringWingsClient, 'CURLOPT_POST => true')
);

$check(
    'Host CPU/RAM/disk sampling is attached only to the explicitly configured local node',
    str_contains($monitoringHostMetrics, '/proc/stat')
        && str_contains($monitoringHostMetrics, '/proc/meminfo')
        && str_contains($monitoringHostMetrics, 'disk_total_space')
        && str_contains($monitoringHostMetrics, 'disk_free_space')
        && str_contains($monitoringService, '$localNodeId > 0 && $localNodeId === $nodeId')
        && str_contains($monitoringService, 'host_metrics')
);

$check(
    'Pterodactyl monitoring service returns sanitized node and CS2 container metrics',
    str_contains($monitoringService, 'getNodeWingsConfiguration')
        && str_contains($monitoringService, 'getSystemInformation')
        && str_contains($monitoringService, 'getServers()')
        && str_contains($monitoringService, 'memory_bytes')
        && str_contains($monitoringService, 'cpu_absolute')
        && str_contains($monitoringService, 'disk_bytes')
        && str_contains($monitoringService, 'network_rx_bytes')
        && !str_contains($monitoringService, 'application_api_key')
);

$check(
    'Website monitoring endpoint remains admin-only and keeps Pterodactyl secrets server-side',
    str_contains($monitoringSiteClient, 'integrations.pterodactyl_monitoring_api.shared_secret')
        && str_contains($monitoringSiteClient, '/v1/pterodactyl-monitoring')
        && str_contains($monitoringSiteRepository, 'new PterodactylMonitoringApiClient()')
        && str_contains($monitoringSiteEndpoint, "\$_SESSION['role']")
        && str_contains($monitoringSiteEndpoint, "!== 'admin'")
        && str_contains($monitoringSiteEndpoint, 'session_write_close()')
        && !str_contains($monitoringSiteClient, 'application_api_key')
        && !str_contains($monitoringSiteClient, 'wings_token')
        && (($monitoringSettings['integrations']['pterodactyl_monitoring_api']['base_url'] ?? null) === 'https://api.fragcity.ir')
        && str_contains($monitoringDocs, 'no browser monitoring page is shipped')
);

$check(
    'The legacy CS2 admin panel directory, links, settings and route aliases are removed',
    !is_dir($root . DIRECTORY_SEPARATOR . 'panels')
        && !str_contains($siteHeaderMarkup, 'shockAdminPanel')
        && !str_contains($siteHeaderMarkup, 'panels/')
        && !str_contains($websiteIndex, 'adminprofile')
        && !str_contains($websiteIndex, 'panels/cs2')
        && !str_contains($testHarnessIndex, 'adminprofile')
        && !str_contains($testHarnessIndex, 'panels/cs2')
        && !str_contains($websiteSettingsRaw, 'panels.cs2.')
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
    'Pterodactyl standby client is read-only and selects the Application API primary Allocation by ID',
    str_contains($standbyPterodactylClient, 'CURLOPT_HTTPGET => true')
        && str_contains($standbyPterodactylClient, 'Authorization: Bearer ')
        && str_contains($standbyPterodactylClient, 'allowed_egg_ids')
        && str_contains($standbyPterodactylClient, 'allowed_docker_images')
        && str_contains($standbyPterodactylClient, "['allocation']")
        && str_contains($standbyPterodactylClient, '(int)$allocationId === (int)$primaryAllocationId')
        && str_contains($standbyPterodactylClient, 'read access to Allocations')
        && !str_contains($standbyPterodactylClient, 'CURLOPT_POST => true')
        && str_contains($standbyDiscovery, '$this->a2s->queryBatch')
        && str_contains($standbyA2S, 'queryBatch')
        && str_contains($standbyA2S, 'stream_select')
        && str_contains($standbyA2S, 'Source Engine Query')
        && str_contains($standbyCache, 'getOrRefresh')
        && str_contains($standbyCache, 'cache_ttl_seconds')
        && str_contains($standbyA2STest, 'queryBatch')
        && str_contains($standbyAllocationTest, "'allocation' => 77")
        && str_contains($standbyAllocationTest, 'primary Allocation was selected')
        && str_contains($standbyDocs, 'does not require Cron')
);

$check(
    'A2S_PLAYER parser reads names, signed scores and connection durations without fabricating Steam IDs',
    str_contains($standbyA2SPlayers, "\\xFF\\xFF\\xFF\\xFF\\x55")
        && str_contains($standbyA2SPlayers, "\\xFF\\xFF\\xFF\\xFF\\x44")
        && str_contains($standbyA2SPlayers, "unpack('gvalue'")
        && str_contains($standbyA2SPlayers, "'connected_seconds'")
        && str_contains($standbyA2SPlayers, "'steam_id' => null")
        && str_contains($standbyA2SPlayers, 'parseResponse')
        && str_contains($standbyA2SPlayersTest, 'Signed negative score is parsed')
        && str_contains($standbyA2SPlayersTest, 'SteamID is not fabricated')
);

$check(
    'Player queries resolve an opaque server ID to an allowlisted Pterodactyl primary Allocation',
    str_contains($standbyDiscovery, 'public function queryPlayers(string $serverId)')
        && str_contains($standbyDiscovery, "hash('sha256', (string)\$candidate['server_uuid'] . ':' . (int)\$candidate['allocation_id'])")
        && str_contains($standbyDiscovery, '$this->a2sPlayers->query($queryHost, $port)')
        && str_contains($standbyDiscovery, 'node_query_hosts')
        && !str_contains($standbyDiscovery, "\$_GET")
);

$check(
    'Player-list action uses the existing signed Standby route and validates the server identifier',
    str_contains($index, "\$action === 'get_players'")
        && str_contains($index, "'invalid_server_id'")
        && str_contains($index, '$discovery->queryPlayers($serverId)')
        && str_contains($standbyApiClient, "'action' => 'get_players'")
        && str_contains($standbyApiClient, 'hash_hmac')
        && str_contains($standbyRepository, 'getPlayers(string $serverId)')
);

$check(
    'Public player AJAX endpoint is read-only, same-origin constrained and never accepts a host or port',
    str_contains($standbySitePlayersEndpoint, "REQUEST_METHOD")
        && str_contains($standbySitePlayersEndpoint, 'HTTP_SEC_FETCH_SITE')
        && str_contains($standbySitePlayersEndpoint, "['server_id']")
        && str_contains($standbySitePlayersEndpoint, 'PterodactylStandbyRepository')
        && !str_contains($standbySitePlayersEndpoint, "['host']")
        && !str_contains($standbySitePlayersEndpoint, "['port']")
);

$check(
    'Standby cURL client releases CurlHandle without the PHP 8.5-deprecated curl_close() call',
    str_contains($standbyApiClient, 'curl_exec($handle)')
        && str_contains($standbyApiClient, 'unset($handle)')
        && !str_contains($standbyApiClient, 'curl_close(')
);

$check(
    'Server Browser desaturates offline cards and does not mislabel server-side RTT as visitor ping',
    str_contains($serverListPage, "sl-server-card--offline")
        && str_contains($serverListPage, "sl-metric--client-ping")
        && str_contains($serverListPage, "fa-signal")
        && str_contains($serverListPage, "Ping شما")
        && str_contains($serverListPage, "data-sl-client-ping")
        && str_contains($serverListPage, "مرورگر به‌تنهایی نمی‌تواند UDP")
        && !str_contains($serverListPage, "A2S RTT")
        && str_contains($serverListStylesheet, '.sl-server-card[data-status="offline"]')
        && str_contains($serverListStylesheet, 'filter: grayscale(1) saturate(0);')
);

$check(
    'Server Browser defaults to Online and does not sort the visitor by A2S RTT',
    str_contains($serverListPage, 'data-sl-filter="online" aria-pressed="true"')
        && str_contains($standbyBrowserJavascript, "let statusFilter = 'online';")
        && str_contains($standbyBrowserJavascript, "statusFilter = 'online';")
        && !str_contains($serverListPage, 'query-asc')
        && !str_contains($standbyBrowserJavascript, 'query-asc')
);

$check(
    'Mobile header places the hamburger to the right of the profile and stays graphite-filled until page top',
    str_contains($siteHeaderMarkup, 'id="mobileNavOpen"')
        && str_contains($siteHeaderMarkup, 'id="shockUserDropdown"')
        && str_contains($siteHeaderStylesheet, 'flex-direction: row-reverse !important;')
        && str_contains($siteHeaderStylesheet, '.shock-header.is-scroll-down')
        && str_contains($siteHeaderStylesheet, 'background: #0D0D0D !important;')
        && str_contains($headerJavascript, "siteHeader.classList.add('is-scrolled', 'is-scroll-down')")
        && !str_contains($headerJavascript, "remove('is-scroll-down')")
);

$check(
    'WeaponPaints category active states share the FragCity orange and mobile team/search controls align',
    str_contains($weaponPaintsStylesheet, '.wp-category-switcher--unified > .wp-tab[data-bs-target].is-active')
        && str_contains($weaponPaintsStylesheet, 'background: rgba(249, 115, 22, .12) !important;')
        && str_contains($weaponPaintsStylesheet, 'height: 42px !important;')
        && str_contains($weaponPaintsStylesheet, '.wp-team-btn')
);

$check(
    'Footer routes use canonical page slugs and every linked destination has a real page',
    str_contains($footerMarkup, 'index.php?page=servers_vps')
        && str_contains($footerMarkup, 'data-slug="servers_vps"')
        && str_contains($footerMarkup, 'index.php?page=skins')
        && str_contains($footerMarkup, 'index.php?page=launcher')
        && str_contains($footerMarkup, 'index.php?page=support')
        && str_contains($footerMarkup, 'index.php?page=usermanual')
        && str_contains($footerMarkup, 'index.php?page=rules')
        && str_contains($footerMarkup, 'index.php?page=privacy')
        && is_file($root . '/pages/launcher.php')
        && is_file($root . '/pages/serverlist.php')
        && is_file($root . '/pages/weaponpaints.php')
);

$check(
    'Footer and legacy route aliases load their canonical page CSS/JS through the AJAX asset registry',
    str_contains($websiteIndex, "'support' => [")
        && str_contains($websiteIndex, "'usermanual' => [")
        && str_contains($websiteIndex, "'rules' => [")
        && str_contains($websiteIndex, "'privacy' => [")
        && str_contains($websiteIndex, "'about' => [")
        && str_contains($websiteIndex, "'launcher' => [")
        && str_contains($websiteIndex, "'servers' => 'servers_vps'")
        && str_contains($websiteIndex, "'skins_vps' => 'skins'")
        && str_contains($headerJavascript, "servers: 'servers_vps'")
        && str_contains($headerJavascript, "skins_vps: 'skins'")
        && !str_contains($supportPage, '<link rel="stylesheet"')
        && !str_contains($supportPage, '<script src="js/support.js"')
        && !str_contains($userManualPage, '<link rel="stylesheet"')
        && !str_contains($userManualPage, '<script src="js/usermanual.js"')
        && !str_contains($rulesPage, '<link rel="stylesheet"')
        && !str_contains($rulesPage, '<script src="js/rules.js"')
        && !str_contains($privacyPage, '<link rel="stylesheet"')
        && !str_contains($privacyPage, '<script src="js/privacy.js"')
        && !str_contains($aboutPage, '<link rel="stylesheet"')
        && !str_contains($aboutPage, '<script src="js/about.js"')
        && str_contains($launcherPage, 'در حال آماده‌سازی')
        && str_contains($launcherStylesheet, '.launcher-page')
);

$check(
    'Footer trust badges have a fixed square image viewport',
    str_contains($footerStylesheet, 'width: 54px !important;')
        && str_contains($footerStylesheet, 'height: 54px !important;')
        && str_contains($footerStylesheet, 'object-fit: contain !important;')
);

$check(
    'Responsive header uses the tracked logoresponsive asset without replacing the desktop logo',
    str_contains($siteHeaderMarkup, '<picture class="shock-logo-picture">')
        && str_contains($siteHeaderMarkup, 'srcset="/img/logoresponsive.png"')
        && str_contains($siteHeaderMarkup, 'assets/images/logo/main.png')
        && is_file($root . '/img/logoresponsive.png')
        && str_contains($siteHeaderStylesheet, '.shock-logo-picture')
);

$check(
    'WeaponPaints team switcher and search input have aligned outer dimensions',
    str_contains($weaponPaintsStylesheet, '.wp-app .wp-header .wp-team-switcher')
        && str_contains($weaponPaintsStylesheet, 'height: 48px !important;')
        && str_contains($weaponPaintsStylesheet, 'height: 38px !important;')
        && str_contains($weaponPaintsStylesheet, '.wp-app .wp-header .wp-tabs > .wp-search-wrap')
        && str_contains($weaponPaintsStylesheet, 'height: 46px !important;')
        && str_contains($weaponPaintsStylesheet, 'grid-template-areas:')
);

$check(
    'Sticker and Keychain modal frames, search headers, split panes and mobile tabs match Paint selection',
    str_contains($weaponPaintsStylesheet, 'body > #skinModal .modal-content,')
        && str_contains($weaponPaintsStylesheet, 'body > #stickerModal .modal-content,')
        && str_contains($weaponPaintsStylesheet, 'body > #keychainModal .modal-content')
        && str_contains($weaponPaintsStylesheet, 'grid-template-columns: minmax(260px, 280px) minmax(0, 1fr) !important;')
        && str_contains($weaponPaintsStylesheet, 'body > #stickerModal .wp-modal-header-tools')
        && str_contains($weaponPaintsStylesheet, 'body > #keychainModal .wp-modal-header-tools')
        && str_contains($weaponPaintsStylesheet, '.wp-addon-list-pane.is-mobile-active')
        && str_contains($weaponPaintsStylesheet, '.wp-addon-properties.is-mobile-active')
        && str_contains($weaponPaintsJavascript, "label: selectionLabel")
        && str_contains($weaponPaintsJavascript, "label: 'افزودن ویژگی‌ها'")
);

$check(
    'Server Browser exposes player controls and modal columns in the requested order',
    str_contains($publicPage, 'data-sl-players')
        && str_contains($publicPage, 'id="slPlayerModal"')
        && str_contains($publicPage, 'اسم</th>')
        && str_contains($publicPage, 'استیم آیدی</th>')
        && str_contains($publicPage, 'مدت اتصال</th>')
        && str_contains($publicPage, 'امتیاز</th>')
        && str_contains($publicPage, 'لول FACEIT</th>')
        && str_contains($standbyBrowserJavascript, '/api/public-server-players.php?server_id=')
        && str_contains($standbyBrowserJavascript, 'connected_seconds')
        && str_contains($standbyBrowserJavascript, 'faceit_level')
        && str_contains($standbyBrowserCss, '.sl-player-modal-panel')
        && str_contains($standbyBrowserCss, '.sl-players-btn')
);

$check(
    'Runtime example and standby deployment documentation use the verified Pterodactyl Panel origin',
    str_contains($linuxConfig, "'base_url' => 'https://panel.fragcity.ir'")
        && str_contains($standbyDocs, "'base_url' => 'https://panel.fragcity.ir'")
        && str_contains($standbyDocs, 'A2S_PLAYER')
        && str_contains($standbyDocs, 'does not return SteamID')
);

$check(
    'Standby snapshot route and Nginx example use a distinct Pterodactyl Standby path',
    str_contains($index, "'/v1/pterodactyl-standby'")
        && str_contains($index, '$config[\'pterodactyl_standby\'][\'shared_secret\']')
        && str_contains($index, 'new PterodactylStandbySnapshotCache($serverConfig)')
        && str_contains($index, 'getOrRefresh')
        && str_contains($nginxApi, 'location = /v1/pterodactyl-standby')
        && str_contains($nginxApi, 'SCRIPT_FILENAME $document_root/index.php')
);

$check(
    'HTTP bootstrap and HTTPS API Nginx examples route every active endpoint to the fixed front controller',
    str_contains($nginxApiBootstrap, 'location = /v1/weaponpaints')
        && str_contains($nginxApiBootstrap, 'location = /v1/pterodactyl-standby')
        && str_contains($nginxApiBootstrap, 'location = /v1/pterodactyl-monitoring')
        && str_contains($nginxApi, 'location = /v1/weaponpaints')
        && str_contains($nginxApi, 'location = /v1/pterodactyl-standby')
        && str_contains($nginxApi, 'location = /v1/pterodactyl-monitoring')
        && str_contains($nginxApiBootstrap, 'SCRIPT_FILENAME $document_root/index.php')
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

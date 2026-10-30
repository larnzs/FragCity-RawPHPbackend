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

    // Runtime settings examples are tracked templates, never live credentials.
    $websiteSettingsRaw = $read('settings/settings.example.json');
    $websiteSettings = json_decode($websiteSettingsRaw, true, 512, JSON_THROW_ON_ERROR);
    $weaponPaintsSettingsRaw = $read('settings/weaponpaints-api.integration.example.json');
    $weaponPaintsSettings = json_decode($weaponPaintsSettingsRaw, true, 512, JSON_THROW_ON_ERROR);
    $standbySettingsRaw = $read('settings/pterodactyl-standby.integration.example.json');
    $standbySettings = json_decode($standbySettingsRaw, true, 512, JSON_THROW_ON_ERROR);
    $standbyApiClient = $read('class/PterodactylStandbyApiClient.php');
    $standbyRepository = $read('class/PterodactylStandbyRepository.php');
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
    $monitoringPage = $read('panels/cs2/monitoring.php');
    $monitoringSidebar = $read('panels/cs2/sidebar.php');
    $monitoringPanelRouter = $read('panels/cs2/index.php');
    $monitoringJavascript = $read('panels/cs2/js/monitoring.js');
    $monitoringCss = $read('panels/cs2/css/monitoring.css');
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
    'Website monitoring endpoint and panel are admin-only and keep Pterodactyl secrets server-side',
    str_contains($monitoringSiteClient, 'integrations.pterodactyl_monitoring_api.shared_secret')
        && str_contains($monitoringSiteClient, '/v1/pterodactyl-monitoring')
        && str_contains($monitoringSiteRepository, 'new PterodactylMonitoringApiClient()')
        && str_contains($monitoringSiteEndpoint, "\$_SESSION['role']")
        && str_contains($monitoringSiteEndpoint, "!== 'admin'")
        && str_contains($monitoringSiteEndpoint, 'session_write_close()')
        && str_contains($monitoringPage, "\$_SESSION['role']")
        && str_contains($monitoringPage, "!== 'admin'")
        && str_contains($monitoringPage, 'data-endpoint="/api/admin-pterodactyl-monitoring.php"')
        && !str_contains($monitoringJavascript, 'application_api_key')
        && !str_contains($monitoringJavascript, 'shared_secret')
);

$check(
    'Monitoring page is reachable from the admin panel and refreshes without Cron',
    str_contains($monitoringSidebar, "'monitoring'")
        && str_contains($monitoringSidebar, 'monitoring.php')
        && str_contains($monitoringPanelRouter, "'monitoring' =>")
        && str_contains($monitoringJavascript, 'setInterval')
        && str_contains($monitoringJavascript, '15000')
        && str_contains($monitoringCss, '.fc-monitor-node-card')
        && str_contains($monitoringDocs, 'No Cron job')
        && (($monitoringSettings['integrations']['pterodactyl_monitoring_api']['base_url'] ?? null) === 'https://api.fragcity.ir')
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

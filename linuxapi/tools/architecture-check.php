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
    $weaponPaintsGlovesIcon = $read('img/weaponpaintsicons/gloves.svg');
    $weaponPaintsJavascript = $read('js/weaponpaints.js');
    $weaponPaintsStylesheet = $read('css/weaponpaints.css');
    $pinCatalog = json_decode($read('data/collectibles_en.json'), true, 512, JSON_THROW_ON_ERROR);
    $apiClient = $read('class/FragCityLinuxApiClient.php');
    $ranksRepository = $read('class/RanksRepository.php');
    $ranksService = $read('linuxapi/src/Ranks/RanksService.php');
    $ranksServiceTest = $read('linuxapi/tests/ranks-service-test.php');
    $ranksSmokeTest = $read('tools/ranks-smoke-test.php');
    $leaderboardPage = $read('pages/leaderboard.php');
    $profilePage = $read('pages/profile.php');
    $profileJavascript = $read('js/profile.js');
    $profileStylesheet = $read('css/profile.css');
    $steamLoginPage = $read('steam/steam_login.php');
    $steamLoginView = $read('pages/login.php');
    $steamRegisterView = $read('pages/register.php');
    $profileIntegrationsSmokeTest = $read('tools/profile-integrations-smoke-test.php');
    $vipCoreSiteRepository = $read('class/VipCoreRepository.php');
    $vipCoreSiteEndpoint = $read('api/vip-test-purchase.php');
    $vipCorePage = $read('pages/vip.php');
    $vipCoreJavascript = $read('js/vip.js');
    $vipCoreService = $read('linuxapi/src/VIPCore/VipCoreService.php');
    $vipCoreServiceTest = $read('linuxapi/tests/vipcore-service-test.php');
    $vipTestPurchaseEndpoint = $read('api/vip-test-purchase.php');
    $vipStorefrontPage = $read('pages/vip.php');
    $vipCoreSmokeTest = $read('tools/vipcore-smoke-test.php');
    $settingsLoader = $read('settings/loader.php');

    // Runtime settings examples are tracked templates, never live credentials.
    $websiteSettingsRaw = $read('settings/settings.example.json');
    $websiteSettings = json_decode($websiteSettingsRaw, true, 512, JSON_THROW_ON_ERROR);
    $weaponPaintsSettingsRaw = $read('settings/weaponpaints-api.integration.example.json');
    $weaponPaintsSettings = json_decode($weaponPaintsSettingsRaw, true, 512, JSON_THROW_ON_ERROR);
    $standbySettingsRaw = $read('settings/pterodactyl-standby.integration.example.json');
    $standbySettings = json_decode($standbySettingsRaw, true, 512, JSON_THROW_ON_ERROR);
    $standbyApiClient = $read('class/PterodactylStandbyApiClient.php');
    $linuxApiClient = $read('class/FragCityLinuxApiClient.php');
    $standbyRepository = $read('class/PterodactylStandbyRepository.php');
    $serverListPage = $read('pages/serverlist.php');
    $serverListStylesheet = $read('css/serverlist.css');
    $statusPage = $read('pages/status.php');
    $statusRepository = $read('class/UptimeKumaStatusRepository.php');
    $publicStatusEndpoint = $read('api/public-status.php');
    $statusJavascript = $read('js/status.js');
    $statusStylesheet = $read('css/status.css');
    $uptimeKumaCompose = $read('deploy/uptime-kuma/compose.yaml');
    $uptimeKumaNginx = $read('deploy/uptime-kuma/nginx-status.fragcity.ir.conf.example');
    $uptimeKumaDocs = $read('docs/UPTIME-KUMA-STATUS-PAGE.md');
    $siteHeaderMarkup = $read('includes/header.php');
    $siteHeaderStylesheet = $read('css/header.css');
    $headerJavascript = $read('js/header.js');
    $motionController = $read('js/motion.js');
    $websiteIndex = $read('index.php');
    // The legacy test harness is intentionally absent in current deployments.
    // Keep the retired-route regression assertion optional instead of failing
    // the entire architecture check when the optional file is not present.
    $testHarnessPath = $root . DIRECTORY_SEPARATOR . 'tst' . DIRECTORY_SEPARATOR . 'index.php';
    $testHarnessIndex = is_file($testHarnessPath) && is_readable($testHarnessPath)
        ? (string) file_get_contents($testHarnessPath)
        : '';
    $footerMarkup = $read('includes/footer.php');
    $footerStylesheet = $read('css/footer.css');
    $routesMarkup = $read('includes/routes.php');
    $htaccessMarkup = $read('.htaccess');
    $robotsMarkup = $read('robots.txt');
    $sitemapMarkup = $read('sitemap.xml');
    $notFoundMarkup = $read('pages/404.php');
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
    $simpleAdminSiteRepository = $read('class/SimpleAdminRepository.php');
    $simpleAdminLookup = $read('linuxapi/src/SimpleAdmin/SimpleAdminPlayerLookup.php');
    $simpleAdminBanlist = $read('linuxapi/src/SimpleAdmin/SimpleAdminBanlist.php');
    $banlistPage = $read('pages/banlist.php');
    $simpleAdminDocs = $read('docs/CONFIGURATION.md');
    $simpleAdminMigration = $read('linuxapi/migrations/20261031_simpleadmin_player_lookup.sql');
    $faceitApiClient = $read('class/FaceitApiClient.php');
    $faceitSettingsRaw = $read('settings/faceit-api.integration.example.json');
    $faceitSettings = json_decode($faceitSettingsRaw, true, 512, JSON_THROW_ON_ERROR);
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
    $serverGuidePage = $read('pages/serverguide.php');
    $homePage = $read('pages/home.php');
    $serverGuideStylesheet = $read('css/serverguide.css');
    $serverGuideJavascript = $read('js/serverguide.js');
    $motionJavascript = $read('js/motion.js');
    $serverGuideDocs = $read('docs/SERVER-GUIDE.md');
    $serverGuideCatalogRaw = $read('data/server-guide.json');
    $serverGuideCatalog = json_decode($serverGuideCatalogRaw, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . PHP_EOL);
    exit(2);
}

$serverGuideCategoryIds = [];
foreach (($serverGuideCatalog['categories'] ?? []) as $category) {
    if (!is_array($category) || !is_string($category['id'] ?? null) || $category['id'] === ''
        || isset($serverGuideCategoryIds[$category['id']])) {
        throw new RuntimeException('Server Guide catalog contains an invalid or duplicate category ID.');
    }
    $serverGuideCategoryIds[$category['id']] = true;
}

$serverGuideItemIds = [];
$serverGuideRiskLevels = ['high', 'action', 'info', 'safe'];
$serverGuideRiskCounts = array_fill_keys($serverGuideRiskLevels, 0);
$serverGuideCatalogValid = count($serverGuideCategoryIds) > 0
    && is_array($serverGuideCatalog['items'] ?? null)
    && count($serverGuideCatalog['items']) > 0;
foreach (($serverGuideCatalog['items'] ?? []) as $item) {
    $sourceUrl = is_array($item) ? trim((string)($item['source_url'] ?? '')) : '';
    $sourceParts = $sourceUrl !== '' ? parse_url($sourceUrl) : false;
    $sourceIsSafe = $sourceUrl === ''
        || (filter_var($sourceUrl, FILTER_VALIDATE_URL) !== false
            && is_array($sourceParts)
            && strtolower((string)($sourceParts['scheme'] ?? '')) === 'https'
            && trim((string)($sourceParts['host'] ?? '')) !== '');
    $availability = is_array($item) ? (string)($item['availability'] ?? '') : '';
    $risk = is_array($item) ? (string)($item['risk'] ?? '') : '';

    if (!is_array($item)
        || !is_string($item['id'] ?? null)
        || $item['id'] === ''
        || isset($serverGuideItemIds[$item['id']])
        || !isset($serverGuideCategoryIds[(string)($item['category'] ?? '')])
        || !in_array((string)($item['type'] ?? 'command'), ['command', 'setting', 'mode'], true)
        || trim((string)($item['command'] ?? '')) === ''
        || trim((string)($item['description'] ?? '')) === ''
        || !$sourceIsSafe
        || !in_array($risk, $serverGuideRiskLevels, true)
        || ($availability !== '' && !in_array($availability, ['default', 'config', 'mode', 'verify', 'disabled'], true))) {
        $serverGuideCatalogValid = false;
        break;
    }
    $serverGuideItemIds[$item['id']] = true;
    ++$serverGuideRiskCounts[$risk];
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
    'VIPCore entitlements stay behind the signed Linux API and reuse the existing plugins database account',
    str_contains($index, "'/v1/vipcore'")
        && str_contains($linuxConfig, "'name' => 'GLOBAL_vip'")
        && str_contains($linuxConfig, "'username' => 'plugins'")
        && substr_count($linuxConfig, "'password' => $pluginsDbPassword") >= 3
        && !str_contains($linuxConfig, 'fragcity_vip_api')
        && str_contains($linuxConfig, "'duration_seconds' => 3600")
        && str_contains($apiClient, "'/v1/vipcore'")
        && str_contains($apiClient, "hash_hmac('sha256'")
        && str_contains($vipCoreService, "FROM `vip_users`")
        && str_contains($vipCoreService, "INSERT INTO `vip_users`")
        && str_contains($vipCoreService, "76561197960265728")
        && str_contains($vipCoreService, "membership_already_active")
        && !str_contains($vipCoreSiteRepository, 'new PDO')
        && str_contains($vipCoreSiteEndpoint, 'X_FRAGCITY_CSRF')
        && str_contains($vipCoreSiteEndpoint, 'user_logged_in')
        && str_contains($vipCorePage, 'new VipCoreRepository()')
        && !str_contains($vipCorePage, 'SELECT vip_level, vip_expire FROM users')
        && str_contains($vipCoreJavascript, '/api/vip-test-purchase.php')
        && str_contains($vipCoreJavascript, '[1, 2, 3]')
        && str_contains($vipCoreSmokeTest, "'/v1/vipcore'")
        && str_contains($nginxApiBootstrap, 'location = /v1/vipcore')
        && str_contains($nginxApi, 'location = /v1/vipcore')
        && str_contains($vipCoreServiceTest, 'Inactive tier 4 cannot be granted')
        && str_contains($websiteSettingsRaw, 'VIP status and entitlements are read from VIPCore')
        && !isset($websiteSettings['files']['pages.vip']['database_profile'])
);

$check(
    'Free VIP test grants are restricted to admin sessions and explicit Linux SteamID allowlist',
    str_contains($index, 'isTestGrantAllowed')
        && str_contains($index, 'allowed_steamids')
        && str_contains($linuxConfig, "'allowed_steamids' => []")
        && str_contains($vipTestPurchaseEndpoint, "!== 'admin'")
        && str_contains($vipStorefrontPage, '$isVipTestAdmin')
        && str_contains($vipTestPurchaseEndpoint, 'test_grant_not_allowed')
        && str_contains($vipCoreServiceTest, 'require an exact allowlisted SteamID64')
);

$check(
    'Ranks leaderboard and profile use the signed read-only Linux API and reuse the existing plugins database account',
    str_contains($index, "'/v1/ranks'")
        && str_contains($linuxConfig, "'name' => 'GLOBAL_ranks'")
        && str_contains($linuxConfig, "'username' => 'plugins'")
        && substr_count($linuxConfig, "'password' => $pluginsDbPassword") >= 3
        && !str_contains($linuxConfig, 'fragcity_ranks_api')
        && str_contains($apiClient, "'/v1/ranks'")
        && str_contains($ranksRepository, "'/v1/ranks'")
        && !str_contains($ranksRepository, 'new PDO')
        && str_contains($ranksService, 'FROM `lvl_base`')
        && str_contains($ranksService, 'steamId64ToSteam2')
        && str_contains($ranksService, 'ORDER BY')
        && !str_contains($ranksService, 'UPDATE `lvl_base`')
        && !str_contains($ranksService, 'INSERT INTO `lvl_base`')
        && str_contains($ranksServiceTest, 'STEAM_1:1:127247282')
        && str_contains($ranksSmokeTest, 'new RanksRepository()')
        && str_contains($nginxApiBootstrap, 'location = /v1/ranks')
        && str_contains($nginxApi, 'location = /v1/ranks')
        && str_contains($leaderboardPage, 'new RanksRepository()')
        && !str_contains($leaderboardPage, 'RemoteRanksDatabase')
        && !str_contains($profilePage, 'RemoteRanksDatabase')
        && str_contains($profilePage, 'new RanksRepository()')
        && str_contains($websiteSettingsRaw, 'Signed Linux API')
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
        && str_contains($weaponPaintsStylesheet, '.wp-addon-workspace > .wp-addon-list-pane.is-mobile-active')
        && str_contains($weaponPaintsStylesheet, '.wp-addon-workspace > .wp-addon-properties.is-mobile-active')
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
        && !isset($websiteSettings['databases']['simpleadmin_plugin'])
        && !isset($websiteSettings['files']['database.simpleadmin_plugin'])
        && (($websiteSettings['integrations']['weaponpaints_api']['base_url'] ?? null) === 'https://api.fragcity.ir')
        && (($websiteSettings['integrations']['pterodactyl_standby_api']['base_url'] ?? null) === 'https://api.fragcity.ir')
        && (($websiteSettings['integrations']['pterodactyl_monitoring_api']['base_url'] ?? null) === 'https://api.fragcity.ir')
        && is_string($websiteSettings['integrations']['weaponpaints_api']['shared_secret'] ?? null)
        && is_string($websiteSettings['integrations']['pterodactyl_standby_api']['shared_secret'] ?? null)
        && is_string($websiteSettings['integrations']['pterodactyl_monitoring_api']['shared_secret'] ?? null)
        && is_string($websiteSettings['integrations']['faceit_api']['api_key'] ?? null)
);

$check(
    'Standalone website Integration examples are valid and document the matching Linux secrets',
    (($weaponPaintsSettings['integrations']['weaponpaints_api']['base_url'] ?? null) === 'https://api.fragcity.ir')
        && str_contains((string)($weaponPaintsSettings['integrations']['weaponpaints_api']['_comment'] ?? ''), 'shared_secret')
        && (($standbySettings['integrations']['pterodactyl_standby_api']['base_url'] ?? null) === 'https://api.fragcity.ir')
        && str_contains((string)($standbySettings['integrations']['pterodactyl_standby_api']['_comment'] ?? ''), 'pterodactyl_standby.shared_secret')
        && (($monitoringSettings['integrations']['pterodactyl_monitoring_api']['base_url'] ?? null) === 'https://api.fragcity.ir')
        && str_contains((string)($monitoringSettings['integrations']['pterodactyl_monitoring_api']['_comment'] ?? ''), 'pterodactyl_monitoring.shared_secret')
        && is_string($faceitSettings['integrations']['faceit_api']['api_key'] ?? null)
        && str_contains((string)($faceitSettings['integrations']['faceit_api']['_comment'] ?? ''), 'Server-side')
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
    'SimpleAdmin Ban List is API-backed with DB-authorized manager/admin IP fields',
    str_contains($simpleAdminSiteRepository, "'/v1/simpleadmin'")
        && str_contains($simpleAdminSiteRepository, 'resolve_player_names')
        && str_contains($simpleAdminSiteRepository, 'nameCounts')
        && str_contains($simpleAdminSiteRepository, 'normalizePlayerNameKey')
        && str_contains($simpleAdminSiteRepository, "'ambiguous'")
        && str_contains($simpleAdminSiteRepository, 'getBanlistRecords')
        && str_contains($linuxApiClient, 'Unsupported Linux API path.')
        && str_contains($index, 'get_records')
        && str_contains($index, 'new SimpleAdminBanlist($simplePdo)')
        && str_contains($simpleAdminLookup, 'FROM sa_renames')
        && str_contains($simpleAdminLookup, 'CAST(player_steamid AS CHAR)')
        && str_contains($simpleAdminLookup, 'queryRenamedCandidates')
        && str_contains($simpleAdminLookup, 'FROM sa_players_ips')
        && str_contains($simpleAdminLookup, 'BINARY name')
        && str_contains($simpleAdminLookup, 'CAST(steamid AS CHAR)')
        && str_contains($simpleAdminLookup, 'ambiguous')
        && !str_contains($simpleAdminLookup, 'address')
        && str_contains($simpleAdminBanlist, 'FROM sa_bans')
        && str_contains($simpleAdminBanlist, 'FROM sa_unbans')
        && str_contains($simpleAdminBanlist, 'FROM sa_mutes')
        && str_contains($simpleAdminBanlist, 'FROM sa_unmutes')
        && str_contains($simpleAdminBanlist, 'FROM sa_warns')
        && str_contains($simpleAdminBanlist, 'FROM sa_players_ips')
        && str_contains($simpleAdminBanlist, 'INET_NTOA(address)')
        && str_contains($simpleAdminBanlist, 'LIMIT :page_limit OFFSET :page_offset')
        && str_contains($simpleAdminBanlist, 'includeSensitive')
        && str_contains($banlistPage, 'getBanlistRecords')
        && str_contains($banlistPage, '$_SESSION')
        && str_contains($banlistPage, 'SELECT insmaster FROM users WHERE steamid64 = ? LIMIT 1')
        && str_contains($banlistPage, '$isAdmin || $isInsMaster')
        && str_contains($banlistPage, '$type === \'playerips\' && !$canViewPlayerIps')
        && str_contains($banlistPage, '$showSensitiveData = $canViewPlayerIps;')
        && str_contains($preflight, "'insmaster'")
        && !str_contains($banlistPage, 'RemoteBanlistDatabase')
        && !is_file($root . '/database/kyonxcbt_simpleadmin_plugin.php')
        && str_contains($linuxConfig, "'GLOBAL_simpleadmin'")
        && str_contains($linuxConfig, "Reuses the root database connection above; only the schema name changes.")
        && str_contains($linuxConfig, "'username' => 'plugins'")
        && str_contains($linuxConfig, "'password' => $pluginsDbPassword")
        && !str_contains($linuxConfig, 'column-level SELECT')
        && !str_contains($linuxConfig, "'fragcity_simpleadmin_api'")
        && !str_contains($linuxConfig, "'REPLACE_WITH_A_UNIQUE_READ_ONLY_DATABASE_PASSWORD'")
        && str_contains($simpleAdminDocs, 'GRANT SELECT ON GLOBAL_simpleadmin.*')
        && str_contains($simpleAdminDocs, 'EXACT_DB_HOST')
        && str_contains($simpleAdminDocs, 'get_records')
        && str_contains($simpleAdminMigration, 'idx_sa_players_ips_name_steamid_used_at')
        && str_contains($nginxApiBootstrap, 'location = /v1/simpleadmin')
        && str_contains($nginxApi, 'location = /v1/simpleadmin')
        && str_contains($standbySitePlayersEndpoint, 'new SimpleAdminRepository()')
        && str_contains($standbySitePlayersEndpoint, 'new FaceitApiClient()')
);

$check(
    'FACEIT levels are resolved server-side from SteamID64 and do not block the A2S player list',
    str_contains($faceitApiClient, 'Authorization: Bearer ')
        && str_contains($faceitApiClient, "'game' => 'cs2'")
        && str_contains($faceitApiClient, "'game_player_id' =>")
        && str_contains($faceitApiClient, "['skill_level']")
        && str_contains($standbySitePlayersEndpoint, 'new FaceitApiClient()')
        && str_contains($standbyBrowserJavascript, 'simpleadmin_unavailable')
        && str_contains($standbyBrowserJavascript, '/img/faceit/')
        && str_contains($standbyBrowserCss, '.sl-faceit-level-icon')
        && !str_contains($standbyBrowserJavascript, 'api_key')
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
    'Server Browser measures visitor-to-node HTTPS RTT separately from in-game UDP ping',
    str_contains($serverListPage, "sl-server-card--offline")
        && str_contains($serverListPage, "sl-metric--client-ping")
        && str_contains($serverListPage, "data-node-ping-url")
        && str_contains($serverListPage, "Ping شما تا نود")
        && str_contains($serverListPage, "پینگ UDP واقعی داخل بازی CS2 نیست")
        && str_contains($standbyBrowserJavascript, "mode: 'no-cors'")
        && str_contains($standbyBrowserJavascript, "performance.now()")
        && str_contains($standbyBrowserJavascript, "samples.length < 3")
        && str_contains($serverListStylesheet, 'data-ping-state="good"')
        && str_contains($serverListStylesheet, '.sl-server-card[data-status="offline"]')
        && str_contains($serverListStylesheet, 'filter: grayscale(1) saturate(0);')
);

$check(
    'Server Browser search hides matching cards and empty groups in list and grid views',
    str_contains($standbyBrowserJavascript, 'card.hidden = !isVisible;')
        && str_contains($serverListStylesheet, '#serverList .sl-server-card[hidden]')
        && str_contains($serverListStylesheet, '#serverList .sl-server-group[hidden]')
        && str_contains($serverListStylesheet, 'display: none !important;')
);

$check(
    'Server Browser defaults to hiding non-online servers and does not sort the visitor by A2S RTT',
    str_contains($serverListPage, 'data-sl-hide-offline checked')
        && str_contains($standbyBrowserJavascript, 'hideOffline.checked')
        && str_contains($standbyBrowserJavascript, "if (hideOffline && hideOffline.checked && !online) return false;")
        && !str_contains($serverListPage, 'query-asc')
        && !str_contains($standbyBrowserJavascript, 'query-asc')
);

$check(
    'Mobile header keeps its layout and frosted glass while scrolling in either direction',
    str_contains($siteHeaderMarkup, 'id="mobileNavOpen"')
        && str_contains($siteHeaderMarkup, 'id="shockUserDropdown"')
        && str_contains($siteHeaderStylesheet, 'flex-direction: row-reverse !important;')
        && str_contains($siteHeaderStylesheet, '.shock-header.is-scroll-up')
        && str_contains($siteHeaderStylesheet, '.shock-header.is-scroll-down')
        && str_contains($siteHeaderStylesheet, 'background: rgba(13, 13, 13, .58) !important;')
        && str_contains($siteHeaderStylesheet, 'backdrop-filter: blur(18px) saturate(125%) !important;')
        && str_contains($siteHeaderStylesheet, '.shock-header:not(.is-scroll-down):not(.is-scroll-up):not(.is-scrolled)')
        && str_contains($headerJavascript, "siteHeader.classList.add('is-scrolled')")
        && str_contains($headerJavascript, "siteHeader.classList.remove('is-scroll-down')")
        && str_contains($headerJavascript, "siteHeader.classList.remove('is-scroll-up')")
);

$check(
    'WeaponPaints category active states share the FragCity orange and mobile team/search controls align',
    str_contains($weaponPaintsStylesheet, '.wp-category-switcher--unified > .wp-tab[data-bs-target].is-active')
        && str_contains($weaponPaintsStylesheet, 'background: rgba(249, 115, 22, .12) !important;')
        && str_contains($weaponPaintsStylesheet, 'height: 42px !important;')
        && str_contains($weaponPaintsStylesheet, '.wp-team-btn')
);

$check(
    'Footer exposes canonical public routes and keeps decorative labels out of the heading outline',
    str_contains($footerMarkup, "fragcity_page_url('servers_vps')")
        && str_contains($footerMarkup, "fragcity_page_url('serverguide')")
        && str_contains($footerMarkup, "fragcity_page_url('buyserveropen')")
        && str_contains($footerMarkup, "fragcity_page_url('vip')")
        && str_contains($footerMarkup, "fragcity_page_url('status')")
        && str_contains($footerMarkup, "fragcity_page_url('support')")
        && str_contains($footerMarkup, '<p class="footer-heading">دسترسی سریع</p>')
        && !str_contains($footerMarkup, '<h2 class="footer-heading">')
        && is_file($root . '/pages/serverlist.php')
        && is_file($root . '/pages/weaponpaints.php')
);

$check(
    'Profile clean route, root-anchored redirects, and profile actions stay reachable',
    str_contains($routesMarkup, "'profile' => 'profile'")
        && str_contains($htaccessMarkup, 'RewriteRule ^profile/?$ index.php?page=profile')
        && str_contains($websiteIndex, "\$target = trim(\$fallback);")
        && str_contains($websiteIndex, "return '/' . ltrim(\$target, '/');")
        && str_contains($websiteIndex, "'profile' => 'profile'")
        && str_contains($headerJavascript, "'profile': 'profile'")
        && str_contains($profilePage, 'action="/profile/"')
        && substr_count($profileJavascript, "'/profile/'") >= 3
);

$check(
    'Profile images use root-absolute paths and FACEIT/Ranks API states are visible to the account owner',
    str_contains($profilePage, "'/img/banners/banner_'")
        && str_contains($profilePage, "'/img/avatars/avatar_'")
        && str_contains($profilePage, "'/img/steam-avatars/'")
        && str_contains($profilePage, "data-banner-url=\"<?= htmlspecialchars(\$bannerUrl,")
        && !str_contains($profilePage, "'assets/images/default_banner.jpg'")
        && str_contains($profilePage, 'new FaceitApiClient()')
        && str_contains($profilePage, 'data-api="faceit"')
        && str_contains($profilePage, 'data-api="ranks"')
        && str_contains($profilePage, "\$ranksApiStatus = 'player_not_found';")
        && str_contains($profilePage, "\$faceitApiStatusLabel")
        && str_contains($profilePage, "this.src='/img/logoresponsive.png';")
        && str_contains($siteHeaderMarkup, "return '/img/logoresponsive.png';")
        && str_contains($siteHeaderMarkup, "action=\"/\"")
        && str_contains($siteHeaderMarkup, "this.src='/img/logoresponsive.png';")
        && str_contains($profileStylesheet, '.profile-api-status[data-state="available"]')
        && str_contains($profileStylesheet, '.profile-api-status[data-state="api_unavailable"]')
);

$check(
    'Steam OpenID uses the configured canonical origin and login/register links are root-absolute',
    str_contains($steamLoginPage, "fragcity_settings('app.site_base_url'")
        && str_contains($steamLoginPage, "new LightOpenID(\$openidOrigin)")
        && str_contains($steamLoginPage, 'STEAM_DOMAIN_NAME')
        && str_contains($steamLoginPage, 'new LightOpenID($openidOrigin)')
        && str_contains($steamLoginPage, "REQUEST_SCHEME")
        && str_contains($steamLoginPage, "HTTP_X_FORWARDED_PROTO")
        && str_contains($steamLoginPage, "origin mismatch diagnostics")
        && str_contains($steamLoginPage, "domain mismatch diagnostics")
        && str_contains($steamLoginPage, "explode(")
        && !str_contains($steamLoginPage, 'new LightOpenID($domain)')
        && str_contains($steamLoginView, 'href="/steam/steam_login.php"')
        && str_contains($steamRegisterView, 'href="/steam/steam_login.php"')
        && str_contains($steamLoginView, 'action="/?page=login&amp;auth_modal=1"')
);

$check(
    'Profile integration diagnostics are CLI-only, test both APIs, and do not print runtime secrets',
    str_contains($profileIntegrationsSmokeTest, "PHP_SAPI !== 'cli'")
        && str_contains($profileIntegrationsSmokeTest, "new RanksRepository()")
        && str_contains($profileIntegrationsSmokeTest, "new FaceitApiClient()")
        && str_contains($profileIntegrationsSmokeTest, 'integrations.faceit_api.api_key')
        && str_contains($profileIntegrationsSmokeTest, 'integrations.weaponpaints_api.shared_secret')
        && !str_contains($profileIntegrationsSmokeTest, 'echo $faceitKey')
        && !str_contains($profileIntegrationsSmokeTest, 'echo $linuxSecret')
);

$check(
    'Canonical URL routing, sitemap, robots policy, staging noindex and 404 handling are present',
    str_contains($routesMarkup, 'function fragcity_page_url')
        && str_contains($htaccessMarkup, 'RewriteRule ^serverguide/?$ index.php?page=serverguide')
        && str_contains($htaccessMarkup, 'https://fragcity.ir')
        && str_contains($websiteIndex, 'rel="canonical"')
        && str_contains($websiteIndex, 'X-Robots-Tag: noindex')
        && str_contains($robotsMarkup, 'Sitemap: https://fragcity.ir/sitemap.xml')
        && str_contains($sitemapMarkup, 'https://fragcity.ir/serverguide/')
        && str_contains($notFoundMarkup, 'FRAGCITY / 404')
);

$check(
    'WeaponPaints inherits the Server List reveal, ambient, hover, selection and low-motion effects',
    str_contains($weaponPaintsStylesheet, 'Server List motion parity')
        && str_contains($weaponPaintsStylesheet, 'wpAmbientOrbit')
        && str_contains($weaponPaintsStylesheet, 'wpHeaderAccent')
        && str_contains($weaponPaintsStylesheet, '.wp-app .wp-card::after')
        && str_contains($weaponPaintsStylesheet, 'wpSelectionGlow')
        && str_contains($weaponPaintsStylesheet, '.wp-app .wp-empty-icon::after')
        && str_contains($weaponPaintsStylesheet, 'html.fc-no-animation .wp-app::after')
        && str_contains($motionController, "'.wp-app .wp-item'")
        && str_contains($motionController, "'.wp-app .wp-empty-state'")
        && str_contains($motionController, 'IntersectionObserver')
);

$check(
    'WeaponPaints category tabs, team selector and search share a horizontal glass toolbar below the site header',
    str_contains($weaponPaintsPage, '<aside class="wp-sidebar"')
        && !str_contains($weaponPaintsPage, 'wp-sidebar-brand')
        && str_contains($weaponPaintsPage, '<nav class="wp-tabs wp-sidebar-tabs" id="mainTabs"')
        && !str_contains($weaponPaintsPage, 'id="wpToolbarControls"')
        && str_contains($weaponPaintsPage, 'id="skinSearchGlobal"')
        && str_contains($weaponPaintsPage, 'class="wp-team-switcher"')
        && str_contains($weaponPaintsPage, '/img/weaponpaintsicons/inventory.svg')
        && str_contains($weaponPaintsPage, '/img/weaponpaintsicons/allskins.svg')
        && str_contains($weaponPaintsPage, '/img/weaponpaintsicons/ak47.svg')
        && str_contains($weaponPaintsPage, '/img/weaponpaintsicons/p90.svg')
        && str_contains($weaponPaintsPage, '/img/weaponpaintsicons/m249.svg')
        && str_contains($weaponPaintsPage, '/img/weaponpaintsicons/usp_silencer.svg')
        && str_contains($weaponPaintsPage, '/img/weaponpaintsicons/awp.svg')
        && str_contains($weaponPaintsPage, '/img/weaponpaintsicons/knife_m9_bayonet.svg')
        && str_contains($weaponPaintsPage, '/img/weaponpaintsicons/gloves.svg')
        && str_contains($weaponPaintsPage, '/img/weaponpaintsicons/agent.svg')
        && str_contains($weaponPaintsPage, '/img/weaponpaintsicons/music_kit.svg')
        && str_contains($weaponPaintsPage, '/img/weaponpaintsicons/pin.svg')
        && str_contains($weaponPaintsPage, '/img/weaponpaintsicons/t_char_bg.svg')
        && str_contains($weaponPaintsPage, '/img/weaponpaintsicons/ct_char_bg.svg')
        && str_contains($weaponPaintsStylesheet, 'horizontal glass navigation below the site header')
        && str_contains($weaponPaintsStylesheet, 'grid-template-columns: minmax(0, 1fr) !important;')
        && str_contains($weaponPaintsStylesheet, 'grid-row: 1 !important;')
        && str_contains($weaponPaintsStylesheet, 'grid-row: 2 !important;')
        && str_contains($weaponPaintsStylesheet, 'position: sticky !important;')
        && str_contains($weaponPaintsStylesheet, 'backdrop-filter: blur(20px) saturate(145%) !important;')
        && str_contains($weaponPaintsStylesheet, 'background: rgba(17, 17, 17, .72) !important;')
        && str_contains($weaponPaintsStylesheet, 'flex-direction: row !important;')
        && str_contains($weaponPaintsStylesheet, 'overflow-x: auto !important;')
        && str_contains($weaponPaintsStylesheet, 'scrollbar-width: none !important;')
        && str_contains($weaponPaintsStylesheet, '-ms-overflow-style: none !important;')
        && str_contains($weaponPaintsStylesheet, '::-webkit-scrollbar-track')
        && str_contains($weaponPaintsStylesheet, 'overscroll-behavior-x: contain;')
        && str_contains($weaponPaintsStylesheet, 'flex-wrap: nowrap !important;')
        && str_contains($weaponPaintsStylesheet, '.wp-sidebar-tabs .wp-tab > .wp-tab-label')
        && str_contains($weaponPaintsStylesheet, '.wp-sidebar .wp-team-switcher')
        && str_contains($weaponPaintsStylesheet, '.wp-sidebar .wp-search-wrap:focus-within')
        && !str_contains($weaponPaintsGlovesIcon, '#FF3333')
        && str_contains($weaponPaintsGlovesIcon, '#FFFFFF')
        && str_contains($weaponPaintsJavascript, 'wp-tab-exiting')
        && str_contains($weaponPaintsJavascript, 'tabExitDuration')
        && str_contains($weaponPaintsJavascript, 'const preferredPane = state.currentActiveTab')
        && is_file($root . '/img/weaponpaintsicons/inventory.svg')
        && is_file($root . '/img/weaponpaintsicons/allskins.svg')
        && is_file($root . '/img/weaponpaintsicons/ak47.svg')
        && is_file($root . '/img/weaponpaintsicons/p90.svg')
        && is_file($root . '/img/weaponpaintsicons/m249.svg')
        && is_file($root . '/img/weaponpaintsicons/usp_silencer.svg')
        && is_file($root . '/img/weaponpaintsicons/awp.svg')
        && is_file($root . '/img/weaponpaintsicons/knife_m9_bayonet.svg')
        && is_file($root . '/img/weaponpaintsicons/gloves.svg')
        && is_file($root . '/img/weaponpaintsicons/agent.svg')
        && is_file($root . '/img/weaponpaintsicons/music_kit.svg')
        && is_file($root . '/img/weaponpaintsicons/pin.svg')
        && is_file($root . '/img/weaponpaintsicons/t_char_bg.svg')
        && is_file($root . '/img/weaponpaintsicons/ct_char_bg.svg')
        && is_file($root . '/img/weaponpaintsicons/README.md')
);


$check(
    'WeaponPaints paint, sticker and keychain modals use Server List glass blur and enter/exit motion',
    str_contains($weaponPaintsStylesheet, 'blur(13px) saturate(115%)')
        && str_contains($weaponPaintsStylesheet, 'body > .wp-modal-backdrop[data-wp-modal-backdrop="1"].is-visible')
        && str_contains($weaponPaintsStylesheet, 'body > .wp-modal.wp-modal-visible .modal-dialog')
        && str_contains($weaponPaintsStylesheet, 'body > .wp-modal.wp-modal-closing .modal-dialog')
        && str_contains($weaponPaintsStylesheet, 'translateY(22px) scale(.965)')
        && str_contains($weaponPaintsJavascript, 'getCustomModalBackdrop')
        && str_contains($weaponPaintsJavascript, 'wp-modal-closing')
);

$check(
    'Desktop and mobile navigation prioritize Server List and Skin Selection, with secondary links under More',
    str_contains($siteHeaderMarkup, 'data-slug="servers_vps"')
        && str_contains($siteHeaderMarkup, 'data-slug="skins"')
        && str_contains($siteHeaderMarkup, 'id="shockMoreDropdown"')
        && str_contains($siteHeaderMarkup, '$mobileMoreNavItems = [')
        && str_contains($siteHeaderMarkup, '<details class="mobile-nav-more"')
        && str_contains($siteHeaderMarkup, '<summary class="mobile-nav-link mobile-nav-more-toggle')
        && str_contains($siteHeaderMarkup, "['buyserveropen', 'خرید سرور'")
        && str_contains($siteHeaderMarkup, 'mobile-nav-more-menu')
        && str_contains($siteHeaderStylesheet, '.mobile-nav-more[open] .mobile-nav-more-arrow')
        && str_contains($siteHeaderMarkup, "['servers_vps', 'لیست سرورها'")
        && str_contains($siteHeaderMarkup, "['skins', 'انتخاب اسکین'")
);

$check(
    'Header desktop and responsive navigation hide deferred support/manual/legal pages',
    !str_contains($siteHeaderMarkup, 'page=support')
        && !str_contains($siteHeaderMarkup, 'page=usermanual')
        && !str_contains($siteHeaderMarkup, 'page=rules')
        && !str_contains($siteHeaderMarkup, 'page=privacy')
        && !str_contains($siteHeaderMarkup, "'usermanual', 'راهنمای کاربر'")
        && str_contains($siteHeaderMarkup, "['launcher', 'دانلود لانچر'")
        && str_contains($siteHeaderMarkup, "['leaderboard', 'لیدربورد'")
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
    'Footer trust badges use fixed square containers while preserving image aspect ratios',
    str_contains($footerStylesheet, '.enamad-badge,')
        && str_contains($footerStylesheet, '.zarinpal-box')
        && str_contains($footerStylesheet, 'width: 58px;')
        && str_contains($footerStylesheet, 'height: 58px;')
        && str_contains($footerStylesheet, 'width: auto !important;')
        && str_contains($footerStylesheet, 'height: auto !important;')
        && str_contains($footerStylesheet, 'max-width: 46px !important;')
        && str_contains($footerStylesheet, 'max-height: 46px !important;')
        && str_contains($footerStylesheet, 'object-fit: contain !important;')
);

$check(
    'Header uses the current WebP logo across desktop and responsive navigation, with icon-led primary links',
    substr_count($siteHeaderMarkup, 'src="/img/webp/newlogo.webp?v=20261102-2"') === 2
        && substr_count($siteHeaderMarkup, "this.src='/img/logoresponsive.png?v=20261102-2'") === 2
        && is_file($root . '/img/webp/newlogo.webp')
        && is_file($root . '/img/logoresponsive.png')
        && str_contains($siteHeaderMarkup, 'fa-server')
        && str_contains($siteHeaderMarkup, 'fa-palette')
        && str_contains($siteHeaderMarkup, 'shock-nav-item--icon-only')
        && str_contains($siteHeaderMarkup, 'aria-label="بیشتر"')
        && str_contains($siteHeaderStylesheet, '.shock-nav-item--icon-only')
        && str_contains($siteHeaderStylesheet, '@media (max-width: 1100px)')
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
    'WeaponPaints inventory deletion accepts numeric defindex and paint IDs without escaped-regex false negatives',
    str_contains($weaponPaintsJavascript, 'const isUnsignedInteger = value => /^[0-9]+$/.test(value);')
        && str_contains($weaponPaintsJavascript, '!isUnsignedInteger(defindex)')
        && str_contains($weaponPaintsJavascript, '!isUnsignedInteger(paintId)')
        && !str_contains($weaponPaintsJavascript, '/^\\\\d+$/')
);

$check(
    'WeaponPaints inventory remove control overlays the card and the English TEAM label aligns left',
    str_contains($weaponPaintsPage, 'data-wp-inventory-remove')
        && str_contains($weaponPaintsStylesheet, '.wp-app .wp-saved-paint-item')
        && str_contains($weaponPaintsStylesheet, 'top: 9px !important;')
        && str_contains($weaponPaintsStylesheet, 'right: 9px !important;')
        && preg_match('/\\.wp-app \\.wp-sidebar-team\\s*\\{\\s*direction:\\s*ltr\\s*!important;/s', $weaponPaintsStylesheet) === 1
        && preg_match('/\\.wp-app \\.wp-sidebar-team\\s*>\\s*\\.wp-team-switcher\\s*\\{\\s*direction:\\s*rtl\\s*!important;/s', $weaponPaintsStylesheet) === 1
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
    'Server List exposes real CS2-only VAC, password, player, mode, map, and local favorite facets',
    str_contains($publicPage, 'data-sl-facet="vac"')
        && str_contains($publicPage, 'data-sl-facet="game"')
        && str_contains($publicPage, 'data-sl-facet="mode"')
        && str_contains($publicPage, 'data-sl-facet="players"')
        && str_contains($publicPage, 'data-sl-hide-offline')
        && str_contains($publicPage, 'data-sl-hide-password')
        && str_contains($publicPage, 'data-sl-favorites-only')
        && str_contains($publicPage, 'data-sl-map-option')
        && str_contains($publicPage, 'data-vac=')
        && str_contains($publicPage, 'data-password=')
        && str_contains($publicPage, 'data-game="cs2"')
        && str_contains($publicPage, '$availableSlots')
        && !str_contains($publicPage, 'HvH')
        && str_contains($standbyBrowserJavascript, 'cardMatchesFacet')
        && str_contains($standbyBrowserJavascript, 'updateFacetCounts')
        && str_contains($standbyBrowserJavascript, 'fragcity.serverlist.favorites.v1')
        && str_contains($standbyBrowserJavascript, 'data-sl-favorite')
        && str_contains($standbyBrowserCss, '.sl-filter-sidebar')
        && str_contains($standbyBrowserCss, '.sl-favorite-toggle')
);

$check(
    'FragCity public Status Page consumes sanitized published Uptime Kuma data without exposing management secrets',
    str_contains($websiteIndex, "'status',")
        && str_contains($websiteIndex, "'status' => [")
        && str_contains($websiteIndex, '/css/status.css?v=')
        && str_contains($websiteIndex, '/js/status.js?v=')
        && str_contains($statusPage, 'data-status-endpoint="/api/public-status.php"')
        && str_contains($statusJavascript, 'setInterval')
        && str_contains($statusJavascript, 'data-status-summary')
        && str_contains($statusStylesheet, '.fc-status-page')
        && str_contains($statusRepository, '/api/status-page/heartbeat/')
        && str_contains($statusRepository, '/incident-history')
        && str_contains($statusRepository, 'uptime_24h')
        && str_contains($statusRepository, 'CURLOPT_SSL_VERIFYPEER => true')
        && str_contains($publicStatusEndpoint, 'same_origin_required')
        && str_contains($publicStatusEndpoint, 'status_not_configured')
        && !str_contains($publicStatusEndpoint, 'api_key')
        && !str_contains($statusRepository, 'api_key')
        && (($websiteSettings['integrations']['uptime_kuma']['base_url'] ?? null) === 'https://status.fragcity.ir')
        && (($websiteSettings['integrations']['uptime_kuma']['status_page_slug'] ?? null) === 'fragcity')
        && str_contains($uptimeKumaCompose, 'louislam/uptime-kuma:2.5.9')
        && str_contains($uptimeKumaCompose, '127.0.0.1:3001:3001')
        && str_contains($uptimeKumaNginx, 'proxy_set_header Upgrade')
        && str_contains($uptimeKumaDocs, 'does **not** provide a 90-day numeric uptime series')
        && str_contains($siteHeaderMarkup, "['status', 'وضعیت سرویس‌ها', 'fa-heart-pulse', ['status']]")
        && str_contains($footerMarkup, "fragcity_page_url('status')")
);

$check(
    'Runtime example and standby deployment documentation use the verified Pterodactyl Panel origin',
    str_contains($linuxConfig, "'base_url' => 'https://panel.fragcity.ir'")
        && str_contains($standbyDocs, "'base_url' => 'https://panel.fragcity.ir'")
        && str_contains($standbyDocs, 'A2S_PLAYER')
        && str_contains($standbyDocs, 'does not return SteamID')
);

$check(
    'Server Guide is reachable from primary navigation, the home page, and the server directory',
    str_contains($websiteIndex, "'serverguide',")
        && str_contains($websiteIndex, "'serverguide' => [")
        && str_contains($websiteIndex, '/css/serverguide.css?v=')
        && str_contains($websiteIndex, '/js/serverguide.js?v=')
        && str_contains($siteHeaderMarkup, 'shock-nav-item--guide')
        && str_contains($siteHeaderMarkup, 'fa-book-open')
        && str_contains($siteHeaderMarkup, "['serverguide', 'راهنمای سرور', 'fa-book-open', ['serverguide']]")
        && str_contains($homePage, 'home-button-guide')
        && str_contains($publicPage, 'sl-guide-action')
        && str_contains($footerMarkup, "fragcity_page_url('serverguide')")
);

$check(
    'Server Guide catalog is valid JSON with unique IDs, known categories, and documented command entries',
    $serverGuideCatalogValid
        && count($serverGuideItemIds) === count($serverGuideCatalog['items'])
        && str_contains($serverGuideDocs, 'data/server-guide.json')
        && str_contains($serverGuidePage, "json_decode")
);

$check(
    'Server Guide renders plugin sources, availability state, game modes, and copyable console commands',
    str_contains($serverGuidePage, '$itemTypeLabels')
        && str_contains($serverGuidePage, '$availabilityLabels')
        && str_contains($serverGuidePage, 'sg-entry-plugin')
        && str_contains($serverGuidePage, 'sg-entry-source')
        && str_contains($serverGuidePage, 'availability_note')
        && str_contains($serverGuidePage, 'console_command')
        && str_contains($serverGuideStylesheet, '.sg-entry-availability--mode')
        && str_contains($serverGuideStylesheet, '.sg-entry-type--mode')
);

$check(
    'Server Guide classifies every catalog entry by operational impact and renders all four impact levels',
    $serverGuideCatalogValid
        && array_sum($serverGuideRiskCounts) === count($serverGuideCatalog['items'])
        && count(array_filter($serverGuideRiskCounts, static fn($count) => $count > 0)) === 4
        && str_contains($serverGuidePage, '$riskLabels')
        && str_contains($serverGuidePage, 'sg-risk-chip')
        && str_contains($serverGuideStylesheet, '.sg-entry--risk-high')
        && str_contains($serverGuideStylesheet, '.sg-entry--risk-action')
        && str_contains($serverGuideStylesheet, '.sg-entry--risk-info')
        && str_contains($serverGuideStylesheet, '.sg-entry--risk-safe')
);

$check(
    'Server Guide uses WeaponPaints-style page-fader entrances and Server Browser-style viewport reveals',
    str_contains($serverGuidePage, 'sg-page-fader')
        && str_contains($serverGuidePage, 'data-sg-reveal="up"')
        && str_contains($serverGuidePage, 'data-sg-reveal="left"')
        && str_contains($serverGuideJavascript, 'setupPageMotion')
        && str_contains($serverGuideJavascript, 'requestAnimationFrame')
        && str_contains($serverGuideJavascript, 'IntersectionObserver')
        && str_contains($serverGuideJavascript, "window.addEventListener('content-loaded', initServerGuide)")
        && str_contains($serverGuideStylesheet, '.sg-page-fader.sg-page-ready')
        && str_contains($serverGuideStylesheet, '.sg-revealed')
        && str_contains($serverGuideStylesheet, '@keyframes sg-orbit-float')
);

$check(
    'Server Guide uses a compact three-column desktop grid with tablet and mobile breakpoints',
    str_contains($serverGuideStylesheet, 'repeat(3, minmax(0, 1fr))')
        && str_contains($serverGuideStylesheet, 'repeat(2, minmax(0, 1fr))')
        && str_contains($serverGuideStylesheet, 'grid-template-columns: minmax(0, 1fr)')
);

$check(
    'Server Guide supports category filters, search, command copying, empty results, and responsive styling',
    str_contains($serverGuidePage, 'data-guide-filter')
        && str_contains($serverGuidePage, 'data-guide-copy')
        && str_contains($serverGuidePage, 'sgEmptyState')
        && str_contains($serverGuideJavascript, 'normalize(entry.dataset.guideSearch)')
        && str_contains($serverGuideJavascript, 'navigator.clipboard.writeText')
        && str_contains($serverGuideJavascript, 'data-guide-filter')
        && str_contains($serverGuideStylesheet, '.sg-entry-grid')
        && str_contains($serverGuideStylesheet, '@media (max-width: 650px)')
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

<?php
declare(strict_types=1);

/**
 * Read-only diagnostics for the Profile page's FACEIT and signed Ranks integrations.
 *
 * Usage:
 *   php tools/profile-integrations-smoke-test.php 7656119xxxxxxxxxx
 *
 * This script never prints API keys, HMAC secrets, passwords, or full settings.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once dirname(__DIR__) . '/settings/loader.php';

$steamId64 = isset($argv[1]) ? trim((string) $argv[1]) : '';
$settingsPath = getenv('FRAGCITY_SETTINGS_FILE');
if (!is_string($settingsPath) || trim($settingsPath) === '') {
    $settingsPath = dirname(__DIR__) . '/settings/settings.json';
}

$settings = fragcity_load_settings();
$faceitKey = trim((string) fragcity_settings('integrations.faceit_api.api_key', ''));
$linuxBaseUrl = trim((string) fragcity_settings('integrations.weaponpaints_api.base_url', ''));
$linuxSecret = (string) fragcity_settings('integrations.weaponpaints_api.shared_secret', '');
$siteBaseUrl = trim((string) fragcity_settings('app.site_base_url', ''));
$steamDomain = strtolower(trim((string) fragcity_settings('integrations.steam.domain_name', '')));
$siteParts = parse_url($siteBaseUrl);
$linuxParts = parse_url($linuxBaseUrl);

$faceitConfigured = $faceitKey !== ''
    && !str_starts_with($faceitKey, 'REPLACE_WITH_')
    && !preg_match('/[\\r\\n]/', $faceitKey);

$linuxSecretConfigured = preg_match('/^[a-f0-9]{64,128}$/D', $linuxSecret) === 1;
$linuxUrlValid = is_array($linuxParts)
    && strtolower((string) ($linuxParts['scheme'] ?? '')) === 'https'
    && !empty($linuxParts['host'])
    && !isset($linuxParts['user'])
    && !isset($linuxParts['pass'])
    && !isset($linuxParts['query'])
    && !isset($linuxParts['fragment'])
    && (!isset($linuxParts['path']) || $linuxParts['path'] === '' || $linuxParts['path'] === '/');

$siteHost = is_array($siteParts) ? strtolower((string) ($siteParts['host'] ?? '')) : '';
$siteScheme = is_array($siteParts) ? strtolower((string) ($siteParts['scheme'] ?? '')) : '';
$steamDomainMatches = $steamDomain === '' || ($siteHost !== '' && $steamDomain === $siteHost);

$print = static function (string $label, bool $ok, string $detail = ''): void {
    echo ($ok ? '[OK] ' : '[FAIL] ') . $label;
    if ($detail !== '') {
        echo ': ' . $detail;
    }
    echo PHP_EOL;
};

echo "========== FRAGCITY PROFILE INTEGRATION TEST ==========\n";
$print('Runtime settings file', is_file($settingsPath) && is_readable($settingsPath), basename($settingsPath));
$print('Site origin configured', in_array($siteScheme, ['http', 'https'], true) && $siteHost !== '', $siteHost !== '' ? $siteScheme . '://' . $siteHost : 'missing or invalid');
$print('Steam domain matches site origin', $steamDomainMatches, $steamDomain !== '' ? $steamDomain : 'domain_name empty; origin fallback used');
$print('PHP cURL extension', function_exists('curl_init'));
$print('PHP cURL multi support', function_exists('curl_multi_init'));
$print('FACEIT API key configured', $faceitConfigured, $faceitConfigured ? 'configured; value hidden' : 'missing or still a template placeholder');
$print('Linux API HTTPS origin', $linuxUrlValid, is_array($linuxParts) ? (string) ($linuxParts['host'] ?? 'invalid') : 'invalid');
$print('Linux API HMAC configured', $linuxSecretConfigured, $linuxSecretConfigured ? 'configured; value hidden' : 'missing or invalid');

if (!preg_match('/^7656119[0-9]{10}$/D', $steamId64)) {
    echo PHP_EOL . "Usage: php tools/profile-integrations-smoke-test.php 7656119xxxxxxxxxx" . PHP_EOL;
    echo "Pass the SteamID64 of the account whose Profile page is being tested." . PHP_EOL;
    exit(2);
}

echo PHP_EOL . "========== RANKS SIGNED API ==========\n";
if (!$linuxUrlValid || !$linuxSecretConfigured || !function_exists('curl_init')) {
    echo "[SKIP] Ranks API request: Linux API URL, HMAC or cURL is not configured." . PHP_EOL;
} else {
    try {
        require_once dirname(__DIR__) . '/class/RanksRepository.php';
        $ranks = new RanksRepository();
        $health = $ranks->ping();
        $print('Ranks API authentication and DB ping', true, 'table=' . $health['table'] . '; rows=' . $health['row_count']);

        $playerResult = $ranks->getPlayer($steamId64);
        if (($playerResult['found'] ?? false) === true && is_array($playerResult['player'] ?? null)) {
            $player = $playerResult['player'];
            $print('Ranks player lookup', true, 'global_place=' . (int) ($playerResult['global_place'] ?? 0)
                . '; points=' . (int) ($player['points'] ?? 0)
                . '; kills=' . (int) ($player['kills'] ?? 0));
        } elseif (($playerResult['found'] ?? null) === false) {
            $print('Ranks player lookup response', true, 'API is reachable; no row matched this SteamID64');
        } else {
            $print('Ranks player lookup', false, 'unexpected API response');
        }
    } catch (FragCityLinuxApiException $e) {
        $print('Ranks API request', false, 'HTTP ' . $e->httpStatus . '; code=' . $e->errorCode);
    } catch (Throwable $e) {
        $print('Ranks API request', false, get_class($e) . ': ' . $e->getMessage());
    }
}

echo PHP_EOL . "========== FACEIT DATA API ==========\n";
if (!$faceitConfigured) {
    echo "[FAIL] FACEIT lookup skipped: configure integrations.faceit_api.api_key in the private runtime settings.json." . PHP_EOL;
} elseif (!function_exists('curl_init') || !function_exists('curl_multi_init')) {
    echo "[FAIL] FACEIT lookup skipped: PHP cURL and cURL multi support are required." . PHP_EOL;
} else {
    try {
        require_once dirname(__DIR__) . '/class/FaceitApiClient.php';
        $rows = (new FaceitApiClient())->enrichPlayers([
            ['steam_id' => $steamId64],
        ]);
        $faceit = is_array($rows[0] ?? null) ? $rows[0] : [];
        $status = (string) ($faceit['faceit_status'] ?? 'api_unavailable');
        $level = $faceit['faceit_level'] ?? null;
        $elo = $faceit['faceit_elo'] ?? null;

        $apiResponded = in_array($status, [
            'available',
            'unranked',
            'no_cs2_profile',
            'profile_not_found',
        ], true);
        $detail = 'status=' . $status;
        if (is_int($level)) {
            $detail .= '; level=' . $level;
        }
        if (is_int($elo)) {
            $detail .= '; elo=' . $elo;
        }
        $print('FACEIT API response', $apiResponded, $detail);
    } catch (Throwable $e) {
        $print('FACEIT player lookup', false, get_class($e) . ': ' . $e->getMessage());
    }
}

echo PHP_EOL . "Diagnostic complete. No secret values were printed." . PHP_EOL;

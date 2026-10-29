<?php
declare(strict_types=1);

/**
 * Read-only Steam profile diagnostic.
 *
 * Run in the target environment from the repository root:
 *   php tools/steam-profile-preflight.php 7656119xxxxxxxxxx
 *
 * It never prints credentials or modifies database rows. It does make one
 * read-only request to Steam Web API when a key is configured.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once dirname(__DIR__) . '/settings/loader.php';
require_once dirname(__DIR__) . '/class/config.php';
require_once dirname(__DIR__) . '/class/SteamProfileService.php';

$steamid64 = isset($argv[1]) ? trim((string)$argv[1]) : '';
if (!preg_match('/^[0-9]{17}$/D', $steamid64)) {
    fwrite(STDERR, "Usage: php tools/steam-profile-preflight.php <17-digit-SteamID64>\n");
    exit(2);
}

$failed = 0;
$check = static function (string $label, bool $ok, string $detail = '') use (&$failed): void {
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label;
    if (!$ok && $detail !== '') echo ' — ' . $detail;
    echo PHP_EOL;
    if (!$ok) ++$failed;
};

$profile = fragcity_settings('files.index.database_profile', 'main');
if (!is_string($profile) || trim($profile) === '') $profile = 'main';
$profile = trim($profile);
$check('site database profile is configured', $profile !== '');

$apiKeyConfigured = defined('STEAM_API_KEY') && trim((string)STEAM_API_KEY) !== '';
$check(
    'Steam Web API key is configured at integrations.steam.api_key',
    $apiKeyConfigured,
    'key missing or empty; the key itself is never printed'
);

$pdo = fragcity_create_pdo($profile);
if (!$pdo instanceof PDO) {
    $check('connect to configured site database', false, 'PDO connection unavailable; credentials were not printed');
    echo PHP_EOL . 'Summary: ' . $failed . ' failed check(s).' . PHP_EOL;
    exit(1);
}
$check('connect to configured site database', true);

$activeDatabase = '';
try {
    $activeDatabase = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    $dbSettings = fragcity_database_settings($profile);
    $configuredDatabase = is_array($dbSettings) ? trim((string)($dbSettings['name'] ?? '')) : '';
    $check(
        'connected database matches the configured database',
        $activeDatabase !== '' && $configuredDatabase !== '' && hash_equals($configuredDatabase, $activeDatabase),
        'configured database and active database identity differ or are empty'
    );
    echo 'INFO: active database = ' . ($activeDatabase !== '' ? $activeDatabase : '(unknown)') . PHP_EOL;
} catch (Throwable $e) {
    $check('read connected database identity', false, 'database identity query failed');
}

$requiredColumns = [
    'steamid64',
    'steam_personaname',
    'steam_profileurl',
    'steam_avatar_url',
    'steam_avatar_path',
    'steam_personastate',
    'steam_communityvisibilitystate',
    'steam_profilestate',
    'steam_timecreated',
    'steam_lastlogoff',
    'steam_loccountrycode',
    'steam_gameextrainfo',
    'steam_gameid',
    'steam_profile_updated_at',
];

$existingColumns = [];
try {
    $columnStatement = $pdo->query(
        'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "users"'
    );
    $existingColumns = array_fill_keys($columnStatement->fetchAll(PDO::FETCH_COLUMN), true);
    $missingColumns = array_values(array_filter(
        $requiredColumns,
        static fn(string $column): bool => !isset($existingColumns[$column])
    ));
    $check(
        'users table has all Steam profile columns',
        $missingColumns === [],
        $missingColumns === [] ? '' : 'missing: ' . implode(', ', $missingColumns)
    );
} catch (Throwable $e) {
    $check('inspect users table columns', false, 'INFORMATION_SCHEMA query failed');
}

$userExists = false;
if ($existingColumns !== [] && count(array_filter($requiredColumns, static fn(string $c): bool => !isset($existingColumns[$c]))) === 0) {
    try {
        $userStatement = $pdo->prepare(
            'SELECT steam_personaname, steam_profileurl, steam_avatar_url, steam_avatar_path,
                    steam_personastate, steam_communityvisibilitystate, steam_profilestate,
                    steam_timecreated, steam_lastlogoff, steam_loccountrycode,
                    steam_gameextrainfo, steam_gameid, steam_profile_updated_at
             FROM users WHERE steamid64 = ? LIMIT 1'
        );
        $userStatement->execute([$steamid64]);
        $userRow = $userStatement->fetch(PDO::FETCH_ASSOC);
        $userExists = is_array($userRow);
        $check(
            'SteamID has a registered row in this database users table',
            $userExists,
            'if this is a first-time Steam login, finish the site registration form first'
        );

        if ($userExists) {
            $displayFields = [
                'steam_personaname', 'steam_profileurl', 'steam_avatar_url', 'steam_avatar_path',
                'steam_personastate', 'steam_communityvisibilitystate', 'steam_profilestate',
                'steam_timecreated', 'steam_lastlogoff', 'steam_loccountrycode',
                'steam_gameextrainfo', 'steam_gameid', 'steam_profile_updated_at',
            ];
            $populated = [];
            foreach ($displayFields as $field) {
                $value = $userRow[$field] ?? null;
                $populated[$field] = $value !== null && trim((string)$value) !== '';
            }
            echo 'INFO: stored-field counts below are read BEFORE the Steam API lookup; this preflight never writes to the database.' . PHP_EOL;
            echo 'INFO: populated Steam fields = ' . count(array_filter($populated)) . '/' . count($populated) . PHP_EOL;
            foreach ($populated as $field => $isPopulated) {
                echo 'INFO: ' . $field . ' = ' . ($isPopulated ? 'populated' : 'empty') . PHP_EOL;
            }
        }
    } catch (Throwable $e) {
        $check('read Steam profile fields for the supplied SteamID', false, 'users row query failed');
    }
} else {
    echo "SKIP: row check skipped because the target database schema is incomplete.\n";
}

try {
    $summary = SteamProfileService::getPlayerSummary($steamid64, true);
    $hasSummary = $summary !== [] && (string)($summary['steamid'] ?? '') === $steamid64;
    $check(
        'Steam Web API returned a valid summary for the supplied SteamID',
        $hasSummary,
        'check server error_log for the detailed, secret-free fetch reason'
    );
    if ($hasSummary) {
        foreach (['personaname', 'profileurl', 'avatarfull'] as $field) {
            echo 'INFO: Steam response contains ' . $field . ' = '
                . (trim((string)($summary[$field] ?? '')) !== '' ? 'yes' : 'no') . PHP_EOL;
        }
    }
} catch (Throwable $e) {
    $check('Steam Web API summary lookup', false, 'unexpected error; check server error_log');
}

echo PHP_EOL . 'Summary: ' . ($failed === 0 ? 'all checks passed' : $failed . ' failed check(s)') . PHP_EOL;
exit($failed === 0 ? 0 : 1);

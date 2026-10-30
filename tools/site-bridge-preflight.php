<?php
declare(strict_types=1);

/**
 * Read-only preflight for the current FragCity website database schema.
 *
 * Run from the repository root in the local Laragon PHP CLI:
 *   php tools/site-bridge-preflight.php
 *
 * This checks only the ten tables supplied for kyonxcbt_fragcity_services.
 * It never prints credentials or writes/migrates database records.
 * The legacy Windows-Agent bridge is retired and is not checked here.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once dirname(__DIR__) . '/settings/loader.php';

$passed = 0;
$failed = 0;

$check = static function (string $label, bool $ok, string $failure = '') use (&$passed, &$failed): void {
    if ($ok) {
        ++$passed;
        echo 'PASS: ' . $label . PHP_EOL;
        return;
    }
    ++$failed;
    echo 'FAIL: ' . $label;
    if ($failure !== '') {
        echo ' — ' . $failure;
    }
    echo PHP_EOL;
};

$expectedDb = getenv('FRAGCITY_EXPECTED_MAIN_DB');
if (!is_string($expectedDb) || trim($expectedDb) === '') {
    $expectedDb = 'kyonxcbt_fragcity_services';
}
$expectedDb = trim($expectedDb);

$mainSettings = fragcity_database_settings('main');
$configuredDb = isset($mainSettings['name']) ? trim((string)$mainSettings['name']) : '';
$check(
    'main DB profile targets the expected website database',
    $configuredDb !== '' && hash_equals($expectedDb, $configuredDb),
    $configuredDb === '' ? 'main database name is empty' : 'configured database name does not match expected name'
);

$pdo = fragcity_create_pdo('main');
if (!$pdo instanceof PDO) {
    $check('connect to the local website database', false, 'PDO connection unavailable; credentials are never printed');
    echo PHP_EOL . 'Summary: ' . $passed . ' passed, ' . $failed . ' failed.' . PHP_EOL;
    exit(1);
}

try {
    $activeDb = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    $check(
        'connected database identity matches the expected website database',
        $activeDb !== '' && hash_equals($expectedDb, $activeDb),
        $activeDb === '' ? 'database identity could not be determined' : 'connected database does not match expected name'
    );
} catch (Throwable $e) {
    $check('read active database identity', false, 'read-only identity query failed');
}

$requiredColumns = [
    'donations' => ['id', 'steamid', 'amount', 'username', 'message', 'admin_reply', 'authority', 'ref_id', 'status', 'created_at', 'phone'],
    'password_resets' => ['id', 'steamid', 'token', 'expires_at', 'created_at'],
    'tickets' => ['id', 'steamid', 'subject', 'status', 'created_at', 'updated_at'],
    'ticket_replies' => ['id', 'ticket_id', 'steamid', 'message', 'is_admin', 'created_at'],
    'users' => [
        'id', 'steamid64', 'steam', 'username', 'email', 'password_hash', 'role', 'created_at',
        'vip_level', 'vip_expire', 'phone', 'insmaster', 'avatar',
        'steam_personaname', 'steam_profileurl', 'steam_avatar_url', 'steam_avatar_path',
        'steam_personastate', 'steam_communityvisibilitystate', 'steam_profilestate',
        'steam_timecreated', 'steam_lastlogoff', 'steam_loccountrycode',
        'steam_gameextrainfo', 'steam_gameid', 'steam_profile_updated_at'
    ],
    'user_wallets' => ['steamid', 'balance', 'updated_at'],
    'vip_codes' => ['id', 'code', 'vip_level', 'duration_days', 'max_uses', 'used_count', 'created_by', 'created_at'],
    'vip_usage' => ['id', 'code_id', 'steamid', 'used_at'],
    'wallet_transactions' => ['id', 'steamid', 'amount', 'type', 'description', 'authority', 'ref_id', 'status', 'created_at'],
    'withdraw_requests' => ['id', 'steamid', 'amount', 'card_number', 'status', 'created_at'],
];

try {
    $actualTables = $pdo->query(
        "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
    )->fetchAll(PDO::FETCH_COLUMN);
    sort($actualTables);
    $expectedTables = array_keys($requiredColumns);
    sort($expectedTables);
    $unexpectedTables = array_values(array_diff($actualTables, $expectedTables));
    $missingTables = array_values(array_diff($expectedTables, $actualTables));
    $check(
        'database contains exactly the ten confirmed base tables',
        $unexpectedTables === [] && $missingTables === [],
        implode('; ', array_filter([
            $missingTables === [] ? '' : 'missing: ' . implode(', ', $missingTables),
            $unexpectedTables === [] ? '' : 'unexpected: ' . implode(', ', $unexpectedTables),
        ]))
    );
} catch (Throwable $e) {
    $check('inspect website database table inventory', false, 'INFORMATION_SCHEMA read failed');
}

foreach ($requiredColumns as $table => $columns) {
    try {
        $stmt = $pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);
        $existing = array_fill_keys($stmt->fetchAll(PDO::FETCH_COLUMN), true);
        if ($existing === []) {
            $check('required table ' . $table, false, 'table does not exist in the active database');
            continue;
        }

        $missing = array_values(array_filter(
            $columns,
            static fn(string $column): bool => !isset($existing[$column])
        ));
        $check(
            'required columns for ' . $table,
            $missing === [],
            $missing === [] ? '' : 'missing: ' . implode(', ', $missing)
        );
    } catch (Throwable $e) {
        $check('inspect table ' . $table, false, 'INFORMATION_SCHEMA read failed');
    }
}

$requiredUniqueColumns = [
    'users' => 'steamid64',
    'vip_codes' => 'code',
];

foreach ($requiredUniqueColumns as $table => $column) {
    try {
        $stmt = $pdo->prepare(
            'SELECT INDEX_NAME
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND NON_UNIQUE = 0
             GROUP BY INDEX_NAME
             HAVING COUNT(*) = 1 AND SUM(COLUMN_NAME = ?) = 1'
        );
        $stmt->execute([$table, $column]);
        $check(
            'unique index on ' . $table . '.' . $column,
            $stmt->fetchColumn() !== false,
            'single-column UNIQUE/PRIMARY index is required'
        );
    } catch (Throwable $e) {
        $check('inspect index on ' . $table . '.' . $column, false, 'index metadata query failed');
    }
}

echo PHP_EOL . 'Summary: ' . $passed . ' passed, ' . $failed . ' failed.' . PHP_EOL;
if ($failed > 0) {
    echo 'Schema preflight did not pass. This script made no changes.' . PHP_EOL;
    exit(1);
}
echo 'Schema preflight passed for the supplied website-table inventory.' . PHP_EOL;
exit(0);

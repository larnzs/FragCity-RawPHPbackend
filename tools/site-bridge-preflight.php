<?php
declare(strict_types=1);

/**
 * Read-only preflight for the shared-hosting Agent API bridge.
 *
 * Run from the website repository root on the shared host:
 *   php tools/site-bridge-preflight.php
 *
 * This script is CLI-only and never prints passwords, tokens, or HMAC secrets.
 * It does not create, update, delete, or migrate database records.
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

$expectedMainDb = getenv('FRAGCITY_BRIDGE_EXPECTED_MAIN_DB');
if (!is_string($expectedMainDb) || trim($expectedMainDb) === '') {
    $expectedMainDb = 'kyonxcbt_fragcity_services';
}
$expectedMainDb = trim($expectedMainDb);

$mainSettings = fragcity_database_settings('main');
$configuredMainDb = isset($mainSettings['name']) ? trim((string)$mainSettings['name']) : '';
$check(
    'main DB profile is configured for the expected website database',
    $configuredMainDb !== '' && hash_equals($expectedMainDb, $configuredMainDb),
    $configuredMainDb === '' ? 'main database name is empty' : 'configured database name does not match expected name'
);

$bridgeSecret = fragcity_settings('integrations.linuxapi_site_bridge.shared_secret', '');
$secretValid = is_string($bridgeSecret) && preg_match('/^[a-f0-9]{64,128}$/D', $bridgeSecret) === 1;
$check('site bridge HMAC secret is configured in private settings', $secretValid, 'secret missing or not valid hexadecimal length');
unset($bridgeSecret);

$pdo = fragcity_create_pdo('main');
if (!$pdo instanceof PDO) {
    $check('connect to website main database using the configured profile', false, 'PDO connection unavailable; credentials are never printed');
    echo PHP_EOL . 'Summary: ' . $passed . ' passed, ' . $failed . ' failed.' . PHP_EOL;
    exit(1);
}

try {
    $actualDb = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    $check(
        'connected database matches the expected website database',
        $actualDb !== '' && hash_equals($expectedMainDb, $actualDb),
        $actualDb === '' ? 'database identity could not be determined' : 'connected database is not the expected website database'
    );
} catch (Throwable $e) {
    $check('read the active database identity', false, 'read-only identity query failed');
}

$requiredColumns = [
    'agent_tokens' => ['token_id', 'username', 'token', 'expires_at', 'created_at', 'last_used'],
    'users' => ['username', 'steamid64', 'phone', 'password_hash', 'insmaster'],
    'fragcity_api_bridge_nonces' => ['nonce_hash', 'created_at'],
    'fragcity_api_bridge_login_attempts' => ['id', 'ip_hash', 'attempted_at'],
    'agent_nodes' => ['node_id', 'node_name', 'ip_address', 'cpu_usage', 'ram_usage', 'is_ready', 'last_heartbeat'],
    'pending_servers' => [
        'server_id', 'steam_id', 'node_name', 'server_name', 'server_ip', 'port', 'slots',
        'modes', 'tickrate', 'rcon_password', 'server_password', 'gslt_token', 'insecure',
        'paid_price', 'payment_token', 'time_left', 'expire_date', 'status', 'created_at',
    ],
    'game_servers' => ['ip', 'port', 'server_name', 'status', 'updated_at'],
    'server_runtime_stats' => ['id', 'server_id', 'current_players', 'max_players', 'current_map', 'is_online', 'last_updated'],
    'rcon_command_logs' => ['id', 'server_ip', 'server_port', 'steam_id', 'user_command', 'server_response', 'status', 'created_at'],
    'registered_servers' => ['server_id', 'port', 'node_name', 'steam_id', 'server_ip'],
    'server_configs' => ['server_id', 'current_config'],
    'server_ports' => ['node_name', 'port', 'status'],
    'agent_commands' => ['id', 'server_id', 'action', 'port', 'steam_id', 'status', 'created_at', 'node_name'],
];

foreach ($requiredColumns as $table => $columns) {
    try {
        $stmt = $pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);
        $existing = array_fill_keys($stmt->fetchAll(PDO::FETCH_COLUMN), true);
        if ($existing === []) {
            $check('required table ' . $table, false, 'table does not exist in the active database');
            continue;
        }

        $missing = array_values(array_filter($columns, static fn(string $column): bool => !isset($existing[$column])));
        $check(
            'required columns for ' . $table,
            $missing === [],
            $missing === [] ? '' : 'missing: ' . implode(', ', $missing)
        );
    } catch (Throwable $e) {
        $check('inspect table ' . $table, false, 'information_schema read failed');
    }
}

/**
 * Ensure these columns are individually covered by a UNIQUE or PRIMARY index.
 * A composite index containing the column is not sufficient for these code paths.
 */
$requiredUniqueColumns = [
    'fragcity_api_bridge_nonces' => 'nonce_hash',
    'agent_nodes' => 'node_name',
    'agent_tokens' => 'token',
];

foreach ($requiredUniqueColumns as $table => $column) {
    try {
        $stmt = $pdo->prepare(
            'SELECT INDEX_NAME
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
             GROUP BY INDEX_NAME
             HAVING MAX(NON_UNIQUE) = 0 AND COUNT(*) = 1'
        );
        $stmt->execute([$table, $column]);
        $check(
            'unique index on ' . $table . '.' . $column,
            $stmt->fetchColumn() !== false,
            'a single-column UNIQUE/PRIMARY index is required'
        );
    } catch (Throwable $e) {
        $check('inspect index on ' . $table . '.' . $column, false, 'index metadata query failed');
    }
}

/*
 * The Agent bulk sync and skin export use separate, explicitly mapped website
 * plugin databases. Confirm the bridge can open each expected database and
 * that all source-defined sync/export tables exist. The checks are SELECT-only.
 */
$mappedDatabases = [
    'kyonxcbt_matchzy_plugin' => ['matchzy_stats_maps', 'matchzy_stats_matches', 'matchzy_stats_players'],
    'kyonxcbt_ranks_plugin' => ['lvl_base', 'lvl_base_geoip', 'lvl_base_hits'],
    'kyonxcbt_simpleadmin_plugin' => [
        'sa_admins', 'sa_admins_flags', 'sa_bans', 'sa_groups', 'sa_groups_flags',
        'sa_groups_servers', 'sa_migrations', 'sa_mutes', 'sa_players_ips',
        'sa_servers', 'sa_unbans', 'sa_unmutes', 'sa_warns',
    ],
    'kyonxcbt_vip_plugin' => ['vipcore_test', 'vip_servers', 'vip_users'],
    'kyonxcbt_weaponpaints_plugin' => [
        'wp_player_agents', 'wp_player_gloves', 'wp_player_knife',
        'wp_player_music', 'wp_player_pins', 'wp_player_skins',
    ],
];

foreach ($mappedDatabases as $database => $tables) {
    $target = fragcity_create_pdo('main', $database);
    if (!$target instanceof PDO) {
        $check('read-only connection to mapped database ' . $database, false, 'database unavailable to the configured main DB user');
        continue;
    }

    try {
        $actual = (string)$target->query('SELECT DATABASE()')->fetchColumn();
        $check(
            'mapped database identity ' . $database,
            $actual !== '' && hash_equals($database, $actual),
            'connection did not select the expected mapped database'
        );

        $stmt = $target->prepare(
            'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        foreach ($tables as $table) {
            $stmt->execute([$table]);
            $check(
                'mapped table ' . $database . '.' . $table,
                $stmt->fetchColumn() !== false,
                'table missing'
            );
        }
    } catch (Throwable $e) {
        $check('inspect mapped database ' . $database, false, 'read-only schema query failed');
    }
}

echo PHP_EOL . 'Summary: ' . $passed . ' passed, ' . $failed . ' failed.' . PHP_EOL;
if ($failed > 0) {
    echo 'Preflight did not pass. Resolve the reported schema/configuration gaps before enabling bridge operations.' . PHP_EOL;
    exit(1);
}

echo 'Preflight passed. This confirms only the inspected schema/configuration; it does not prove node-bound authentication or production readiness.' . PHP_EOL;
exit(0);

<?php
declare(strict_types=1);

/**
 * CLI-only, read/write-permission preflight for paid VIP checkout.
 *
 * Usage:
 *   php linuxapi/tools/vipcore-paid-ledger-preflight.php
 *
 * Uses the same private vipcore database settings as /v1/vipcore.
 * INSERT and UPDATE probes use WHERE 1=0, so they affect zero rows.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$configPath = getenv('FRAGCITY_API_CONFIG') ?: '/etc/fragcity-api/config.php';
if (!is_file($configPath) || !is_readable($configPath)) {
    fwrite(STDERR, "FAIL: runtime config missing or unreadable: {$configPath}\n");
    exit(2);
}

try {
    $config = require $configPath;
} catch (Throwable $e) {
    fwrite(STDERR, "FAIL: could not load runtime config.\n");
    exit(2);
}

$vipConfig = is_array($config) && is_array($config['vipcore'] ?? null)
    ? $config['vipcore']
    : [];
$db = is_array($vipConfig['database'] ?? null) ? $vipConfig['database'] : [];

$host = trim((string)($db['host'] ?? '127.0.0.1'));
$port = (int)($db['port'] ?? 3306);
$name = trim((string)($db['name'] ?? ''));
$user = trim((string)($db['username'] ?? ''));
$password = (string)($db['password'] ?? '');
$charset = trim((string)($db['charset'] ?? 'utf8mb4'));

if (empty($vipConfig['enabled'])
    || $host !== '127.0.0.1'
    || $port < 1 || $port > 65535
    || $name !== 'GLOBAL_vip'
    || $user === ''
    || $password === ''
    || !preg_match('/^[A-Za-z0-9_]+$/D', $charset)) {
    fwrite(STDERR, "FAIL: vipcore runtime database configuration is incomplete or invalid.\n");
    exit(2);
}

try {
    $pdo = new PDO(
        'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=' . $charset,
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "FAIL: could not connect to VIPCore database; SQLSTATE "
        . $e->getCode() . "; driver code " . (int)($e->errorInfo[1] ?? 0) . ".\n");
    exit(1);
}

$currentUser = (string)$pdo->query('SELECT CURRENT_USER()')->fetchColumn();
$currentDatabase = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
echo "Connected database: {$currentDatabase}\n";
echo "MariaDB account: {$currentUser}\n";

$groupColumn = chr(96) . 'group' . chr(96);

$checks = [
    'paid ledger SELECT and required columns' => static function (PDO $pdo): void {
        $pdo->query(
            'SELECT order_ref, steamid64, account_id, sid, vip_level, vip_group,
                    duration_seconds, tier_price_rial, granted_at, expires
             FROM fragcity_vip_paid_orders
             LIMIT 0'
        );
    },
    'paid ledger INSERT privilege' => static function (PDO $pdo): void {
        $pdo->exec(
            'INSERT INTO fragcity_vip_paid_orders
                (order_ref, steamid64, account_id, sid, vip_level, vip_group,
                 duration_seconds, tier_price_rial, granted_at, expires)
             SELECT
                CAST(REPEAT(\'a\', 32) AS CHAR(32)),
                CAST(REPEAT(\'0\', 17) AS CHAR(17)),
                CAST(0 AS UNSIGNED),
                CAST(0 AS UNSIGNED),
                CAST(1 AS UNSIGNED),
                CAST(\'BRONZE\' AS CHAR(16)),
                CAST(604800 AS UNSIGNED),
                CAST(0 AS UNSIGNED),
                CAST(1 AS UNSIGNED),
                CAST(604801 AS UNSIGNED)
             WHERE 1 = 0'
        );
    },
    'vip_users SELECT and required columns' => static function (PDO $pdo) use ($groupColumn): void {
        $pdo->query(
            'SELECT account_id, name, lastvisit, ' . $groupColumn . ', expires
             FROM vip_users
             LIMIT 0'
        );
    },
    'vip_users INSERT privilege' => static function (PDO $pdo) use ($groupColumn): void {
        $pdo->exec(
            'INSERT INTO vip_users
                (account_id, name, lastvisit, ' . $groupColumn . ', expires)
             SELECT
                CAST(0 AS UNSIGNED),
                CAST(\'FragCity\' AS CHAR(64)),
                CAST(0 AS UNSIGNED),
                CAST(0 AS UNSIGNED),
                CAST(\'BRONZE\' AS CHAR(64)),
                CAST(0 AS UNSIGNED)
             WHERE 1 = 0'
        );
    },
    'vip_users UPDATE privilege' => static function (PDO $pdo) use ($groupColumn): void {
        $pdo->exec(
            'UPDATE vip_users
             SET name = name, lastvisit = lastvisit,
                 ' . $groupColumn . ' = ' . $groupColumn . ', expires = expires
             WHERE 1 = 0'
        );
    },
];

$failed = false;
foreach ($checks as $label => $check) {
    try {
        $check($pdo);
        echo "PASS: {$label}\n";
    } catch (PDOException $e) {
        $failed = true;
        echo "FAIL: {$label} — SQLSTATE " . $e->getCode()
            . "; driver code " . (int)($e->errorInfo[1] ?? 0) . "\n";
    }
}

if ($failed) {
    fwrite(STDERR, "RESULT: paid VIP checkout is NOT ready. Fix the failed migration/privilege checks above.\n");
    exit(1);
}

echo "RESULT: paid VIP checkout database prerequisites are ready.\n";

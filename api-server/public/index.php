<?php
declare(strict_types=1);

/**
 * FragCity WeaponPaints API.
 *
 * Public routes:
 *   GET  /health
 *   POST /v1/weaponpaints (signed server-to-server JSON)
 *
 * Deploy this app separately from the Pterodactyl Panel. Only public/ is webroot.
 * The runtime secrets and MariaDB credentials stay in /etc/fragcity-api/config.php.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function apiRespond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

function apiHeader(string $name): string
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    $value = $_SERVER[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function apiInt(mixed $value, string $field, int $min, int $max): int
{
    if (is_int($value)) {
        $number = $value;
    } elseif (is_string($value) && preg_match('/^-?\d{1,10}$/D', $value)) {
        $number = (int)$value;
    } else {
        throw new InvalidArgumentException('Invalid field: ' . $field);
    }

    if ($number < $min || $number > $max) {
        throw new InvalidArgumentException('Out-of-range field: ' . $field);
    }
    return $number;
}

function apiFloat(mixed $value, string $field, float $min, float $max): float
{
    if (!is_numeric($value)) {
        throw new InvalidArgumentException('Invalid field: ' . $field);
    }

    $number = (float)$value;
    if (!is_finite($number) || $number < $min || $number > $max) {
        throw new InvalidArgumentException('Out-of-range field: ' . $field);
    }
    return $number;
}

function apiSteamId(array $body): string
{
    $steamid = $body['steamid'] ?? null;
    if (!is_string($steamid) || !preg_match('/^[0-9]{1,20}$/D', $steamid)) {
        throw new InvalidArgumentException('Invalid steamid.');
    }
    return $steamid;
}

function apiTeams(array $body): array
{
    $teams = $body['teams'] ?? null;
    if (!is_array($teams) || count($teams) < 1 || count($teams) > 2) {
        throw new InvalidArgumentException('Invalid teams.');
    }

    $normalized = [];
    foreach ($teams as $team) {
        $normalized[] = apiInt($team, 'teams', 2, 3);
    }

    $normalized = array_values(array_unique($normalized));
    sort($normalized);
    return $normalized;
}

function apiExec(PDO $pdo, string $sql, array $params = []): void
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function apiTransaction(PDO $pdo, callable $callback): void
{
    $pdo->beginTransaction();
    try {
        $callback();
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function apiNumericText(float $number): string
{
    return rtrim(rtrim(number_format($number, 12, '.', ''), '0'), '.') ?: '0';
}

function apiSaveGlove(PDO $pdo, string $steamid, array $teams, array $body): void
{
    $defindex = apiInt($body['defindex'] ?? null, 'defindex', 0, 10000);
    $allowedGloves = [0, 4725, 5027, 5030, 5031, 5032, 5033, 5034, 5035];
    if (!in_array($defindex, $allowedGloves, true)) {
        throw new InvalidArgumentException('Invalid glove defindex.');
    }
    $paint = apiInt($body['paint_id'] ?? null, 'paint_id', 0, 100000);

    foreach ($teams as $team) {
        apiExec($pdo,
            'INSERT INTO wp_player_skins
                (steamid, weapon_team, weapon_defindex, weapon_paint_id, weapon_wear, weapon_seed)
             VALUES (:steamid, :team, :defindex, :paint_id, 0.001, 0)
             ON DUPLICATE KEY UPDATE weapon_paint_id = :paint_update',
            [
                ':steamid' => $steamid, ':team' => $team, ':defindex' => $defindex,
                ':paint_id' => $paint, ':paint_update' => $paint,
            ]
        );
        apiExec($pdo,
            'INSERT INTO wp_player_gloves (steamid, weapon_team, weapon_defindex)
             VALUES (:steamid, :team, :defindex)
             ON DUPLICATE KEY UPDATE weapon_defindex = :defindex_update',
            [
                ':steamid' => $steamid, ':team' => $team, ':defindex' => $defindex,
                ':defindex_update' => $defindex,
            ]
        );
    }
}

function apiSaveAgent(PDO $pdo, string $steamid, array $teams, array $body): void
{
    $agent = $body['agent_id'] ?? null;
    if (!is_string($agent) || $agent === '' || strlen($agent) > 160
        || preg_match('/[\x00-\x1F]/', $agent)) {
        throw new InvalidArgumentException('Invalid agent_id.');
    }

    foreach ($teams as $team) {
        if ($team === 2) {
            apiExec($pdo,
                'INSERT INTO wp_player_agents (steamid, agent_t) VALUES (:steamid, :agent)
                 ON DUPLICATE KEY UPDATE agent_t = :agent_update',
                [':steamid' => $steamid, ':agent' => $agent, ':agent_update' => $agent]
            );
        } else {
            apiExec($pdo,
                'INSERT INTO wp_player_agents (steamid, agent_ct) VALUES (:steamid, :agent)
                 ON DUPLICATE KEY UPDATE agent_ct = :agent_update',
                [':steamid' => $steamid, ':agent' => $agent, ':agent_update' => $agent]
            );
        }
    }
}

function apiSaveMusic(PDO $pdo, string $steamid, array $teams, array $body): void
{
    $musicId = apiInt($body['music_id'] ?? null, 'music_id', 0, 100000);
    foreach ($teams as $team) {
        apiExec($pdo,
            'INSERT INTO wp_player_music (steamid, weapon_team, music_id)
             VALUES (:steamid, :team, :music_id)
             ON DUPLICATE KEY UPDATE music_id = :music_update',
            [
                ':steamid' => $steamid, ':team' => $team, ':music_id' => $musicId,
                ':music_update' => $musicId,
            ]
        );
    }
}

function apiSavePin(PDO $pdo, string $steamid, array $teams, array $body): void
{
    $pinId = apiInt($body['pin_id'] ?? null, 'pin_id', 0, 100000);
    foreach ($teams as $team) {
        apiExec($pdo,
            'INSERT INTO wp_player_pins (steamid, weapon_team, id)
             VALUES (:steamid, :team, :pin_id)
             ON DUPLICATE KEY UPDATE id = :pin_update',
            [
                ':steamid' => $steamid, ':team' => $team, ':pin_id' => $pinId,
                ':pin_update' => $pinId,
            ]
        );
    }
}

function apiSaveKnife(PDO $pdo, string $steamid, array $body): void
{
    $knife = $body['knife'] ?? null;
    if (!is_string($knife) || $knife === '' || strlen($knife) > 160
        || preg_match('/[\x00-\x1F]/', $knife)) {
        throw new InvalidArgumentException('Invalid knife.');
    }

    foreach ([2, 3] as $team) {
        apiExec($pdo,
            'INSERT INTO wp_player_knife (steamid, knife, weapon_team)
             VALUES (:steamid, :knife, :team)
             ON DUPLICATE KEY UPDATE knife = :knife_update',
            [
                ':steamid' => $steamid, ':knife' => $knife, ':team' => $team,
                ':knife_update' => $knife,
            ]
        );
    }
}

function apiSaveSkin(PDO $pdo, string $steamid, array $teams, array $body): void
{
    $defindex = apiInt($body['defindex'] ?? null, 'defindex', 1, 10000);
    $paint = apiInt($body['paint_id'] ?? null, 'paint_id', 0, 100000);
    $wear = apiFloat($body['wear'] ?? 0.001, 'wear', 0.0, 1.0);
    $seed = apiInt($body['seed'] ?? 0, 'seed', 0, 1000000);
    $stattrak = !empty($body['stattrak']) ? 1 : 0;

    $nametag = $body['nametag'] ?? '';
    if (!is_string($nametag)) {
        throw new InvalidArgumentException('Invalid nametag.');
    }
    $nametag = trim($nametag);
    $nametag = function_exists('mb_substr')
        ? mb_substr($nametag, 0, 20, 'UTF-8')
        : substr($nametag, 0, 80);
    $nametag = $nametag === '' ? null : $nametag;

    $stickerInput = $body['stickers'] ?? null;
    if (!is_array($stickerInput) || count($stickerInput) !== 5) {
        throw new InvalidArgumentException('Exactly five sticker slots are required.');
    }

    $stickers = [];
    for ($i = 0; $i < 5; $i++) {
        $sticker = $stickerInput[$i] ?? null;
        if (!is_array($sticker)) {
            throw new InvalidArgumentException('Invalid sticker slot.');
        }

        $stickers[] = implode(';', [
            apiInt($sticker['id'] ?? 0, 'sticker.id', 0, 1000000),
            '0',
            apiNumericText(apiFloat($sticker['x'] ?? 0, 'sticker.x', -1000, 1000)),
            apiNumericText(apiFloat($sticker['y'] ?? 0, 'sticker.y', -1000, 1000)),
            apiNumericText(apiFloat($sticker['wear'] ?? 0.001, 'sticker.wear', 0, 1)),
            apiNumericText(apiFloat($sticker['scale'] ?? 1, 'sticker.scale', 0, 100)),
            apiNumericText(apiFloat($sticker['rotate'] ?? 0, 'sticker.rotate', -3600, 3600)),
        ]);
    }

    $keychain = $body['keychain'] ?? [];
    if (!is_array($keychain)) {
        throw new InvalidArgumentException('Invalid keychain.');
    }
    $keychainText = implode(';', [
        apiInt($keychain['id'] ?? 0, 'keychain.id', 0, 1000000),
        apiNumericText(apiFloat($keychain['x'] ?? 0, 'keychain.x', -1000, 1000)),
        apiNumericText(apiFloat($keychain['y'] ?? 0, 'keychain.y', -1000, 1000)),
        apiNumericText(apiFloat($keychain['z'] ?? 0, 'keychain.z', -1000, 1000)),
        apiInt($keychain['seed'] ?? 0, 'keychain.seed', 0, 1000000),
    ]);

    foreach ($teams as $team) {
        apiExec($pdo,
            'INSERT INTO wp_player_skins
                (steamid, weapon_team, weapon_defindex, weapon_paint_id, weapon_wear, weapon_seed,
                 weapon_nametag, weapon_stattrak, weapon_sticker_0, weapon_sticker_1,
                 weapon_sticker_2, weapon_sticker_3, weapon_sticker_4, weapon_keychain)
             VALUES
                (:steamid, :team, :defindex, :paint, :wear, :seed, :nametag, :stattrak,
                 :sticker0, :sticker1, :sticker2, :sticker3, :sticker4, :keychain)
             ON DUPLICATE KEY UPDATE
                weapon_paint_id = :paint_update,
                weapon_wear = :wear_update,
                weapon_seed = :seed_update,
                weapon_nametag = :nametag_update,
                weapon_stattrak = :stattrak_update,
                weapon_sticker_0 = :sticker0_update,
                weapon_sticker_1 = :sticker1_update,
                weapon_sticker_2 = :sticker2_update,
                weapon_sticker_3 = :sticker3_update,
                weapon_sticker_4 = :sticker4_update,
                weapon_keychain = :keychain_update',
            [
                ':steamid' => $steamid, ':team' => $team, ':defindex' => $defindex,
                ':paint' => $paint, ':wear' => $wear, ':seed' => $seed,
                ':nametag' => $nametag, ':stattrak' => $stattrak,
                ':sticker0' => $stickers[0], ':sticker1' => $stickers[1],
                ':sticker2' => $stickers[2], ':sticker3' => $stickers[3],
                ':sticker4' => $stickers[4], ':keychain' => $keychainText,
                ':paint_update' => $paint, ':wear_update' => $wear, ':seed_update' => $seed,
                ':nametag_update' => $nametag, ':stattrak_update' => $stattrak,
                ':sticker0_update' => $stickers[0], ':sticker1_update' => $stickers[1],
                ':sticker2_update' => $stickers[2], ':sticker3_update' => $stickers[3],
                ':sticker4_update' => $stickers[4], ':keychain_update' => $keychainText,
            ]
        );
    }
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$path = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');

if ($method === 'GET' && $path === '/health') {
    apiRespond(200, ['ok' => true, 'service' => 'fragcity-weaponpaints-api', 'version' => 1]);
}

if ($path !== '/v1/weaponpaints') {
    apiRespond(404, ['success' => false, 'error' => 'not_found']);
}
if ($method !== 'POST') {
    header('Allow: POST');
    apiRespond(405, ['success' => false, 'error' => 'method_not_allowed']);
}

$rawBody = file_get_contents('php://input');
if (!is_string($rawBody) || strlen($rawBody) > 32768) {
    apiRespond(413, ['success' => false, 'error' => 'request_too_large']);
}

$configPath = getenv('FRAGCITY_API_CONFIG') ?: '/etc/fragcity-api/config.php';
if (!is_file($configPath) || !is_readable($configPath)) {
    error_log('[FragCity API] Runtime config file is missing or unreadable.');
    apiRespond(503, ['success' => false, 'error' => 'service_not_configured']);
}

try {
    $config = require $configPath;
} catch (Throwable $e) {
    error_log('[FragCity API] Runtime config could not be loaded.');
    apiRespond(503, ['success' => false, 'error' => 'service_not_configured']);
}

$secret = is_array($config) ? (string)($config['shared_secret'] ?? '') : '';
if (!preg_match('/^[a-f0-9]{64,128}$/D', $secret)) {
    error_log('[FragCity API] shared_secret is missing or invalid.');
    apiRespond(503, ['success' => false, 'error' => 'service_not_configured']);
}

$timestamp = apiHeader('X-FragCity-Timestamp');
$nonce = apiHeader('X-FragCity-Nonce');
$signature = apiHeader('X-FragCity-Signature');

if (!preg_match('/^[0-9]{10}$/D', $timestamp)
    || abs(time() - (int)$timestamp) > 300
    || !preg_match('/^[a-f0-9]{64}$/D', $nonce)
    || !preg_match('/^[a-f0-9]{64}$/D', $signature)) {
    apiRespond(401, ['success' => false, 'error' => 'unauthorized']);
}

$canonical = $timestamp . "\n" . $nonce . "\n" . $method . "\n" . $path . "\n" . hash('sha256', $rawBody);
$expectedSignature = hash_hmac('sha256', $canonical, $secret);
if (!hash_equals($expectedSignature, $signature)) {
    apiRespond(401, ['success' => false, 'error' => 'unauthorized']);
}

$contentType = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
if ($contentType !== 'application/json') {
    apiRespond(415, ['success' => false, 'error' => 'content_type_must_be_json']);
}

$body = json_decode($rawBody, true);
if (!is_array($body) || json_last_error() !== JSON_ERROR_NONE) {
    apiRespond(400, ['success' => false, 'error' => 'invalid_json']);
}

$db = is_array($config) ? ($config['database'] ?? null) : null;
if (!is_array($db)) {
    error_log('[FragCity API] Database config is missing.');
    apiRespond(503, ['success' => false, 'error' => 'service_not_configured']);
}

try {
    $dbHost = trim((string)($db['host'] ?? '127.0.0.1'));
    $dbPort = (int)($db['port'] ?? 3306);
    $dbName = trim((string)($db['name'] ?? ''));
    $dbUser = trim((string)($db['username'] ?? ''));
    $dbPass = (string)($db['password'] ?? '');
    $charset = (string)($db['charset'] ?? 'utf8mb4');

    if ($dbHost === '' || $dbPort < 1 || $dbPort > 65535
        || !preg_match('/^[A-Za-z0-9_]+$/D', $dbName)
        || $dbUser === ''
        || !preg_match('/^[A-Za-z0-9_]+$/D', $charset)) {
        error_log('[FragCity API] Invalid database configuration.');
        apiRespond(503, ['success' => false, 'error' => 'service_not_configured']);
    }

    $pdo = new PDO(
        'mysql:host=' . $dbHost . ';port=' . $dbPort . ';dbname=' . $dbName . ';charset=' . $charset,
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    // Nonce uniqueness prevents replay. Store only the hash, never the nonce itself.
    apiExec($pdo,
        'INSERT INTO fragcity_api_nonces (nonce_hash, created_at) VALUES (:nonce, UTC_TIMESTAMP())',
        [':nonce' => hash('sha256', $nonce)]
    );

    // Accepted signatures expire after five minutes; ten-minute retention covers the full window.
    apiExec($pdo, 'DELETE FROM fragcity_api_nonces WHERE created_at < (UTC_TIMESTAMP() - INTERVAL 10 MINUTE)');

    $action = $body['action'] ?? '';
    if (!is_string($action)) {
        throw new InvalidArgumentException('Invalid action.');
    }

    if ($action === 'ping_db') {
        $pdo->query('SELECT 1')->fetchColumn();
        apiRespond(200, ['success' => true, 'data' => ['database' => 'ok']]);
    }

    $steamid = apiSteamId($body);

    if ($action === 'get_selection') {
        $queries = [
            'skins' => 'SELECT weapon_defindex, weapon_paint_id, weapon_wear, weapon_seed, weapon_team,
                               weapon_nametag, weapon_stattrak, weapon_sticker_0, weapon_sticker_1,
                               weapon_sticker_2, weapon_sticker_3, weapon_sticker_4, weapon_keychain
                        FROM wp_player_skins WHERE steamid = :steamid',
            'knife' => 'SELECT knife, weapon_team FROM wp_player_knife WHERE steamid = :steamid LIMIT 1',
            'agents' => 'SELECT agent_t, agent_ct FROM wp_player_agents WHERE steamid = :steamid LIMIT 1',
            'music' => 'SELECT weapon_team, music_id FROM wp_player_music WHERE steamid = :steamid',
            'pins' => 'SELECT weapon_team, id FROM wp_player_pins WHERE steamid = :steamid',
        ];

        $result = [];
        foreach ($queries as $key => $sql) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':steamid' => $steamid]);
            $result[$key] = $stmt->fetchAll();
        }
        apiRespond(200, ['success' => true, 'data' => $result]);
    }

    $teams = apiTeams($body);
    switch ($action) {
        case 'save_glove':
            apiTransaction($pdo, fn() => apiSaveGlove($pdo, $steamid, $teams, $body));
            break;
        case 'save_agent':
            apiTransaction($pdo, fn() => apiSaveAgent($pdo, $steamid, $teams, $body));
            break;
        case 'save_music':
            apiTransaction($pdo, fn() => apiSaveMusic($pdo, $steamid, $teams, $body));
            break;
        case 'save_pin':
            apiTransaction($pdo, fn() => apiSavePin($pdo, $steamid, $teams, $body));
            break;
        case 'save_knife':
            apiTransaction($pdo, fn() => apiSaveKnife($pdo, $steamid, $body));
            break;
        case 'save_skin':
            apiTransaction($pdo, fn() => apiSaveSkin($pdo, $steamid, $teams, $body));
            break;
        default:
            apiRespond(400, ['success' => false, 'error' => 'unsupported_action']);
    }

    apiRespond(200, ['success' => true, 'data' => ['saved' => true]]);
} catch (InvalidArgumentException $e) {
    apiRespond(400, ['success' => false, 'error' => 'invalid_request']);
} catch (PDOException $e) {
    if ((string)$e->getCode() === '23000') {
        // Includes repeated nonce, rejected by its unique primary key.
        apiRespond(401, ['success' => false, 'error' => 'unauthorized']);
    }
    error_log('[FragCity API] Database request failed (SQLSTATE ' . $e->getCode() . ').');
    apiRespond(500, ['success' => false, 'error' => 'database_error']);
} catch (Throwable $e) {
    error_log('[FragCity API] Request failed: ' . get_class($e) . ' (code ' . $e->getCode() . ').');
    apiRespond(500, ['success' => false, 'error' => 'internal_error']);
}

<?php
declare(strict_types=1);

/**
 * FragCity Linux API v1.
 * Active routes: WeaponPaints, SimpleAdmin, VIPCore, read-only Ranks leaderboard,
 * on-demand Pterodactyl Server Browser and Pterodactyl Monitoring. FACEIT stays on the website.
 * Legacy Windows-Agent routes are retired.
 * Only this file under public/ is routed by Nginx. Runtime settings are kept
 * outside the app and outside Git, normally at /etc/fragcity-api/config.php.
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
    $value = $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $name))] ?? '';
    return is_string($value) ? trim($value) : '';
}

function apiInt(mixed $value, string $field, int $min, int $max): int
{
    if (is_int($value)) {
        $number = $value;
    } elseif (is_string($value) && preg_match('/^-?\d{1,10}$/D', $value)) {
        $number = (int)$value;
    } else {
        throw new InvalidArgumentException('Invalid integer field: ' . $field);
    }

    if ($number < $min || $number > $max) {
        throw new InvalidArgumentException('Out of range: ' . $field);
    }
    return $number;
}

function apiFloat(mixed $value, string $field, float $min, float $max): float
{
    if (!is_numeric($value)) {
        throw new InvalidArgumentException('Invalid numeric field: ' . $field);
    }

    $number = (float)$value;
    if (!is_finite($number) || $number < $min || $number > $max) {
        throw new InvalidArgumentException('Out of range: ' . $field);
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

    $valid = [];
    foreach ($teams as $team) {
        $valid[] = apiInt($team, 'teams', 2, 3);
    }
    $valid = array_values(array_unique($valid));
    sort($valid);
    return $valid;
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

function apiNumberText(float $value): string
{
    $text = rtrim(rtrim(number_format($value, 12, '.', ''), '0'), '.');
    return $text === '' || $text === '-0' ? '0' : $text;
}

function apiSaveGlove(PDO $pdo, string $steamid, array $teams, array $body): void
{
    $defindex = apiInt($body['defindex'] ?? null, 'defindex', 0, 10000);
    if (!in_array($defindex, [0, 4725, 5027, 5030, 5031, 5032, 5033, 5034, 5035], true)) {
        throw new InvalidArgumentException('Invalid glove defindex.');
    }
    $paint = apiInt($body['paint_id'] ?? null, 'paint_id', 0, 100000);

    foreach ($teams as $team) {
        apiExec($pdo,
            'INSERT INTO wp_player_skins
                (steamid, weapon_team, weapon_defindex, weapon_paint_id, weapon_wear, weapon_seed)
             VALUES (:steamid, :team, :defindex, :paint, 0.001, 0)
             ON DUPLICATE KEY UPDATE weapon_paint_id = :paint_update',
            [
                ':steamid' => $steamid, ':team' => $team, ':defindex' => $defindex,
                ':paint' => $paint, ':paint_update' => $paint,
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

    // Preserve current FragCity behavior: a knife selection applies to both teams.
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
    $wear = apiFloat($body['wear'] ?? 0.001, 'wear', 0, 1);
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
            apiNumberText(apiFloat($sticker['x'] ?? 0, 'sticker.x', -1000, 1000)),
            apiNumberText(apiFloat($sticker['y'] ?? 0, 'sticker.y', -1000, 1000)),
            apiNumberText(apiFloat($sticker['wear'] ?? 0.001, 'sticker.wear', 0, 1)),
            apiNumberText(apiFloat($sticker['scale'] ?? 1, 'sticker.scale', 0, 100)),
            apiNumberText(apiFloat($sticker['rotate'] ?? 0, 'sticker.rotate', -3600, 3600)),
        ]);
    }

    $keychain = $body['keychain'] ?? [];
    if (!is_array($keychain)) {
        throw new InvalidArgumentException('Invalid keychain.');
    }
    $keychainText = implode(';', [
        apiInt($keychain['id'] ?? 0, 'keychain.id', 0, 1000000),
        apiNumberText(apiFloat($keychain['x'] ?? 0, 'keychain.x', -1000, 1000)),
        apiNumberText(apiFloat($keychain['y'] ?? 0, 'keychain.y', -1000, 1000)),
        apiNumberText(apiFloat($keychain['z'] ?? 0, 'keychain.z', -1000, 1000)),
        apiInt($keychain['seed'] ?? 0, 'keychain.seed', 0, 1000000),
    ]);

    foreach ($teams as $team) {
        // Preserve the previously active paint before wp_player_skins is
        // overwritten. This also migrates the legacy active row into inventory
        // on the first save after deploying per-paint storage.
        apiExec($pdo,
            'INSERT IGNORE INTO wp_player_skin_inventory
                (steamid, weapon_team, weapon_defindex, weapon_paint_id, weapon_wear, weapon_seed,
                 weapon_nametag, weapon_stattrak, weapon_sticker_0, weapon_sticker_1,
                 weapon_sticker_2, weapon_sticker_3, weapon_sticker_4, weapon_keychain)
             SELECT steamid, weapon_team, weapon_defindex, weapon_paint_id, weapon_wear, weapon_seed,
                    weapon_nametag, weapon_stattrak, weapon_sticker_0, weapon_sticker_1,
                    weapon_sticker_2, weapon_sticker_3, weapon_sticker_4, weapon_keychain
             FROM wp_player_skins
             WHERE steamid = :old_steamid AND weapon_team = :old_team
               AND weapon_defindex = :old_defindex',
            [
                ':old_steamid' => $steamid,
                ':old_team' => $team,
                ':old_defindex' => $defindex,
            ]
        );

        // Keep an independent saved configuration for each paint finish.
        apiExec($pdo,
            'INSERT INTO wp_player_skin_inventory
                (steamid, weapon_team, weapon_defindex, weapon_paint_id, weapon_wear, weapon_seed,
                 weapon_nametag, weapon_stattrak, weapon_sticker_0, weapon_sticker_1,
                 weapon_sticker_2, weapon_sticker_3, weapon_sticker_4, weapon_keychain)
             VALUES
                (:inv_steamid, :inv_team, :inv_defindex, :inv_paint, :inv_wear, :inv_seed,
                 :inv_nametag, :inv_stattrak, :inv_sticker0, :inv_sticker1,
                 :inv_sticker2, :inv_sticker3, :inv_sticker4, :inv_keychain)
             ON DUPLICATE KEY UPDATE
                weapon_wear = :inv_wear_update, weapon_seed = :inv_seed_update,
                weapon_nametag = :inv_nametag_update, weapon_stattrak = :inv_stattrak_update,
                weapon_sticker_0 = :inv_sticker0_update, weapon_sticker_1 = :inv_sticker1_update,
                weapon_sticker_2 = :inv_sticker2_update, weapon_sticker_3 = :inv_sticker3_update,
                weapon_sticker_4 = :inv_sticker4_update, weapon_keychain = :inv_keychain_update',
            [
                ':inv_steamid' => $steamid, ':inv_team' => $team,
                ':inv_defindex' => $defindex, ':inv_paint' => $paint,
                ':inv_wear' => $wear, ':inv_seed' => $seed,
                ':inv_nametag' => $nametag, ':inv_stattrak' => $stattrak,
                ':inv_sticker0' => $stickers[0], ':inv_sticker1' => $stickers[1],
                ':inv_sticker2' => $stickers[2], ':inv_sticker3' => $stickers[3],
                ':inv_sticker4' => $stickers[4], ':inv_keychain' => $keychainText,
                ':inv_wear_update' => $wear, ':inv_seed_update' => $seed,
                ':inv_nametag_update' => $nametag, ':inv_stattrak_update' => $stattrak,
                ':inv_sticker0_update' => $stickers[0], ':inv_sticker1_update' => $stickers[1],
                ':inv_sticker2_update' => $stickers[2], ':inv_sticker3_update' => $stickers[3],
                ':inv_sticker4_update' => $stickers[4], ':inv_keychain_update' => $keychainText,
            ]
        );

        // The plugin's original table continues to track only the active paint.
        apiExec($pdo,
            'INSERT INTO wp_player_skins
                (steamid, weapon_team, weapon_defindex, weapon_paint_id, weapon_wear, weapon_seed,
                 weapon_nametag, weapon_stattrak, weapon_sticker_0, weapon_sticker_1,
                 weapon_sticker_2, weapon_sticker_3, weapon_sticker_4, weapon_keychain)
             VALUES
                (:steamid, :team, :defindex, :paint, :wear, :seed, :nametag, :stattrak,
                 :sticker0, :sticker1, :sticker2, :sticker3, :sticker4, :keychain)
             ON DUPLICATE KEY UPDATE
                weapon_paint_id = :paint_update, weapon_wear = :wear_update,
                weapon_seed = :seed_update, weapon_nametag = :nametag_update,
                weapon_stattrak = :stattrak_update, weapon_sticker_0 = :sticker0_update,
                weapon_sticker_1 = :sticker1_update, weapon_sticker_2 = :sticker2_update,
                weapon_sticker_3 = :sticker3_update, weapon_sticker_4 = :sticker4_update,
                weapon_keychain = :keychain_update',
            [
                ':steamid' => $steamid, ':team' => $team, ':defindex' => $defindex,
                ':paint' => $paint, ':wear' => $wear, ':seed' => $seed,
                ':nametag' => $nametag, ':stattrak' => $stattrak,
                ':sticker0' => $stickers[0], ':sticker1' => $stickers[1],
                ':sticker2' => $stickers[2], ':sticker3' => $stickers[3],
                ':sticker4' => $stickers[4], ':keychain' => $keychainText,
                ':paint_update' => $paint, ':wear_update' => $wear,
                ':seed_update' => $seed, ':nametag_update' => $nametag,
                ':stattrak_update' => $stattrak, ':sticker0_update' => $stickers[0],
                ':sticker1_update' => $stickers[1], ':sticker2_update' => $stickers[2],
                ':sticker3_update' => $stickers[3], ':sticker4_update' => $stickers[4],
                ':keychain_update' => $keychainText,
            ]
        );
    }
}

/**
 * Remove one saved skin configuration for exactly one player/team/weapon/paint.
 * The plugin's active row is cleared only when it references that same paint.
 */
function apiDeleteSkinInventory(PDO $pdo, string $steamid, array $teams, array $body): bool
{
    $defindex = apiInt($body['defindex'] ?? null, 'defindex', 1, 10000);
    $paint = apiInt($body['paint_id'] ?? null, 'paint_id', 0, 100000);
    $deleted = false;

    foreach ($teams as $team) {
        $stmt = $pdo->prepare(
            'DELETE FROM wp_player_skin_inventory
             WHERE steamid = :steamid
               AND weapon_team = :team
               AND weapon_defindex = :defindex
               AND weapon_paint_id = :paint'
        );
        $stmt->execute([
            ':steamid' => $steamid,
            ':team' => $team,
            ':defindex' => $defindex,
            ':paint' => $paint,
        ]);

        if ($stmt->rowCount() < 1) {
            continue;
        }

        $deleted = true;

        // Clear the plugin's active loadout only if it points to this exact paint.
        apiExec($pdo,
            'DELETE FROM wp_player_skins
             WHERE steamid = :steamid
               AND weapon_team = :team
               AND weapon_defindex = :defindex
               AND weapon_paint_id = :paint',
            [
                ':steamid' => $steamid,
                ':team' => $team,
                ':defindex' => $defindex,
                ':paint' => $paint,
            ]
        );
    }

    return $deleted;
}

/**
 * Clear the authenticated player's saved firearm-paint inventory and active
 * firearm-skin rows. This intentionally leaves all non-skin plugin tables alone.
 *
 * @return array{deleted:bool,deleted_inventory:int,deleted_active:int}
 */
function apiClearSkinInventory(PDO $pdo, string $steamid): array
{
    $inventory = $pdo->prepare(
        'DELETE FROM wp_player_skin_inventory WHERE steamid = :steamid'
    );
    $inventory->execute([':steamid' => $steamid]);

    $active = $pdo->prepare(
        'DELETE FROM wp_player_skins WHERE steamid = :steamid'
    );
    $active->execute([':steamid' => $steamid]);

    return [
        'deleted' => true,
        'deleted_inventory' => (int)$inventory->rowCount(),
        'deleted_active' => (int)$active->rowCount(),
    ];
}



$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
$path = (string)(parse_url($uri, PHP_URL_PATH) ?: '/');

if ($method === 'GET' && $path === '/health') {
    apiRespond(200, ['ok' => true, 'service' => 'fragcity-linuxapi', 'version' => 1]);
}
if (str_starts_with($path, '/v1/agent/')) {
    apiRespond(410, ['status' => 'error', 'message' => 'legacy_agent_api_retired']);
}
$isPterodactylStandbyRequest = $path === '/v1/pterodactyl-standby';
$isPterodactylMonitoringRequest = $path === '/v1/pterodactyl-monitoring';
$isWeaponPaintsRequest = $path === '/v1/weaponpaints';
$isSimpleAdminRequest = $path === '/v1/simpleadmin';
$isVipCoreRequest = $path === '/v1/vipcore';
$isRanksRequest = $path === '/v1/ranks';
if (!$isPterodactylStandbyRequest && !$isPterodactylMonitoringRequest
    && !$isWeaponPaintsRequest && !$isSimpleAdminRequest && !$isVipCoreRequest && !$isRanksRequest) {
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
    error_log('[FragCity API] Runtime config missing/unreadable.');
    apiRespond(503, ['success' => false, 'error' => 'service_not_configured']);
}
try {
    $config = require $configPath;
} catch (Throwable $e) {
    error_log('[FragCity API] Runtime config failed to load.');
    apiRespond(503, ['success' => false, 'error' => 'service_not_configured']);
}

$secret = '';
if (is_array($config)) {
    if ($isPterodactylStandbyRequest) {
        $secret = (string)($config['pterodactyl_standby']['shared_secret'] ?? '');
    } elseif ($isPterodactylMonitoringRequest) {
        $secret = (string)($config['pterodactyl_monitoring']['shared_secret'] ?? '');
    } else {
        $secret = (string)($config['shared_secret'] ?? '');
    }
}
if (!preg_match('/^[a-f0-9]{64,128}$/D', $secret)) {
    error_log('[FragCity API] Endpoint shared secret missing/invalid.');
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
if (!hash_equals(hash_hmac('sha256', $canonical, $secret), $signature)) {
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

$dbConfig = is_array($config) ? ($config['database'] ?? null) : null;
if (!is_array($dbConfig)) {
    apiRespond(503, ['success' => false, 'error' => 'service_not_configured']);
}

try {
    $host = trim((string)($dbConfig['host'] ?? '127.0.0.1'));
    $port = (int)($dbConfig['port'] ?? 3306);
    $name = trim((string)($dbConfig['name'] ?? ''));
    $user = trim((string)($dbConfig['username'] ?? ''));
    $pass = (string)($dbConfig['password'] ?? '');
    $charset = (string)($dbConfig['charset'] ?? 'utf8mb4');

    if ($host === '' || $port < 1 || $port > 65535
        || !preg_match('/^[A-Za-z0-9_]+$/D', $name)
        || $user === ''
        || !preg_match('/^[A-Za-z0-9_]+$/D', $charset)) {
        apiRespond(503, ['success' => false, 'error' => 'service_not_configured']);
    }

    $pdo = new PDO(
        'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=' . $charset,
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    // A duplicate nonce is rejected before any requested mutation is processed.
    try {
        apiExec($pdo,
            'INSERT INTO fragcity_api_nonces (nonce_hash, created_at) VALUES (:nonce, UTC_TIMESTAMP())',
            [':nonce' => hash('sha256', $nonce)]
        );
    } catch (PDOException $e) {
        $driverCode = (int)($e->errorInfo[1] ?? 0);
        if ((string)$e->getCode() === '23000' && $driverCode === 1062) {
            apiRespond(401, ['success' => false, 'error' => 'unauthorized']);
        }
        throw $e;
    }
    if (random_int(1, 20) === 1) {
        apiExec($pdo, 'DELETE FROM fragcity_api_nonces WHERE created_at < (UTC_TIMESTAMP() - INTERVAL 10 MINUTE)');
    }

    $action = $body['action'] ?? '';
    if (!is_string($action)) {
        throw new InvalidArgumentException('Invalid action.');
    }

    if ($isSimpleAdminRequest) {
        if (!in_array($action, ['resolve_player_names', 'get_records'], true)) {
            apiRespond(400, ['success' => false, 'error' => 'invalid_action']);
        }

        $simpleAdminConfig = is_array($config['simpleadmin'] ?? null)
            ? $config['simpleadmin']
            : [];
        if (empty($simpleAdminConfig['enabled'])) {
            apiRespond(503, ['success' => false, 'error' => 'simpleadmin_disabled']);
        }

        $simpleDatabase = is_array($simpleAdminConfig['database'] ?? null)
            ? $simpleAdminConfig['database']
            : [];

        // Reuse the same existing Plugin DB account already used for WeaponPaints.
        // Only the target schema changes; grants remain read-only on SimpleAdmin.
        $simpleHost = trim((string)($dbConfig['host'] ?? '127.0.0.1'));
        $simplePort = (int)($dbConfig['port'] ?? 3306);
        $simpleName = trim((string)($simpleDatabase['name'] ?? 'GLOBAL_simpleadmin'));
        $simpleUser = trim((string)($dbConfig['username'] ?? ''));
        $simplePass = (string)($dbConfig['password'] ?? '');
        $simpleCharset = (string)($dbConfig['charset'] ?? 'utf8mb4');

        if ($simpleHost === '' || $simplePort < 1 || $simplePort > 65535
            || !preg_match('/^[A-Za-z0-9_]+$/D', $simpleName)
            || $simpleUser === ''
            || !preg_match('/^[A-Za-z0-9_]+$/D', $simpleCharset)) {
            apiRespond(503, ['success' => false, 'error' => 'simpleadmin_not_configured']);
        }

        try {
            $simplePdo = new PDO(
                'mysql:host=' . $simpleHost . ';port=' . $simplePort
                    . ';dbname=' . $simpleName . ';charset=' . $simpleCharset,
                $simpleUser,
                $simplePass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            if ($action === 'resolve_player_names') {
                require_once __DIR__ . '/../src/SimpleAdmin/SimpleAdminPlayerLookup.php';
                $lookup = new SimpleAdminPlayerLookup($simplePdo);
                apiRespond(200, [
                    'success' => true,
                    'data' => [
                        'matches' => $lookup->resolveNames($body['names'] ?? null),
                    ],
                ]);
            }

            require_once __DIR__ . '/../src/SimpleAdmin/SimpleAdminBanlist.php';
            $banlist = new SimpleAdminBanlist($simplePdo);
            $records = $banlist->getRecords(
                $body['type'] ?? null,
                $body['search'] ?? '',
                $body['page'] ?? null,
                $body['per_page'] ?? null,
                $body['include_sensitive'] ?? false
            );

            apiRespond(200, ['success' => true, 'data' => $records]);
        } catch (InvalidArgumentException $e) {
            $errorCode = $action === 'resolve_player_names'
                ? 'invalid_player_names'
                : 'invalid_banlist_request';
            apiRespond(400, ['success' => false, 'error' => $errorCode]);
        } catch (Throwable $e) {
            error_log('[FragCity API] SimpleAdmin request failed; action=' . $action
                . '; type=' . get_class($e));
            apiRespond(503, ['success' => false, 'error' => 'simpleadmin_unavailable']);
        }
    }
    if ($isVipCoreRequest) {
        if (!in_array($action, ['ping_db', 'get_status', 'get_free_trial_status', 'claim_free_silver_trial', 'grant_test_vip'], true)) {
            apiRespond(400, ['success' => false, 'error' => 'invalid_action']);
        }

        $vipConfig = is_array($config['vipcore'] ?? null) ? $config['vipcore'] : [];
        if (empty($vipConfig['enabled'])) {
            apiRespond(503, ['success' => false, 'error' => 'vipcore_disabled']);
        }

        $vipDatabase = is_array($vipConfig['database'] ?? null) ? $vipConfig['database'] : [];
        $vipHost = trim((string)($vipDatabase['host'] ?? '127.0.0.1'));
        $vipPort = (int)($vipDatabase['port'] ?? 3306);
        $vipName = trim((string)($vipDatabase['name'] ?? ''));
        $vipUser = trim((string)($vipDatabase['username'] ?? ''));
        $vipPass = (string)($vipDatabase['password'] ?? '');
        $vipCharset = trim((string)($vipDatabase['charset'] ?? 'utf8mb4'));

        if ($vipHost !== '127.0.0.1' || $vipPort < 1 || $vipPort > 65535
            || $vipName !== 'GLOBAL_vip' || $vipUser === '' || $vipPass === ''
            || !preg_match('/^[A-Za-z0-9_]+$/D', $vipCharset)) {
            apiRespond(503, ['success' => false, 'error' => 'vipcore_not_configured']);
        }

        $vipServerId = apiInt($vipConfig['server_id'] ?? 0, 'vipcore.server_id', 0, 2147483647);
        $testConfig = is_array($vipConfig['test_mode'] ?? null) ? $vipConfig['test_mode'] : [];
        $testDuration = apiInt($testConfig['duration_seconds'] ?? 3600, 'vipcore.test_duration_seconds', 300, 86400);

        require_once __DIR__ . '/../src/VIPCore/VipCoreService.php';
        try {
            $vipPdo = new PDO(
                'mysql:host=' . $vipHost . ';port=' . $vipPort
                    . ';dbname=' . $vipName . ';charset=' . $vipCharset,
                $vipUser,
                $vipPass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            $vipService = new VipCoreService($vipPdo, $vipServerId, $testDuration);

            if ($action === 'ping_db') {
                apiRespond(200, ['success' => true, 'data' => $vipService->ping()]);
            }

            if ($action === 'get_status') {
                $steamId64 = $body['steamid64'] ?? null;
                if (!is_string($steamId64)) {
                    throw new InvalidArgumentException('Invalid SteamID64.');
                }
                apiRespond(200, [
                    'success' => true,
                    'data' => $vipService->getStatus($steamId64),
                ]);
            }

            if ($action === 'get_free_trial_status') {
                $steamId64 = $body['steamid64'] ?? null;
                if (!is_string($steamId64)) {
                    throw new InvalidArgumentException('Invalid SteamID64.');
                }
                apiRespond(200, [
                    'success' => true,
                    'data' => $vipService->getFreeTrialStatus($steamId64),
                ]);
            }

            if ($action === 'claim_free_silver_trial') {
                $steamId64 = $body['steamid64'] ?? null;
                $playerName = $body['player_name'] ?? null;
                if (!is_string($steamId64) || !is_string($playerName)) {
                    throw new InvalidArgumentException('Invalid VIP free-trial identity.');
                }
                $grant = $vipService->grantFreeSilverTrial($steamId64, $playerName);
                apiRespond(200, [
                    'success' => true,
                    'data' => $grant,
                ]);
            }

            if (empty($testConfig['enabled'])) {
                apiRespond(503, ['success' => false, 'error' => 'test_mode_disabled']);
            }

            $steamId64 = $body['steamid64'] ?? null;
            $playerName = $body['player_name'] ?? null;
            if (!is_string($steamId64) || !is_string($playerName)) {
                throw new InvalidArgumentException('Invalid VIP test grant identity.');
            }
            $allowedSteamIds = is_array($testConfig['allowed_steamids'] ?? null)
                ? $testConfig['allowed_steamids']
                : [];
            if (!VipCoreService::isTestGrantAllowed($steamId64, $allowedSteamIds)) {
                apiRespond(403, ['success' => false, 'error' => 'test_grant_not_allowed']);
            }
            $level = apiInt($body['level'] ?? null, 'level', 1, 3);

            $grant = $vipService->grantTestMembership($steamId64, $playerName, $level);
            apiRespond(200, ['success' => true, 'data' => $grant]);
        } catch (VipCoreFreeTrialAlreadyClaimed $e) {
            apiRespond(409, ['success' => false, 'error' => 'free_trial_already_claimed']);
        } catch (VipCoreMembershipConflict $e) {
            apiRespond(409, ['success' => false, 'error' => 'membership_already_active']);
        } catch (InvalidArgumentException $e) {
            apiRespond(400, ['success' => false, 'error' => 'invalid_request']);
        } catch (PDOException $e) {
            error_log('[FragCity API] VIPCore database request failed; SQLSTATE ' . $e->getCode());
            apiRespond(503, ['success' => false, 'error' => 'vipcore_unavailable']);
        } catch (Throwable $e) {
            error_log('[FragCity API] VIPCore request failed; type=' . get_class($e));
            apiRespond(503, ['success' => false, 'error' => 'vipcore_unavailable']);
        }
    }

    if ($isRanksRequest) {
        if (!in_array($action, ['ping_db', 'get_leaderboard', 'get_player'], true)) {
            apiRespond(400, ['success' => false, 'error' => 'invalid_action']);
        }

        $ranksConfig = is_array($config['ranks'] ?? null) ? $config['ranks'] : [];
        if (empty($ranksConfig['enabled'])) {
            apiRespond(503, ['success' => false, 'error' => 'ranks_disabled']);
        }

        $ranksDatabase = is_array($ranksConfig['database'] ?? null)
            ? $ranksConfig['database']
            : [];
        $ranksHost = trim((string)($ranksDatabase['host'] ?? '127.0.0.1'));
        $ranksPort = (int)($ranksDatabase['port'] ?? 3306);
        $ranksName = trim((string)($ranksDatabase['name'] ?? ''));
        $ranksUser = trim((string)($ranksDatabase['username'] ?? ''));
        $ranksPass = (string)($ranksDatabase['password'] ?? '');
        $ranksCharset = trim((string)($ranksDatabase['charset'] ?? 'utf8mb4'));

        if ($ranksHost !== '127.0.0.1' || $ranksPort < 1 || $ranksPort > 65535
            || $ranksName !== 'GLOBAL_ranks' || $ranksUser === '' || $ranksPass === ''
            || !preg_match('/^[A-Za-z0-9_]+$/D', $ranksCharset)) {
            apiRespond(503, ['success' => false, 'error' => 'ranks_not_configured']);
        }

        require_once __DIR__ . '/../src/Ranks/RanksService.php';
        try {
            $ranksPdo = new PDO(
                'mysql:host=' . $ranksHost . ';port=' . $ranksPort
                    . ';dbname=' . $ranksName . ';charset=' . $ranksCharset,
                $ranksUser,
                $ranksPass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            $ranksService = new RanksService($ranksPdo);

            if ($action === 'ping_db') {
                apiRespond(200, ['success' => true, 'data' => $ranksService->ping()]);
            }

            if ($action === 'get_player') {
                $steamId64 = $body['steamid64'] ?? null;
                if (!is_string($steamId64)) {
                    throw new InvalidArgumentException('Invalid SteamID64.');
                }

                apiRespond(200, [
                    'success' => true,
                    'data' => $ranksService->getPlayer($steamId64),
                ]);
            }

            $sort = $body['sort'] ?? 'points';
            $order = $body['order'] ?? 'DESC';
            $search = $body['search'] ?? '';
            if (!is_string($sort) || !is_string($order) || !is_string($search)) {
                throw new InvalidArgumentException('Invalid Ranks leaderboard filters.');
            }

            $page = apiInt($body['page'] ?? 1, 'ranks.page', 1, 1000000);
            $perPage = apiInt($body['per_page'] ?? 15, 'ranks.per_page', 1, 100);

            apiRespond(200, [
                'success' => true,
                'data' => $ranksService->getLeaderboard($sort, $order, $search, $page, $perPage),
            ]);
        } catch (InvalidArgumentException $e) {
            apiRespond(400, ['success' => false, 'error' => 'invalid_ranks_request']);
        } catch (PDOException $e) {
            error_log('[FragCity API] Ranks database request failed; SQLSTATE ' . $e->getCode());
            apiRespond(503, ['success' => false, 'error' => 'ranks_unavailable']);
        } catch (Throwable $e) {
            error_log('[FragCity API] Ranks request failed; type=' . get_class($e));
            apiRespond(503, ['success' => false, 'error' => 'ranks_unavailable']);
        }
    }

    if ($isPterodactylMonitoringRequest) {
        if ($action !== 'get_snapshot') {
            apiRespond(400, ['success' => false, 'error' => 'invalid_action']);
        }

        $monitoringConfig = is_array($config['pterodactyl_monitoring'] ?? null)
            ? $config['pterodactyl_monitoring']
            : [];
        if (empty($monitoringConfig['enabled'])) {
            apiRespond(503, ['success' => false, 'error' => 'pterodactyl_monitoring_disabled']);
        }

        try {
            require_once __DIR__ . '/../src/PterodactylMonitoring/PterodactylMonitoringService.php';
            $monitoring = new PterodactylMonitoringService($config);
            apiRespond(200, [
                'success' => true,
                'data' => $monitoring->snapshot(),
            ]);
        } catch (Throwable $e) {
            error_log(
                '[FragCity API] Pterodactyl monitoring failed; type=' . get_class($e)
                . '; code=' . $e->getCode()
            );
            apiRespond(503, ['success' => false, 'error' => 'pterodactyl_monitoring_unavailable']);
        }
    }

    if ($isPterodactylStandbyRequest) {
        $serverConfig = is_array($config['pterodactyl_standby'] ?? null)
            ? $config['pterodactyl_standby']
            : [];
        if (empty($serverConfig['enabled'])) {
            apiRespond(503, ['success' => false, 'error' => 'pterodactyl_standby_disabled']);
        }

        if ($action === 'get_players') {
            $serverId = $body['server_id'] ?? null;
            if (!is_string($serverId) || !preg_match('/^[a-f0-9]{16}$/Di', $serverId)) {
                apiRespond(400, ['success' => false, 'error' => 'invalid_server_id']);
            }

            try {
                require_once __DIR__ . '/../src/PterodactylStandby/PterodactylStandbyDiscovery.php';
                $discovery = new PterodactylStandbyDiscovery($config);
                apiRespond(200, [
                    'success' => true,
                    'data' => $discovery->queryPlayers($serverId),
                ]);
            } catch (OutOfBoundsException $e) {
                apiRespond(404, ['success' => false, 'error' => 'server_not_found']);
            } catch (Throwable $e) {
                $detail = preg_replace('/[\\r\\n]+/', ' ', $e->getMessage()) ?? '';
                error_log(
                    '[FragCity API] Pterodactyl Standby player query failed; type='
                    . get_class($e)
                    . '; detail=' . substr($detail, 0, 180)
                );
                apiRespond(503, [
                    'success' => false,
                    'error' => 'pterodactyl_players_unavailable',
                ]);
            }
        }

        if ($action !== 'list_servers') {
            apiRespond(400, ['success' => false, 'error' => 'invalid_action']);
        }

        try {
            require_once __DIR__ . '/../src/PterodactylStandby/PterodactylStandbyDiscovery.php';
            require_once __DIR__ . '/../src/PterodactylStandby/PterodactylStandbySnapshotCache.php';

            $discovery = new PterodactylStandbyDiscovery($config);
            $cache = new PterodactylStandbySnapshotCache($serverConfig);
            $snapshot = $cache->getOrRefresh(
                static fn(): array => $discovery->discover()
            );

            apiRespond(200, [
                'success' => true,
                'data' => $snapshot,
            ]);
        } catch (Throwable $e) {
            $detail = preg_replace('/[\\r\\n]+/', ' ', $e->getMessage()) ?? '';
            error_log(
                '[FragCity API] Pterodactyl Standby live discovery failed; type='
                . get_class($e)
                . '; detail=' . substr($detail, 0, 180)
            );
            apiRespond(503, [
                'success' => false,
                'error' => 'pterodactyl_standby_unavailable',
            ]);
        }
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
                        FROM wp_player_skins WHERE steamid = :steamid
                        ORDER BY weapon_team, weapon_defindex',
            'skin_inventory' => 'SELECT weapon_defindex, weapon_paint_id, weapon_wear, weapon_seed, weapon_team,
                                         weapon_nametag, weapon_stattrak, weapon_sticker_0, weapon_sticker_1,
                                         weapon_sticker_2, weapon_sticker_3, weapon_sticker_4, weapon_keychain
                                  FROM wp_player_skin_inventory
                                  WHERE steamid = :steamid
                                  ORDER BY weapon_team, weapon_defindex, weapon_paint_id',
            'knife' => 'SELECT knife, weapon_team FROM wp_player_knife WHERE steamid = :steamid LIMIT 1',
            'agents' => 'SELECT agent_t, agent_ct FROM wp_player_agents WHERE steamid = :steamid LIMIT 1',
            'music' => 'SELECT weapon_team, music_id FROM wp_player_music WHERE steamid = :steamid',
            'pins' => 'SELECT weapon_team, id FROM wp_player_pins WHERE steamid = :steamid',
        ];

        $data = [];
        foreach ($queries as $key => $sql) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':steamid' => $steamid]);
            $data[$key] = $stmt->fetchAll();
        }
        apiRespond(200, ['success' => true, 'data' => $data]);
    }

    $teams = apiTeams($body);
    $mutationResult = ['saved' => true];

    apiTransaction($pdo, function () use ($pdo, $steamid, $teams, $body, $action, &$mutationResult): void {
        switch ($action) {
            case 'save_glove': apiSaveGlove($pdo, $steamid, $teams, $body); break;
            case 'save_agent': apiSaveAgent($pdo, $steamid, $teams, $body); break;
            case 'save_music': apiSaveMusic($pdo, $steamid, $teams, $body); break;
            case 'save_pin': apiSavePin($pdo, $steamid, $teams, $body); break;
            case 'save_knife': apiSaveKnife($pdo, $steamid, $body); break;
            case 'save_skin': apiSaveSkin($pdo, $steamid, $teams, $body); break;
            case 'delete_skin':
                $mutationResult = [
                    'deleted' => apiDeleteSkinInventory($pdo, $steamid, $teams, $body),
                ];
                break;
            case 'clear_skin_inventory':
                $mutationResult = apiClearSkinInventory($pdo, $steamid);
                break;
            default: throw new InvalidArgumentException('Unsupported action.');
        }
    });

    apiRespond(200, ['success' => true, 'data' => $mutationResult]);
} catch (InvalidArgumentException $e) {
    apiRespond(400, ['success' => false, 'error' => 'invalid_request']);
} catch (PDOException $e) {
    error_log('[FragCity API] Database request failed; SQLSTATE ' . $e->getCode());
    apiRespond(500, ['success' => false, 'error' => 'database_error']);
} catch (Throwable $e) {
    error_log('[FragCity API] Request failed: ' . get_class($e) . ' code=' . $e->getCode());
    apiRespond(500, ['success' => false, 'error' => 'internal_error']);
}

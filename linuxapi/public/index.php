<?php
declare(strict_types=1);

/**
 * FragCity Linux API v1.
 * Active product API: WeaponPaints only. Legacy Windows-Agent routes are retired.
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


$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
$path = (string)(parse_url($uri, PHP_URL_PATH) ?: '/');

if ($method === 'GET' && $path === '/health') {
    apiRespond(200, ['ok' => true, 'service' => 'fragcity-linuxapi', 'version' => 1]);
}
if (str_starts_with($path, '/v1/agent/')) {
    apiRespond(410, ['status' => 'error', 'message' => 'legacy_agent_api_retired']);
}
$isServerBrowserRequest = $path === '/v1/server-browser';
$isWeaponPaintsRequest = $path === '/v1/weaponpaints';
if (!$isServerBrowserRequest && !$isWeaponPaintsRequest) {
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
    $secret = $isServerBrowserRequest
        ? (string)($config['server_browser']['shared_secret'] ?? '')
        : (string)($config['shared_secret'] ?? '');
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

    if ($isServerBrowserRequest) {
        if ($action !== 'list_servers') {
            apiRespond(400, ['success' => false, 'error' => 'invalid_action']);
        }

        $serverConfig = is_array($config['server_browser'] ?? null)
            ? $config['server_browser']
            : [];
        if (empty($serverConfig['enabled'])) {
            apiRespond(503, ['success' => false, 'error' => 'server_browser_disabled']);
        }

        $cacheFile = trim((string)($serverConfig['cache_file'] ?? ''));
        if ($cacheFile === '' || !is_file($cacheFile) || !is_readable($cacheFile)) {
            apiRespond(503, ['success' => false, 'error' => 'server_browser_cache_unavailable']);
        }

        $cacheRaw = file_get_contents($cacheFile);
        $snapshot = is_string($cacheRaw) ? json_decode($cacheRaw, true) : null;
        if (!is_array($snapshot) || !is_array($snapshot['servers'] ?? null)) {
            error_log('[FragCity API] Server Browser cache is invalid.');
            apiRespond(503, ['success' => false, 'error' => 'server_browser_cache_invalid']);
        }

        $generatedAt = is_string($snapshot['generated_at'] ?? null)
            ? $snapshot['generated_at']
            : '';
        $generatedTimestamp = $generatedAt !== '' ? strtotime($generatedAt) : false;
        if ($generatedTimestamp === false) {
            apiRespond(503, ['success' => false, 'error' => 'server_browser_cache_invalid']);
        }

        $cacheAge = max(0, time() - $generatedTimestamp);
        $maxCacheAge = max(30, min(3600, (int)($serverConfig['cache_max_age_seconds'] ?? 180)));

        apiRespond(200, [
            'success' => true,
            'data' => [
                'generated_at' => $generatedAt,
                'cache_age_seconds' => $cacheAge,
                'stale' => $cacheAge > $maxCacheAge,
                'servers' => array_values($snapshot['servers']),
            ],
        ]);
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
    apiTransaction($pdo, function () use ($pdo, $steamid, $teams, $body, $action): void {
        switch ($action) {
            case 'save_glove': apiSaveGlove($pdo, $steamid, $teams, $body); break;
            case 'save_agent': apiSaveAgent($pdo, $steamid, $teams, $body); break;
            case 'save_music': apiSaveMusic($pdo, $steamid, $teams, $body); break;
            case 'save_pin': apiSavePin($pdo, $steamid, $teams, $body); break;
            case 'save_knife': apiSaveKnife($pdo, $steamid, $body); break;
            case 'save_skin': apiSaveSkin($pdo, $steamid, $teams, $body); break;
            default: throw new InvalidArgumentException('Unsupported action.');
        }
    });

    apiRespond(200, ['success' => true, 'data' => ['saved' => true]]);
} catch (InvalidArgumentException $e) {
    apiRespond(400, ['success' => false, 'error' => 'invalid_request']);
} catch (PDOException $e) {
    error_log('[FragCity API] Database request failed; SQLSTATE ' . $e->getCode());
    apiRespond(500, ['success' => false, 'error' => 'database_error']);
} catch (Throwable $e) {
    error_log('[FragCity API] Request failed: ' . get_class($e) . ' code=' . $e->getCode());
    apiRespond(500, ['success' => false, 'error' => 'internal_error']);
}

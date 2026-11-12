<?php
declare(strict_types=1);

/**
 * Private Paymenter -> FragCity identity/profile bridge.
 *
 * This endpoint is server-to-server only. It never starts a browser session,
 * never trusts a browser-supplied Paymenter user ID, and never returns the
 * complete users row. Paymenter must sign the exact raw JSON body with the
 * existing integrations.paymenter_backend.shared_secret.
 */

require_once __DIR__ . '/../settings/loader.php';

$respond = static function (int $status, array $body): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, private, max-age=0');
    header('Pragma: no-cache');
    header('Referrer-Policy: no-referrer');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Vary: Authorization');
    echo json_encode(
        $body,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    $respond(405, ['success' => false, 'error' => 'method_not_allowed']);
}

$contentType = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
if ($contentType !== 'application/json') {
    $respond(415, ['success' => false, 'error' => 'json_required']);
}

$rawBody = file_get_contents('php://input');
if (!is_string($rawBody) || $rawBody === '' || strlen($rawBody) > 16384) {
    $respond(413, ['success' => false, 'error' => 'invalid_request_size']);
}

$secret = trim((string) fragcity_settings('integrations.paymenter_backend.shared_secret', ''));
if (!preg_match('/\A[a-f0-9]{64,256}\z/iD', $secret)) {
    error_log('[FragCity] Paymenter profile API is unavailable: bridge secret is not configured.');
    $respond(503, ['success' => false, 'error' => 'bridge_not_configured']);
}

$headers = function_exists('getallheaders') ? getallheaders() : [];
$headers = is_array($headers) ? $headers : [];
$header = static function (string $name) use ($headers): string {
    foreach ($headers as $key => $value) {
        if (strcasecmp((string) $key, $name) === 0 && is_scalar($value)) {
            return trim((string) $value);
        }
    }
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return trim((string) ($_SERVER[$serverKey] ?? ''));
};

$timestamp = $header('X-FragCity-Timestamp');
$nonce = strtolower($header('X-FragCity-Nonce'));
$signature = strtolower($header('X-FragCity-Signature'));

if (
    !preg_match('/\A[0-9]{10}\z/D', $timestamp)
    || !preg_match('/\A[a-f0-9]{32,64}\z/D', $nonce)
    || !preg_match('/\A[a-f0-9]{64}\z/D', $signature)
    || abs(time() - (int) $timestamp) > 300
) {
    $respond(401, ['success' => false, 'error' => 'invalid_signature_headers']);
}

$expectedSignature = hash_hmac('sha256', $timestamp . "\n" . $nonce . "\n" . $rawBody, $secret);
if (!hash_equals($expectedSignature, $signature)) {
    $respond(401, ['success' => false, 'error' => 'invalid_signature']);
}

try {
    $payload = json_decode($rawBody, true, 32, JSON_THROW_ON_ERROR);
} catch (Throwable) {
    $respond(400, ['success' => false, 'error' => 'invalid_json_body']);
}

if (!is_array($payload)) {
    $respond(400, ['success' => false, 'error' => 'invalid_json_body']);
}

$steamId64 = trim((string) ($payload['steamid64'] ?? ''));
$paymenterUserId = filter_var(
    $payload['paymenter_user_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if (!preg_match('/\A[0-9]{17}\z/D', $steamId64) || $paymenterUserId === false) {
    $respond(422, ['success' => false, 'error' => 'validation_failed']);
}

$databaseProfile = fragcity_settings('files.index.database_profile', 'main');
$databaseProfile = is_string($databaseProfile) && trim($databaseProfile) !== ''
    ? trim($databaseProfile)
    : 'main';
$pdo = fragcity_create_pdo($databaseProfile);
if (!$pdo instanceof PDO) {
    $respond(503, ['success' => false, 'error' => 'source_database_unavailable']);
}

/* Consume a one-time nonce after signature validation. Only the SHA-256
   digest is stored; raw nonces and request bodies are never persisted. */
try {
    $pdo->prepare(
        'DELETE FROM fragcity_paymenter_profile_api_nonces WHERE expires_at < NOW()'
    )->execute();

    $nonceInsert = $pdo->prepare(
        'INSERT INTO fragcity_paymenter_profile_api_nonces (nonce_hash, expires_at, created_at)
         VALUES (?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), NOW())'
    );
    $nonceInsert->execute([hash('sha256', $nonce)]);
} catch (PDOException $error) {
    if ((string) $error->getCode() === '23000') {
        $respond(409, ['success' => false, 'error' => 'request_replayed']);
    }

    error_log('[FragCity] Paymenter profile API nonce storage is unavailable (SQLSTATE ' . (string) $error->getCode() . ').');
    $respond(503, ['success' => false, 'error' => 'nonce_storage_unavailable']);
}

/* Select only account/profile fields. Never use SELECT * for an identity API. */
try {
    $statement = $pdo->prepare(
        'SELECT steamid64, username, first_name, last_name, email, phone,
                paymenter_user_id, paymenter_sync_status,
                steam_personaname, steam_profileurl, steam_avatar_url, steam_avatar_path,
                steam_personastate, steam_communityvisibilitystate, steam_profilestate,
                steam_timecreated, steam_lastlogoff, steam_loccountrycode,
                steam_gameextrainfo, steam_gameid, steam_profile_updated_at
         FROM users
         WHERE steamid64 = ?
           AND paymenter_user_id = ?
           AND paymenter_sync_status = \'synced\'
         LIMIT 1'
    );
    $statement->execute([$steamId64, (int) $paymenterUserId]);
    $user = $statement->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $error) {
    error_log('[FragCity] Paymenter profile API users lookup failed (SQLSTATE ' . (string) $error->getCode() . ').');
    $respond(503, ['success' => false, 'error' => 'profile_schema_unavailable']);
}

if (!is_array($user)) {
    $respond(404, ['success' => false, 'error' => 'linked_profile_not_found']);
}

$siteBase = trim((string) fragcity_settings('app.site_base_url', 'https://fragcity.ir'));
$siteParts = parse_url($siteBase);
if (
    !is_array($siteParts)
    || strtolower((string) ($siteParts['scheme'] ?? '')) !== 'https'
    || empty($siteParts['host'])
    || isset($siteParts['user'])
    || isset($siteParts['pass'])
) {
    error_log('[FragCity] Paymenter profile API rejected an invalid configured site origin.');
    $respond(503, ['success' => false, 'error' => 'site_origin_not_configured']);
}
$siteOrigin = 'https://' . strtolower((string) $siteParts['host'])
    . (isset($siteParts['port']) ? ':' . (int) $siteParts['port'] : '');

$safeProfileUrl = static function (string $url): string {
    $parts = parse_url(trim($url));
    if (
        !is_array($parts)
        || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
        || !in_array(strtolower((string) ($parts['host'] ?? '')), ['steamcommunity.com', 'www.steamcommunity.com'], true)
    ) {
        return '';
    }
    return trim($url);
};

$safeSteamAvatarUrl = static function (string $url): string {
    $url = trim($url);
    $parts = parse_url($url);
    $host = is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';
    $trustedHost = $host === 'steamstatic.com'
        || str_ends_with($host, '.steamstatic.com')
        || $host === 'akamaihd.net'
        || str_ends_with($host, '.akamaihd.net');

    if (
        !is_array($parts)
        || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
        || !$trustedHost
        || isset($parts['user'])
        || isset($parts['pass'])
    ) {
        return '';
    }
    return $url;
};

$localImageUrl = static function (string $relativePath) use ($steamId64, $siteOrigin): string {
    $relativePath = ltrim($relativePath, '/');
    $allowed = preg_match(
        '#\Aimg/(?:avatars/avatar_' . preg_quote($steamId64, '#') . '\.jpg|steam-avatars/steam_' . preg_quote($steamId64, '#') . '\.(?:jpg|png|webp))\z#D',
        $relativePath
    );
    if (!$allowed) {
        return '';
    }

    $diskPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (!is_file($diskPath) || !is_readable($diskPath)) {
        return '';
    }

    return $siteOrigin . '/' . $relativePath . '?v=' . (string) filemtime($diskPath);
};

$customAvatarUrl = $localImageUrl('img/avatars/avatar_' . $steamId64 . '.jpg');
$steamAvatarImageUrl = '';
$storedSteamPath = trim((string) ($user['steam_avatar_path'] ?? ''));

if ($storedSteamPath !== '') {
    $steamAvatarImageUrl = $localImageUrl($storedSteamPath);
}

if ($steamAvatarImageUrl === '') {
    foreach (['jpg', 'png', 'webp'] as $extension) {
        $candidate = $localImageUrl('img/steam-avatars/steam_' . $steamId64 . '.' . $extension);
        if ($candidate !== '') {
            $steamAvatarImageUrl = $candidate;
            break;
        }
    }
}

$remoteSteamAvatarUrl = $safeSteamAvatarUrl((string) ($user['steam_avatar_url'] ?? ''));
if ($steamAvatarImageUrl === '') {
    $steamAvatarImageUrl = $remoteSteamAvatarUrl;
}

$avatarUrl = $customAvatarUrl !== '' ? $customAvatarUrl : $steamAvatarImageUrl;

$personaName = trim((string) ($user['steam_personaname'] ?? ''));
$steamState = (int) ($user['steam_personastate'] ?? 0);
$steamStatusLabels = [
    0 => 'آفلاین',
    1 => 'آنلاین',
    2 => 'مشغول',
    3 => 'دور از دسترس',
    4 => 'خواب',
    5 => 'مایل به معامله',
    6 => 'مایل به بازی',
];
$visibility = (int) ($user['steam_communityvisibilitystate'] ?? 0);
$visibilityLabels = [
    1 => 'خصوصی',
    2 => 'فقط دوستان',
    3 => 'عمومی',
];
$profileState = (int) ($user['steam_profilestate'] ?? 0);

$data = [
    'paymenter_user_id' => (int) $user['paymenter_user_id'],
    'steamid64' => (string) $user['steamid64'],
    'username' => (string) ($user['username'] ?? ''),
    'first_name' => (string) ($user['first_name'] ?? ''),
    'last_name' => (string) ($user['last_name'] ?? ''),
    'full_name' => trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? '')),
    'email' => (string) ($user['email'] ?? ''),
    'phone' => (string) ($user['phone'] ?? ''),
    'steam_personaname' => $personaName,
    'steam_profile_url' => $safeProfileUrl((string) ($user['steam_profileurl'] ?? '')),
    'steam_avatar_url' => $remoteSteamAvatarUrl,
    'steam_avatar_image_url' => $steamAvatarImageUrl,
    'custom_avatar_url' => $customAvatarUrl,
    'avatar_url' => $avatarUrl,
    'steam_personastate' => $steamState,
    'steam_status' => $steamStatusLabels[$steamState] ?? 'نامشخص',
    'steam_communityvisibilitystate' => $visibility,
    'steam_visibility' => $visibilityLabels[$visibility] ?? 'نامشخص',
    'steam_profilestate' => $profileState,
    'steam_profile_configured' => $profileState === 1,
    'steam_timecreated' => max(0, (int) ($user['steam_timecreated'] ?? 0)),
    'steam_lastlogoff' => max(0, (int) ($user['steam_lastlogoff'] ?? 0)),
    'steam_loccountrycode' => strtoupper((string) ($user['steam_loccountrycode'] ?? '')),
    'steam_gameextrainfo' => (string) ($user['steam_gameextrainfo'] ?? ''),
    'steam_gameid' => (string) ($user['steam_gameid'] ?? ''),
    'steam_profile_updated_at' => (string) ($user['steam_profile_updated_at'] ?? ''),
];

$respond(200, [
    'success' => true,
    'data' => $data,
]);

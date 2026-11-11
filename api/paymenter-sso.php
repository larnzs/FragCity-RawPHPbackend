<?php
declare(strict_types=1);

/**
 * FragCity -> Paymenter browser SSO hand-off.
 *
 * This endpoint never accepts identity from the query string. The only query
 * parameter is a short-lived state value created by Paymenter. Identity is
 * derived from the authenticated FragCity session (or its validated remember
 * token), then sent in a one-time HMAC-signed form POST to Paymenter.
 */

require_once __DIR__ . '/../settings/session.php';
fragcity_start_session();
require_once __DIR__ . '/../class/config.php';
require_once __DIR__ . '/../class/RememberMeService.php';

$fail = static function (string $message, int $status = 400): never {
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store, private, max-age=0');
    header('Pragma: no-cache');
    header('Referrer-Policy: no-referrer');
    header('X-Content-Type-Options: nosniff');
    echo $message;
    exit;
};

$state = $_GET['state'] ?? '';
if (!is_string($state) || !preg_match('/\A[a-f0-9]{64}\z/D', $state)) {
    $fail('درخواست ورود یکپارچه معتبر نیست.', 400);
}

$paymenterBaseUrl = trim((string) fragcity_settings('integrations.paymenter_backend.base_url', ''));
$sharedSecret = trim((string) fragcity_settings('integrations.paymenter_backend.shared_secret', ''));
$siteBaseUrl = trim((string) fragcity_settings('app.site_base_url', ''));

$parseHttpsOrigin = static function (string $value): ?string {
    $parts = parse_url($value);
    if (
        !is_array($parts)
        || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
        || empty($parts['host'])
        || isset($parts['user'])
        || isset($parts['pass'])
        || isset($parts['query'])
        || isset($parts['fragment'])
        || (isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/')
        || (isset($parts['port']) && ((int) $parts['port'] < 1 || (int) $parts['port'] > 65535))
    ) {
        return null;
    }

    $host = strtolower((string) $parts['host']);
    $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
    return 'https://' . $host . $port;
};

$paymenterOrigin = $parseHttpsOrigin($paymenterBaseUrl);
$siteOrigin = $parseHttpsOrigin($siteBaseUrl);

if (
    $paymenterOrigin === null
    || $siteOrigin === null
    || !preg_match('/\A[a-f0-9]{64,256}\z/iD', $sharedSecret)
) {
    error_log('[FragCity] Paymenter SSO rejected because its HTTPS origin or shared secret is not configured.');
    $fail('ورود یکپارچه موقتاً در دسترس نیست؛ تنظیمات اتصال Billing را بررسی کنید.', 503);
}

$databaseProfile = fragcity_settings('files.index.database_profile', 'main');
$databaseProfile = is_string($databaseProfile) && trim($databaseProfile) !== ''
    ? trim($databaseProfile)
    : 'main';
$pdo = fragcity_create_pdo($databaseProfile);
if (!$pdo instanceof PDO) {
    $fail('اتصال به حساب کاربری FragCity برقرار نشد. لطفاً دوباره تلاش کنید.', 503);
}

$activeSteamId = (
    ($_SESSION['user_logged_in'] ?? false) === true
    && is_string($_SESSION['steamid'] ?? null)
    && preg_match('/\A[0-9]{17}\z/D', (string) $_SESSION['steamid'])
)
    ? (string) $_SESSION['steamid']
    : null;

$remember = false;
if ($activeSteamId === null) {
    // Restore only a valid, unexpired server-side remember token. The cookie
    // contains a random validator; the database stores only its SHA-256 hash.
    $rememberedUser = RememberMeService::restore($pdo, null);
    if (is_array($rememberedUser)) {
        $restoredSteamId = (string) ($rememberedUser['steamid64'] ?? '');
        if (preg_match('/\A[0-9]{17}\z/D', $restoredSteamId)) {
            session_regenerate_id(true);
            $_SESSION['user_logged_in'] = true;
            $_SESSION['steamid'] = $restoredSteamId;
            $_SESSION['username'] = (string) ($rememberedUser['username'] ?? $restoredSteamId);
            $_SESSION['role'] = (string) ($rememberedUser['role'] ?? 'user');
            $_SESSION['auth_csrf_token'] = bin2hex(random_bytes(32));
            $activeSteamId = $restoredSteamId;
            $remember = true;
        }
    }
} else {
    // A remember cookie is optional. It is forwarded as Paymenter's "remember"
    // choice only when it is valid and belongs to this exact signed-in account.
    $remember = is_array(RememberMeService::restore($pdo, $activeSteamId));
}

if ($activeSteamId === null) {
    $_SESSION['paymenter_sso_state'] = $state;
    $_SESSION['login_return_url'] = '/api/paymenter-sso.php?state=' . rawurlencode($state);
    header('Location: /index.php?page=login', true, 303);
    exit;
}

try {
    $statement = $pdo->prepare(
        'SELECT steamid64, username, first_name, last_name, email, phone,
                paymenter_user_id, paymenter_sync_status
         FROM users
         WHERE steamid64 = ?
         LIMIT 1'
    );
    $statement->execute([$activeSteamId]);
    $user = $statement->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $error) {
    error_log('[FragCity] Paymenter SSO account lookup failed (SQLSTATE ' . (string) $error->getCode() . ').');
    $fail('خواندن اطلاعات حساب امکان‌پذیر نیست. لطفاً دوباره تلاش کنید.', 503);
}

$accountReady = is_array($user)
    && preg_match('/\A[a-z0-9][a-z0-9_-]{1,48}[a-z0-9]\z/D', strtolower(trim((string) ($user['username'] ?? '')))) === 1
    && trim((string) ($user['first_name'] ?? '')) !== ''
    && trim((string) ($user['last_name'] ?? '')) !== ''
    && filter_var(trim((string) ($user['email'] ?? '')), FILTER_VALIDATE_EMAIL) !== false
    && preg_match('/\A09[0-9]{9}\z/D', trim((string) ($user['phone'] ?? ''))) === 1
    && (int) ($user['paymenter_user_id'] ?? 0) > 0
    && (string) ($user['paymenter_sync_status'] ?? '') === 'synced';

if (!$accountReady) {
    $_SESSION['paymenter_sso_state'] = $state;
    $_SESSION['login_return_url'] = '/api/paymenter-sso.php?state=' . rawurlencode($state);
    header('Location: /index.php?page=register&complete=1', true, 303);
    exit;
}

$payload = [
    'iss' => $siteOrigin,
    'aud' => $paymenterOrigin,
    'sub' => $activeSteamId,
    'user_id' => (int) $user['paymenter_user_id'],
    'iat' => time(),
    'exp' => time() + 90,
    'jti' => bin2hex(random_bytes(16)),
    'state' => $state,
    'remember' => $remember,
];

try {
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable) {
    $fail('ساخت درخواست ورود امکان‌پذیر نیست. لطفاً دوباره تلاش کنید.', 500);
}

$base64UrlEncode = static function (string $value): string {
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
};

$encodedPayload = $base64UrlEncode($json);
$signature = hash_hmac('sha256', $encodedPayload, $sharedSecret);
$ticket = $encodedPayload . '.' . $signature;

if (strlen($ticket) > 4096) {
    $fail('درخواست ورود بیش از اندازه بزرگ است.', 500);
}

$callbackUrl = $paymenterOrigin . '/auth/fragcity/sso/callback';
$escapedCallback = htmlspecialchars($callbackUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$escapedTicket = htmlspecialchars($ticket, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$escapedState = htmlspecialchars($state, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; form-action {$paymenterOrigin}; script-src 'unsafe-inline'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");

echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>ورود به FragCity Billing</title></head><body>';
echo '<form id="fragcity-paymenter-sso" method="post" action="' . $escapedCallback . '">';
echo '<input type="hidden" name="ticket" value="' . $escapedTicket . '">';
echo '<input type="hidden" name="state" value="' . $escapedState . '">';
echo '<noscript><button type="submit">ادامه به Billing</button></noscript>';
echo '</form><script>document.getElementById("fragcity-paymenter-sso").submit();</script></body></html>';

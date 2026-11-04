<?php
declare(strict_types=1);

/**
 * Authenticated JSON endpoint for customer-owned Pterodactyl panel actions.
 *
 * The browser never receives Paymenter's HMAC secret or a Pterodactyl API key.
 * The FragCity password is re-verified before allowing a panel-password change.
 */

require_once dirname(__DIR__) . '/settings/session.php';

fragcity_start_session();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

$respond = static function (array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    $respond(['success' => false, 'error' => 'method_not_allowed'], 405);
}

if (
    !isset($_SESSION['user_logged_in'])
    || $_SESSION['user_logged_in'] !== true
) {
    $respond(['success' => false, 'error' => 'authentication_required'], 401);
}

$sessionCsrf = $_SESSION['auth_csrf_token'] ?? '';
$submittedCsrf = (string) ($_SERVER['HTTP_X_FRAGCITY_CSRF'] ?? '');

if (
    !is_string($sessionCsrf)
    || !preg_match('/^[a-f0-9]{64}$/D', $sessionCsrf)
    || !hash_equals($sessionCsrf, $submittedCsrf)
) {
    $respond(['success' => false, 'error' => 'csrf_failed'], 419);
}

$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 8192) {
    $respond(['success' => false, 'error' => 'invalid_request'], 400);
}

$rawBody = file_get_contents('php://input');
if (!is_string($rawBody) || $rawBody === '' || strlen($rawBody) > 8192) {
    $respond(['success' => false, 'error' => 'invalid_request'], 400);
}

try {
    $payload = json_decode($rawBody, true, 32, JSON_THROW_ON_ERROR);
} catch (Throwable) {
    $respond(['success' => false, 'error' => 'invalid_request'], 400);
}

if (!is_array($payload) || ($payload['action'] ?? '') !== 'set_panel_password') {
    $respond(['success' => false, 'error' => 'invalid_action'], 400);
}

$steamId = (string) ($_SESSION['steamid'] ?? '');
if (!preg_match('/^[0-9]{17}$/D', $steamId)) {
    $respond(['success' => false, 'error' => 'invalid_session'], 401);
}

$serviceId = filter_var(
    $payload['service_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$currentPassword = is_string($payload['current_password'] ?? null)
    ? $payload['current_password']
    : '';
$newPassword = is_string($payload['password'] ?? null)
    ? $payload['password']
    : '';
$passwordConfirm = is_string($payload['password_confirm'] ?? null)
    ? $payload['password_confirm']
    : '';

if ($serviceId === false || $currentPassword === '' || $newPassword === '') {
    $respond(['success' => false, 'error' => 'required_fields_missing'], 422);
}

if (!hash_equals($newPassword, $passwordConfirm)) {
    $respond(['success' => false, 'error' => 'password_confirmation_mismatch'], 422);
}

if (hash_equals($newPassword, $currentPassword)) {
    $respond(['success' => false, 'error' => 'panel_password_must_be_different'], 422);
}

if (
    strlen($newPassword) < 12
    || strlen($newPassword) > 128
    || preg_match('/\s/u', $newPassword)
    || preg_match('/\p{L}/u', $newPassword) !== 1
    || preg_match('/[0-9]/', $newPassword) !== 1
) {
    $respond(['success' => false, 'error' => 'panel_password_policy'], 422);
}

try {
    $indexSettings = fragcity_settings('files.index', []);
    $dbProfile = is_array($indexSettings)
        ? (string) ($indexSettings['database_profile'] ?? 'main')
        : 'main';

    $pdo = fragcity_create_pdo($dbProfile);
    if (!$pdo instanceof PDO) {
        $respond(['success' => false, 'error' => 'database_unavailable'], 503);
    }

    $accountQuery = $pdo->prepare(
        'SELECT password_hash, paymenter_user_id, paymenter_sync_status
         FROM users
         WHERE steamid64 = ?
         LIMIT 1'
    );
    $accountQuery->execute([$steamId]);
    $account = $accountQuery->fetch(PDO::FETCH_ASSOC);

    if (!is_array($account)) {
        $respond(['success' => false, 'error' => 'account_not_found'], 404);
    }

    $attemptState = $_SESSION['panel_password_change_attempts'][$steamId] ?? [];
    $attemptCount = is_array($attemptState) ? (int) ($attemptState['count'] ?? 0) : 0;
    $attemptTime = is_array($attemptState) ? (int) ($attemptState['time'] ?? 0) : 0;

    if ($attemptTime > 0 && (time() - $attemptTime) > 900) {
        $attemptCount = 0;
    }

    if ($attemptCount >= 5 && $attemptTime > 0 && (time() - $attemptTime) <= 900) {
        $respond(['success' => false, 'error' => 'panel_password_rate_limited'], 429);
    }

    if (!password_verify($currentPassword, (string) ($account['password_hash'] ?? ''))) {
        $_SESSION['panel_password_change_attempts'][$steamId] = [
            'count' => $attemptCount + 1,
            'time' => time(),
        ];
        $respond(['success' => false, 'error' => 'current_password_incorrect'], 403);
    }

    unset($_SESSION['panel_password_change_attempts'][$steamId]);

    if (
        (int) ($account['paymenter_user_id'] ?? 0) < 1
        || (string) ($account['paymenter_sync_status'] ?? '') !== 'synced'
    ) {
        $respond(['success' => false, 'error' => 'paymenter_account_not_linked'], 409);
    }

    require_once dirname(__DIR__) . '/class/PaymenterApiClient.php';

    $client = new PaymenterApiClient();
    $result = $client->setCustomerPanelPassword(
        $steamId,
        (int) $serviceId,
        $newPassword
    );

    $respond([
        'success' => true,
        'data' => [
            'password_updated' => (bool) ($result['password_updated'] ?? false),
        ],
    ]);
} catch (Throwable $e) {
    $safeError = $e->getMessage();
    $allowedErrors = [
        'paymenter_unreachable',
        'paymenter_invalid_response',
        'bridge_not_configured',
        'source_ip_not_allowed',
        'invalid_signature_headers',
        'invalid_signature',
        'request_replayed',
        'validation_failed',
        'service_not_found',
        'panel_password_update_failed',
        'customer_servers_unavailable',
    ];

    if (!in_array($safeError, $allowedErrors, true)) {
        $safeError = 'panel_password_update_failed';
    }

    error_log(
        '[FragCity] Panel password action failed; steam_hash='
        . substr(hash('sha256', $steamId), 0, 16)
        . '; service_id=' . (int) $serviceId
        . '; error=' . $safeError . '.'
    );

    $status = $safeError === 'service_not_found' ? 404 : 502;
    $respond(['success' => false, 'error' => $safeError], $status);
}

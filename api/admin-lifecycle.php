<?php
declare(strict_types=1);

/**
 * Administrator-only proxy for the private Paymenter lifecycle bridge.
 * The browser sees neither the HMAC secret nor any Pterodactyl credentials.
 */
require_once dirname(__DIR__) . '/settings/session.php';
fragcity_start_session();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

$respond = static function (array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
};

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    header('Allow: POST');
    $respond(['success' => false, 'error' => 'method_not_allowed'], 405);
}

if (
    ($_SESSION['user_logged_in'] ?? false) !== true
    || (string) ($_SESSION['role'] ?? '') !== 'admin'
) {
    $respond(['success' => false, 'error' => 'admin_access_required'], 403);
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
if ($contentLength < 1 || $contentLength > 16384) {
    $respond(['success' => false, 'error' => 'invalid_request'], 400);
}

$rawBody = file_get_contents('php://input');
if (!is_string($rawBody) || $rawBody === '' || strlen($rawBody) > 16384) {
    $respond(['success' => false, 'error' => 'invalid_request'], 400);
}

try {
    $payload = json_decode($rawBody, true, 16, JSON_THROW_ON_ERROR);
} catch (Throwable) {
    $respond(['success' => false, 'error' => 'invalid_request'], 400);
}
if (!is_array($payload)) {
    $respond(['success' => false, 'error' => 'invalid_request'], 400);
}

$action = is_string($payload['action'] ?? null) ? $payload['action'] : '';
if (!in_array($action, ['list', 'detail', 'reconcile', 'authorize_recreation', 'revoke_recreation', 'acknowledge_paymenter_missing', 'verify_client_identity'], true)) {
    $respond(['success' => false, 'error' => 'invalid_action'], 422);
}

$actorSteamId = (string) ($_SESSION['steamid'] ?? '');
$actorUsername = trim((string) ($_SESSION['username'] ?? ''));
if (!preg_match('/^[0-9]{17}$/D', $actorSteamId)) {
    $respond(['success' => false, 'error' => 'invalid_session'], 401);
}

// The user/admin identity and CSRF token have been copied; release the lock
// before waiting on Paymenter/Pterodactyl network requests.
session_write_close();

require_once dirname(__DIR__) . '/class/PaymenterApiClient.php';

try {
    $client = new PaymenterApiClient();
    $serviceId = filter_var($payload['service_id'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    switch ($action) {
        case 'list':
            $filters = [
                'page' => filter_var($payload['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1,
                'per_page' => filter_var($payload['per_page'] ?? 25, FILTER_VALIDATE_INT) ?: 25,
                'state' => is_string($payload['state'] ?? null) ? $payload['state'] : 'all',
                'search' => is_string($payload['search'] ?? null) ? $payload['search'] : '',
            ];
            $result = $client->adminLifecycleInventory($actorSteamId, $actorUsername, $filters);
            break;

        case 'detail':
            if ($serviceId === false) $respond(['success' => false, 'error' => 'validation_failed'], 422);
            $result = $client->adminLifecycleDetail($actorSteamId, $actorUsername, (int) $serviceId);
            break;

        case 'reconcile':
            if ($serviceId === false) $respond(['success' => false, 'error' => 'validation_failed'], 422);
            $result = $client->adminLifecycleReconcile($actorSteamId, $actorUsername, (int) $serviceId);
            break;

        case 'authorize_recreation':
            if ($serviceId === false || !is_string($payload['reason'] ?? null)) {
                $respond(['success' => false, 'error' => 'validation_failed'], 422);
            }
            $result = $client->adminLifecycleAuthorizeRecreation(
                $actorSteamId,
                $actorUsername,
                (int) $serviceId,
                $payload['reason']
            );
            break;

        case 'revoke_recreation':
            if ($serviceId === false || !is_string($payload['reason'] ?? null)) {
                $respond(['success' => false, 'error' => 'validation_failed'], 422);
            }
            $result = $client->adminLifecycleRevokeRecreation(
                $actorSteamId,
                $actorUsername,
                (int) $serviceId,
                $payload['reason']
            );
            break;

        case 'acknowledge_paymenter_missing':
            if ($serviceId === false || !is_string($payload['reason'] ?? null)) {
                $respond(['success' => false, 'error' => 'validation_failed'], 422);
            }
            $result = $client->adminLifecycleAcknowledgePaymenterMissing(
                $actorSteamId,
                $actorUsername,
                (int) $serviceId,
                $payload['reason']
            );
            break;

        case 'verify_client_identity':
            $pterodactylUserId = filter_var($payload['pterodactyl_user_id'] ?? null, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);
            if ($serviceId === false || $pterodactylUserId === false || !is_string($payload['reason'] ?? null)) {
                $respond(['success' => false, 'error' => 'validation_failed'], 422);
            }
            $result = $client->adminLifecycleVerifyClientIdentity(
                $actorSteamId,
                $actorUsername,
                (int) $serviceId,
                (int) $pterodactylUserId,
                $payload['reason']
            );
            break;

        default:
            $respond(['success' => false, 'error' => 'invalid_action'], 422);
    }

    $respond(['success' => true, 'data' => $result]);
} catch (Throwable $e) {
    $safeError = $e->getMessage();
    $allowedErrors = [
        'paymenter_unreachable',
        'paymenter_invalid_response',
        'paymenter_request_failed',
        'bridge_not_configured',
        'source_ip_not_allowed',
        'invalid_signature_headers',
        'invalid_signature',
        'request_replayed',
        'validation_failed',
        'lifecycle_storage_not_ready',
        'admin_lifecycle_record_not_found',
        'admin_lifecycle_action_not_allowed',
        'admin_lifecycle_unavailable',
        'admin_lifecycle_reconcile_failed',
        'admin_pterodactyl_client_not_found',
        'admin_lifecycle_identity_conflict',
        'admin_lifecycle_identity_verification_failed',
        'bridge_not_configured',
        'bridge_ip_allowlist_invalid',
        'https_required',
        'request_too_large',
        'source_ip_not_allowed',
        'invalid_signature_headers',
        'invalid_signature',
        'request_replayed',
    ];
    if (!in_array($safeError, $allowedErrors, true)) $safeError = 'admin_lifecycle_unavailable';

    error_log('[FragCity] Admin lifecycle API action failed; action=' . $action
        . '; actor_hash=' . substr(hash('sha256', $actorSteamId), 0, 16)
        . '; error=' . $safeError . '.');

    $status = match ($safeError) {
        'admin_lifecycle_record_not_found' => 404,
        'admin_lifecycle_action_not_allowed' => 409,
        'admin_lifecycle_identity_conflict' => 409,
        'admin_pterodactyl_client_not_found' => 404,
        'admin_lifecycle_identity_verification_failed' => 502,
        'validation_failed' => 422,
        'paymenter_unreachable', 'bridge_not_configured', 'bridge_ip_allowlist_invalid' => 503,
        'source_ip_not_allowed', 'https_required', 'request_too_large',
        'invalid_signature_headers', 'invalid_signature', 'request_replayed' => 502,
        default => 502,
    };
    $respond(['success' => false, 'error' => $safeError], $status);
}

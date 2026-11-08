<?php
declare(strict_types=1);

/**
 * Customer-facing, read-only endpoint for Paymenter server invoices.
 *
 * The browser never supplies a SteamID64, Paymenter user ID, email or invoice
 * owner. All identity values are read from the authenticated FragCity session
 * and the FragCity users table.
 */

require_once dirname(__DIR__) . '/settings/session.php';
require_once dirname(__DIR__) . '/settings/loader.php';
require_once dirname(__DIR__) . '/class/PaymenterInvoiceApiClient.php';
require_once dirname(__DIR__) . '/class/PaymenterInvoiceContext.php';

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

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    $respond(['success' => false, 'error' => 'method_not_allowed'], 405);
}

if (($_SESSION['user_logged_in'] ?? false) !== true) {
    $respond(['success' => false, 'error' => 'authentication_required'], 401);
}

$steamId64 = is_string($_SESSION['steamid'] ?? null)
    ? (string) $_SESSION['steamid']
    : '';

if (!preg_match('/^[0-9]{17}$/D', $steamId64)) {
    $respond(['success' => false, 'error' => 'invalid_session'], 401);
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
if ($contentLength > 8 * 1024) {
    $respond(['success' => false, 'error' => 'invalid_request'], 413);
}

$rawBody = file_get_contents('php://input');
if (!is_string($rawBody) || strlen($rawBody) > 8 * 1024) {
    $respond(['success' => false, 'error' => 'invalid_request'], 400);
}

try {
    $payload = $rawBody === ''
        ? []
        : json_decode($rawBody, true, 16, JSON_THROW_ON_ERROR);
} catch (Throwable) {
    $respond(['success' => false, 'error' => 'invalid_request'], 400);
}

if (!is_array($payload)) {
    $respond(['success' => false, 'error' => 'invalid_request'], 400);
}

$action = is_string($payload['action'] ?? null)
    ? trim($payload['action'])
    : 'list';

if (!in_array($action, ['list'], true)) {
    $respond(['success' => false, 'error' => 'invalid_action'], 400);
}

try {
    $indexSettings = fragcity_settings('files.index', []);
    $databaseProfile = is_array($indexSettings)
        ? (string) ($indexSettings['database_profile'] ?? 'main')
        : 'main';

    $pdo = fragcity_create_pdo($databaseProfile);
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('fragcity_account_unavailable');
    }

    $accountStatement = $pdo->prepare(
        'SELECT email, paymenter_user_id, paymenter_sync_status
         FROM users
         WHERE steamid64 = ?
         LIMIT 1'
    );
    $accountStatement->execute([$steamId64]);
    $account = $accountStatement->fetch(PDO::FETCH_ASSOC);

    $paymenterUserId = filter_var(
        is_array($account) ? ($account['paymenter_user_id'] ?? null) : null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    $customerEmail = mb_strtolower(trim((string) ($account['email'] ?? '')));

    if (
        !is_array($account)
        || $paymenterUserId === false
        || (string) ($account['paymenter_sync_status'] ?? '') !== 'synced'
        || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)
    ) {
        throw new RuntimeException('paymenter_invoice_identity_not_synced');
    }

    $client = new PaymenterInvoiceApiClient();
    $result = $client->listInvoices((int) $paymenterUserId, $customerEmail, 100);

    // Resolve explicit Service / ServiceUpgrade references through the
    // existing SteamID-scoped HMAC bridge. Invoice history remains available
    // even when this optional server-summary lookup is temporarily offline.
    $serverRows = [];
    $serverDetailsAvailable = false;
    if (!empty($result['invoices'])) {
        try {
            require_once dirname(__DIR__) . '/class/PaymenterApiClient.php';
            $serverClient = new PaymenterApiClient();
            $serverRows = $serverClient->getCustomerServers($steamId64);
            $serverDetailsAvailable = true;
        } catch (Throwable $contextException) {
            error_log(
                '[FragCity] Invoice server context lookup unavailable; steam_hash='
                . substr(hash('sha256', $steamId64), 0, 16)
                . '; exception=' . get_class($contextException) . '.'
            );
        }
    }

    $result['invoices'] = PaymenterInvoiceContext::attach(
        $result['invoices'],
        $serverRows,
        $serverDetailsAvailable
    );
    $result['server_details_available'] = $serverDetailsAvailable;
    $result['server_count'] = count($serverRows);

    $respond([
        'success' => true,
        'data' => $result,
    ]);
} catch (Throwable $exception) {
    $message = $exception->getMessage();
    $knownErrors = [
        'fragcity_account_unavailable',
        'paymenter_invoice_identity_not_synced',
        'paymenter_invoices_not_configured',
        'paymenter_invoice_curl_required',
        'paymenter_invoice_unreachable',
        'paymenter_invoice_invalid_response',
        'paymenter_invoice_invalid_query',
        'paymenter_api_permission_denied',
        'paymenter_invoice_resource_not_found',
        'paymenter_invoice_validation_failed',
        'paymenter_invoice_rate_limited',
        'paymenter_invoice_request_failed',
        'paymenter_invoice_owner_unverified',
        'paymenter_invoice_identity_mismatch',
        'paymenter_invoice_items_unavailable',
    ];

    $safeError = in_array($message, $knownErrors, true)
        ? $message
        : 'paymenter_invoice_request_failed';

    error_log(
        '[FragCity] Server invoice lookup failed; steam_hash='
        . substr(hash('sha256', $steamId64), 0, 16)
        . '; error=' . $safeError . '.'
    );

    $status = match ($safeError) {
        'fragcity_account_unavailable' => 503,
        'paymenter_invoice_identity_not_synced' => 409,
        'paymenter_invoice_owner_unverified',
        'paymenter_invoice_identity_mismatch' => 403,
        'paymenter_invoices_not_configured',
        'paymenter_invoice_curl_required',
        'paymenter_invoice_unreachable',
        'paymenter_api_permission_denied' => 502,
        default => 502,
    };

    $respond([
        'success' => false,
        'error' => $safeError,
    ], $status);
}

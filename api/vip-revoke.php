<?php
declare(strict_types=1);

/**
 * Customer-confirmed VIP membership revocation.
 *
 * The SteamID64 is taken only from the authenticated session. The signed
 * Linux API records the revocation and removes the live VIPCore row; the
 * website marks stale paid VIP orders and the matching current membership order as
 * revoked without deleting payment history. Unpaid gateway-pending orders remain intact.
 */
require_once dirname(__DIR__) . '/settings/session.php';
require_once dirname(__DIR__) . '/class/VipCoreRepository.php';

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

$steamId64 = (string)($_SESSION['steamid'] ?? '');
if (!preg_match('/^[0-9]{17}$/D', $steamId64)) {
    $respond(['success' => false, 'error' => 'invalid_session'], 401);
}

$sessionCsrf = $_SESSION['auth_csrf_token'] ?? '';
$submittedCsrf = (string)($_SERVER['HTTP_X_FRAGCITY_CSRF'] ?? '');
if (!is_string($sessionCsrf)
    || !preg_match('/^[a-f0-9]{64}$/D', $sessionCsrf)
    || !hash_equals($sessionCsrf, $submittedCsrf)) {
    $respond(['success' => false, 'error' => 'csrf_failed'], 419);
}

$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength < 2 || $contentLength > 2048) {
    $respond(['success' => false, 'error' => 'invalid_request'], 400);
}

$rawBody = file_get_contents('php://input');
if (!is_string($rawBody) || $rawBody === '' || strlen($rawBody) > 2048) {
    $respond(['success' => false, 'error' => 'invalid_request'], 400);
}

try {
    $payload = json_decode($rawBody, true, 8, JSON_THROW_ON_ERROR);
} catch (Throwable) {
    $respond(['success' => false, 'error' => 'invalid_request'], 400);
}

// No account identifier, order reference, VIP level or expiry is accepted from the browser.
if (!is_array($payload) || $payload !== []) {
    $respond(['success' => false, 'error' => 'invalid_request'], 400);
}

// Do not hold the PHP session lock while the server-to-server API request runs.
session_write_close();

try {
    $revocation = (new VipCoreRepository())->revokeMembership($steamId64);

    if (($revocation['revoked'] ?? false) !== true) {
        $respond([
            'success' => false,
            'error' => 'membership_not_active',
            'message' => 'در حال حاضر اشتراک VIP فعالی برای ابطال وجود ندارد.',
        ], 409);
    }

    $orderRef = $revocation['order_ref'] ?? null;
    if ($orderRef !== null
        && (!is_string($orderRef) || !preg_match('/^[a-f0-9]{32}$/D', $orderRef))) {
        throw new RuntimeException('vip_revocation_order_reference_invalid');
    }

    $databaseProfile = fragcity_settings(
        'files.pages.zarinpal.vip_request.database_profile',
        'main'
    );
    $databaseProfile = is_string($databaseProfile) && trim($databaseProfile) !== ''
        ? trim($databaseProfile)
        : 'main';

    $ordersPdo = fragcity_create_pdo($databaseProfile);
    if (!$ordersPdo instanceof PDO) {
        throw new RuntimeException('vip_orders_database_unavailable');
    }

    // Remove any stale paid-but-not-provisioned VIP order from the checkout
    // blocker, and mark the exact order that created the current membership
    // as revoked. The records remain in history; only their activation state
    // changes. Pending ZarinPal orders are deliberately left untouched because
    // a gateway payment may still complete and must not be silently discarded.
    $ordersPdo->beginTransaction();
    try {
        $markRevoked = $ordersPdo->prepare(
            "UPDATE vip_orders
             SET status = 'revoked', last_error = 'customer_revoked_membership'
             WHERE steamid64 = ?
               AND (
                    status = 'paid'
                    OR (? IS NOT NULL AND order_ref = ? AND status = 'provisioned')
               )"
        );
        $markRevoked->execute([$steamId64, $orderRef, $orderRef]);
        $ordersPdo->commit();
    } catch (Throwable $e) {
        if ($ordersPdo->inTransaction()) {
            $ordersPdo->rollBack();
        }
        throw $e;
    }

    $respond([
        'success' => true,
        'data' => [
            'revoked' => true,
            'already_revoked' => (bool)($revocation['already_revoked'] ?? false),
            'level' => (int)$revocation['level'],
            'group' => (string)$revocation['group'],
            'expires' => (int)$revocation['expires'],
            'revoked_at' => (int)$revocation['revoked_at'],
            'order_ref' => $orderRef,
            'message' => 'وضعیت VIP با موفقیت باطل شد. اکنون می‌توانید اشتراک جدیدی تهیه کنید.',
        ],
    ]);
} catch (FragCityLinuxApiException $e) {
    $messages = [
        'membership_not_active' => 'در حال حاضر اشتراک VIP فعالی برای ابطال وجود ندارد.',
        'vipcore_disabled' => 'اتصال VIPCore روی سرور فعال نیست.',
        'vipcore_not_configured' => 'تنظیمات اتصال VIPCore کامل نیست.',
        'vipcore_unavailable' => 'دیتابیس VIPCore یا جدول تاریخچه ابطال در دسترس نیست.',
        'invalid_request' => 'درخواست ابطال معتبر نیست.',
    ];
    $safeCode = array_key_exists($e->errorCode, $messages)
        ? $e->errorCode
        : 'vipcore_unavailable';
    $status = match ($safeCode) {
        'membership_not_active' => 409,
        'invalid_request' => 422,
        default => 503,
    };
    $respond([
        'success' => false,
        'error' => $safeCode,
        'message' => $messages[$safeCode],
    ], $status);
} catch (Throwable $e) {
    error_log(
        '[FragCity VIP revoke] Revocation request failed; steam_hash='
        . substr(hash('sha256', $steamId64), 0, 16)
        . '; type=' . get_class($e)
    );
    $respond([
        'success' => false,
        'error' => 'vip_revoke_unavailable',
        'message' => 'ابطال وضعیت VIP انجام نشد. ارتباط سرویس را بررسی کنید و دوباره تلاش کنید.',
    ], 503);
}

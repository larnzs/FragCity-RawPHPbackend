<?php
declare(strict_types=1);

/**
 * Same-origin authenticated relay for Paymenter VIP cancellation/refunds.
 * No RawPHP wallet, order or VIP database is accessed by this endpoint.
 */
require_once dirname(__DIR__) . '/settings/session.php';
require_once dirname(__DIR__) . '/class/PaymenterApiClient.php';

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
$steamId64 = (string) ($_SESSION['steamid'] ?? '');
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

try {
    $client = new PaymenterApiClient();
    $refund = $client->revokeVipStoreMembership($steamId64);
    $refundRial = max(0.0, (float) ($refund['refund_amount_rial'] ?? 0));
    $balanceRial = max(0.0, (float) ($refund['wallet_balance'] ?? 0));

    $respond([
        'success' => true,
        'data' => [
            'revoked' => true,
            'already_revoked' => (bool) ($refund['replayed'] ?? false),
            'order_ref' => (string) ($refund['order_ref'] ?? ''),
            'refund_ref' => (string) ($refund['refund_ref'] ?? ''),
            'refund_amount_toman' => number_format($refundRial / 10, 8, '.', ''),
            'wallet_balance_toman' => number_format($balanceRial / 10, 8, '.', ''),
            'invoice_id' => (int) ($refund['invoice_id'] ?? 0),
        ],
    ]);
} catch (Throwable $e) {
    $code = $e->getMessage();
    $safeMessages = [
        'vip_membership_not_active' => 'در حال حاضر اشتراک VIP فعالی برای ابطال وجود ندارد.',
        'vip_paid_membership_not_migrated' => 'سابقه خرید این عضویت هنوز به Paymenter منتقل نشده و امکان محاسبه امن بازپرداخت وجود ندارد.',
        'vip_revoke_cooldown_unavailable' => 'زمان پرداخت برای بررسی مهلت ابطال در دسترس نیست.',
        'vip_refund_upgrade_chain_invalid' => 'تاریخچه ارتقای این عضویت کامل نیست؛ بازپرداخت خودکار متوقف شد.',
        'vip_refund_paid_order_not_found' => 'سفارش پرداخت‌شده‌ی منطبق پیدا نشد.',
        'vip_refund_window_invalid' => 'بازه محاسبه بازپرداخت معتبر نیست.',
        'vip_refund_amount_out_of_safe_range' => 'مبلغ بازپرداخت خارج از محدوده امن است.',
        'vipcore_unavailable' => 'ارتباط با سرویس VIPCore برقرار نشد.',
        'vipcore_invalid_response' => 'پاسخ سرویس VIPCore قابل تأیید نیست.',
        'vip_revoke_failed' => 'ابطال یا بازپرداخت در Paymenter کامل نشد.',
    ];
    $publicCode = isset($safeMessages[$code]) ? $code : 'vip_revoke_failed';
    error_log(
        '[FragCity VIP revoke] Paymenter relay failed; steam_hash='
        . substr(hash('sha256', $steamId64), 0, 16)
        . '; error=' . $publicCode
        . '; exception=' . get_class($e)
    );
    $httpStatus = match ($publicCode) {
        'vip_membership_not_active',
        'vip_paid_membership_not_migrated',
        'vip_refund_upgrade_chain_invalid',
        'vip_refund_paid_order_not_found',
        'vip_refund_window_invalid' => 409,
        'vip_revoke_cooldown_unavailable',
        'vipcore_unavailable',
        'vipcore_invalid_response' => 503,
        default => 500,
    };
    $respond([
        'success' => false,
        'error' => $publicCode,
        'message' => $safeMessages[$publicCode],
    ], $httpStatus);
}

<?php
declare(strict_types=1);

/**
 * Same-origin authenticated relay for Paymenter VIP Wallet checkout.
 * Paymenter independently resolves Product/Plan/Config Option, tax and
 * Wallet balance, then records the Invoice and paid VIP entitlement.
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
$submittedCsrf = (string) ($_SERVER['HTTP_X_FRAGCITY_CSRF'] ?? $_POST['csrf_token'] ?? '');
if (
    !is_string($sessionCsrf)
    || !preg_match('/^[a-f0-9]{64}$/D', $sessionCsrf)
    || !hash_equals($sessionCsrf, $submittedCsrf)
) {
    $respond(['success' => false, 'error' => 'csrf_failed'], 419);
}

if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 4096) {
    $respond(['success' => false, 'error' => 'request_too_large'], 413);
}

$orderRef = strtolower(trim((string) ($_POST['order_ref'] ?? '')));
$level = filter_var($_POST['level'] ?? null, FILTER_VALIDATE_INT);
$durationDays = filter_var($_POST['duration_days'] ?? null, FILTER_VALIDATE_INT);
$expectedAmountRial = filter_var($_POST['expected_amount_rial'] ?? null, FILTER_VALIDATE_INT);

if (
    !preg_match('/^[a-f0-9]{32}$/D', $orderRef)
    || !in_array($level, [1, 2, 3], true)
    || !in_array($durationDays, [7, 15, 30, 60, 90], true)
    || $expectedAmountRial === false
    || $expectedAmountRial < 10
    || $expectedAmountRial % 10 !== 0
) {
    $respond(['success' => false, 'error' => 'validation_failed'], 422);
}

try {
    $client = new PaymenterApiClient();
    $data = $client->createVipStoreOrder([
        'order_ref' => $orderRef,
        'steamid64' => $steamId64,
        'level' => $level,
        'duration_days' => $durationDays,
        'expected_amount_rial' => $expectedAmountRial,
    ]);
    $pending = ($data['provision_pending'] ?? false) === true;

    $respond([
        'success' => true,
        'data' => $data,
        'message' => $pending
            ? 'پرداخت در Paymenter ثبت شده است؛ فعال‌سازی VIP در حال پیگیری است.'
            : 'پرداخت و فعال‌سازی VIP با موفقیت انجام شد.',
    ], $pending ? 202 : 200);
} catch (Throwable $e) {
    $code = $e->getMessage();
    $messages = [
        'fragcity_user_not_linked' => 'حساب Steam به حساب Billing متصل نیست.',
        'linked_paymenter_user_missing' => 'حساب Paymenter متصل پیدا نشد.',
        'vip_store_not_configured' => 'تنظیمات فروشگاه VIP در Paymenter کامل نیست.',
        'vip_wallet_not_enabled' => 'کیف پول Paymenter برای این حساب فعال نیست.',
        'vip_wallet_insufficient' => 'موجودی کیف پول Paymenter برای این سفارش کافی نیست.',
        'vip_product_invalid' => 'محصول VIP در کاتالوگ Paymenter معتبر یا فعال نیست.',
        'vip_plan_invalid' => 'پلن انتخاب‌شده در Paymenter معتبر نیست.',
        'vip_level_option_invalid' => 'تنظیم سطح VIP در Config Option محصول کامل نیست.',
        'vip_price_missing' => 'قیمت این پلن یا سطح VIP در Paymenter تنظیم نشده است.',
        'vip_price_invalid' => 'قیمت تنظیم‌شده در Paymenter معتبر نیست.',
        'vip_price_not_whole_toman' => 'قیمت نهایی باید مضرب ۱۰ ریال باشد.',
        'vip_quote_amount_mismatch' => 'قیمت صفحه با قیمت نهایی Paymenter مطابقت ندارد؛ صفحه را تازه‌سازی کن.',
        'vip_paid_membership_upgrade_required' => 'برای این عضویت باید مسیر ارتقای پلن استفاده شود.',
        'vip_paid_membership_not_migrated' => 'سابقه خرید قبلی هنوز به Paymenter منتقل نشده است.',
        'vip_membership_already_active' => 'عضویت فعلی اجازه خرید این سطح را نمی‌دهد.',
        'vip_upgrade_source_changed' => 'وضعیت اشتراک در زمان خرید تغییر کرده است؛ صفحه را تازه‌سازی کن.',
        'vip_upgrade_duration_mismatch' => 'مدت ارتقای انتخاب‌شده با اشتراک فعلی هماهنگ نیست.',
        'vip_upgrade_price_invalid' => 'قیمت ارتقای این اشتراک معتبر نیست.',
        'vip_upgrade_amount_too_small' => 'مابه‌التفاوت ارتقا کمتر از حداقل قابل پرداخت است.',
        'vip_order_reference_conflict' => 'شناسه سفارش قبلی با اطلاعات فعلی همخوانی ندارد.',
        'vip_order_not_retryable' => 'این سفارش در وضعیت قابل پیگیری نیست.',
        'vip_player_name_missing' => 'نام بازیکن Steam در حساب Billing ثبت نشده است.',
        'vip_player_name_invalid' => 'نام بازیکن Steam معتبر نیست.',
        'vipcore_unavailable' => 'ارتباط با سرویس VIPCore برقرار نشد.',
    ];
    $publicCode = isset($messages[$code]) ? $code : 'paymenter_vip_checkout_unavailable';
    error_log(
        '[FragCity VIP checkout] Paymenter relay failed; order_ref='
        . $orderRef
        . '; steam_hash=' . substr(hash('sha256', $steamId64), 0, 16)
        . '; error=' . $publicCode
        . '; exception=' . get_class($e)
    );

    $httpStatus = match ($publicCode) {
        'vip_wallet_insufficient',
        'vip_order_not_retryable' => 422,
        'vip_order_reference_conflict',
        'vip_quote_amount_mismatch',
        'vip_paid_membership_upgrade_required',
        'vip_paid_membership_not_migrated',
        'vip_membership_already_active',
        'vip_upgrade_source_changed',
        'vip_upgrade_duration_mismatch',
        'vip_upgrade_price_invalid',
        'vip_upgrade_amount_too_small' => 409,
        'vip_player_name_missing',
        'vip_player_name_invalid',
        'vip_price_invalid',
        'vip_price_not_whole_toman',
        'vip_plan_invalid',
        'vip_level_option_invalid',
        'vip_product_invalid' => 422,
        default => 503,
    };

    $respond([
        'success' => false,
        'error' => $publicCode,
        'message' => $messages[$publicCode] ?? 'Paymenter در حال حاضر نتوانست سفارش را ثبت کند.',
        'order_ref' => $orderRef,
    ], $httpStatus);
}

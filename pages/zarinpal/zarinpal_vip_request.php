<?php
declare(strict_types=1);

/**
 * Creates an independent paid VIP order and redirects to ZarinPal.
 * Amount, Steam identity, VIP tier and duration are validated server-side.
 */
require_once __DIR__ . '/../../settings/session.php';
require_once __DIR__ . '/../../settings/loader.php';
require_once __DIR__ . '/../../class/VipCoreRepository.php';

fragcity_start_session();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

$terminate = static function (string $message, int $status = 400): never {
    http_response_code($status);
    header('Content-Type: text/html; charset=UTF-8');
    $safeMessage = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>پرداخت VIP | FragCity</title>'
        . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;box-sizing:border-box;background:#0b0b0b;color:#eee;font-family:Vazirmatn,Tahoma,sans-serif}.card{width:min(560px,100%);padding:32px;border:1px solid #54351e;border-radius:20px;background:#171412;text-align:center;box-shadow:0 20px 70px #0008}h1{font-size:1.35rem;color:#ffb347}p{line-height:2;color:#bbb}.btn{display:inline-block;margin-top:14px;padding:11px 22px;border-radius:10px;background:#f97316;color:#fff;text-decoration:none;font-weight:800}</style>'
        . '</head><body><main class="card"><h1>پرداخت VIP انجام نشد</h1><p>' . $safeMessage . '</p>'
        . '<a class="btn" href="/index.php?page=vip">بازگشت به فروشگاه VIP</a></main></body></html>';
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    $terminate('روش ارسال درخواست معتبر نیست.', 405);
}

if (($_SESSION['user_logged_in'] ?? false) !== true) {
    $terminate('برای خرید VIP ابتدا با حساب Steam وارد شوید.', 401);
}

$steamId64 = $_SESSION['steamid'] ?? null;
if (!is_string($steamId64) || !preg_match('/^[0-9]{17}$/D', $steamId64)) {
    $terminate('نشست کاربری معتبر نیست؛ دوباره وارد شوید.', 401);
}

$sessionCsrf = $_SESSION['auth_csrf_token'] ?? '';
$submittedCsrf = $_POST['csrf_token'] ?? '';
if (!is_string($sessionCsrf)
    || !preg_match('/^[a-f0-9]{64}$/D', $sessionCsrf)
    || !is_string($submittedCsrf)
    || !hash_equals($sessionCsrf, $submittedCsrf)) {
    $terminate('درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید و دوباره تلاش کنید.', 419);
}

$levelInput = $_POST['level'] ?? null;
$daysInput = $_POST['duration_days'] ?? null;
$level = is_string($levelInput) ? filter_var($levelInput, FILTER_VALIDATE_INT) : false;
$days = is_string($daysInput) ? filter_var($daysInput, FILTER_VALIDATE_INT) : false;

if (!in_array($level, [1, 2, 3], true) || !in_array($days, [7, 15, 30, 60, 90], true)) {
    $terminate('سطح VIP یا مدت اشتراک معتبر نیست.', 422);
}
$level = (int)$level;
$days = (int)$days;
$durationSeconds = $days * 86400;

$catalog = fragcity_settings('files.pages.vip.purchase_catalog', []);
if (!is_array($catalog) || ($catalog['checkout_enabled'] ?? false) !== true) {
    $terminate('اتصال فروشگاه VIP هنوز در تنظیمات سرور فعال نشده است.', 503);
}

$defaultPriceRial = [
    1 => [7 => 44000, 15 => 95000, 30 => 190000, 60 => 380000, 90 => 570000],
    2 => [7 => 114000, 15 => 245000, 30 => 490000, 60 => 980000, 90 => 1470000],
    3 => [7 => 208000, 15 => 445000, 30 => 890000, 60 => 1780000, 90 => 2670000],
];

$packages = is_array($catalog['packages'] ?? null) ? $catalog['packages'] : [];
$package = is_array($packages[(string)$level] ?? null) ? $packages[(string)$level] : [];
$durations = is_array($package['durations'] ?? null) ? $package['durations'] : [];
$durationPackage = is_array($durations[(string)$days] ?? null) ? $durations[(string)$days] : [];
$rawPrice = $durationPackage['price_rial'] ?? null;
$validatedPrice = filter_var($rawPrice, FILTER_VALIDATE_INT);
if ($validatedPrice === false
    || $validatedPrice < 1
    || $validatedPrice > 10000000000000
    || $validatedPrice % 10 !== 0) {
    // Keep the same official defaults used by the storefront if the
    // private runtime settings still have the old price format.
    $validatedPrice = filter_var($defaultPriceRial[$level][$days] ?? null, FILTER_VALIDATE_INT);
}

if ($validatedPrice === false
    || $validatedPrice < 1
    || $validatedPrice > 10000000000000
    || $validatedPrice % 10 !== 0) {
    $terminate('قیمت این اشتراک در تنظیمات معتبر نیست؛ هیچ مبلغی از شما دریافت نشده است.', 503);
}
$amountRial = (int)$validatedPrice;

$zarinpalSettings = fragcity_settings('integrations.zarinpal', []);
$merchantId = is_array($zarinpalSettings) ? trim((string)($zarinpalSettings['merchant_id'] ?? '')) : '';
$requestUrl = is_array($zarinpalSettings) ? trim((string)($zarinpalSettings['request_url'] ?? '')) : '';
$verifyUrl = is_array($zarinpalSettings) ? trim((string)($zarinpalSettings['verify_url'] ?? '')) : '';
$startPayBase = is_array($zarinpalSettings)
    ? rtrim(trim((string)($zarinpalSettings['startpay_base_url'] ?? '')), '/') . '/'
    : '';
$siteBaseUrl = rtrim(trim((string)fragcity_settings('app.site_base_url', '')), '/');

$validHttpsUrl = static function (string $url): bool {
    $parts = parse_url($url);
    return is_array($parts)
        && strtolower((string)($parts['scheme'] ?? '')) === 'https'
        && !empty($parts['host'])
        && !isset($parts['user'])
        && !isset($parts['pass'])
        && !isset($parts['query'])
        && !isset($parts['fragment']);
};

if ($merchantId === ''
    || !$validHttpsUrl($requestUrl)
    || !$validHttpsUrl($verifyUrl)
    || !$validHttpsUrl($startPayBase)
    || !$validHttpsUrl($siteBaseUrl)
    || !function_exists('curl_init')) {
    $terminate('تنظیمات امن زرین‌پال یا آدرس HTTPS سایت کامل نیست.', 503);
}

$databaseProfile = fragcity_settings('files.pages.zarinpal.vip_request.database_profile', 'main');
$databaseProfile = is_string($databaseProfile) && trim($databaseProfile) !== '' ? trim($databaseProfile) : 'main';
$pdo = fragcity_create_pdo($databaseProfile);
if (!$pdo instanceof PDO) {
    $terminate('اتصال دیتابیس سفارش VIP در دسترس نیست.', 503);
}

// Do not let a second active membership or an unavailable VIPCore status
// proceed to payment. The paid grant repeats this check before writing.
try {
    $vipStatus = (new VipCoreRepository())->getStatus($steamId64);
    if (!empty($vipStatus['active'])) {
        $terminate('حساب شما از قبل VIP فعال دارد؛ خرید مجدد یا ارتقای سطح در مدت اشتراک فعال امکان‌پذیر نیست.', 409);
    }
} catch (Throwable $e) {
    error_log('[FragCity VIP checkout] VIPCore pre-check failed; type=' . get_class($e));
    $terminate('وضعیت VIPCore در دسترس نیست؛ برای جلوگیری از پرداخت اشتباه، سفارش ساخته نشد.', 503);
}

$orderRef = bin2hex(random_bytes(16));
$playerName = trim((string)($_SESSION['username'] ?? 'FragCity Player'));
if ($playerName === '') {
    $playerName = 'FragCity Player';
}
$playerName = function_exists('mb_substr')
    ? mb_substr($playerName, 0, 64, 'UTF-8')
    : substr($playerName, 0, 64);

try {
    $pdo->beginTransaction();

    // Serialize concurrent checkout attempts for the same FragCity account.
    $userQuery = $pdo->prepare(
        'SELECT steamid64, username, phone, email
         FROM users
         WHERE steamid64 = ?
         LIMIT 1 FOR UPDATE'
    );
    $userQuery->execute([$steamId64]);
    $user = $userQuery->fetch(PDO::FETCH_ASSOC);
    if (!is_array($user)) {
        throw new RuntimeException('vip_checkout_user_not_found');
    }

    $playerName = trim((string)($user['username'] ?? $playerName));
    if ($playerName === '') {
        $playerName = 'FragCity Player';
    }
    $playerName = function_exists('mb_substr')
        ? mb_substr($playerName, 0, 64, 'UTF-8')
        : substr($playerName, 0, 64);

    // Abandon stale unpaid orders, but retain paid orders until provisioning succeeds.
    $expireOld = $pdo->prepare(
        "UPDATE vip_orders
         SET status = 'expired', last_error = 'payment_window_expired'
         WHERE steamid64 = ? AND status = 'pending'
           AND created_at < (CURRENT_TIMESTAMP - INTERVAL 30 MINUTE)"
    );
    $expireOld->execute([$steamId64]);

    $openOrder = $pdo->prepare(
        "SELECT order_ref, status
         FROM vip_orders
         WHERE steamid64 = ? AND status IN ('pending', 'paid')
         ORDER BY id DESC
         LIMIT 1"
    );
    $openOrder->execute([$steamId64]);
    if ($openOrder->fetch(PDO::FETCH_ASSOC)) {
        throw new DomainException('vip_checkout_order_already_open');
    }

    $insert = $pdo->prepare(
        "INSERT INTO vip_orders
            (order_ref, steamid64, player_name, vip_level, duration_days,
             duration_seconds, amount_rial, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')"
    );
    $insert->execute([
        $orderRef,
        $steamId64,
        $playerName,
        $level,
        $days,
        $durationSeconds,
        $amountRial,
    ]);
    if ($insert->rowCount() !== 1) {
        throw new RuntimeException('vip_checkout_order_insert_failed');
    }

    $pdo->commit();
} catch (DomainException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $terminate('یک سفارش VIP قبلی هنوز در حال پرداخت یا فعال‌سازی است. از پرداخت دوباره خودداری کنید و ابتدا وضعیت سفارش قبلی را بررسی کنید.', 409);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[FragCity VIP checkout] Order creation failed; type=' . get_class($e));
    $terminate('ثبت سفارش VIP انجام نشد؛ هیچ پرداختی به درگاه ارسال نشده است.', 503);
}

$callbackUrl = $siteBaseUrl . '/pages/zarinpal/zarinpal_vip_callback.php';
$metadata = [
    'mobile' => trim((string)($user['phone'] ?? '')),
    'email' => trim((string)($user['email'] ?? '')),
];
$payload = [
    'merchant_id' => $merchantId,
    'amount' => $amountRial,
    'callback_url' => $callbackUrl,
    'description' => 'FragCity VIP ' . $level . ' - ' . $days . ' days',
    'metadata' => $metadata,
];

try {
    $body = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    $curl = curl_init($requestUrl);
    if ($curl === false) {
        throw new RuntimeException('zarinpal_vip_request_curl_init_failed');
    }

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
    ]);

    $response = curl_exec($curl);
    $curlError = curl_errno($curl);
    $httpStatus = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if (!is_string($response) || $curlError !== 0 || $httpStatus < 200 || $httpStatus >= 300) {
        throw new RuntimeException('zarinpal_vip_request_transport_failed');
    }

    $gatewayResponse = json_decode($response, true, 32, JSON_THROW_ON_ERROR);
    $gatewayCode = filter_var($gatewayResponse['data']['code'] ?? null, FILTER_VALIDATE_INT);
    $authority = $gatewayResponse['data']['authority'] ?? null;

    if ($gatewayCode !== 100
        || !is_string($authority)
        || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $authority)) {
        $failed = $pdo->prepare(
            "UPDATE vip_orders
             SET status = 'failed', last_error = 'zarinpal_request_rejected'
             WHERE order_ref = ? AND status = 'pending'"
        );
        $failed->execute([$orderRef]);
        $terminate('زرین‌پال نتوانست درخواست پرداخت را ثبت کند؛ سفارش فعال نشده است.', 502);
    }

    $saveAuthority = $pdo->prepare(
        "UPDATE vip_orders
         SET authority = ?
         WHERE order_ref = ? AND status = 'pending' AND authority IS NULL"
    );
    $saveAuthority->execute([$authority, $orderRef]);
    if ($saveAuthority->rowCount() !== 1) {
        throw new RuntimeException('zarinpal_vip_authority_not_saved');
    }

    header('Location: ' . $startPayBase . rawurlencode($authority), true, 303);
    exit;
} catch (Throwable $e) {
    try {
        $failed = $pdo->prepare(
            "UPDATE vip_orders
             SET status = 'failed', last_error = 'zarinpal_request_transport_failed'
             WHERE order_ref = ? AND status = 'pending'"
        );
        $failed->execute([$orderRef]);
    } catch (Throwable) {
        // Preserve the original gateway or persistence failure.
    }

    error_log('[FragCity VIP checkout] ZarinPal request failed; type=' . get_class($e));
    $terminate('ارتباط با درگاه برقرار نشد یا پاسخ معتبر نبود. در صورت کسر وجه، با پشتیبانی تماس بگیرید.', 502);
}

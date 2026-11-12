<?php
declare(strict_types=1);

require_once __DIR__ . '/../../settings/session.php';
fragcity_start_session();

$zarinpalSettings = fragcity_settings('integrations.zarinpal', []);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function terminateRequest(string $message, int $status = 400): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');

    $safeMessage = htmlspecialchars(
        str_replace('❌ ', '', $message),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
    echo '<!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>خطا در درخواست | FragCity</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700;800&display=swap" rel="stylesheet">
        <style>
            body { background: #0a0a0a; color: #fff; font-family: "Vazirmatn", sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 24px; box-sizing: border-box; }
            .card { max-width: 560px; background: rgba(17,17,17,0.95); border: 1px solid rgba(231,76,60,0.3); padding: 40px; border-radius: 20px; text-align: center; }
            .card i { font-size: 3rem; color: #e74c3c; margin-bottom: 20px; }
            .btn { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #F5B042; color: #000; text-decoration: none; border-radius: 10px; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="card">
            <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
            <h2>خطا در پردازش درخواست</h2>
            <p>' . $safeMessage . '</p>
            <a href="https://fragcity.ir/" class="btn">بازگشت به FragCity</a>
        </div>
    </body>
    </html>';
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    terminateRequest('روش ارسال درخواست معتبر نیست.', 405);
}

if (
    ($_SESSION['user_logged_in'] ?? false) !== true
    || !is_string($_SESSION['steamid'] ?? null)
    || !preg_match('/^[0-9]{17}$/D', $_SESSION['steamid'])
) {
    terminateRequest('برای شارژ کیف پول باید وارد حساب کاربری خود شوید.', 401);
}

$sessionCsrfToken = $_SESSION['auth_csrf_token'] ?? '';
$submittedCsrfToken = $_POST['csrf_token'] ?? '';
if (
    !is_string($sessionCsrfToken)
    || !preg_match('/^[a-f0-9]{64}$/D', $sessionCsrfToken)
    || !is_string($submittedCsrfToken)
    || !hash_equals($sessionCsrfToken, $submittedCsrfToken)
) {
    terminateRequest('درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید و دوباره تلاش کنید.', 419);
}

$amountInput = $_POST['amount'] ?? null;
$maximumSafeTomanAmount = intdiv(PHP_INT_MAX, 10);
$validatedAmount = is_string($amountInput)
    ? filter_var($amountInput, FILTER_VALIDATE_INT, [
        'options' => [
            'min_range' => 1000,
            'max_range' => $maximumSafeTomanAmount,
        ],
    ])
    : false;
if ($validatedAmount === false) {
    terminateRequest('مبلغ شارژ باید عدد صحیح، حداقل ۱,۰۰۰ تومان و در محدوده معتبر باشد.', 422);
}
$amount = (int) $validatedAmount;
$steamId = $_SESSION['steamid'];

$databaseProfile = fragcity_settings('files.index.database_profile', 'main');
$databaseProfile = is_string($databaseProfile) && trim($databaseProfile) !== ''
    ? trim($databaseProfile)
    : 'main';
$pdo = fragcity_create_pdo($databaseProfile);
if (!$pdo instanceof PDO) {
    terminateRequest('اتصال دیتابیس در دسترس نیست یا تنظیم نشده است.', 503);
}

$merchantId = is_array($zarinpalSettings)
    ? trim((string) ($zarinpalSettings['merchant_id'] ?? ''))
    : '';
$requestUrl = is_array($zarinpalSettings)
    ? trim((string) ($zarinpalSettings['request_url'] ?? ''))
    : '';
$startPayBase = is_array($zarinpalSettings)
    ? rtrim(trim((string) ($zarinpalSettings['startpay_base_url'] ?? '')), '/') . '/'
    : '';

$siteBaseUrl = rtrim(trim((string) fragcity_settings('app.site_base_url', '')), '/');
$siteBaseParts = parse_url($siteBaseUrl);
$requestUrlParts = parse_url($requestUrl);
$startPayParts = parse_url($startPayBase);

if (
    $merchantId === ''
    || !is_array($requestUrlParts)
    || strtolower((string) ($requestUrlParts['scheme'] ?? '')) !== 'https'
    || empty($requestUrlParts['host'])
    || !is_array($startPayParts)
    || strtolower((string) ($startPayParts['scheme'] ?? '')) !== 'https'
    || empty($startPayParts['host'])
    || !is_array($siteBaseParts)
    || !in_array(strtolower((string) ($siteBaseParts['scheme'] ?? '')), ['https', 'http'], true)
    || empty($siteBaseParts['host'])
    || isset($siteBaseParts['user'])
    || isset($siteBaseParts['pass'])
    || isset($siteBaseParts['query'])
    || isset($siteBaseParts['fragment'])
) {
    terminateRequest('تنظیمات امن درگاه پرداخت یا آدرس سایت کامل نیست.', 503);
}

$transactionId = 0;
$topupRef = '';
try {
    $userStatement = $pdo->prepare(
        'SELECT phone, email, paymenter_user_id, paymenter_sync_status
         FROM users WHERE steamid64 = ? LIMIT 1'
    );
    $userStatement->execute([$steamId]);
    $user = $userStatement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($user)) {
        terminateRequest('حساب کاربری در دیتابیس پیدا نشد.', 404);
    }

    if (
        (int) ($user['paymenter_user_id'] ?? 0) < 1
        || (string) ($user['paymenter_sync_status'] ?? '') !== 'synced'
    ) {
        terminateRequest('حساب شما هنوز به Paymenter متصل نشده است. ابتدا وارد Billing شوید و دوباره تلاش کنید.', 409);
    }

    // Do not create a second live gateway request while an earlier wallet
    // deposit is still pending. Paymenter also reserves the maximum-credit
    // capacity for 20 minutes, but the local guard prevents duplicate intents.
    $pendingStatement = $pdo->prepare(
        "SELECT id FROM wallet_transactions
         WHERE steamid = ? AND type = 'deposit' AND status = 'pending'
           AND created_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 20 MINUTE)
         LIMIT 1"
    );
    $pendingStatement->execute([$steamId]);
    if ($pendingStatement->fetchColumn() !== false) {
        terminateRequest('یک درخواست شارژ فعال برای حساب شما وجود دارد. ابتدا همان پرداخت را تکمیل کنید یا پس از پایان مهلت دوباره تلاش کنید.', 409);
    }

    $mobile = trim((string) ($user['phone'] ?? ''));
    $email = trim((string) ($user['email'] ?? ''));
    $topupRef = bin2hex(random_bytes(16));

    // This row is only a local payment-attempt receipt. It is never used as
    // a balance ledger; Paymenter owns the actual wallet and credit balance.
    $insert = $pdo->prepare(
        "INSERT INTO wallet_transactions
            (steamid, amount, type, description, authority, status)
         VALUES (?, ?, 'deposit', ?, '', 'pending')"
    );
    $insert->execute([
        $steamId,
        $amount,
        'شارژ کیف پول Paymenter | ref:' . $topupRef,
    ]);
    $transactionId = (int) $pdo->lastInsertId();
    if ($transactionId < 1) {
        throw new RuntimeException('wallet_transaction_insert_failed');
    }

    require_once dirname(__DIR__, 2) . '/class/PaymenterApiClient.php';
    $paymenter = new PaymenterApiClient();
    $reservation = $paymenter->prepareCustomerWalletTopup([
        'topup_ref' => $topupRef,
        'steamid64' => $steamId,
        'amount_toman' => $amount,
        'amount_rial' => $amount * 10,
    ]);

    if (($reservation['status'] ?? '') !== 'prepared') {
        throw new RuntimeException('wallet_topup_reservation_unavailable');
    }
} catch (Throwable $e) {
    if ($transactionId > 0) {
        try {
            $fail = $pdo->prepare(
                "UPDATE wallet_transactions SET status = 'failed'
                 WHERE id = ? AND status = 'pending'"
            );
            $fail->execute([$transactionId]);
        } catch (Throwable) {
            // The original Paymenter/database error remains the primary failure.
        }
    }

    $reason = $e->getMessage();
    error_log('[FragCity] Paymenter wallet top-up preparation failed (' . get_class($e) . ', code ' . (string) $e->getCode() . ', reason ' . preg_replace('/[^a-z0-9_]/i', '', $reason) . ').');
    $message = match ($reason) {
        'wallet_topup_not_configured' => 'کیف پول Paymenter در حال حاضر برای شارژ تنظیم نشده است.',
        'wallet_topup_amount_invalid' => 'مبلغ واردشده با حداقل یا حداکثر شارژ تعیین‌شده در Paymenter مطابقت ندارد.',
        'wallet_maximum_credit_exceeded' => 'با این شارژ، سقف اعتبار مجاز کیف پول شما رد می‌شود.',
        'wallet_topup_pending_invoice_exists' => 'یک فاکتور شارژ پرداخت‌نشده در Billing دارید. ابتدا آن را تعیین تکلیف کنید.',
        'fragcity_user_not_linked', 'linked_paymenter_user_missing' => 'حساب FragCity شما به حساب Billing متصل نیست. یک‌بار وارد Billing شوید و دوباره تلاش کنید.',
        'paymenter_unreachable', 'paymenter_invalid_response', 'bridge_not_configured' => 'ارتباط امن با Paymenter برقرار نشد. هیچ مبلغی از کیف پول کسر نشده است.',
        default => 'آماده‌سازی شارژ از طریق Paymenter انجام نشد. هیچ اعتباری به کیف پول اضافه نشده است.',
    };
    terminateRequest($message, 503);
}

$callbackUrl = $siteBaseUrl . '/pages/zarinpal/zarinpal_wallet_callback.php';
$payload = [
    'merchant_id' => $merchantId,
    'amount' => $amount * 10,
    'callback_url' => $callbackUrl,
    'description' => 'شارژ کیف پول - FragCity',
    'metadata' => [
        'mobile' => $mobile,
        'email' => $email,
    ],
];

try {
    $body = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    $curl = curl_init($requestUrl);
    if ($curl === false) {
        throw new RuntimeException('zarinpal_request_curl_init_failed');
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
    $httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if (!is_string($response) || $curlError !== 0 || $httpStatus < 200 || $httpStatus >= 300) {
        throw new RuntimeException('zarinpal_request_transport_failed');
    }

    $gatewayResponse = json_decode($response, true, 32, JSON_THROW_ON_ERROR);
    $gatewayCode = filter_var($gatewayResponse['data']['code'] ?? null, FILTER_VALIDATE_INT);
    $authority = $gatewayResponse['data']['authority'] ?? null;

    if (
        $gatewayCode !== 100
        || !is_string($authority)
        || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $authority)
    ) {
        $fail = $pdo->prepare(
            "UPDATE wallet_transactions
             SET status = 'failed'
             WHERE id = ? AND status = 'pending'"
        );
        $fail->execute([$transactionId]);
        terminateRequest('درگاه پرداخت نتوانست درخواست شارژ را ثبت کند. مبلغی به کیف پول اضافه نشده است.', 502);
    }

    $update = $pdo->prepare(
        "UPDATE wallet_transactions
         SET authority = ?
         WHERE id = ? AND status = 'pending' AND authority = ''"
    );
    $update->execute([$authority, $transactionId]);
    if ($update->rowCount() !== 1) {
        throw new RuntimeException('wallet_authority_not_saved');
    }

    header('Location: ' . $startPayBase . rawurlencode($authority), true, 303);
    exit;
} catch (Throwable $e) {
    try {
        $fail = $pdo->prepare(
            "UPDATE wallet_transactions
             SET status = 'failed'
             WHERE id = ? AND status = 'pending'"
        );
        $fail->execute([$transactionId]);
    } catch (Throwable) {
        // Keep the original transport or persistence failure as the primary error.
    }

    error_log('[FragCity] ZarinPal wallet request failed (' . get_class($e) . ', code ' . (string) $e->getCode() . ').');
    terminateRequest('ارتباط با درگاه پرداخت برقرار نشد یا پاسخ معتبر نبود. در صورت کسر وجه، با پشتیبانی تماس بگیرید.', 502);
}

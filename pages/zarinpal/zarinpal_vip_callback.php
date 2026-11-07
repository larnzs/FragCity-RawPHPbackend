<?php
declare(strict_types=1);

/**
 * Verifies ZarinPal and provisions the exact VIP membership attached to the
 * stored order. The callback never trusts browser-supplied amount or tier.
 */
require_once __DIR__ . '/../../settings/loader.php';
require_once __DIR__ . '/../../class/VipCoreRepository.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

$renderResult = static function (
    bool $success,
    string $message,
    int $amountRial = 0,
    string $reference = '',
    bool $provisionPending = false
): never {
    header('Content-Type: text/html; charset=UTF-8');

    if ($success) {
        $fcSuccessModalConfig = [
            'auto_open' => true,
            'kind' => 'payment',
            'title' => 'خرید VIP با موفقیت فعال شد',
            'message' => 'پرداخت تأیید شد و عضویت VIP روی حساب Steam شما ثبت شد.',
            'amount' => number_format(intdiv(max(0, $amountRial), 10)) . ' تومان',
            'amount_label' => 'مبلغ پرداخت‌شده',
            'reference' => $reference,
            'return_url' => '/index.php?page=vip',
            'return_label' => 'مشاهده وضعیت VIP',
            'dismissible' => false,
        ];

        echo '<!doctype html><html lang="fa" dir="rtl"><head>'
            . '<meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>پرداخت موفق | FragCity</title>'
            . '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">'
            . '<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700;800&display=swap" rel="stylesheet">'
            . '<link rel="stylesheet" href="/css/fragcity-success-modal.css?v=20261107-1">'
            . '</head><body class="fc-success-page-body">';
        require __DIR__ . '/../../includes/fragcity-success-modal.php';
        echo '<script src="/js/fragcity-success-modal.js?v=20261107-1" defer></script></body></html>';
        exit;
    }

    $safeMessage = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $heading = $provisionPending ? 'پرداخت تأیید شد؛ فعال‌سازی در انتظار بررسی است' : 'پرداخت VIP تکمیل نشد';
    $accent = $provisionPending ? '#fbbf24' : '#f87171';

    echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>نتیجه پرداخت VIP | FragCity</title>'
        . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;box-sizing:border-box;background:#0b0b0b;color:#eee;font-family:Vazirmatn,Tahoma,sans-serif}.card{width:min(600px,100%);padding:32px;border:1px solid #ffffff20;border-radius:20px;background:#171717;text-align:center;box-shadow:0 20px 70px #0009}h1{font-size:1.3rem;color:' . $accent . '}p{line-height:2;color:#bbb}.note{margin-top:20px;padding:14px;border-radius:12px;background:#ffffff08;color:#c9c9c9;font-size:.88rem}.btn{display:inline-block;margin-top:18px;padding:11px 22px;border-radius:10px;background:#f97316;color:#fff;text-decoration:none;font-weight:800}</style>'
        . '</head><body><main class="card"><h1>' . $heading . '</h1><p>' . $safeMessage . '</p>';

    if ($provisionPending) {
        echo '<div class="note">مبلغ را دوباره پرداخت نکنید. سفارش با وضعیت پرداخت‌شده ذخیره شده و تلاش مجدد برای ثبت VIP با همان شناسه سفارش انجام می‌شود. در صورت باقی‌ماندن مشکل، با پشتیبانی FragCity تماس بگیرید.</div>';
    }

    echo '<a class="btn" href="/index.php?page=vip">بازگشت به فروشگاه VIP</a></main></body></html>';
    exit;
};

$authorityInput = $_GET['Authority'] ?? '';
$statusInput = $_GET['Status'] ?? '';
$authority = is_string($authorityInput) ? trim($authorityInput) : '';
$gatewayStatus = is_string($statusInput) ? trim($statusInput) : '';

if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $authority)) {
    $renderResult(false, 'شناسه بازگشت از درگاه معتبر نیست.');
}

$databaseProfile = fragcity_settings('files.pages.zarinpal.vip_callback.database_profile', 'main');
$databaseProfile = is_string($databaseProfile) && trim($databaseProfile) !== '' ? trim($databaseProfile) : 'main';
$pdo = fragcity_create_pdo($databaseProfile);
if (!$pdo instanceof PDO) {
    $renderResult(false, 'اتصال دیتابیس سفارش VIP در دسترس نیست.');
}

try {
    $lookup = $pdo->prepare(
        'SELECT id, order_ref, steamid64, player_name, vip_level,
                duration_days, duration_seconds, amount_rial,
                authority, ref_id, status
         FROM vip_orders
         WHERE authority = ?
         LIMIT 1'
    );
    $lookup->execute([$authority]);
    $order = $lookup->fetch(PDO::FETCH_ASSOC);

    if (!is_array($order)) {
        $renderResult(false, 'سفارش متناظر با این پرداخت پیدا نشد.');
    }

    $orderStatus = (string)($order['status'] ?? '');
    $amountRial = (int)($order['amount_rial'] ?? 0);
    $orderRef = (string)($order['order_ref'] ?? '');
    $steamId64 = (string)($order['steamid64'] ?? '');
    $level = (int)($order['vip_level'] ?? 0);
    $days = (int)($order['duration_days'] ?? 0);
    $durationSeconds = (int)($order['duration_seconds'] ?? 0);
    $playerName = (string)($order['player_name'] ?? '');

    if (!preg_match('/^[a-f0-9]{32}$/D', $orderRef)
        || !preg_match('/^[0-9]{17}$/D', $steamId64)
        || !in_array($level, [1, 2, 3], true)
        || !in_array($days, [7, 15, 30, 60, 90], true)
        || $durationSeconds !== $days * 86400
        || $amountRial < 1
        || $amountRial % 10 !== 0
        || (string)($order['authority'] ?? '') !== $authority) {
        $renderResult(false, 'اطلاعات داخلی سفارش با قالب مورد انتظار مطابقت ندارد. برای بررسی با پشتیبانی تماس بگیرید.');
    }

    if ($orderStatus === 'provisioned') {
        $renderResult(
            true,
            'این سفارش قبلاً با موفقیت فعال شده است.',
            $amountRial,
            (string)($order['ref_id'] ?? '')
        );
    }

    if (in_array($orderStatus, ['failed', 'expired', 'cancelled'], true)) {
        $renderResult(false, 'این سفارش دیگر در وضعیت قابل پردازش نیست. برای بررسی وضعیت پرداخت با پشتیبانی تماس بگیرید.');
    }

    if (!in_array($orderStatus, ['pending', 'paid'], true)) {
        $renderResult(false, 'وضعیت سفارش قابل شناسایی نیست.');
    }

    $refId = trim((string)($order['ref_id'] ?? ''));

    if ($orderStatus === 'pending') {
        // The query-string Status is not proof of payment. It may only mark
        // a pending order as cancelled; activation requires a successful verify.
        if ($gatewayStatus !== 'OK') {
            $cancel = $pdo->prepare(
                "UPDATE vip_orders
                 SET status = 'cancelled', last_error = 'gateway_cancelled'
                 WHERE id = ? AND status = 'pending'"
            );
            $cancel->execute([(int)$order['id']]);
            $renderResult(false, 'پرداخت توسط شما لغو شد یا از درگاه تأیید نشد.');
        }

        $zarinpalSettings = fragcity_settings('integrations.zarinpal', []);
        $merchantId = is_array($zarinpalSettings) ? trim((string)($zarinpalSettings['merchant_id'] ?? '')) : '';
        $verifyUrl = is_array($zarinpalSettings) ? trim((string)($zarinpalSettings['verify_url'] ?? '')) : '';
        $verifyParts = parse_url($verifyUrl);

        if ($merchantId === ''
            || !is_array($verifyParts)
            || strtolower((string)($verifyParts['scheme'] ?? '')) !== 'https'
            || empty($verifyParts['host'])
            || isset($verifyParts['user'])
            || isset($verifyParts['pass'])
            || !function_exists('curl_init')) {
            $renderResult(false, 'تنظیمات امن تأیید زرین‌پال کامل نیست. سفارش برای بررسی حفظ شده است.');
        }

        $verifyPayload = [
            'merchant_id' => $merchantId,
            'amount' => $amountRial,
            'authority' => $authority,
        ];
        $verifyBody = json_encode(
            $verifyPayload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        $curl = curl_init($verifyUrl);
        if ($curl === false) {
            throw new RuntimeException('zarinpal_vip_verify_curl_init_failed');
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $verifyBody,
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

        $verifyResponse = curl_exec($curl);
        $curlError = curl_errno($curl);
        $httpStatus = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if (!is_string($verifyResponse) || $curlError !== 0 || $httpStatus < 200 || $httpStatus >= 300) {
            throw new RuntimeException('zarinpal_vip_verify_transport_failed');
        }

        $gatewayResponse = json_decode($verifyResponse, true, 32, JSON_THROW_ON_ERROR);
        $gatewayCode = filter_var($gatewayResponse['data']['code'] ?? null, FILTER_VALIDATE_INT);

        if ($gatewayCode !== 100 && $gatewayCode !== 101) {
            $markFailed = $pdo->prepare(
                "UPDATE vip_orders
                 SET status = 'failed', last_error = 'zarinpal_verify_rejected'
                 WHERE id = ? AND status = 'pending'"
            );
            $markFailed->execute([(int)$order['id']]);
            $renderResult(false, 'زرین‌پال پرداخت را تأیید نکرد. در صورت کسر وجه، با پشتیبانی تماس بگیرید.');
        }

        $gatewayRef = $gatewayResponse['data']['ref_id'] ?? null;
        if (!is_string($gatewayRef) && !is_int($gatewayRef)) {
            throw new RuntimeException('zarinpal_vip_verify_reference_missing');
        }
        $refId = trim((string)$gatewayRef);
        if ($refId === '' || strlen($refId) > 64) {
            throw new RuntimeException('zarinpal_vip_verify_reference_invalid');
        }

        // Persist the verified transition under a row lock before VIPCore I/O.
        $pdo->beginTransaction();
        $lock = $pdo->prepare(
            'SELECT status, ref_id, amount_rial, authority
             FROM vip_orders
             WHERE id = ?
             LIMIT 1 FOR UPDATE'
        );
        $lock->execute([(int)$order['id']]);
        $locked = $lock->fetch(PDO::FETCH_ASSOC);

        if (!is_array($locked)
            || (string)($locked['authority'] ?? '') !== $authority
            || (int)($locked['amount_rial'] ?? 0) !== $amountRial) {
            throw new RuntimeException('vip_order_changed_during_verification');
        }

        $lockedStatus = (string)($locked['status'] ?? '');
        if ($lockedStatus === 'pending') {
            $paid = $pdo->prepare(
                "UPDATE vip_orders
                 SET status = 'paid', ref_id = ?, paid_at = UTC_TIMESTAMP(), last_error = NULL
                 WHERE id = ? AND status = 'pending'"
            );
            $paid->execute([$refId, (int)$order['id']]);
            if ($paid->rowCount() !== 1) {
                throw new RuntimeException('vip_order_paid_transition_failed');
            }
        } elseif ($lockedStatus === 'provisioned') {
            $pdo->commit();
            $renderResult(true, 'این سفارش قبلاً با موفقیت فعال شده است.', $amountRial, (string)($locked['ref_id'] ?? $refId));
        } elseif ($lockedStatus === 'paid') {
            $refId = trim((string)($locked['ref_id'] ?? $refId));
        } else {
            throw new RuntimeException('vip_order_state_changed_during_verification');
        }
        $pdo->commit();
        $orderStatus = 'paid';
    }

    // Paid orders may be retried safely after a temporary Linux API outage.
    try {
        $grant = (new VipCoreRepository())->grantPaidMembership(
            $orderRef,
            $steamId64,
            $playerName,
            $level,
            $durationSeconds
        );

        $markProvisioned = $pdo->prepare(
            "UPDATE vip_orders
             SET status = 'provisioned',
                 provisioned_at = UTC_TIMESTAMP(),
                 last_error = NULL
             WHERE order_ref = ? AND status = 'paid'"
        );
        $markProvisioned->execute([$orderRef]);

        $verifyProvisioned = $pdo->prepare(
            'SELECT status, ref_id FROM vip_orders WHERE order_ref = ? LIMIT 1'
        );
        $verifyProvisioned->execute([$orderRef]);
        $finalOrder = $verifyProvisioned->fetch(PDO::FETCH_ASSOC);

        if (!is_array($finalOrder) || (string)($finalOrder['status'] ?? '') !== 'provisioned') {
            throw new RuntimeException('vip_order_provision_status_not_saved');
        }

        $finalReference = trim((string)($finalOrder['ref_id'] ?? $refId));
        $renderResult(true, 'اشتراک با موفقیت در VIPCore ثبت و تأیید شد.', $amountRial, $finalReference);
    } catch (Throwable $e) {
        try {
            $errorCode = $e instanceof FragCityLinuxApiException
                ? 'vipcore_' . preg_replace('/[^a-z0-9_]/', '', $e->errorCode)
                : 'vipcore_provision_failed';
            $markPending = $pdo->prepare(
                "UPDATE vip_orders
                 SET status = 'paid', last_error = ?
                 WHERE order_ref = ? AND status = 'paid'"
            );
            $markPending->execute([substr($errorCode, 0, 80), $orderRef]);
        } catch (Throwable) {
            // The verified paid order is retained; a later callback can retry.
        }

        error_log('[FragCity VIP checkout] Verified order could not be provisioned; type=' . get_class($e));
        $renderResult(
            false,
            'پرداخت شما تأیید شده، اما ارتباط با VIPCore برای ثبت اشتراک کامل نشد.',
            $amountRial,
            $refId,
            true
        );
    }
} catch (Throwable $e) {
    error_log('[FragCity VIP callback] Processing failed; type=' . get_class($e));
    $renderResult(false, 'پردازش بازگشت از درگاه با خطا روبه‌رو شد. سفارش پرداخت‌شده بدون حذف اطلاعات برای بررسی حفظ می‌شود.');
}

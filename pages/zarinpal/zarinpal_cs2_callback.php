<?php
declare(strict_types=1);

/**
 * ZarinPal callback for FragCity CS2 Paymenter checkout.
 *
 * A browser redirect is never proof of payment. This endpoint verifies the
 * Authority and exact server-side Rial amount with ZarinPal, persists the
 * verified transition, then submits the same idempotent order reference to
 * Paymenter. A failed Paymenter request leaves a durable payment_verified
 * record so the callback can be retried without charging the customer again.
 */
require_once __DIR__ . '/../../settings/session.php';
require_once __DIR__ . '/../../settings/loader.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

$render = static function (string $title, string $message, bool $success, int $status = 200): never {
    http_response_code($status);
    $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeMessage = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $accent = $success ? '#22c55e' : '#f97316';
    echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow">'
        . '<title>' . $safeTitle . ' | FragCity</title>'
        . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;box-sizing:border-box;background:#0b0b0b;color:#eee;font-family:Vazirmatn,Tahoma,sans-serif}.card{width:min(600px,100%);padding:32px;border:1px solid #54351e;border-radius:20px;background:#171412;text-align:center;box-shadow:0 20px 70px #0008}h1{font-size:1.4rem;color:' . $accent . '}p{line-height:2;color:#bbb}.btn{display:inline-block;margin-top:14px;padding:11px 22px;border-radius:10px;background:#f97316;color:#fff;text-decoration:none;font-weight:800}</style>'
        . '</head><body><main class="card"><h1>' . $safeTitle . '</h1><p>' . $safeMessage . '</p>'
        . '<a class="btn" href="/index.php?page=buyserveropen">بازگشت به FragCity</a></main></body></html>';
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    $render('درخواست نامعتبر', 'روش ارسال درخواست معتبر نیست.', false, 405);
}

$authorityInput = $_GET['Authority'] ?? '';
$gatewayStatusInput = $_GET['Status'] ?? '';
$authority = is_string($authorityInput) ? trim($authorityInput) : '';
$gatewayStatus = is_string($gatewayStatusInput) ? trim($gatewayStatusInput) : '';

if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $authority)) {
    $render('پرداخت نامعتبر', 'شناسه بازگشت از درگاه معتبر نیست.', false, 400);
}

$indexSettings = fragcity_settings('files.index', []);
$databaseProfile = is_array($indexSettings)
    ? trim((string) ($indexSettings['database_profile'] ?? 'main'))
    : 'main';
if ($databaseProfile === '') {
    $databaseProfile = 'main';
}
$pdo = fragcity_create_pdo($databaseProfile);
if (!$pdo instanceof PDO) {
    $render('سفارش در دسترس نیست', 'دیتابیس سفارش در دسترس نیست. پرداخت مجدد انجام ندهید و با پشتیبانی تماس بگیرید.', false, 503);
}

try {
    $lookup = $pdo->prepare(
        'SELECT id, order_ref, steamid64, payload_json, payment_method,
                amount_toman, amount_rial, currency_code, authority, ref_id, status,
                paymenter_order_id, paymenter_invoice_id, paymenter_service_id
         FROM fragcity_cs2_checkout_orders
         WHERE authority = ?
         LIMIT 1'
    );
    $lookup->execute([$authority]);
    $order = $lookup->fetch(PDO::FETCH_ASSOC);
    if (!is_array($order)) {
        $render('سفارش پیدا نشد', 'سفارش متناظر با این پرداخت پیدا نشد. برای جلوگیری از پرداخت تکراری، خرید جدید ایجاد نکنید و با پشتیبانی تماس بگیرید.', false, 404);
    }

    if ((string) ($order['payment_method'] ?? '') !== 'zarinpal'
        || (string) ($order['authority'] ?? '') !== $authority
        || (int) ($order['amount_rial'] ?? 0) < 10
        || (int) ($order['amount_rial'] ?? 0) % 10 !== 0
        || (int) ($order['amount_toman'] ?? 0) !== intdiv((int) $order['amount_rial'], 10)) {
        $render('اطلاعات سفارش نامعتبر', 'اطلاعات پرداخت با سفارش FragCity تطبیق ندارد. سفارش برای بررسی حفظ شده است.', false, 409);
    }

    $status = (string) ($order['status'] ?? '');
    if ($status === 'accepted') {
        $render('خرید ثبت شد', 'پرداخت و ثبت سفارش شما قبلاً با موفقیت در Paymenter انجام شده است. Provisioning سرور در صف اجرا قرار دارد.', true);
    }

    if ($status === 'cancelled' || $status === 'gateway_request_failed') {
        $render('پرداخت تکمیل نشد', 'این سفارش لغو شده یا درخواست درگاه را نپذیرفته است. برای خرید مجدد از فروشگاه اقدام کنید.', false, 409);
    }

    if ($status === 'awaiting_payment') {
        // Always verify the saved Authority and exact amount with ZarinPal,
        // regardless of the browser-controlled Status query parameter. This
        // also recovers a genuinely paid order if someone edits Status=N to OK
        // or OK to N before opening the callback URL.
        $zarinpalSettings = fragcity_settings('integrations.zarinpal', []);
        $merchantId = is_array($zarinpalSettings)
            ? trim((string) ($zarinpalSettings['merchant_id'] ?? ''))
            : '';
        $verifyUrl = is_array($zarinpalSettings)
            ? trim((string) ($zarinpalSettings['verify_url'] ?? ''))
            : '';
        $verifyParts = parse_url($verifyUrl);

        if (
            $merchantId === ''
            || !is_array($verifyParts)
            || strtolower((string) ($verifyParts['scheme'] ?? '')) !== 'https'
            || empty($verifyParts['host'])
            || isset($verifyParts['user'])
            || isset($verifyParts['pass'])
            || isset($verifyParts['query'])
            || isset($verifyParts['fragment'])
            || !function_exists('curl_init')
        ) {
            $render('تأیید پرداخت در دسترس نیست', 'تنظیمات امن تأیید زرین‌پال کامل نیست. سفارش برای بررسی حفظ شده است؛ پرداخت را دوباره انجام ندهید.', false, 503);
        }

        $verifyBody = json_encode([
            'merchant_id' => $merchantId,
            'amount' => (int) $order['amount_rial'],
            'authority' => $authority,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $curl = curl_init($verifyUrl);
        if ($curl === false) {
            throw new RuntimeException('zarinpal_curl_init_failed');
        }
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $verifyBody,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
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
        $httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if (!is_string($verifyResponse) || $curlError !== 0 || $httpStatus < 200 || $httpStatus >= 300) {
            throw new RuntimeException('zarinpal_verify_transport_failed');
        }

        $gatewayResponse = json_decode($verifyResponse, true, 32, JSON_THROW_ON_ERROR);
        $gatewayCode = is_array($gatewayResponse)
            ? filter_var($gatewayResponse['data']['code'] ?? null, FILTER_VALIDATE_INT)
            : false;
        if ($gatewayCode !== 100 && $gatewayCode !== 101) {
            $failed = $pdo->prepare(
                "UPDATE fragcity_cs2_checkout_orders
                 SET status = 'payment_failed', last_error = 'zarinpal_verify_rejected'
                 WHERE id = ? AND status = 'awaiting_payment'"
            );
            $failed->execute([(int) $order['id']]);
            $render('پرداخت تأیید نشد', 'زرین‌پال پرداخت این سفارش را تأیید نکرد. اگر مبلغی از حساب شما کسر شده، با پشتیبانی تماس بگیرید.', false, 402);
        }

        $refValue = $gatewayResponse['data']['ref_id'] ?? null;
        if (!is_string($refValue) && !is_int($refValue)) {
            throw new RuntimeException('zarinpal_verify_reference_missing');
        }
        $verifiedRefId = trim((string) $refValue);
        if ($verifiedRefId === '' || strlen($verifiedRefId) > 64 || !preg_match('/^[A-Za-z0-9_-]+$/D', $verifiedRefId)) {
            throw new RuntimeException('zarinpal_verify_reference_invalid');
        }

        $pdo->beginTransaction();
        $lock = $pdo->prepare(
            'SELECT status, authority, amount_rial, ref_id
             FROM fragcity_cs2_checkout_orders
             WHERE id = ? LIMIT 1 FOR UPDATE'
        );
        $lock->execute([(int) $order['id']]);
        $locked = $lock->fetch(PDO::FETCH_ASSOC);

        if (
            !is_array($locked)
            || (string) ($locked['authority'] ?? '') !== $authority
            || (int) ($locked['amount_rial'] ?? 0) !== (int) $order['amount_rial']
        ) {
            throw new RuntimeException('checkout_changed_during_verify');
        }

        $lockedStatus = (string) ($locked['status'] ?? '');
        if ($lockedStatus === 'awaiting_payment') {
            $paid = $pdo->prepare(
                "UPDATE fragcity_cs2_checkout_orders
                 SET status = 'payment_verified', ref_id = ?, paid_at = CURRENT_TIMESTAMP,
                     last_error = NULL
                 WHERE id = ? AND status = 'awaiting_payment'"
            );
            $paid->execute([$verifiedRefId, (int) $order['id']]);
            if ($paid->rowCount() !== 1) {
                throw new RuntimeException('checkout_verified_transition_failed');
            }
            $order['ref_id'] = $verifiedRefId;
            $order['status'] = 'payment_verified';
        } elseif ($lockedStatus === 'payment_verified') {
            $existingRef = trim((string) ($locked['ref_id'] ?? ''));
            if ($existingRef === '') {
                throw new RuntimeException('checkout_verified_reference_missing');
            }
            $order['ref_id'] = $existingRef;
            $order['status'] = 'payment_verified';
        } elseif ($lockedStatus === 'accepted') {
            $pdo->commit();
            $render('خرید ثبت شد', 'این سفارش قبلاً در Paymenter ثبت شده است و Provisioning سرور در صف اجرا قرار دارد.', true);
        } else {
            throw new RuntimeException('checkout_state_changed_during_verify');
        }
        $pdo->commit();
        $status = 'payment_verified';
    } elseif ($status === 'payment_verified') {
        // The gateway receipt is already durably verified. Retrying here only
        // replays the same Paymenter order_ref and can never issue another charge.
        $gatewayRef = trim((string) ($order['ref_id'] ?? ''));
        if ($gatewayRef === '') {
            throw new RuntimeException('checkout_verified_reference_missing');
        }
    } elseif ($status === 'payment_requesting' || $status === 'gateway_request_unknown') {
        $render('وضعیت درخواست درگاه نامشخص است', 'وضعیت ایجاد درخواست پرداخت قطعی نیست. برای جلوگیری از پرداخت تکراری، فعلاً درخواست جدید نسازید و با پشتیبانی تماس بگیرید.', false, 409);
    } else {
        $render('سفارش قابل پردازش نیست', 'وضعیت این سفارش قابل پردازش نیست. برای بررسی با پشتیبانی تماس بگیرید.', false, 409);
    }

    $payload = json_decode((string) ($order['payload_json'] ?? ''), true, 32, JSON_THROW_ON_ERROR);
    if (
        !is_array($payload)
        || (string) ($payload['order_ref'] ?? '') !== (string) $order['order_ref']
        || (string) ($payload['steamid64'] ?? '') !== (string) $order['steamid64']
        || (int) ($payload['expected_amount_rial'] ?? 0) !== (int) $order['amount_rial']
        || !is_array($payload['resources'] ?? null)
        || !is_array($payload['feature_limits'] ?? null)
        || !is_array($payload['egg'] ?? null)
    ) {
        throw new RuntimeException('checkout_payload_invalid');
    }

    require_once dirname(__DIR__, 2) . '/class/PaymenterApiClient.php';
    $paymenter = new PaymenterApiClient();
    try {
        $result = $paymenter->createCs2ServerOrder([
            ...$payload,
            'payment' => [
                'provider' => 'zarinpal',
                'authority' => $authority,
                'ref_id' => (string) $order['ref_id'],
                'amount_toman' => (int) $order['amount_toman'],
                'amount_rial' => (int) $order['amount_rial'],
            ],
            'expected_amount_rial' => (int) $order['amount_rial'],
        ]);

        $save = $pdo->prepare(
            "UPDATE fragcity_cs2_checkout_orders
             SET status = 'accepted', paymenter_order_id = ?, paymenter_invoice_id = ?,
                 paymenter_service_id = ?, accepted_at = CURRENT_TIMESTAMP, last_error = NULL
             WHERE id = ? AND status = 'payment_verified'"
        );
        $save->execute([
            $result['order_id'],
            $result['invoice_id'],
            $result['service_id'],
            (int) $order['id'],
        ]);
        if ($save->rowCount() !== 1) {
            $reload = $pdo->prepare(
                "SELECT status FROM fragcity_cs2_checkout_orders WHERE id = ? LIMIT 1"
            );
            $reload->execute([(int) $order['id']]);
            if ((string) $reload->fetchColumn() !== 'accepted') {
                throw new RuntimeException('checkout_paymenter_state_save_failed');
            }
        }

        $render('خرید با موفقیت ثبت شد', 'پرداخت توسط زرین‌پال تأیید شد و سفارش در Paymenter ثبت شد. ساخت سرور به‌صورت خودکار در صف Provisioning قرار گرفته است.', true);
    } catch (Throwable $e) {
        $safeError = preg_match('/^[a-z0-9_]{1,96}$/D', $e->getMessage())
            ? $e->getMessage()
            : 'paymenter_order_unavailable';
        $saveError = $pdo->prepare(
            "UPDATE fragcity_cs2_checkout_orders
             SET last_error = ?
             WHERE id = ? AND status = 'payment_verified'"
        );
        $saveError->execute([$safeError, (int) $order['id']]);
        error_log(
            '[FragCity] Verified CS2 payment awaits Paymenter; order='
            . (string) $order['order_ref'] . '; error=' . $safeError . '.'
        );
        $render(
            'پرداخت تأیید شد؛ ثبت سرور در انتظار تکمیل است',
            'زرین‌پال پرداخت را با موفقیت تأیید کرده، اما ارتباط ثبت سفارش با Paymenter کامل نشد. مبلغ دوباره دریافت نمی‌شود. از خرید مجدد خودداری کنید و با پشتیبانی تماس بگیرید؛ سفارش برای تلاش مجدد حفظ شده است.',
            false,
            202
        );
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $safeError = preg_match('/^[a-z0-9_]{1,96}$/D', $e->getMessage())
        ? $e->getMessage()
        : 'cs2_callback_unavailable';
    error_log('[FragCity] CS2 payment callback failed; authority_hash=' . substr(hash('sha256', $authority), 0, 16) . '; error=' . $safeError . '.');
    $render(
        'پردازش پرداخت کامل نشد',
        'نتیجه پرداخت هنوز به‌صورت قطعی پردازش نشده است. برای جلوگیری از پرداخت تکراری، دوباره خرید نکنید و با پشتیبانی تماس بگیرید.',
        false,
        502
    );
}

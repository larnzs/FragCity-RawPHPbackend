<?php
declare(strict_types=1);

require_once __DIR__ . '/../../settings/loader.php';

$zarinpalSettings = fragcity_settings('integrations.zarinpal', []);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$is_success = false;
$error_message = '';
$amount = 0;
$ref_id = '';

$authorityInput = $_GET['Authority'] ?? '';
$gatewayStatus = $_GET['Status'] ?? '';
$authority = is_string($authorityInput) ? trim($authorityInput) : '';

$databaseProfile = fragcity_settings('files.index.database_profile', 'main');
$databaseProfile = is_string($databaseProfile) && trim($databaseProfile) !== ''
    ? trim($databaseProfile)
    : 'main';
$pdo = fragcity_create_pdo($databaseProfile);

if (!$pdo instanceof PDO) {
    http_response_code(503);
    exit('Database unavailable.');
}

if (
    $gatewayStatus !== 'OK'
    || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $authority)
) {
    // The browser's Status query parameter is not proof of payment state.
    // Do not mutate a pending transaction based only on a cancel/failure redirect.
    $error_message = 'تراکنش توسط شما لغو شد یا اطلاعات بازگشت از درگاه معتبر نیست.';
} else {
    try {
        $transactionQuery = $pdo->prepare(
            'SELECT id, steamid, amount, type, status, ref_id
             FROM wallet_transactions
             WHERE authority = ?
             LIMIT 1'
        );
        $transactionQuery->execute([$authority]);
        $transaction = $transactionQuery->fetch(PDO::FETCH_ASSOC);

        if (!is_array($transaction)) {
            $error_message = 'تراکنش یافت نشد.';
        } elseif (
            (string) ($transaction['type'] ?? '') !== 'deposit'
            || !preg_match('/^[0-9]{17}$/D', (string) ($transaction['steamid'] ?? ''))
            || (int) ($transaction['amount'] ?? 0) < 1
        ) {
            $error_message = 'اطلاعات تراکنش معتبر نیست.';
        } elseif ((string) ($transaction['status'] ?? '') === 'success') {
            $is_success = true;
            $amount = (int) $transaction['amount'];
            $ref_id = (string) ($transaction['ref_id'] ?? '');
        } elseif ((string) ($transaction['status'] ?? '') !== 'pending') {
            $error_message = 'این تراکنش دیگر در وضعیت قابل تأیید نیست.';
        } else {
            $merchantId = is_array($zarinpalSettings)
                ? trim((string) ($zarinpalSettings['merchant_id'] ?? ''))
                : '';
            $verifyUrl = is_array($zarinpalSettings)
                ? trim((string) ($zarinpalSettings['verify_url'] ?? ''))
                : '';
            $verifyUrlParts = parse_url($verifyUrl);

            if (
                $merchantId === ''
                || !is_array($verifyUrlParts)
                || strtolower((string) ($verifyUrlParts['scheme'] ?? '')) !== 'https'
                || empty($verifyUrlParts['host'])
                || isset($verifyUrlParts['user'])
                || isset($verifyUrlParts['pass'])
            ) {
                throw new RuntimeException('zarinpal_verify_configuration_invalid');
            }

            $verifyPayload = [
                'merchant_id' => $merchantId,
                'amount' => ((int) $transaction['amount']) * 10,
                'authority' => $authority,
            ];
            $verifyBody = json_encode(
                $verifyPayload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );

            $curl = curl_init($verifyUrl);
            if ($curl === false) {
                throw new RuntimeException('zarinpal_verify_curl_init_failed');
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

            $response = curl_exec($curl);
            $curlError = curl_errno($curl);
            $httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if (!is_string($response) || $curlError !== 0 || $httpStatus < 200 || $httpStatus >= 300) {
                throw new RuntimeException('zarinpal_verify_transport_failed');
            }

            $gatewayResponse = json_decode($response, true, 32, JSON_THROW_ON_ERROR);
            $gatewayCode = filter_var($gatewayResponse['data']['code'] ?? null, FILTER_VALIDATE_INT);

            if ($gatewayCode !== 100 && $gatewayCode !== 101) {
                $markFailed = $pdo->prepare(
                    "UPDATE wallet_transactions
                     SET status = 'failed'
                     WHERE id = ? AND status = 'pending' AND type = 'deposit'"
                );
                $markFailed->execute([(int) $transaction['id']]);
                $error_message = 'پرداخت توسط بانک تأیید نشد. لطفاً در صورت کسر وجه با پشتیبانی تماس بگیرید.';
            } else {
                $gatewayRefId = $gatewayResponse['data']['ref_id'] ?? null;
                if (!is_string($gatewayRefId) && !is_int($gatewayRefId)) {
                    throw new RuntimeException('zarinpal_verify_reference_missing');
                }
                $gatewayRefId = trim((string) $gatewayRefId);
                if ($gatewayRefId === '' || strlen($gatewayRefId) > 255) {
                    throw new RuntimeException('zarinpal_verify_reference_invalid');
                }

                // The gateway verification is external. Lock and re-check the
                // local transaction before crediting; concurrent callbacks then
                // serialize on this row and only one can change pending -> success.
                $pdo->beginTransaction();

                $lockTransaction = $pdo->prepare(
                    'SELECT id, steamid, amount, type, status, ref_id
                     FROM wallet_transactions
                     WHERE id = ?
                     LIMIT 1
                     FOR UPDATE'
                );
                $lockTransaction->execute([(int) $transaction['id']]);
                $lockedTransaction = $lockTransaction->fetch(PDO::FETCH_ASSOC);

                if (!is_array($lockedTransaction)) {
                    throw new RuntimeException('wallet_transaction_disappeared');
                }

                $lockedStatus = (string) ($lockedTransaction['status'] ?? '');
                if ($lockedStatus === 'success') {
                    // Another callback already credited this verified payment.
                    $pdo->commit();
                    $is_success = true;
                    $amount = (int) $lockedTransaction['amount'];
                    $ref_id = (string) ($lockedTransaction['ref_id'] ?? '');
                } elseif (
                    $lockedStatus !== 'pending'
                    || (string) ($lockedTransaction['type'] ?? '') !== 'deposit'
                    || (string) ($lockedTransaction['steamid'] ?? '') !== (string) $transaction['steamid']
                    || (int) ($lockedTransaction['amount'] ?? 0) !== (int) $transaction['amount']
                ) {
                    throw new RuntimeException('wallet_transaction_state_changed');
                } else {
                    $steamId = (string) $lockedTransaction['steamid'];
                    $creditAmount = (int) $lockedTransaction['amount'];

                    // A stable user-row lock also serializes simultaneous first
                    // deposits for the same user before a wallet row exists.
                    $userLock = $pdo->prepare(
                        'SELECT steamid64
                         FROM users
                         WHERE steamid64 = ?
                         LIMIT 1
                         FOR UPDATE'
                    );
                    $userLock->execute([$steamId]);
                    if ($userLock->fetchColumn() === false) {
                        throw new RuntimeException('wallet_owner_not_found');
                    }

                    $walletLock = $pdo->prepare(
                        'SELECT steamid
                         FROM user_wallets
                         WHERE steamid = ?
                         LIMIT 1
                         FOR UPDATE'
                    );
                    $walletLock->execute([$steamId]);

                    if ($walletLock->fetch(PDO::FETCH_ASSOC)) {
                        $credit = $pdo->prepare(
                            'UPDATE user_wallets
                             SET balance = balance + ?
                             WHERE steamid = ?'
                        );
                        $credit->execute([$creditAmount, $steamId]);
                        if ($credit->rowCount() !== 1) {
                            throw new RuntimeException('wallet_balance_update_failed');
                        }
                    } else {
                        $createWallet = $pdo->prepare(
                            'INSERT INTO user_wallets (steamid, balance)
                             VALUES (?, ?)'
                        );
                        $createWallet->execute([$steamId, $creditAmount]);
                    }

                    $markSuccess = $pdo->prepare(
                        "UPDATE wallet_transactions
                         SET status = 'success', ref_id = ?
                         WHERE id = ? AND status = 'pending' AND type = 'deposit'"
                    );
                    $markSuccess->execute([
                        $gatewayRefId,
                        (int) $lockedTransaction['id'],
                    ]);
                    if ($markSuccess->rowCount() !== 1) {
                        throw new RuntimeException('wallet_transaction_success_transition_failed');
                    }

                    $pdo->commit();
                    $is_success = true;
                    $amount = $creditAmount;
                    $ref_id = $gatewayRefId;
                }
            }
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            '[FragCity] ZarinPal wallet callback failed ('
            . get_class($e) . ', code ' . (string) $e->getCode() . ').'
        );
        $is_success = false;
        $error_message = 'تأیید یا ثبت تراکنش با خطا مواجه شد. اگر مبلغ از حساب شما کسر شده، با پشتیبانی تماس بگیرید.';
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_success ? 'پرداخت موفق' : 'خطا در پرداخت' ?> | FragCity</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/fragcity-success-modal.css?v=20261106-1">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0a0a0a; color: #e0e0e0; font-family: 'Vazirmatn', sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; direction: rtl; overflow: hidden; }
        .result-card { background: rgba(17,17,17,0.95); backdrop-filter: blur(12px); border-radius: 32px; border: 1px solid rgba(245,176,66,0.2); padding: 48px 32px; max-width: 480px; width: 90%; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.6); animation: fadeInUp 0.5s ease both; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .icon-circle { width: 100px; height: 100px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; font-size: 3.5rem; }
        .success .icon-circle { background: rgba(46, 204, 113, 0.15); color: #2ecc71; border: 2px solid rgba(46, 204, 113, 0.3); box-shadow: 0 0 30px rgba(46, 204, 113, 0.2); }
        .error .icon-circle { background: rgba(231, 76, 60, 0.15); color: #e74c3c; border: 2px solid rgba(231, 76, 60, 0.3); box-shadow: 0 0 30px rgba(231, 76, 60, 0.2); }
        h1 { font-size: 1.8rem; font-weight: 800; margin-bottom: 16px; }
        .success h1 { color: #2ecc71; }
        .error h1 { color: #e74c3c; }
        .details { background: rgba(0,0,0,0.3); border-radius: 16px; padding: 16px; margin: 24px 0; font-size: 1rem; color: #ccc; }
        .details .amount { font-size: 2rem; font-weight: 800; color: #F5B042; margin: 8px 0; }
        .details .ref-id { direction: ltr; color: #F5B042; font-family: monospace; background: #1a1a1a; padding: 4px 12px; border-radius: 20px; display: inline-block; margin-top: 8px; }
        .progress-container { width: 100%; height: 6px; background: rgba(255,255,255,0.1); border-radius: 10px; margin: 24px 0 16px; overflow: hidden; }
        .progress-bar { height: 100%; background: linear-gradient(90deg, #F5B042, #E68A2E); border-radius: 10px; width: 0%; animation: progressAnim 3s linear forwards; }
        @keyframes progressAnim { from { width: 0%; } to { width: 100%; } }
        .btn-return { display: inline-block; background: linear-gradient(135deg, #F5B042, #E68A2E); color: #000; font-weight: 700; padding: 12px 32px; border-radius: 40px; text-decoration: none; margin-top: 8px; transition: 0.2s; font-family: 'Vazirmatn', sans-serif; cursor: pointer; border: none; }
        .btn-return:hover { opacity: 0.9; transform: scale(0.98); }
    </style>
</head>
<body class="<?= $is_success ? 'fc-success-page-body' : '' ?>">
    <?php if ($is_success): ?>
        <?php
        $fcSuccessModalConfig = [
            'auto_open' => true,
            'kind' => 'payment',
            'title' => 'پرداخت با موفقیت انجام شد',
            'message' => 'پرداخت شما تأیید شد و مبلغ شارژ با موفقیت به کیف پول FragCity اضافه شد.',
            'amount' => number_format($amount) . ' تومان',
            'amount_label' => 'مبلغ شارژ کیف پول',
            'reference' => $ref_id,
            'return_url' => 'https://fragcity.ir/',
            'return_label' => 'بازگشت به FragCity',
            'dismissible' => false,
        ];
        require __DIR__ . '/../../includes/fragcity-success-modal.php';
        ?>
    <?php else: ?>
        <div class="result-card error">
            <div class="icon-circle"><i class="fas fa-exclamation-triangle"></i></div>
            <h1>خطا در پرداخت</h1>
            <p style="color:#aaa; margin-bottom: 24px;"><?= htmlspecialchars($error_message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
            <a href="https://fragcity.ir/" class="btn-return">بازگشت به FragCity</a>
            <script>
                setTimeout(function () {
                    window.location.href = 'https://fragcity.ir/';
                }, 5000);
            </script>
        </div>
    <?php endif; ?>
    <script src="/js/fragcity-success-modal.js?v=20261106-1" defer></script>
</body>
</html>
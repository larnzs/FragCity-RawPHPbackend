<?php
require_once __DIR__ . '/../../settings/loader.php';
$zarinpalSettings=fragcity_settings('integrations.zarinpal',array());
// pages/zarinpal/zarinpal_wallet_callback.php
session_start();

$authority = $_GET['Authority'] ?? '';
$status = $_GET['Status'] ?? '';

$is_success = false;
$error_message = '';
$amount = 0;
$ref_id = '';

$fileSettings=fragcity_settings('files.pages.zarinpal.wallet_callback',array());$dbProfile=isset($fileSettings['database_profile'])?$fileSettings['database_profile']:'main';$pdo=fragcity_create_pdo($dbProfile);if(!$pdo){http_response_code(503);exit('Database unavailable or not configured.');}

if ($status !== 'OK' || empty($authority)) {
    $is_success = false;
    $error_message = 'تراکنش توسط شما لغو شد یا با خطا مواجه گردید.';

    // در صورت لغو تراکنش توسط کاربر، وضعیت failed شود
    if (!empty($authority)) {
        $pdo->prepare("UPDATE wallet_transactions SET status = 'failed' WHERE authority = ?")
            ->execute([$authority]);
    }
} else {
    $merchant_id=isset($zarinpalSettings['merchant_id'])?(string)$zarinpalSettings['merchant_id']:'';

    // پیدا کردن تراکنش
    $stmt = $pdo->prepare("SELECT * FROM wallet_transactions WHERE authority = ? LIMIT 1");
    $stmt->execute([$authority]);
    $transaction = $stmt->fetch();

    if (!$transaction) {
        $is_success = false;
        $error_message = 'تراکنش یافت نشد.';
    } else {
        $amount  = (int)$transaction['amount'];
        $steamid = $transaction['steamid'];
        $currentStatus = $transaction['status'];

        // جلوگیری از شارژ مجدد کیف پول با رفرش صفحه
        if ($currentStatus === 'success') {
            $is_success = true;
            $ref_id = $transaction['ref_id'] ?? '';
        } else {
            // تأیید نهایی پرداخت با زرین‌پال
            $data = [
                "merchant_id" => $merchant_id,
                "amount"      => $amount * 10,
                "authority"   => $authority
            ];

            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => (isset($zarinpalSettings['verify_url']) ? $zarinpalSettings['verify_url'] : ''),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => '',
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => 'POST',
                CURLOPT_POSTFIELDS     => json_encode($data),
                CURLOPT_HTTPHEADER     => array('Content-Type: application/json', 'Accept: application/json'),
            ));

            $response = curl_exec($curl);
            curl_close($curl);
            $result = json_decode($response, true);

            if (isset($result['data']['code']) && ($result['data']['code'] == 100 || $result['data']['code'] == 101)) {
                $ref_id = $result['data']['ref_id'];

                try {
                    // شروع Transaction دیتابیس برای ایمنی موجودی کیف پول
                    $pdo->beginTransaction();

                    $walletStmt = $pdo->prepare("SELECT steamid FROM user_wallets WHERE steamid = ? LIMIT 1");
                    $walletStmt->execute([$steamid]);
                    
                    if ($walletStmt->fetch()) {
                        // آپدیت موجودی
                        $pdo->prepare("UPDATE user_wallets SET balance = balance + ? WHERE steamid = ?")
                            ->execute([$amount, $steamid]);
                    } else {
                        // ایجاد کیف پول جدید در صورت عدم وجود
                        $pdo->prepare("INSERT INTO user_wallets (steamid, balance) VALUES (?, ?)")
                            ->execute([$steamid, $amount]);
                    }

                    // به‌روزرسانی تراکنش به موفق
                    $pdo->prepare("UPDATE wallet_transactions SET status = 'success', ref_id = ? WHERE authority = ?")
                        ->execute([$ref_id, $authority]);

                    $pdo->commit();
                    $is_success = true;

                } catch (Exception $e) {
                    $pdo->rollBack();
                    $is_success = false;
                    $error_message = 'خطا در ثبت اطلاعات مالی در دیتابیس.';
                }
            } else {
                $pdo->prepare("UPDATE wallet_transactions SET status = 'failed' WHERE authority = ?")
                    ->execute([$authority]);
                $is_success = false;
                $error_message = 'پرداخت توسط بانک تأیید نشد. لطفاً در صورت کسر وجه منتظر بازگشت آن باشید.';
            }
        }
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
<body>
    <div class="result-card <?= $is_success ? 'success' : 'error' ?>">
        <?php if ($is_success): ?>
            <div class="icon-circle"><i class="fas fa-check-circle"></i></div>
            <h1>پرداخت موفق</h1>
            <div class="details">
                <div>مبلغ شارژ</div>
                <div class="amount"><?= number_format($amount) ?> تومان</div>
                <?php if ($ref_id): ?>
                <div>شماره پیگیری</div>
                <div class="ref-id"><?= htmlspecialchars($ref_id) ?></div>
                <?php endif; ?>
            </div>
            <div class="progress-container">
                <div class="progress-bar"></div>
            </div>
            <p style="color:#aaa; font-size:0.9rem;">در حال انتقال به کیف پول...</p>
            <script>
                setTimeout(function(){
                    window.location.href = 'https://fragcity.ir/?page=wallet_charge';
                }, 3000);
            </script>
        <?php else: ?>
            <div class="icon-circle"><i class="fas fa-exclamation-triangle"></i></div>
            <h1>خطا در پرداخت</h1>
            <p style="color:#aaa; margin-bottom: 24px;"><?= htmlspecialchars($error_message) ?></p>
            <a href="https://fragcity.ir/?page=wallet_charge" class="btn-return">بازگشت به کیف پول</a>
            <script>
                setTimeout(function(){
                    window.location.href = 'https://fragcity.ir/?page=wallet_charge';
                }, 5000);
            </script>
        <?php endif; ?>
    </div>
</body>
</html>
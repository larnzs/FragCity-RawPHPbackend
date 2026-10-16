<?php
require_once __DIR__ . '/../../settings/loader.php';
$zarinpalSettings=fragcity_settings('integrations.zarinpal',array());
// pages/zarinpal/zarinpal_wallet_request.php
session_start();

// تابع نمایش گرافیکی خطا
function terminateRequest($message) {
    $msg = str_replace('❌ ', '', $message);
    echo '<!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>خطا در درخواست | FragCity</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700;800&display=swap" rel="stylesheet">
        <style>
            body { background: #0a0a0a; color: #fff; font-family: "Vazirmatn", sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .card { background: rgba(17,17,17,0.95); border: 1px solid rgba(231,76,60,0.3); padding: 40px; border-radius: 20px; text-align: center; }
            .card i { font-size: 3rem; color: #e74c3c; margin-bottom: 20px; }
            .btn { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #F5B042; color: #000; text-decoration: none; border-radius: 10px; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="card">
            <i class="fas fa-exclamation-triangle"></i>
            <h2>خطا در پردازش درخواست</h2>
            <p>' . htmlspecialchars($msg) . '</p>
            <a href="https://fragcity.ir/?page=wallet_charge" class="btn">بازگشت به کیف پول</a>
        </div>
    </body>
    </html>';
    exit;
}

if (!isset($_SESSION['user_logged_in']) || empty($_SESSION['steamid'])) {
    terminateRequest('❌ برای شارژ کیف پول باید وارد حساب کاربری خود شوید.');
}

$amount = (int)($_POST['amount'] ?? 0);
if ($amount < 1000) {
    terminateRequest('❌ حداقل مبلغ شارژ ۱,۰۰۰ تومان است.');
}

$steamid = $_SESSION['steamid'];

$fileSettings=fragcity_settings('files.pages.zarinpal.wallet_request',array());$dbProfile=isset($fileSettings['database_profile'])?$fileSettings['database_profile']:'main';$pdo=fragcity_create_pdo($dbProfile);if(!$pdo){terminateRequest('اتصال دیتابیس در دسترس نیست یا تنظیم نشده است.');}

// ذخیره تراکنش موقت در جدول wallet_transactions
try {
    $stmt = $pdo->prepare(
        "INSERT INTO wallet_transactions (steamid, amount, type, description, authority, status) 
         VALUES (?, ?, 'deposit', 'شارژ کیف پول', '', 'pending')"
    );
    $stmt->execute([$steamid, $amount]);
    $transactionId = $pdo->lastInsertId();
} catch (Exception $e) {
    terminateRequest('❌ خطا در ثبت تراکنش موقت.');
}

// ---------- دریافت اطلاعات تماس کاربر ----------
$mobile = "";
if (!empty($_SESSION['phone']) && is_string($_SESSION['phone'])) {
    $mobile = $_SESSION['phone'];
} else {
    $userPhone = $pdo->prepare("SELECT phone FROM users WHERE steamid64 = ?");
    $userPhone->execute([$steamid]);
    $uP = $userPhone->fetch();
    if ($uP && !empty($uP['phone'])) {
        $mobile = $uP['phone'];
    }
}

$email = "";
if (!empty($_SESSION['email']) && is_string($_SESSION['email'])) {
    $email = $_SESSION['email'];
} else {
    $userEmail = $pdo->prepare("SELECT email FROM users WHERE steamid64 = ?");
    $userEmail->execute([$steamid]);
    $uE = $userEmail->fetch();
    if ($uE && !empty($uE['email'])) {
        $email = $uE['email'];
    }
}

// ---------------- ارسال درخواست به زرین‌پال ----------------
$merchant_id=isset($zarinpalSettings['merchant_id'])?(string)$zarinpalSettings['merchant_id']:'';
// آدرس دقیق کال‌بک بر اساس ساختار جدید
$callback_url = "https://fragcity.ir/pages/zarinpal/zarinpal_wallet_callback.php";

$data = [
    "merchant_id"  => $merchant_id,
    "amount"       => $amount * 10,
    "callback_url" => $callback_url,
    "description"  => "شارژ کیف پول - FragCity",
    "metadata"     => [
        "mobile" => $mobile,
        "email"  => $email
    ]
];

$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => (isset($zarinpalSettings['request_url']) ? $zarinpalSettings['request_url'] : ''),
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

// ---------------- بررسی و هدایت به درگاه ----------------
if (isset($result['data']['code']) && $result['data']['code'] == 100) {
    $authority = $result['data']['authority'];
    
    // بروزرسانی authority در دیتابیس
    $updateStmt = $pdo->prepare("UPDATE wallet_transactions SET authority = ? WHERE id = ?");
    $updateStmt->execute([$authority, $transactionId]);
    
    header("Location: https://payment.zarinpal.com/pg/StartPay/" . $authority);
    exit;
} else {
    $errorMsg = $result['errors']['message'] ?? 'خطای ناشناخته';
    terminateRequest("❌ خطا در اتصال به درگاه: " . $errorMsg);
}
?>
<?php
// pages/zarinpal/zarinpal_buyserver_request.php
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
        <link rel="stylesheet" href="../css/zarinpal/zarinpal_request.css">
    </head>
    <body>
        <div class="request-card">
            <i class="fas fa-exclamation-triangle"></i>
            <h1>خطا در پردازش درخواست</h1>
            <p>' . htmlspecialchars($msg) . '</p>
            <a href="https://fragcity.ir/?page=buyserveropen" class="btn-return">بازگشت به صفحه خرید</a>
        </div>
    </body>
    </html>';
    exit;
}

if (!isset($_SESSION['user_logged_in']) || empty($_SESSION['steamid'])) {
    terminateRequest('❌ برای خرید سرور باید وارد حساب کاربری خود شوید.');
}

$steamid = $_SESSION['steamid'];

try {
    $pdo = new PDO(
        'mysql:host=localhost;port=3306;dbname=kyonxcbt_fragcity_services;charset=utf8mb4',
        'kyonxcbt_admin',
        'Atrisayeafsorde824@'
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    terminateRequest('❌ خطا در اتصال به دیتابیس.');
}

// ---------------- دریافت و اعتبارسنجی اطلاعات فرم ----------------
$selectedSlots    = (int)($_POST['slots'] ?? 10);
$selectedTickrate = $_POST['tickrate'] ?? '64';
$selectedHours    = max(1, min(72, (int)($_POST['duration_hours'] ?? 24)));
$selectedMap      = $_POST['map'] ?? 'de_mirage';
$selectedNode     = trim($_POST['node_name'] ?? '');
$serverName       = trim($_POST['hostname'] ?? 'FragCity CS2 Server');
$serverPassword   = !empty($_POST['server_password']) ? $_POST['server_password'] : null;
$rconPassword     = !empty($_POST['rcon_password']) ? $_POST['rcon_password'] : substr(md5(uniqid((string)mt_rand(), true)), 0, 8);
$gsltToken        = trim($_POST['steam_account'] ?? '');
$lanMode          = (int)($_POST['lan'] ?? 0);

if (empty($selectedNode)) {
    terminateRequest('❌ لطفاً یک لوکیشن و نود پردازشی را انتخاب کنید.');
}

// بررسی وضعیت و رم نود پردازشی
$thresholdTime = date('Y-m-d H:i:s', time() - 60);
$nodeStmt = $pdo->prepare("SELECT ip_address, is_ready, ram_usage FROM agent_nodes WHERE node_name = ? AND is_ready = 1 AND last_heartbeat >= ? LIMIT 1");
$nodeStmt->execute([$selectedNode, $thresholdTime]);
$nodeInfo = $nodeStmt->fetch();

if (!$nodeInfo || (float)$nodeInfo['ram_usage'] > 80) {
    terminateRequest('❌ نود انتخابی شما آفلاین است یا ظرفیت آن تکمیل شده است.');
}

// محاسبه مبلغ نهایی سرور
$baseSlots = 10;
$basePricePerHour = 11000;
$pricePerExtraSlotHourly = 1200;
$tickrateExtraHour = ($selectedTickrate === '128') ? 3000 : 0;
$hourlyRate = $basePricePerHour + max(0, $selectedSlots - $baseSlots) * $pricePerExtraSlotHourly + $tickrateExtraHour;
$totalPrice = $hourlyRate * $selectedHours;

if ($totalPrice < 1000) {
    terminateRequest('❌ مبلغ محاسبه شده برای خرید سرور نامعتبر است.');
}

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
$mobile = is_string($mobile) ? $mobile : "";

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
$email = is_string($email) ? $email : "";

// ---------------- ارسال درخواست به زرین‌پال ----------------
$merchant_id = "0d7e7e6c-211f-4c2e-bf11-77ef8c36994a";
$callback_url = "https://fragcity.ir/pages/zarinpal/zarinpal_buyserver_callback.php";

$data = [
    "merchant_id"  => $merchant_id,
    "amount"       => $totalPrice * 10,
    "callback_url" => $callback_url,
    "description"  => "خرید سرور اختصاصی - FragCity",
    "metadata"     => [
        "mobile" => $mobile,
        "email"  => $email
    ]
];

$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL            => 'https://payment.zarinpal.com/pg/v4/payment/request.json',
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

// ---------------- بررسی نتیجه درگاه و ثبت موقت در دیتابیس (بدون رزرو پورت) ----------------
if (isset($result['data']['code']) && $result['data']['code'] == 100) {
    $authority = $result['data']['authority'];

    try {
        $timeLeft = $selectedHours * 3600;
        
        // ثبت در pending_servers با پورت NULL و وضعیت Pending_Payment
        $stmt = $pdo->prepare(
            "INSERT INTO pending_servers
            (steam_id, node_name, port, server_name, slots, modes, tickrate, rcon_password, server_password, gslt_token, insecure, paid_price, payment_token, time_left, expire_date, status)
            VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR), 'Pending_Payment')"
        );
        $stmt->execute([
            $steamid, $selectedNode, $serverName, $selectedSlots, $selectedMap, (int)$selectedTickrate,
            $rconPassword, $serverPassword, $gsltToken, $lanMode, $totalPrice, $authority, $timeLeft, $selectedHours
        ]);

        // انتقال کاربر به درگاه زرین‌پال
        echo "<script>window.location.href = 'https://payment.zarinpal.com/pg/StartPay/" . $authority . "';</script>";
        exit;

    } catch (Exception $e) {
        terminateRequest("❌ خطا در ثبت پیش‌فاکتور خرید: " . $e->getMessage());
    }
} else {
    $errorMsg = $result['errors']['message'] ?? 'خطای ناشناخته';
    terminateRequest("❌ خطا در اتصال به درگاه زرین‌پال: " . $errorMsg);
}
?>
<?php
declare(strict_types=1);

/**
 * Temporary safe shell for legacy server-management screens.
 *
 * These pages previously depended on the retired Windows Agent tables.
 * The replacement will be wired to Pterodactyl after its API and billing
 * contract are implemented. This file performs no database or payment work.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_GET['lang']) && in_array($_GET['lang'], ['fa', 'en'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
}
$lang = $_SESSION['lang'] ?? 'fa';
if (!in_array($lang, ['fa', 'en'], true)) {
    $lang = 'fa';
}
$is_fa = $lang === 'fa';

$pageMap = [
    'servers' => [
        'fa' => ['title' => 'سرورهای من', 'description' => 'فهرست سرورها پس از اتصال پنل به Pterodactyl در این بخش نمایش داده می‌شود.'],
        'en' => ['title' => 'My Servers', 'description' => 'Your server list will return after the panel is integrated with Pterodactyl.'],
    ],
    'overview' => [
        'fa' => ['title' => 'نمای کلی سرور', 'description' => 'کنترل وضعیت، اجرا و مصرف منابع تا تکمیل اتصال Pterodactyl موقتاً غیرفعال است.'],
        'en' => ['title' => 'Server Overview', 'description' => 'Status, start/stop controls, and resource details are disabled until the Pterodactyl integration is complete.'],
    ],
    'config' => [
        'fa' => ['title' => 'گیم‌مود و کانفیگ', 'description' => 'اعمال کانفیگ و تغییر گیم‌مود پس از پیاده‌سازی API جدید پنل دوباره فعال می‌شود.'],
        'en' => ['title' => 'Game Mode & Config', 'description' => 'Config and game-mode controls will return after the new panel API is implemented.'],
    ],
    'players' => [
        'fa' => ['title' => 'بازیکنان', 'description' => 'فهرست بازیکنان و عملیات مدیریتی تا تکمیل اتصال امن سرور موقتاً غیرفعال است.'],
        'en' => ['title' => 'Players', 'description' => 'The player list and management actions are disabled until secure server integration is complete.'],
    ],
];

$key = isset($legacyServerPage) && is_string($legacyServerPage) && isset($pageMap[$legacyServerPage])
    ? $legacyServerPage
    : 'servers';
$page = $pageMap[$key][$lang];
$current_page = $key;
$page_title = $is_fa ? 'پنل مدیریت CS2' : 'CS2 Admin Panel';

?><!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>" dir="<?= $is_fa ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page['title'], ENT_QUOTES, 'UTF-8') ?> | FragCity</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/panel.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/panel-motion.css">
    <style>
        .fc-migration-notice { max-width: 920px; margin: 36px auto; padding: 30px; border: 1px solid rgba(245,176,66,.24); border-radius: 20px; background: #151515; color: #eee; }
        .fc-migration-notice h1 { margin: 0 0 14px; font-size: 1.5rem; }
        .fc-migration-notice p { line-height: 1.9; color: #c8c8c8; }
        .fc-migration-notice .fc-migration-icon { color: #F5B042; font-size: 2rem; margin-bottom: 14px; }
        .fc-migration-notice a { display: inline-block; margin-top: 10px; color: #111; background: #F5B042; padding: 10px 18px; border-radius: 10px; text-decoration: none; font-weight: 700; }
    </style>
</head>
<body>
    <?php require __DIR__ . '/sidebar.php'; ?>
    <div class="main-content-wrapper">
        <?php require __DIR__ . '/header.php'; ?>
        <main class="main-content">
            <section class="fc-migration-notice" role="status">
                <div class="fc-migration-icon"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i></div>
                <h1><?= htmlspecialchars($page['title'], ENT_QUOTES, 'UTF-8') ?></h1>
                <p><?= htmlspecialchars($page['description'], ENT_QUOTES, 'UTF-8') ?></p>
                <p><?= $is_fa
                    ? 'سامانه قدیمی متکی به Agent ویندوزی کنار گذاشته شده است. در این نسخه هیچ سفارش، پرداخت یا دستور سروری از این صفحه اجرا نمی‌شود.'
                    : 'The legacy Windows-Agent backend has been retired. This page does not create orders, process payments, or send server commands.' ?></p>
                <a href="/index.php"><?= $is_fa ? 'بازگشت به سایت' : 'Back to website' ?></a>
            </section>
        </main>
    </div>
</body>
</html>

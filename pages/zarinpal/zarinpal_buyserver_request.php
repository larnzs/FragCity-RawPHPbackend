<?php
declare(strict_types=1);

/**
 * Retired server-purchase payment route.
 *
 * This endpoint must not start or verify a payment because the old purchase
 * lifecycle depended on retired Windows-Agent tables. Wallet top-up routes
 * are separate and remain unchanged.
 */
http_response_code(410);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>خرید سرور غیرفعال | FragCity</title>
    <style>
        body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0d0d0d;color:#eee;font-family:Tahoma,Arial,sans-serif;padding:24px;box-sizing:border-box}
        main{max-width:620px;padding:32px;border:1px solid rgba(245,176,66,.25);border-radius:20px;background:#171717;line-height:2}
        h1{color:#F5B042;font-size:1.5rem}
        p{color:#ccc}
        a{display:inline-block;margin-top:10px;background:#F5B042;color:#111;padding:10px 18px;border-radius:10px;text-decoration:none;font-weight:700}
    </style>
</head>
<body>
<main>
    <h1>فرآیند خرید سرور موقتاً غیرفعال است</h1>
    <p>مسیر پرداخت قدیمی به جدول‌های حذف‌شدهٔ Agent ویندوزی وابسته بود. تا تکمیل اتصال جدید Pterodactyl و فرآیند مالی، پرداخت سرور آغاز یا تأیید نمی‌شود.</p>
    <p>هیچ تغییری در کیف پول یا تراکنش‌های سایت توسط این endpoint انجام نمی‌شود.</p>
    <a href="/index.php">بازگشت به سایت</a>
</main>
</body>
</html>

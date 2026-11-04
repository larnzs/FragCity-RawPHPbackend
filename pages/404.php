<?php
require_once __DIR__ . '/../includes/routes.php';
?>
<section class="fc-not-found" dir="rtl" aria-labelledby="fcNotFoundTitle" style="min-height:52vh;display:grid;place-items:center;padding:80px 20px;color:#f5f5f5;text-align:center">
    <div style="width:min(100%,620px)">
        <span style="display:inline-block;margin-bottom:12px;color:#F97316;font:800 .75rem/1.5 sans-serif;letter-spacing:.14em">FRAGCITY / 404</span>
        <h1 id="fcNotFoundTitle" style="margin:0 0 12px;font-size:clamp(2rem,5vw,3rem);font-weight:900">این صفحه پیدا نشد</h1>
        <p style="margin:0 auto 24px;max-width:500px;color:#a3a3a3;line-height:2">آدرسی که باز کرده‌ای معتبر نیست یا این صفحه جابه‌جا شده است. از یکی از مسیرهای زیر ادامه بده.</p>
        <div style="display:flex;flex-wrap:wrap;justify-content:center;gap:10px">
            <a href="<?= htmlspecialchars(fragcity_page_url('home'), ENT_QUOTES, 'UTF-8') ?>" style="padding:11px 18px;border-radius:9px;background:#F97316;color:#171717;font-weight:800;text-decoration:none">صفحه اصلی</a>
            <a href="<?= htmlspecialchars(fragcity_page_url('serverguide'), ENT_QUOTES, 'UTF-8') ?>" style="padding:11px 18px;border:1px solid #444;border-radius:9px;color:#eee;text-decoration:none">راهنمای سرور</a>
            <a href="<?= htmlspecialchars(fragcity_page_url('servers_vps'), ENT_QUOTES, 'UTF-8') ?>" style="padding:11px 18px;border:1px solid #444;border-radius:9px;color:#eee;text-decoration:none">لیست سرورها</a>
        </div>
    </div>
</section>

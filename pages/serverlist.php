<?php
/**
 * The old public list queried a server-inventory table belonging to the
 * retired Windows-Agent backend. No dynamic server catalog exists in the
 * current website database. Do not query arbitrary IPs/ports without a
 * server inventory supplied by the future Pterodactyl integration.
 */
$isAjaxRequest = isset($_GET['ajax']) && $_GET['ajax'] === '1';

if ($isAjaxRequest) {
    http_response_code(410);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode([
        'error' => 'server_catalog_unavailable',
        'message' => 'The legacy server catalog has been retired.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!defined('FRAGCITY_INDEX_RENDER')) {
    header('Location: /index.php?page=servers_vps', true, 303);
    exit;
}
?>
<div class="serverlist-page" dir="rtl">
    <div class="sl-page-head">
        <div class="sl-page-title">
            <div class="sl-title-icon"><i class="fas fa-server" aria-hidden="true"></i></div>
            <div>
                <span class="sl-eyebrow">COUNTER-STRIKE 2</span>
                <h1>سرورهای پابلیک</h1>
                <p>فهرست سرورها پس از اتصال کاتالوگ به زیرساخت جدید فعال می‌شود.</p>
            </div>
        </div>
    </div>
    <div class="sl-empty-state" role="status" style="margin-top:24px;padding:24px;border:1px solid rgba(245,176,66,.2);border-radius:16px">
        <h2>در حال آماده‌سازی</h2>
        <p>فهرست قبلی از جدول قدیمی سرورها تغذیه می‌شد و دیگر استفاده نمی‌شود. تا زمان اتصال امن به Pterodactyl، این صفحه هیچ سروری را از دیتابیس نمی‌خواند و هیچ IP/port دلخواهی را query نمی‌کند.</p>
    </div>
</div>

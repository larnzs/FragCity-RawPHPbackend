<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (($_SESSION['user_logged_in'] ?? false) !== true
    || (string)($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /index.php', true, 303);
    exit;
}

if (isset($_GET['lang']) && in_array($_GET['lang'], ['fa', 'en'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
}
$lang = $_SESSION['lang'] ?? 'fa';
if (!in_array($lang, ['fa', 'en'], true)) {
    $lang = 'fa';
}
$is_fa = $lang === 'fa';
$current_page = 'monitoring';
$page_title = $is_fa ? 'مانیتورینگ سرورها' : 'Server Monitoring';

?><!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>" dir="<?= $is_fa ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?> | FragCity</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/panel.css">
    <link rel="stylesheet" href="css/panel-motion.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/monitoring.css">
</head>
<body>
    <?php require __DIR__ . '/sidebar.php'; ?>
    <div class="main-content-wrapper">
        <?php require __DIR__ . '/header.php'; ?>
        <main class="main-content">
            <section class="fc-monitoring-page" id="fcPterodactylMonitoring" data-endpoint="/api/admin-pterodactyl-monitoring.php">
                <header class="fc-monitoring-heading">
                    <div>
                        <span class="fc-monitoring-eyebrow">FRAGCITY INFRASTRUCTURE</span>
                        <h1><?= $is_fa ? 'مانیتورینگ زیرساخت' : 'Infrastructure Monitoring' ?></h1>
                        <p><?= $is_fa
                            ? 'وضعیت Nodeهای Pterodactyl و مصرف منابع سرورهای CS2 را به‌صورت زنده بررسی کن.'
                            : 'Live Pterodactyl node health and CS2 server resource utilization.' ?></p>
                    </div>
                    <div class="fc-monitoring-actions">
                        <span class="fc-monitoring-live"><i></i><?= $is_fa ? 'به‌روزرسانی خودکار' : 'AUTO REFRESH' ?></span>
                        <button type="button" class="fc-monitoring-refresh" id="fcMonitoringRefresh">
                            <i class="fa-solid fa-rotate"></i>
                            <?= $is_fa ? 'بروزرسانی' : 'Refresh' ?>
                        </button>
                    </div>
                </header>

                <div class="fc-monitoring-toolbar">
                    <div class="fc-monitoring-connection" id="fcMonitoringConnection" role="status" aria-live="polite">
                        <span class="fc-monitoring-connection-dot"></span>
                        <span id="fcMonitoringConnectionText"><?= $is_fa ? 'در حال اتصال به Monitoring API...' : 'Connecting to Monitoring API...' ?></span>
                    </div>
                    <span class="fc-monitoring-updated" id="fcMonitoringUpdated">—</span>
                </div>

                <div class="fc-monitoring-summary" id="fcMonitoringSummary">
                    <article class="fc-monitor-summary-card">
                        <span class="fc-monitor-summary-icon orange"><i class="fa-solid fa-server"></i></span>
                        <div><small><?= $is_fa ? 'Node آنلاین' : 'ONLINE NODES' ?></small><strong id="fcSummaryNodes">—</strong></div>
                    </article>
                    <article class="fc-monitor-summary-card">
                        <span class="fc-monitor-summary-icon green"><i class="fa-solid fa-gamepad"></i></span>
                        <div><small><?= $is_fa ? 'سرورهای در حال اجرا' : 'RUNNING SERVERS' ?></small><strong id="fcSummaryServers">—</strong></div>
                    </article>
                    <article class="fc-monitor-summary-card">
                        <span class="fc-monitor-summary-icon blue"><i class="fa-solid fa-microchip"></i></span>
                        <div><small><?= $is_fa ? 'CPU مصرفی CS2' : 'CS2 CPU USAGE' ?></small><strong id="fcSummaryCpu">—</strong></div>
                    </article>
                    <article class="fc-monitor-summary-card">
                        <span class="fc-monitor-summary-icon violet"><i class="fa-solid fa-memory"></i></span>
                        <div><small><?= $is_fa ? 'RAM مصرفی CS2' : 'CS2 MEMORY' ?></small><strong id="fcSummaryMemory">—</strong></div>
                    </article>
                    <article class="fc-monitor-summary-card">
                        <span class="fc-monitor-summary-icon warm"><i class="fa-solid fa-hard-drive"></i></span>
                        <div><small><?= $is_fa ? 'فضای دیسک CS2' : 'CS2 DISK USAGE' ?></small><strong id="fcSummaryDisk">—</strong></div>
                    </article>
                </div>

                <section class="fc-monitor-section">
                    <div class="fc-monitor-section-heading">
                        <div>
                            <span class="fc-monitor-section-kicker">NODE HEALTH</span>
                            <h2><?= $is_fa ? 'وضعیت Nodeها' : 'Pterodactyl Nodes' ?></h2>
                        </div>
                        <span class="fc-monitor-section-count" id="fcNodeCount">—</span>
                    </div>
                    <div class="fc-monitor-node-grid" id="fcMonitoringNodes">
                        <div class="fc-monitor-loading">
                            <i class="fa-solid fa-circle-notch fa-spin"></i>
                            <?= $is_fa ? 'در حال دریافت اطلاعات Nodeها...' : 'Loading node metrics...' ?>
                        </div>
                    </div>
                </section>

                <section class="fc-monitor-section fc-monitor-servers-section">
                    <div class="fc-monitor-section-heading">
                        <div>
                            <span class="fc-monitor-section-kicker">CONTAINER RESOURCES</span>
                            <h2><?= $is_fa ? 'مصرف منابع سرورها' : 'CS2 Server Resources' ?></h2>
                        </div>
                        <span class="fc-monitor-section-count" id="fcServerCount">—</span>
                    </div>
                    <div class="fc-monitor-table-wrap">
                        <table class="fc-monitor-table">
                            <thead>
                                <tr>
                                    <th><?= $is_fa ? 'سرور' : 'SERVER' ?></th>
                                    <th>NODE</th>
                                    <th><?= $is_fa ? 'وضعیت' : 'STATE' ?></th>
                                    <th>CPU</th>
                                    <th>RAM</th>
                                    <th>DISK</th>
                                    <th>NETWORK RX / TX</th>
                                    <th><?= $is_fa ? 'مدت اجرا' : 'UPTIME' ?></th>
                                </tr>
                            </thead>
                            <tbody id="fcMonitoringServers">
                                <tr><td colspan="8" class="fc-monitor-empty"><?= $is_fa ? 'در انتظار داده‌های مانیتورینگ...' : 'Waiting for monitoring data...' ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <footer class="fc-monitoring-footnote">
                    <i class="fa-solid fa-circle-info"></i>
                    <?= $is_fa
                        ? 'اطلاعات Node از Wings API و مصرف کانتینرها از آمار لحظه‌ای Docker در Wings دریافت می‌شود. CPU نمایش‌داده‌شده در کارت Node مجموع مصرف سرورهای CS2 مجاز است، نه کل مصرف سیستم‌عامل.'
                        : 'Node information comes from Wings. Container metrics are live Docker statistics. Node CPU is the sum for allowlisted CS2 containers, not total host CPU usage.' ?>
                </footer>

                <div class="fc-monitoring-error" id="fcMonitoringError" hidden role="alert"></div>
            </section>
        </main>
    </div>
    <script src="js/monitoring.js" defer></script>
</body>
</html>

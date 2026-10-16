<?php
require_once __DIR__ . '/../settings/loader.php';
// pages/buyserveropen.php

$thresholdTime = date('Y-m-d H:i:s', time() - 60);

// AJAX endpoint for live node status refresh.
if (isset($_GET['ajax_check_nodes']) && $_GET['ajax_check_nodes'] === '1') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $ajaxSettings=fragcity_settings('files.pages.buyserveropen',array());
        $ajaxProfile=isset($ajaxSettings['database_profile'])?$ajaxSettings['database_profile']:'main';
        $pdoAjax=fragcity_create_pdo($ajaxProfile);
        if(!$pdoAjax)throw new PDOException('Database unavailable or not configured.');
        $pdoAjax->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $nodeStmt = $pdoAjax->prepare(
            "SELECT node_name, ip_address, cpu_usage, ram_usage, is_ready, last_heartbeat
             FROM agent_nodes
             WHERE is_ready = 1
             AND last_heartbeat >= ?"
        );
        $nodeStmt->execute([$thresholdTime]);

        $nodesData = $nodeStmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(
            ['status' => 'success', 'nodes' => $nodesData],
            JSON_UNESCAPED_UNICODE
        );
    } catch (Exception $e) {
        echo json_encode(
            [
                'status' => 'error',
                'message' => $e->getMessage(),
            ],
            JSON_UNESCAPED_UNICODE
        );
    }

    exit;
}

$pageTitle = 'خرید سرور اختصاصی CS2';
$baseSlots = 10;
$basePricePerHour = 11000;
$pricePerExtraSlotHourly = 1200;
$maxSlots = 24;

$tickrateOptions = [
    '64' => [
        'label' => '64 Tick (MM)',
        'extra_price' => 0,
    ],
    '128' => [
        'label' => '128 Tick (Faceit/ESEA)',
        'extra_price' => 3000,
    ],
];

$mapOptions = [
    'de_mirage' => 'Mirage (de_mirage)',
    'de_dust2' => 'Dust II (de_dust2)',
    'de_inferno' => 'Inferno (de_inferno)',
    'de_nuke' => 'Nuke (de_nuke)',
    'de_vertigo' => 'Vertigo (de_vertigo)',
    'de_ancient' => 'Ancient (de_ancient)',
    'de_anubis' => 'Anubis (de_anubis)',
];

$isLoggedIn = isset($_SESSION['user_logged_in']) && !empty($_SESSION['steamid']);
$sessionSteamId = $_SESSION['steamid'] ?? '';
$successMessage = '';
$errorMessage = '';

$userName = '';
$userPhone = '';
$userWallet = 0;
$userEmail = '';
$pdo = null;

$selectedSlots = $baseSlots;
$selectedTickrate = '64';
$selectedHours = 24;
$selectedMap = 'de_mirage';
$selectedNode = '';
$selectedPlanType = 'hourly';
$gsltToken = '';
$serverName = '';
$lanMode = 0;

try {
    $buySettings=fragcity_settings('files.pages.buyserveropen',array());
    $buyProfile=isset($buySettings['database_profile'])?$buySettings['database_profile']:'main';
    $pdo=fragcity_create_pdo($buyProfile);
    if(!$pdo)throw new PDOException('Database unavailable or not configured.');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMessage = '❌ خطا در اتصال به دیتابیس.';
}

if ($isLoggedIn && $pdo) {
    try {
        $userStmt = $pdo->prepare(
            "SELECT u.username, u.phone, u.email, COALESCE(w.balance, 0) AS wallet
             FROM users u
             LEFT JOIN user_wallets w ON u.steamid64 = w.steamid
             WHERE u.steamid64 = ?
             LIMIT 1"
        );
        $userStmt->execute([$sessionSteamId]);
        $userData = $userStmt->fetch();

        if ($userData) {
            $userName = $userData['username'] ?? '';
            $userPhone = $userData['phone'] ?? '';
            $userEmail = $userData['email'] ?? '';
            $userWallet = (float)($userData['wallet'] ?? 0);
        } else {
            $walletStmt = $pdo->prepare(
                'SELECT balance FROM user_wallets WHERE steamid = ? LIMIT 1'
            );
            $walletStmt->execute([$sessionSteamId]);
            $walletData = $walletStmt->fetch();

            if ($walletData) {
                $userWallet = (float)($walletData['balance'] ?? 0);
            }
        }
    } catch (Exception $e) {}
}

$nodes = [];
if ($pdo) {
    try {
        $nodeStmt = $pdo->prepare(
            "SELECT node_name, ip_address, cpu_usage, ram_usage, is_ready, last_heartbeat
             FROM agent_nodes
             WHERE is_ready = 1
             AND last_heartbeat >= ?"
        );
        $nodeStmt->execute([$thresholdTime]);
        $nodes = $nodeStmt->fetchAll();
    } catch (Exception $e) {
        $nodes = [];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedSlots = (int)($_POST['slots'] ?? $baseSlots);
    $selectedTickrate = $_POST['tickrate'] ?? $selectedTickrate;
    $selectedHours = max(1, min(72, (int)($_POST['duration_hours'] ?? 24)));
    $selectedMap = $_POST['map'] ?? $selectedMap;
    $selectedNode = trim($_POST['node_name'] ?? '');
    $selectedPlanType = $_POST['plan_type'] ?? 'hourly';

    $serverName = trim($_POST['hostname'] ?? 'FragCity CS2 Server');
    $serverPassword = !empty($_POST['server_password']) ? $_POST['server_password'] : null;
    $rconPassword = !empty($_POST['rcon_password'])
        ? $_POST['rcon_password']
        : substr(md5(uniqid((string)mt_rand(), true)), 0, 8);
    $gsltToken = trim($_POST['steam_account'] ?? '');
    $lanMode = (int)($_POST['lan'] ?? 0);

    $nodeIsValid = false;
    $nodeIp = null;

    if (!empty($selectedNode) && $pdo) {
        $checkNodeStmt = $pdo->prepare(
            "SELECT ip_address, is_ready, ram_usage
             FROM agent_nodes
             WHERE node_name = ?
             AND is_ready = 1
             AND last_heartbeat >= ?
             LIMIT 1"
        );
        $checkNodeStmt->execute([$selectedNode, $thresholdTime]);
        $nodeInfo = $checkNodeStmt->fetch();

        if ($nodeInfo) {
            $nodeIp = $nodeInfo['ip_address'];
            $currentRam = (float)($nodeInfo['ram_usage'] ?? 0);
            if ($currentRam <= 80) {
                $nodeIsValid = true;
            }
        }
    }

    if (!$isLoggedIn) {
        $errorMessage = '❌ برای خرید سرور باید حتماً از طریق استیم وارد حساب خود شوید.';
    } elseif ($selectedPlanType !== 'hourly') {
        $errorMessage = '❌ این نوع پلن در حال حاضر فعال نیست.';
    } elseif (empty($selectedNode)) {
        $errorMessage = '❌ لطفا یک لوکیشن و نود پردازشی را انتخاب کنید.';
    } elseif (!$nodeIsValid) {
        $errorMessage = '❌ نود انتخابی شما یا آفلاین است، یا مصرف رم آن بیش از ۸۰٪ بوده و قابل انتخاب نیست.';
    } elseif (empty($userPhone)) {
        $errorMessage = '❌ شماره تماس شما در پروفایل ثبت نشده است. لطفا ابتدا آن را تکمیل کنید.';
    } elseif (empty($gsltToken)) {
        $errorMessage = '❌ وارد کردن توکن GSLT (STEAM_ACCOUNT) الزامی است.';
    } elseif (!preg_match('/^[A-Fa-f0-9]{32}$/', $gsltToken)) {
        $errorMessage = '❌ توکن GSLT وارد شده نامعتبر است. توکن GSLT باید دقیقاً یک رشته ۳۲ کاراکتری هگزادسیمال معتبر باشد.';
    } else {
        $selectedSlots = max($baseSlots, min($maxSlots, $selectedSlots));
        $tickrateExtraHour = $tickrateOptions[$selectedTickrate]['extra_price'] ?? 0;

        $hourlyRate =
            $basePricePerHour
            + max(0, $selectedSlots - $baseSlots) * $pricePerExtraSlotHourly
            + $tickrateExtraHour;

        $totalPrice = $hourlyRate * $selectedHours;

        if ($userWallet < $totalPrice) {
            $errorMessage = '❌ موجودی کیف پول شما برای پرداخت این مبلغ کافی نیست. لطفا ابتدا کیف پول خود را شارژ کنید.';
        } else {
            $paymentToken = 'WALLET_' . strtoupper(substr(md5(uniqid('', true)), 0, 12));
            $status = 'Pending';

            $timeLeft = $selectedHours * 3600;

            try {
                $pdo->beginTransaction();

                // 1. انتخاب پورت آزاد (inactive) برای نود مورد نظر
                $portStmt = $pdo->prepare(
                    "SELECT id, port
                     FROM server_ports
                     WHERE node_name = ?
                     AND status = 'inactive'
                     LIMIT 1
                     FOR UPDATE"
                );
                $portStmt->execute([$selectedNode]);
                $portRow = $portStmt->fetch();

                if (!$portRow) {
                    throw new Exception(
                        "هیچ پورت آزادی روی نود انتخابی ($selectedNode) یافت نشد."
                    );
                }

                $portId = (int)$portRow['id'];
                $allocatedPort = (int)$portRow['port'];

                // 2. تغییر وضعیت پورت به used
                $updatePort = $pdo->prepare(
                    "UPDATE server_ports SET status = 'used' WHERE id = ?"
                );
                $updatePort->execute([$portId]);

                // 3. ثبت سرور در جدول pending_servers
                $stmt = $pdo->prepare(
                    "INSERT INTO pending_servers
                    (steam_id, node_name, port, server_name, slots, modes, tickrate, rcon_password, server_password, gslt_token, insecure, paid_price, payment_token, time_left, expire_date, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR), ?)"
                );

                $stmt->execute([
                    $sessionSteamId,
                    $selectedNode,
                    $allocatedPort,
                    $serverName,
                    $selectedSlots,
                    $selectedMap,
                    (int)$selectedTickrate,
                    $rconPassword,
                    $serverPassword,
                    $gsltToken,
                    $lanMode,
                    $totalPrice,
                    $paymentToken,
                    $timeLeft,
                    $selectedHours,
                    $status,
                ]);

                $insertedServerId = $pdo->lastInsertId();
                $billingType = 'hourly';
                $remainingMinutes = $selectedHours * 60;

                // 4. ثبت سرور در جدول registered_servers
                $regStmt = $pdo->prepare(
                    "INSERT INTO registered_servers 
                    (server_id, steam_id, node_name, server_name, server_ip, port, billing_type, remaining_minutes, expire_date, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR), 'Stopped')"
                );

                $regStmt->execute([
                    $insertedServerId,
                    $sessionSteamId,
                    $selectedNode,
                    $serverName,
                    $nodeIp,
                    $allocatedPort,
                    $billingType,
                    $remainingMinutes,
                    $selectedHours
                ]);

                // 5. کسر مبلغ از کیف پول کاربر
                $updateWallet = $pdo->prepare(
                    "UPDATE user_wallets
                     SET balance = balance - ?
                     WHERE steamid = ?"
                );
                $updateWallet->execute([$totalPrice, $sessionSteamId]);

                $pdo->commit();

                $safeNodeName = htmlspecialchars(
                    $selectedNode,
                    ENT_QUOTES,
                    'UTF-8'
                );

                $successMessage =
                    "✅ سرور شما روی نود <b>{$safeNodeName}</b> با موفقیت ثبت شد و پورت {$allocatedPort} به آن اختصاص یافت.";

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $errorMessage =
                    '❌ خطا در پردازش سفارش: ' . $e->getMessage();
            }
        }
    }
}
?>

<form
    method="post"
    id="buyServerForm"
    class="buy-server-page"
    data-base-slots="<?= (int)$baseSlots ?>"
    data-base-price-per-hour="<?= (int)$basePricePerHour ?>"
    data-price-per-extra-slot-hourly="<?= (int)$pricePerExtraSlotHourly ?>"
    data-tickrate-prices="<?= htmlspecialchars(json_encode(array_map(static fn($v) => (int)$v['extra_price'], $tickrateOptions), JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
    data-tickrate-labels="<?= htmlspecialchars(json_encode(array_map(static fn($v) => $v['label'], $tickrateOptions), JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
>

    <div class="buy-wrapper">

        <!-- Main configuration column -->
        <main class="buy-main-column">

            <header class="buy-page-heading">
                <div class="buy-page-icon">
                    <i class="fas fa-server"></i>
                </div>
                <div>
                    <h1>خرید سرور اختصاصی CS2</h1>
                    <p>لوکیشن، پلن، مدت زمان و تنظیمات سرور را با چند کلیک پیکربندی کنید.</p>
                </div>
            </header>

            <!-- Game selection -->
            <section class="section-box">
                <div class="section-heading">
                    <div class="section-heading-icon">
                        <i class="fas fa-gamepad"></i>
                    </div>
                    <div>
                        <h2>انتخاب بازی</h2>
                        <p>در حال حاضر فقط Counter-Strike 2 در دسترس است.</p>
                    </div>
                </div>

                <div class="game-grid">

                    <label class="game-card active">
                        <div class="game-card-main">
                            <img src="assets/images/logo/cs2.png" alt="Counter Strike 2" class="game-img">
                            <div>
                                <strong>Counter Strike 2</strong>
                                <span class="game-status available">فعال و آماده خرید</span>
                            </div>
                        </div>
                        <input type="radio" name="selected_game" value="cs2" checked>
                    </label>

                    <div class="game-card disabled" title="سروری موجود نیست">
                        <div class="game-card-main">
                            <img src="assets/images/logo/cs1.6.png" alt="Counter Strike 1.6" class="game-img">
                            <div>
                                <strong>Counter Strike 1.6</strong>
                                <span class="game-status unavailable">سروری موجود نیست</span>
                            </div>
                        </div>
                    </div>

                    <div class="game-card disabled" title="سروری موجود نیست">
                        <div class="game-card-main">
                            <img src="assets/images/logo/csgo.png" alt="CS:GO" class="game-img">
                            <div>
                                <strong>CS:GO</strong>
                                <span class="game-status unavailable">سروری موجود نیست</span>
                            </div>
                        </div>
                    </div>

                    <div class="game-card disabled" title="سروری موجود نیست">
                        <div class="game-card-main">
                            <img src="assets/images/logo/css.png" alt="Counter Strike Source" class="game-img">
                            <div>
                                <strong>CS: Source</strong>
                                <span class="game-status unavailable">سروری موجود نیست</span>
                            </div>
                        </div>
                    </div>

                </div>
            </section>

            <!-- Rental plan -->
            <section class="section-box">
                <div class="section-heading">
                    <div class="section-heading-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div>
                        <h2>نوع پلن و دوره سرور</h2>
                        <p>در حال حاضر سرور ساعتی تنها گزینه فعال است.</p>
                    </div>
                </div>

                <div class="plan-grid">

                    <label class="plan-card active" id="hourlyPlanCard">
                        <div class="plan-card-top">
                            <input type="radio" name="plan_type" id="hourlyRadio" value="hourly" checked>
                            <strong>سرور ساعتی (پویا)</strong>
                        </div>
                        <p>شروع قیمت از ۱۱,۰۰۰ تومان؛ سرورهای قدرتمند بدون slowframe.</p>
                    </label>

                    <div class="plan-card disabled" title="سرور ماهانه در حال حاضر غیرفعال است">
                        <div class="plan-card-top">
                            <input type="radio" disabled>
                            <strong>سرور ماهانه (غیرفعال)</strong>
                        </div>
                        <p>پلن اقتصادی و طولانی‌مدت در حال حاضر در دسترس نیست.</p>
                    </div>

                </div>
            </section>

            <!-- Node selection -->
            <section class="section-box">
                <div class="section-heading">
                    <div class="section-heading-icon">
                        <i class="fas fa-globe"></i>
                    </div>
                    <div>
                        <h2>لوکیشن و نود پردازشی</h2>
                        <p>نودی را انتخاب کنید که آنلاین باشد و RAM آن بیشتر از ۸۰٪ نباشد.</p>
                    </div>
                </div>

                <div id="nodeListContainer">
                    <?php if (empty($nodes)): ?>
                        <div class="status-message error">
                            <i class="fas fa-triangle-exclamation"></i>
                            <span>هیچ نود فعالی با هارت‌بیت معتبر یافت نشد. لطفا ایجنت را روشن کنید.</span>
                        </div>
                    <?php else: ?>
                        <div class="node-list-scroll">
                            <?php foreach ($nodes as $node):
                                $nodeNameRaw = $node['node_name'] ?? 'Node';
                                $nName = htmlspecialchars($nodeNameRaw, ENT_QUOTES, 'UTF-8');
                                $nIp = htmlspecialchars($node['ip_address'] ?? '', ENT_QUOTES, 'UTF-8');
                                $nRam = isset($node['ram_usage']) ? round((float)$node['ram_usage'], 1) : 0;
                                $nCpuUsage = isset($node['cpu_usage']) ? round((float)$node['cpu_usage'], 1) : 0;
                                $isRamHigh = ($nRam > 80);
                                $isChecked = (!$isRamHigh && $selectedNode === $nodeNameRaw) ? 'checked' : '';
                                $cardClass = $isRamHigh
                                    ? 'node-card disabled'
                                    : (($selectedNode === $nodeNameRaw) ? 'node-card selected' : 'node-card');
                            ?>
                                <label
                                    class="<?= $cardClass ?>"
                                    data-node-card
                                    data-node-name="<?= $nName ?>"
                                >
                                    <div class="node-main">
                                        <input
                                            type="radio"
                                            name="node_name"
                                            value="<?= $nName ?>"
                                            <?= $isChecked ?>
                                            <?= $isRamHigh ? 'disabled' : 'required' ?>
                                        >
                                        <div class="node-copy">
                                            <div class="node-title-row">
                                                <strong><?= $nName ?></strong>
                                                <span class="node-ip">IP: <?= $nIp ?></span>
                                            </div>
                                            <div class="node-metrics">
                                                <span>
                                                    <i class="fas fa-microchip"></i>
                                                    CPU: <?= $nCpuUsage ?>%
                                                </span>
                                                <span class="metric-separator">|</span>
                                                <span class="<?= $isRamHigh ? 'metric-high' : '' ?>">
                                                    <i class="fas fa-memory"></i>
                                                    RAM: <?= $nRam ?>%
                                                    <?= $isRamHigh ? '(پر)' : '' ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="node-side">
                                        <?php if ($isRamHigh): ?>
                                            <div class="node-warning">
                                                <i class="fas fa-triangle-exclamation"></i>
                                                رم تکمیل
                                            </div>
                                        <?php else: ?>
                                            <div class="node-ping" data-ip="<?= $nIp ?>">
                                                <span class="ping-text">
                                                    <i class="fas fa-wifi"></i>
                                                    <span class="ping-val">-- ms</span>
                                                </span>
                                                <button
                                                    type="button"
                                                    class="ping-refresh-btn"
                                                    data-ping-refresh
                                                    title="بروزرسانی پینگ"
                                                >
                                                    <i class="fas fa-sync-alt"></i>
                                                </button>
                                            </div>
                                            <div class="node-online">
                                                <i class="fas fa-circle"></i>
                                                Online
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Duration -->
            <section class="section-box">
                <div class="range-heading">
                    <div class="section-heading-inline">
                        <i class="fas fa-clock"></i>
                        <span>مدت زمان اجاره (ساعت)</span>
                    </div>
                    <strong id="durationCount" class="range-value">
                        <?= htmlspecialchars((string)$selectedHours, ENT_QUOTES, 'UTF-8') ?> ساعت
                    </strong>
                </div>

                <input
                    type="range"
                    name="duration_hours"
                    id="durationSlider"
                    min="1"
                    max="72"
                    value="<?= htmlspecialchars((string)$selectedHours, ENT_QUOTES, 'UTF-8') ?>"
                    step="1"
                    class="buy-sidebar-range"
                >

                <div class="range-scale">
                    <span>۱ ساعت</span>
                    <span>۳۶ ساعت</span>
                    <span>۷۲ ساعت (حداکثر)</span>
                </div>
            </section>

            <!-- Slots & tickrate -->
            <section class="section-box">
                <div class="range-heading">
                    <div class="section-heading-inline">
                        <i class="fas fa-users"></i>
                        <span>MAXPLAYERS (تعداد اسلات)</span>
                    </div>
                    <strong id="slotCount" class="range-value">
                        <?= htmlspecialchars((string)$selectedSlots, ENT_QUOTES, 'UTF-8') ?> نفر
                    </strong>
                </div>

                <input
                    type="range"
                    name="slots"
                    id="slotSlider"
                    min="<?= $baseSlots ?>"
                    max="<?= $maxSlots ?>"
                    value="<?= htmlspecialchars((string)$selectedSlots, ENT_QUOTES, 'UTF-8') ?>"
                    step="1"
                    class="buy-sidebar-range"
                >

                <div class="sub-field">
                    <label for="tickrateSelect">
                        <i class="fas fa-bolt"></i>
                        TICKRATE (تیک‌ریت سرور)
                    </label>

                    <select name="tickrate" id="tickrateSelect" class="buy-sidebar-select">
                        <?php foreach ($tickrateOptions as $key => $item): ?>
                            <option
                                value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                                <?= $selectedTickrate === $key ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
                                <?= $item['extra_price'] > 0 ? ' + ' . number_format($item['extra_price']) . ' تومان / ساعت' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </section>

            <!-- Server config -->
            <section class="section-box">
                <div class="section-heading">
                    <div class="section-heading-icon">
                        <i class="fas fa-sliders-h"></i>
                    </div>
                    <div>
                        <h2>پارامترهای اختصاصی فایل تنظیمات</h2>
                        <p>تنظیمات اتصال، مپ و پارامترهای اصلی سرور را شخصی‌سازی کنید.</p>
                    </div>
                </div>

                <div class="field-grid two-columns">
                    <div class="field-group">
                        <label for="hostname">HOSTNAME (نام سرور)</label>
                        <input
                            type="text"
                            name="hostname"
                            id="hostname"
                            value="<?= htmlspecialchars($serverName, ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="FragCity #1 | Public Server"
                            class="buy-sidebar-input"
                        >
                    </div>

                    <div class="field-group">
                        <label for="rconPassword">RCON_PASSWORD (رمز مدیریت RCON)</label>
                        <input
                            type="text"
                            name="rcon_password"
                            id="rconPassword"
                            placeholder="خالی = تولید خودکار امن"
                            class="buy-sidebar-input"
                        >
                    </div>
                </div>

                <div class="field-grid two-columns">
                    <div class="field-group">
                        <label for="mapSelect">MAP (مپ پیش‌فرض)</label>
                        <select name="map" id="mapSelect" class="buy-sidebar-select">
                            <?php foreach ($mapOptions as $mKey => $mLabel): ?>
                                <option
                                    value="<?= htmlspecialchars($mKey, ENT_QUOTES, 'UTF-8') ?>"
                                    <?= $selectedMap === $mKey ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($mLabel, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field-group">
                        <label for="lanSelect">LAN (حالت سرور)</label>
                        <select name="lan" id="lanSelect" class="buy-sidebar-select">
                            <option value="0" <?= $lanMode === 0 ? 'selected' : '' ?>>0 = عمومی (Public)</option>
                            <option value="1" <?= $lanMode === 1 ? 'selected' : '' ?>>1 = خصوصی (LAN / Private)</option>
                        </select>
                    </div>
                </div>

                <!-- GSLT Token Full-width Field -->
                <div class="field-group" style="margin-bottom: 15px;">
                    <label for="steamAccount">STEAM_ACCOUNT (توکن GSLT) <span style="color: #ef4444;">*</span></label>
                    <input
                        type="text"
                        name="steam_account"
                        id="steamAccount"
                        value="<?= htmlspecialchars($gsltToken, ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="مثال: 05D4655927CEA09FCE1C777EC2779E4F"
                        class="buy-sidebar-input"
                        required
                    >
                    <small class="field-hint">
                        برای لیست شدن سرور در استیم، یک توکن GSLT دریافت و در این فیلد وارد کنید.
                        <a
                            href="https://steamcommunity.com/dev/managegameservers"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            مدیریت Game Servers
                        </a>
                    </small>
                </div>

                <!-- Red Warning Box with Exclamation Icon -->
                <div class="buyserveropen-red-warning-box">
                    <i class="fas fa-circle-exclamation"></i>
                    <div>
                        در صورت وارد نکردن GSLT یا باطل بودن کد شما موفق به اتصال به سرور نخواهید شد و سرور شما در لیست سرور های استیم قابل مشاهده نخواهد بود.<br>
                        درصورت وارد کردن کد اشتباه یا تکراری از طریق پنل مدیریت سرور اقدام به تغییر توکن GSLT کنید.
                    </div>
                </div>

                <!-- Warning Box -->
                <div class="buyserveropen-warning-box">
                    <i class="fas fa-triangle-exclamation"></i>
                    <span>در صورت وارد کردن توکن GSLT یکسان برای دو یا چند سرور همزمان, اتصال تنها فقط به سروری میسر خواهد بود که زودتر از سرور قبل اجرا شده باشد</span>
                </div>

                <div class="field-group">
                    <label for="serverPassword">SERVER_PASSWORD (رمز ورود بازیکنان)</label>
                    <input
                        type="text"
                        name="server_password"
                        id="serverPassword"
                        placeholder="خالی = بدون رمز"
                        class="buy-sidebar-input"
                    >
                </div>
            </section>

            <!-- User account -->
            <section class="section-box section-box-last">
                <div class="section-heading">
                    <div class="section-heading-icon">
                        <i class="fas fa-user-lock"></i>
                    </div>
                    <div>
                        <h2>اطلاعات حساب کاربری</h2>
                        <p>اطلاعات زیر از حساب فعلی شما خوانده می‌شود.</p>
                    </div>
                </div>

                <?php if (!$isLoggedIn): ?>
                    <div class="status-message error">
                        <i class="fas fa-triangle-exclamation"></i>
                        <span>شما به حساب خود متصل نیستید. برای خرید سرور لطفاً ابتدا از طریق دکمه ورود استیم لاگین کنید.</span>
                    </div>
                <?php else: ?>
                    <div class="field-grid two-columns">
                        <div class="field-group">
                            <label for="displayUsername">نام کاربری</label>
                            <input
                                type="text"
                                id="displayUsername"
                                value="<?= htmlspecialchars($userName ?: 'کاربر استیم', ENT_QUOTES, 'UTF-8') ?>"
                                disabled
                                class="buy-sidebar-input readonly-input"
                            >
                        </div>

                        <div class="field-group">
                            <label for="displayPhone">شماره تماس</label>
                            <input
                                type="text"
                                id="displayPhone"
                                value="<?= htmlspecialchars($userPhone ?: 'ثبت نشده', ENT_QUOTES, 'UTF-8') ?>"
                                disabled
                                class="buy-sidebar-input readonly-input"
                            >
                        </div>
                    </div>

                    <div class="field-grid two-columns account-bottom-grid">
                        <div class="field-group">
                            <label for="steamIdReadonly">شناسه استیم (steamid64)</label>
                            <input
                                type="text"
                                name="steam_id"
                                id="steamIdReadonly"
                                value="<?= htmlspecialchars($sessionSteamId, ENT_QUOTES, 'UTF-8') ?>"
                                readonly
                                class="buy-sidebar-input readonly-input steam-id-input"
                            >
                        </div>

                        <div class="field-group">
                            <label for="walletReadonly">موجودی کیف پول</label>
                            <input
                                type="text"
                                id="walletReadonly"
                                value="<?= number_format($userWallet) ?> تومان"
                                disabled
                                class="buy-sidebar-input readonly-input wallet-input"
                            >
                        </div>
                    </div>
                <?php endif; ?>
            </section>

        </main>

        <!-- Pricing sidebar -->
        <aside class="buy-card">

            <div class="buy-card-header">
                <div class="buy-card-icon">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <div>
                    <h2>فاکتور و پرداخت</h2>
                    <p>قبل از ثبت سفارش، جزئیات هزینه را بررسی کنید.</p>
                </div>
            </div>

            <div class="guarantee-box">
                <i class="fas fa-shield-halved"></i>
                <span>گارانتی اجرای ابری پایدار و زیرساخت مناسب گیمینگ</span>
            </div>

            <div class="price-card">
                <div id="priceDisplay" class="price-display">—</div>
                <div id="priceBreakdown" class="price-breakdown"></div>
            </div>

            <?php if ($errorMessage): ?>
                <div class="status-message error order-status">
                    <i class="fas fa-circle-exclamation"></i>
                    <span><?= $errorMessage ?></span>
                </div>
            <?php elseif ($successMessage): ?>
                <div class="status-message success order-status">
                    <i class="fas fa-circle-check"></i>
                    <span><?= $successMessage ?></span>
                </div>
                <div class="buy-success-redirect" data-redirect-url="index.php?page=adminprofile">
                    <i class="fas fa-arrow-rotate-left"></i>
                    در حال انتقال به پنل مدیریت سرور...
                </div>
            <?php elseif (!$isLoggedIn): ?>
                <button type="button" class="checkout-btn disabled" disabled>
                    <i class="fas fa-lock"></i>
                    ابتدا وارد شوید
                </button>
            <?php else: ?>
                <?php
                $tickrateExtraHourInit = $tickrateOptions[$selectedTickrate]['extra_price'] ?? 0;
                $initialHourlyRate = $basePricePerHour + max(0, $selectedSlots - $baseSlots) * $pricePerExtraSlotHourly + $tickrateExtraHourInit;
                $initialTotalPrice = $initialHourlyRate * $selectedHours;
                $isWalletInsufficient = ($userWallet < $initialTotalPrice);
                ?>
                <div id="checkoutButtonContainer">
                    <?php if ($isWalletInsufficient): ?>
                        <a href="https://fragcity.ir/?page=wallet" class="checkout-btn wallet-charge-redirect-btn" id="dynamicCheckoutBtn">
                            <i class="fas fa-wallet"></i>
                            افزایش موجودی کیف پول
                        </a>
                    <?php else: ?>
                        <button type="submit" class="checkout-btn" id="dynamicCheckoutBtn">
                            <i class="fas fa-credit-card"></i>
                            تأیید و پرداخت نهایی
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="buy-card-note">
                <i class="fas fa-circle-info"></i>
                <span>قیمت نهایی بر اساس اسلات، Tickrate و مدت زمان انتخابی محاسبه می‌شود.</span>
            </div>

        </aside>

    </div>

</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('buyServerForm');
    if (!form) return;

    const baseSlots = parseInt(form.dataset.baseSlots) || 10;
    const basePricePerHour = parseInt(form.dataset.basePricePerHour) || 11000;
    const pricePerExtraSlotHourly = parseInt(form.dataset.pricePerExtraSlotHourly) || 1200;
    const tickratePrices = JSON.parse(form.dataset.tickratePrices || '{}');
    
    const userWallet = <?= (float)$userWallet ?>;
    const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;

    const slotSlider = document.getElementById('slotSlider');
    const durationSlider = document.getElementById('durationSlider');
    const tickrateSelect = document.getElementById('tickrateSelect');
    const checkoutButtonContainer = document.getElementById('checkoutButtonContainer');

    function updatePricingAndButton() {
        if (!isLoggedIn || !checkoutButtonContainer) return;

        const slots = parseInt(slotSlider ? slotSlider.value : baseSlots) || baseSlots;
        const hours = parseInt(durationSlider ? durationSlider.value : 24) || 24;
        const tickrate = tickrateSelect ? tickrateSelect.value : '64';

        const tickrateExtra = tickratePrices[tickrate] || 0;
        const hourlyRate = basePricePerHour + Math.max(0, slots - baseSlots) * pricePerExtraSlotHourly + tickrateExtra;
        const totalPrice = hourlyRate * hours;

        // بررسی موجودی کیف پول در برابر مبلغ نهایی به صورت لحظه‌ای
        if (userWallet < totalPrice) {
            checkoutButtonContainer.innerHTML = `
                <a href="https://fragcity.ir/?page=wallet" class="checkout-btn wallet-charge-redirect-btn" id="dynamicCheckoutBtn">
                    <i class="fas fa-wallet"></i>
                    افزایش موجودی کیف پول
                </a>
            `;
        } else {
            checkoutButtonContainer.innerHTML = `
                <button type="submit" class="checkout-btn" id="dynamicCheckoutBtn">
                    <i class="fas fa-credit-card"></i>
                    تأیید و پرداخت نهایی
                </button>
            `;
        }
    }

    if (slotSlider) slotSlider.addEventListener('input', updatePricingAndButton);
    if (durationSlider) durationSlider.addEventListener('input', updatePricingAndButton);
    if (tickrateSelect) tickrateSelect.addEventListener('change', updatePricingAndButton);

    updatePricingAndButton();
});
</script>
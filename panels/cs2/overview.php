<?php
require_once __DIR__ . '/../../settings/loader.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_GET['lang']) && in_array($_GET['lang'], ['fa', 'en'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
}
$lang = $_SESSION['lang'] ?? 'fa';
$is_fa = ($lang === 'fa');

$translations = [
    'fa' => [
        'title' => 'نمای کلی | پنل مدیریت CS2',
        'nav_title' => 'داشبورد مدیریت سرور',
        'server_name' => 'سرور من CS2',
        'tag_cs' => 'کانتر استرایک',
        'tag_latest' => 'کانتر استرایک ۲',
        'tag_cs2' => 'CS2',
        'tag_players' => 'بازیکن',
        'start' => 'روشن کردن',
        'restart' => 'راه‌اندازی مجدد',
        'shutdown' => 'خاموش کردن',
        'tab_overview' => 'نمای کلی',
        'tab_console' => 'کنسول',
        'tab_files' => 'فایل‌ها',
        'tab_config' => 'پیکربندی',
        'server_card_title' => 'مشخصات سرور',
        'online' => 'آنلاین',
        'offline' => 'OFFLINE',
        'node_offline' => 'نود آفلاین (Server Offline)',
        'starting' => 'در حال راه‌اندازی',
        'lbl_name' => 'نام سرور',
        'lbl_players' => 'ظرفیت بازیکنان',
        'lbl_ip' => 'آدرس آی‌پی و پورت',
        'lbl_location' => 'موقعیت جغرافیایی',
        'lbl_tickrate' => 'تیک‌ریت',
        'lbl_modes' => 'حالت بازی',
        'lbl_rcon' => 'رمز RCON',
        'lbl_gslt' => 'توکن GSLT',
        'lbl_created' => 'تاریخ ساخت',
        'lbl_expire' => 'تاریخ انقضا / اعتبار',
        'lbl_billing' => 'نوع محاسبه تعرفه',
        'btn_edit' => 'ویرایش سرور',
        'btn_invite' => 'دعوت افراد',
        'active_instance' => 'نمونه فعال',
        'view_all' => 'مشاهده همه',
        'console' => 'کنسول',
        'full_log' => 'لاگ کامل سرور',
        'usage' => 'منابع مصرفی نود میزبان',
        'show_advanced' => 'نمایش پیشرفته',
        'server_not_selected' => 'لطفاً ابتدا از صفحه «سرورهای من» روی دکمه «برو به داشبورد» یک سرور را انتخاب کنید.',
        'back_to_servers' => 'بازگشت به لیست سرورها',
        'not_set' => 'تنظیم نشده',
        'high_usage_error' => 'به دلیل استفاده بالای منابع نود (سی‌پی‌یو بالای ۷۰٪ یا رم بالای ۹۰٪)، امکان روشن کردن سرور وجود ندارد.'
    ],
    'en' => [
        'title' => 'Overview | CS2 Admin Panel',
        'nav_title' => 'CS2 Admin Panel',
        'server_name' => 'My CS2 Server',
        'tag_cs' => 'Counter-Strike',
        'tag_latest' => 'Counter-Strike 2 Latest',
        'tag_cs2' => 'CS2',
        'tag_players' => 'players',
        'start' => 'Start',
        'restart' => 'Restart',
        'shutdown' => 'Shut Down',
        'tab_overview' => 'Overview',
        'tab_console' => 'Console',
        'tab_files' => 'Files',
        'tab_config' => 'Config',
        'server_card_title' => 'Server Details',
        'online' => 'ONLINE',
        'offline' => 'OFFLINE',
        'node_offline' => 'NODE OFFLINE',
        'starting' => 'Starting...',
        'lbl_name' => 'Server Name',
        'lbl_players' => 'Players',
        'lbl_ip' => 'IP & Port',
        'lbl_location' => 'Location',
        'lbl_tickrate' => 'Tickrate',
        'lbl_modes' => 'Game Mode',
        'lbl_rcon' => 'RCON Password',
        'lbl_gslt' => 'GSLT Token',
        'lbl_created' => 'Created At',
        'lbl_expire' => 'Expire Date / Credit',
        'lbl_billing' => 'Billing Type',
        'btn_edit' => 'Edit Server',
        'btn_invite' => 'Invite People',
        'active_instance' => 'Active Instance',
        'view_all' => 'View All',
        'console' => 'Console',
        'full_log' => 'Full Server Log',
        'usage' => 'Host Node Usage',
        'show_advanced' => 'Show Advanced',
        'server_not_selected' => 'Please select a server from the "My Servers" page using the "Go to dashboard" button first.',
        'back_to_servers' => 'Back to Servers List',
        'not_set' => 'Not Set',
        'high_usage_error' => 'Due to high node resource usage (CPU > 70% or RAM > 90%), server startup is restricted.'
    ]
];

$t = $translations[$lang];
$current_page = 'overview';
$page_title = $t['nav_title'];

$overviewSettings=fragcity_settings('files.panels.cs2.overview',array());
$overviewProfile=isset($overviewSettings['database_profile'])?$overviewSettings['database_profile']:'main';

$pdo = null;
$server = null;
$billingType = 'hourly';
$nodeUsage = ['cpu_usage' => 0.00, 'ram_usage' => 0.00];
$isNodeOffline = true;
$thresholdTime = date('Y-m-d H:i:s', time() - 60);

$sessionSteamId = $_SESSION['steamid'] ?? $_SESSION['steam_id'] ?? $_SESSION['user']['steamid'] ?? $_SESSION['user_steamid'] ?? '';
$serverId = $_GET['server_id'] ?? $_GET['id'] ?? '';
$errorMsg = '';

if (isset($_GET['error']) && $_GET['error'] === 'high_usage') {
    $errorMsg = $t['high_usage_error'];
}

$pdo=fragcity_create_pdo($overviewProfile);
if(!$pdo){$server=null;}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['server_action']) && !empty($sessionSteamId) && !empty($serverId)) {
    $action = $_POST['server_action'];
    $newStatus = '';
    $agentAction = '';

    if ($action === 'start' || $action === 'restart') {
        $srvCheckStmt = $pdo->prepare("SELECT node_name FROM pending_servers WHERE server_id = ? LIMIT 1");
        $srvCheckStmt->execute([$serverId]);
        $srvCheckData = $srvCheckStmt->fetch();

        if ($srvCheckData) {
            $checkNodeName = $srvCheckData['node_name'];
            $nodeStatStmt = $pdo->prepare("SELECT cpu_usage, ram_usage, is_ready, last_heartbeat FROM agent_nodes WHERE node_name = ? LIMIT 1");
            $nodeStatStmt->execute([$checkNodeName]);
            $nodeStatData = $nodeStatStmt->fetch();

            if ($nodeStatData) {
                $nodeLastHb = $nodeStatData['last_heartbeat'] ?? '';
                $nodeIsReady = intval($nodeStatData['is_ready'] ?? 0);

                if ($nodeIsReady !== 1 || empty($nodeLastHb) || $nodeLastHb < $thresholdTime) {
                    header("Location: overview.php?server_id=" . urlencode($serverId) . "&error=high_usage");
                    exit;
                }

                $currentCpu = floatval($nodeStatData['cpu_usage'] ?? 0);
                $currentRam = floatval($nodeStatData['ram_usage'] ?? 0);

                if ($currentCpu > 70.0 || $currentRam > 90.0) {
                    header("Location: overview.php?server_id=" . urlencode($serverId) . "&error=high_usage");
                    exit;
                }
            } else {
                header("Location: overview.php?server_id=" . urlencode($serverId) . "&error=high_usage");
                exit;
            }
        }

        $newStatus = 'Starting';
        $agentAction = 'start_server';
    } elseif ($action === 'stop') {
        $newStatus = 'Stopped';
        $agentAction = 'stop_server';
    }

    if (!empty($newStatus) && $pdo) {
        try {
            $srvStmt = $pdo->prepare("SELECT * FROM pending_servers WHERE server_id = ? AND steam_id = ? LIMIT 1");
            $srvStmt->execute([$serverId, $sessionSteamId]);
            $srvData = $srvStmt->fetch();

            if ($srvData) {
                $nodeName = $srvData['node_name'] ?? 'Node-Tehran-01';
                $serverPort = intval($srvData['port']);
                $steamId = $srvData['steam_id'];

                $updateRegStmt = $pdo->prepare("UPDATE registered_servers SET status = ? WHERE port = ? AND node_name = ?");
                $updateRegStmt->execute([$newStatus, $serverPort, $nodeName]);

                $cmdStmt = $pdo->prepare("INSERT INTO agent_commands (server_id, node_name, action, port, steam_id, status) VALUES (?, ?, ?, ?, ?, 'action_queued')");
                $cmdStmt->execute([intval($serverId), $nodeName, $agentAction, $serverPort, $steamId]);
            }

            header("Location: overview.php?server_id=" . urlencode($serverId));
            exit;
        } catch (Exception $e) {
        }
    }
}

if (!empty($sessionSteamId) && !empty($serverId) && $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT ps.*, rs.billing_type, rs.remaining_minutes, rs.status AS reg_status, rs.updated_at AS reg_updated_at FROM pending_servers ps LEFT JOIN registered_servers rs ON ps.port = rs.port AND ps.node_name = rs.node_name WHERE ps.server_id = ? AND ps.steam_id = ? LIMIT 1");
        $stmt->execute([$serverId, $sessionSteamId]);
        $server = $stmt->fetch();

        if ($server && isset($server['billing_type'])) {
            $billingType = $server['billing_type'];
        }

        if ($server && !empty($server['node_name'])) {
            $nodeStmt = $pdo->prepare("SELECT ip_address, cpu_usage, ram_usage, is_ready, last_heartbeat FROM agent_nodes WHERE node_name = ? LIMIT 1");
            $nodeStmt->execute([$server['node_name']]);
            $nodeData = $nodeStmt->fetch();

            if ($nodeData) {
                $lastHeartbeat = $nodeData['last_heartbeat'] ?? '';
                $isReady = intval($nodeData['is_ready'] ?? 0);

                if ($isReady === 1 && !empty($lastHeartbeat) && $lastHeartbeat >= $thresholdTime) {
                    $isNodeOffline = false;
                    $nodeUsage = [
                        'cpu_usage' => $nodeData['cpu_usage'] ?? 0,
                        'ram_usage' => $nodeData['ram_usage'] ?? 0
                    ];

                    $regStatusRaw = strtolower(trim($server['reg_status'] ?? ''));
                    $isServerRunning = ($regStatusRaw === 'running');

                    if ($isServerRunning && !$isNodeOffline) {
                        $sessionDeductKey = 'last_deduct_time_' . $serverId;
                        if (!isset($_SESSION[$sessionDeductKey]) || (time() - $_SESSION[$sessionDeductKey]) >= 60) {
                            if (isset($server['remaining_minutes']) && intval($server['remaining_minutes']) > 0) {
                                $deductStmt = $pdo->prepare("UPDATE registered_servers SET remaining_minutes = remaining_minutes - 1 WHERE port = ? AND node_name = ? AND remaining_minutes > 0");
                                $deductStmt->execute([$server['port'], $server['node_name']]);
                                $server['remaining_minutes'] = max(0, intval($server['remaining_minutes']) - 1);
                                $_SESSION[$sessionDeductKey] = time();
                            }
                        }
                    }

                    if (!empty($nodeData['ip_address'])) {
                        $nodeIp = $nodeData['ip_address'];
                        $updateIpStmt = $pdo->prepare("UPDATE pending_servers SET server_ip = ? WHERE server_id = ?");
                        $updateIpStmt->execute([$nodeIp, $serverId]);
                        $server['server_ip'] = $nodeIp;
                    }
                } else {
                    $isNodeOffline = true;
                }
            } else {
                $isNodeOffline = true;
            }

            // اگر نود خاموش/آفلاین است، وضعیت سرور را در registered_servers به stopped تغییر می‌دهیم تا شارژ کسر نشود
            if ($isNodeOffline) {
                $regStatusRaw = strtolower(trim($server['reg_status'] ?? ''));
                if ($regStatusRaw !== 'stopped') {
                    try {
                        $autoStopStmt = $pdo->prepare("UPDATE registered_servers SET status = 'stopped' WHERE port = ? AND node_name = ?");
                        $autoStopStmt->execute([$server['port'], $server['node_name']]);
                        $server['reg_status'] = 'stopped';
                    } catch (Exception $ex) {
                    }
                }
            }
        }

        // Sync active server for console actions inside overview.php
        if ($server) {
            $_SESSION['current_server_id'] = $serverId;
            $_SESSION['current_server_ip'] = $server['server_ip'] ?? '';
            $_SESSION['current_server_port'] = intval($server['port'] ?? 0);
        }
    } catch (Exception $e) {
        $server = null;
    }
}

/* =========================================================
   AJAX Handler for Console (Queue & Check)
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json; charset=utf-8');

    if (!$pdo) {
        echo json_encode(['status' => 'error', 'message' => 'Database connection error']);
        exit;
    }

    $activeServerId = $serverId ?: ($_SESSION['current_server_id'] ?? '');
    $activeIp = $_SESSION['current_server_ip'] ?? ($server['server_ip'] ?? '');
    $activePort = intval($_SESSION['current_server_port'] ?? ($server['port'] ?? 0));
    $action = $_POST['ajax_action'];

    if ($action === 'queue_command') {
        $userCommand = trim($_POST['user_command'] ?? '');
        if ($userCommand === '') {
            echo json_encode(['status' => 'error', 'message' => 'Command is empty']);
            exit;
        }

        if (empty($activeServerId) || empty($activeIp) || $activePort <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'No active server selected']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO rcon_command_logs (server_ip, server_port, steam_id, user_command, server_response, status, created_at)
                VALUES (?, ?, ?, ?, '', 'pending', NOW())
            ");
            $stmt->execute([$activeIp, $activePort, $sessionSteamId, $userCommand]);

            echo json_encode([
                'status' => 'success',
                'command_id' => $pdo->lastInsertId()
            ]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'check_command') {
        $commandId = (int)($_POST['command_id'] ?? 0);
        if ($commandId <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid command ID']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT server_response, status FROM rcon_command_logs WHERE id = ? LIMIT 1");
            $stmt->execute([$commandId]);
            $row = $stmt->fetch();

            if (!$row) {
                echo json_encode(['status' => 'error', 'message' => 'Command not found']);
                exit;
            }

            echo json_encode([
                'status' => $row['status'],
                'server_response' => $row['server_response']
            ]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang, ENT_QUOTES, 'UTF-8'); ?>" dir="<?php echo $is_fa ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($t['title'], ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/panel.css">
    <link rel="stylesheet" href="css/overview.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/panel-motion.css">
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content-wrapper">
    <?php include 'header.php'; ?>

    <div class="main-content overview-page">
        <?php if (!empty($errorMsg)): ?>
            <div class="overview-alert-error">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><?php echo htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!$server): ?>
            <div class="panel-card overview-server-empty">
                <div class="overview-empty-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><?php echo $t['server_not_selected']; ?></span>
                </div>
                <a href="servers.php" class="dashboard-btn overview-back-button">
                    <i class="fas fa-arrow-left"></i>
                    <?php echo $t['back_to_servers']; ?>
                </a>
            </div>
        <?php else: ?>
            <?php
            $serverName = htmlspecialchars($server['server_name'] ?? $t['server_name']);
            $serverIp = htmlspecialchars($server['server_ip'] ?? '0.0.0.0');
            $serverPort = htmlspecialchars($server['port'] ?? '27015');
            $fullIpAddress = $serverIp . ':' . $serverPort;
            $slots = htmlspecialchars($server['slots'] ?? '10');
            $tickrate = htmlspecialchars($server['tickrate'] ?? '128');
            $modes = htmlspecialchars($server['modes'] ?? 'Classic');
            $rconPassword = htmlspecialchars($server['rcon_password'] ?? $t['not_set']);
            $gsltToken = htmlspecialchars($server['gslt_token'] ?? $t['not_set']);
            $createdAt = htmlspecialchars($server['created_at'] ?? '-');
            $expireDate = htmlspecialchars($server['expire_date'] ?? '-');
            $billingType = htmlspecialchars($server['billing_type'] ?? 'hourly');
            $remainingMinutes = isset($server['remaining_minutes']) ? intval($server['remaining_minutes']) : 0;
            $nodeName = htmlspecialchars($server['node_name'] ?? '-');

            $cpuVal = floatval($nodeUsage['cpu_usage'] ?? 0);
            $ramVal = floatval($nodeUsage['ram_usage'] ?? 0);
            $isResourceOverloaded = (!$isNodeOffline && ($cpuVal > 70.0 || $ramVal > 90.0));

            $statusRaw = ucfirst(strtolower($server['reg_status'] ?? 'Stopped'));
            $regUpdatedAt = $server['reg_updated_at'] ?? '';

            if ($isNodeOffline) {
                $statusRaw = 'Stopped';
            } elseif ($statusRaw === 'Running' && !empty($regUpdatedAt)) {
                if ((time() - strtotime($regUpdatedAt)) > 60) {
                    $statusRaw = 'Stopped';
                    try {
                        $timeoutUpdate = $pdo->prepare("UPDATE registered_servers SET status = 'stopped' WHERE port = ? AND node_name = ?");
                        $timeoutUpdate->execute([$server['port'], $server['node_name']]);
                    } catch (Exception $ex) {
                    }
                }
            }

            $statusClass = 'online';
            $statusText = $t['online'];

            if ($isNodeOffline) {
                $statusClass = 'stopped';
                $statusText = $t['node_offline'];
            } else {
                if ($statusRaw === 'Running') {
                    $statusClass = 'online';
                    $statusText = $t['online'];
                } elseif (in_array($statusRaw, ['Pending', 'Starting'], true)) {
                    $statusClass = 'pending';
                    $statusText = ($statusRaw === 'Starting') ? $t['starting'] : 'Pending';
                } else {
                    $statusClass = 'stopped';
                    $statusText = $t['offline'];
                }
            }

            $usageCpuWidth = min(max($cpuVal, 0), 100);
            $usageRamWidth = min(max($ramVal, 0), 100);
            $usageCpuClass = $cpuVal > 70 ? 'overview-metric-danger' : '';
            $usageRamClass = $ramVal > 90 ? 'overview-metric-danger' : '';
            ?>

            <div class="server-overview-header">
                <div class="server-overview-title-area">
                    <h1 class="page-title overview-server-title"><?php echo $serverName; ?></h1>
                    <div class="server-tags">
                        <span class="tag"><i class="fa-solid fa-gamepad"></i> CS2</span>
                        <span class="tag"><i class="fa-solid fa-server"></i> Node: <?php echo $nodeName; ?></span>
                        <span class="tag"><i class="fa-solid fa-gauge-high"></i> <?php echo $tickrate; ?> Tick</span>
                        <span class="tag"><i class="fa-solid fa-list"></i> <?php echo $modes; ?></span>
                        <span class="tag"><i class="fa-solid fa-users"></i> <?php echo $slots; ?> <?php echo $t['tag_players']; ?></span>
                        <span class="tag"><i class="fa-solid fa-key"></i> RCON: <?php echo $rconPassword; ?></span>
                        <span class="tag"><i class="fa-solid fa-ticket"></i> GSLT: <?php echo (strlen($gsltToken) > 10 ? substr($gsltToken, 0, 8) . '...' : $gsltToken); ?></span>
                        <span class="tag"><i class="fa-solid fa-calendar-plus"></i> <?php echo $createdAt; ?></span>
                    </div>
                </div>

                <div class="server-overview-actions">
                    <form method="POST" class="overview-server-action-form">
                        <?php if ($isNodeOffline): ?>
                            <button type="button" class="btn-custom btn-custom-disabled" disabled title="<?php echo $is_fa ? 'نود یا سرور آفلاین است' : 'Node or Server is Offline'; ?>">
                                <i class="fa-solid fa-power-off"></i> <?php echo $is_fa ? 'نود آفلاین است' : 'Node Offline'; ?>
                            </button>
                        <?php elseif ($isResourceOverloaded): ?>
                            <button type="button" class="btn-custom btn-custom-disabled" disabled title="<?php echo htmlspecialchars($t['high_usage_error'], ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="fa-solid fa-triangle-exclamation"></i> <?php echo $is_fa ? 'مصرف منابع بالاست' : 'High Usage'; ?>
                            </button>
                        <?php elseif ($statusRaw === 'Stopped' || $statusRaw === 'Suspended' || $statusRaw === 'Unknown'): ?>
                            <button type="submit" name="server_action" value="start" class="btn-custom btn-custom-start">
                                <i class="fa-solid fa-play"></i> <?php echo $t['start']; ?>
                            </button>
                        <?php else: ?>
                            <button type="submit" name="server_action" value="restart" class="btn-custom" title="<?php echo $t['restart']; ?>">
                                <i class="fa-solid fa-rotate-right"></i> <?php echo $t['restart']; ?>
                            </button>
                            <button type="submit" name="server_action" value="stop" class="btn-custom btn-custom-stop" title="<?php echo $t['shutdown']; ?>">
                                <i class="fa-solid fa-power-off"></i> <?php echo $t['shutdown']; ?>
                            </button>
                        <?php endif; ?>
                    </form>
                    <button class="btn-icon-only" type="button" title="Megaphone"><i class="fa-solid fa-bullhorn"></i></button>
                    <button class="btn-icon-only" type="button" title="Notifications"><i class="fa-solid fa-bell"></i></button>
                </div>
            </div>

            <div class="tabs-nav server-sub-tabs">
                <a href="overview.php?server_id=<?php echo urlencode($serverId); ?>" class="tab-btn active"><?php echo $t['tab_overview']; ?></a>
                <a href="console.php?server_id=<?php echo urlencode($serverId); ?>" class="tab-btn"><?php echo $t['tab_console']; ?></a>
                <a href="files.php?server_id=<?php echo urlencode($serverId); ?>" class="tab-btn"><?php echo $t['tab_files']; ?></a>
                <a href="config.php?server_id=<?php echo urlencode($serverId); ?>" class="tab-btn"><?php echo $t['tab_config']; ?></a>
            </div>

            <div class="overview-grid">
                <div class="overview-col-left">
                    <div class="panel-card server-info-card">
                        <div class="card-section-title"><?php echo $t['server_card_title']; ?></div>
                        
                        <div class="server-status-box">
                            <div class="server-icon-title">
                                <div class="game-icon-badge overview-cs2-logo"><img src="https://fragcity.ir/assets/images/logo/cs2.png" alt="CS2"></div>
                                <div>
                                    <div class="game-name"><?php echo $t['tag_cs']; ?></div>
                                    <div class="status-badge <?php echo $statusClass; ?>"><span class="dot"></span><?php echo $statusText; ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="server-detail-row">
                            <span class="detail-label"><?php echo $t['lbl_name']; ?></span>
                            <span class="detail-value"><?php echo $serverName; ?></span>
                        </div>
                        <div class="server-detail-row">
                            <span class="detail-label"><?php echo $t['lbl_players']; ?></span>
                            <span class="detail-value"><?php echo $slots; ?></span>
                        </div>
                        <div class="server-detail-row">
                            <span class="detail-label"><?php echo $t['lbl_tickrate']; ?></span>
                            <span class="detail-value"><?php echo $tickrate; ?></span>
                        </div>
                        <div class="server-detail-row">
                            <span class="detail-label"><?php echo $t['lbl_ip']; ?></span>
                            <span class="detail-value ip-box">
                                <code><?php echo $fullIpAddress; ?></code>
                                <button type="button" class="copy-icon copy-ip-button" data-copy-text="<?php echo htmlspecialchars($fullIpAddress, ENT_QUOTES, 'UTF-8'); ?>" title="Copy IP" aria-label="Copy IP"><i class="fa-regular fa-copy"></i></button>
                            </span>
                        </div>
                        <div class="server-detail-row">
                            <span class="detail-label"><?php echo $t['lbl_rcon']; ?></span>
                            <span class="detail-value"><code><?php echo $rconPassword; ?></code></span>
                        </div>
                        <div class="server-detail-row">
                            <span class="detail-label"><?php echo $t['lbl_created']; ?></span>
                            <span class="detail-value"><?php echo $createdAt; ?></span>
                        </div>

                        <div class="server-detail-row" style="flex-direction: column; align-items: stretch; gap: 8px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                                <span class="detail-label"><?php echo $t['lbl_expire']; ?></span>
                                <span class="detail-value overview-expire-date" id="creditCountdownText">
                                    <?php 
                                    if ($billingType === 'hourly') {
                                        echo $remainingMinutes . ' ' . ($is_fa ? 'دقیقه باقی‌مانده' : 'mins remaining');
                                    } else {
                                        echo $expireDate;
                                    }
                                    ?>
                                </span>
                            </div>
                            <?php if ($billingType === 'hourly'): ?>
                                <div class="progress-bar-bg" style="height: 8px; border-radius: 4px; background: rgba(255,255,255,0.1); overflow: hidden; width: 100%;">
                                    <div id="creditProgressBar" class="progress-fill" style="width: 100%; height: 100%; transition: width 0.5s ease, background-color 0.5s ease; background-color: #10b981;"></div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="server-detail-row">
                            <span class="detail-label"><?php echo $t['lbl_billing']; ?></span>
                            <span class="detail-value"><span class="tag" style="margin: 0; padding: 2px 8px; font-size: 11px;"><?php echo ucfirst($billingType); ?></span></span>
                        </div>

                        <div class="server-card-buttons overview-card-actions">
                            <button type="button" class="btn-secondary" <?php echo ($isNodeOffline || $isResourceOverloaded) ? 'disabled' : ''; ?>><i class="fa-solid fa-pen-to-square"></i> <?php echo $t['btn_edit']; ?></button>
                            <button type="button" class="btn-secondary" <?php echo ($isNodeOffline || $isResourceOverloaded) ? 'disabled' : ''; ?>><i class="fa-solid fa-share-nodes"></i> <?php echo $t['btn_invite']; ?></button>
                        </div>
                    </div>

                    <div class="panel-card active-instance-card">
                        <div class="card-header-flex">
                            <span class="card-section-title overview-card-title-inline"><?php echo $t['active_instance']; ?></span>
                            <a href="#" class="link-blue"><?php echo $t['view_all']; ?></a>
                        </div>
                        <div class="instance-item">
                            <div class="game-icon-badge sm overview-cs2-logo"><img src="https://fragcity.ir/assets/images/logo/cs2.png" alt="CS2"></div>
                            <div>
                                <div class="instance-name">CS2</div>
                                <div class="instance-desc"><?php echo $isNodeOffline ? ($is_fa ? 'سرور آفلاین (Server Offline)' : 'Server Offline') : $t['tag_latest']; ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="overview-col-right">
                    <div class="panel-card console-card" id="overview-console-card">
                        <div class="card-header-flex">
                            <span class="card-section-title overview-card-title-inline"><?php echo $is_fa ? 'کنسول زنده' : 'Live Console'; ?></span>
                            <div class="console-header-actions">
                                <span class="status-badge <?php echo $isNodeOffline ? 'offline' : 'online'; ?>">
                                    <span class="dot"></span>
                                    <?php echo $isNodeOffline ? ($is_fa ? 'آفلاین' : 'Offline') : ($is_fa ? 'آنلاین' : 'Online'); ?>
                                </span>
                                <button type="button" class="btn-secondary console-clear-btn" id="overview-clear-console">
                                    <i class="fa-solid fa-trash-can"></i>
                                    <?php echo $is_fa ? 'پاک کردن' : 'Clear'; ?>
                                </button>
                            </div>
                        </div>
                        <div class="console-screen" id="overview-console-output" style="min-height: 220px;">
                            <?php if ($isNodeOffline): ?>
                                <div class="log-line overview-log-error">[Error] Server offline / Host node is not responding. No heartbeat received.</div>
                            <?php else: ?>
                                <div class="log-line console-muted"><?php echo $is_fa ? 'سرور برای کنسول آماده است.' : 'Server initialized successfully.'; ?></div>
                                <div class="log-line console-success"><?php echo $is_fa ? ('اتصال فعال برای ' . $fullIpAddress . '.') : ('RCON connected to ' . $fullIpAddress . '.'); ?></div>
                                <div class="log-line console-muted"><?php echo $is_fa ? 'دستور را وارد کرده و ارسال کنید.' : 'Enter a command and press Send.'; ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="console-input-wrapper" style="display:flex; gap:10px; align-items:center; margin-top:12px;">
                            <input type="text" id="overview-command-input" placeholder="<?php echo $is_fa ? 'وارد کردن دستور...' : 'Enter a command...'; ?>" autocomplete="off" <?php echo ($isNodeOffline || !$server) ? 'disabled' : ''; ?> style="flex:1;">
                            <button type="button" id="overview-send-btn" class="dashboard-btn console-send-btn" <?php echo ($isNodeOffline || !$server) ? 'disabled' : ''; ?>>
                                <i class="fa-solid fa-paper-plane"></i>
                                <?php echo $is_fa ? 'ارسال' : 'Send'; ?>
                            </button>
                        </div>
                    </div>

                    <div class="panel-card usage-card <?php echo ($isNodeOffline || $isResourceOverloaded) ? 'overview-usage-muted' : ''; ?>">
                        <div class="card-header-flex">
                            <span class="card-section-title overview-card-title-inline"><?php echo $t['usage']; ?> (<?php echo $nodeName; ?>)</span>
                            <?php if ($isNodeOffline || $isResourceOverloaded): ?>
                                <span class="overview-usage-status">
                                    <?php if ($isNodeOffline) { echo $is_fa ? '● نود آفلاین' : '● Node Offline'; } else { echo $is_fa ? '● مصرف منابع بالا (محدود شده)' : '● High Usage Restricted'; } ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if ($isNodeOffline): ?>
                            <div class="overview-usage-empty">
                                <i class="fa-solid fa-power-off"></i>
                                <span><?php echo $is_fa ? 'نود یا سرور آفلاین است و هیچ اطلاعاتی ارسال نمی‌کند.' : 'Node or server is offline. No data available.'; ?></span>
                            </div>
                        <?php else: ?>
                            <div class="usage-metric-item">
                                <div class="metric-info">
                                    <span class="metric-name"><span class="dot-indicator cpu"></span> CPU Usage</span>
                                    <span class="metric-val <?php echo $usageCpuClass; ?>"><?php echo number_format($cpuVal, 2); ?>%</span>
                                </div>
                                <div class="progress-bar-bg">
                                    <div class="progress-fill cpu-fill <?php echo $usageCpuClass; ?>" style="width: <?php echo $usageCpuWidth; ?>%;"></div>
                                </div>
                            </div>
                            <div class="usage-metric-item">
                                <div class="metric-info">
                                    <span class="metric-name"><span class="dot-indicator ram"></span> RAM Usage</span>
                                    <span class="metric-val <?php echo $usageRamClass; ?>"><?php echo number_format($ramVal, 2); ?>%</span>
                                </div>
                                <div class="progress-bar-bg">
                                    <div class="progress-fill ram-fill <?php echo $usageRamClass; ?>" style="width: <?php echo $usageRamWidth; ?>%;"></div>
                                </div>
                            </div>
                            <div class="usage-metric-item">
                                <div class="metric-info">
                                    <span class="metric-name"><span class="dot-indicator storage"></span> <?php echo $is_fa ? 'فضای ذخیره‌سازی' : 'Storage'; ?></span>
                                    <span class="metric-val">49 / ∞ GB</span>
                                </div>
                                <div class="progress-bar-bg">
                                    <div class="progress-fill storage-fill" style="width: 25%;"></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="js/overview.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const consoleOutput = document.getElementById('overview-console-output');
    const commandInput = document.getElementById('overview-command-input');
    const sendBtn = document.getElementById('overview-send-btn');
    const clearBtn = document.getElementById('overview-clear-console');

    if (!consoleOutput || !commandInput || !sendBtn || !clearBtn) {
        return;
    }

    function appendLog(text, className = '') {
        const div = document.createElement('div');
        div.className = 'log-line ' + className;
        div.textContent = text;
        consoleOutput.appendChild(div);
        consoleOutput.scrollTop = consoleOutput.scrollHeight;
    }

    function unlockInput() {
        if (commandInput.disabled === false && sendBtn.disabled === false) return;
        commandInput.disabled = false;
        sendBtn.disabled = false;
        commandInput.focus();
    }

    function pollCommandResult(commandId) {
        let attempts = 0;
        const maxAttempts = 20;

        const interval = setInterval(() => {
            attempts++;
            const formData = new FormData();
            formData.append('ajax_action', 'check_command');
            formData.append('command_id', commandId);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success' || data.status === 'error') {
                    clearInterval(interval);
                    if (data.server_response) {
                        appendLog(data.server_response, data.status === 'error' ? 'overview-log-error' : 'console-success');
                    } else {
                        appendLog('<?php echo $is_fa ? 'خروجی خاصی ثبت نشد.' : '[Command executed with no output]'; ?>', 'console-muted');
                    }
                    unlockInput();
                } else if (attempts >= maxAttempts) {
                    clearInterval(interval);
                    appendLog('<?php echo $is_fa ? 'مهلت دریافت پاسخ تمام شد.' : '[Timeout] Server response took too long.'; ?>', 'overview-log-error');
                    unlockInput();
                }
            })
            .catch(() => {
                if (attempts >= maxAttempts) {
                    clearInterval(interval);
                    appendLog('<?php echo $is_fa ? 'خطا در دریافت پاسخ.' : '[Error] Failed to check response.'; ?>', 'overview-log-error');
                    unlockInput();
                }
            });
        }, 1000);
    }

    function sendCommand() {
        const cmd = commandInput.value.trim();
        if (!cmd) return;

        appendLog('> ' + cmd, 'console-muted');
        commandInput.value = '';
        commandInput.disabled = true;
        sendBtn.disabled = true;

        const formData = new FormData();
        formData.append('ajax_action', 'queue_command');
        formData.append('user_command', cmd);

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                pollCommandResult(data.command_id);
            } else {
                appendLog('<?php echo $is_fa ? 'خطا: ' : 'Error: '; ?>' + (data.message || '<?php echo $is_fa ? 'امکان صف کردن دستور وجود ندارد.' : 'Could not queue command'; ?>'), 'overview-log-error');
                unlockInput();
            }
        })
        .catch(() => {
            appendLog('<?php echo $is_fa ? 'خطای شبکه رخ داد.' : 'Network Error occurred.'; ?>', 'overview-log-error');
            unlockInput();
        });
    }

    sendBtn.addEventListener('click', sendCommand);
    commandInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            sendCommand();
        }
    });

    clearBtn.addEventListener('click', function() {
        consoleOutput.innerHTML = '';
    });
});
</script>

<?php if (isset($billingType, $statusRaw) && $billingType === 'hourly' && $statusRaw === 'Running' && !$isNodeOffline): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        let totalSeconds = <?php echo ($server['remaining_minutes'] ?? 0) * 60; ?>;
        const maxScaleSeconds = 1440 * 60;
        const countdownEl = document.getElementById('creditCountdownText');
        const progressBarEl = document.getElementById('creditProgressBar');
        const isFa = <?php echo $is_fa ? 'true' : 'false'; ?>;

        function updateTimer() {
            if (totalSeconds < 0) totalSeconds = 0;
            const hours = Math.floor(totalSeconds / 3600);
            const minutes = Math.floor((totalSeconds % 3600) / 60);
            const seconds = totalSeconds % 60;
            const pad = (n) => String(n).padStart(2, '0');

            if (countdownEl) {
                if (isFa) {
                    countdownEl.textContent = `${hours} ساعت و ${pad(minutes)} دقیقه و ${pad(seconds)} ثانیه`;
                } else {
                    countdownEl.textContent = `${hours}h ${pad(minutes)}m ${pad(seconds)}s`;
                }
            }

            if (progressBarEl) {
                let percent = (totalSeconds / maxScaleSeconds) * 100;
                if (percent > 100) percent = 100;
                progressBarEl.style.width = percent + '%';

                const totalMinutes = totalSeconds / 60;
                if (totalMinutes < 60) {
                    progressBarEl.style.backgroundColor = '#ef4444';
                } else if (totalMinutes >= 60 && totalMinutes < 180) {
                    progressBarEl.style.backgroundColor = '#f59e0b';
                } else if (totalMinutes >= 180 && totalMinutes < 360) {
                    progressBarEl.style.backgroundColor = '#84cc16';
                } else {
                    progressBarEl.style.backgroundColor = '#10b981';
                }
            }

            if (totalSeconds > 0) {
                totalSeconds--;
            }
        }

        updateTimer();
        setInterval(updateTimer, 1000);
    });
    </script>
<?php endif; ?>

</body>
</html>
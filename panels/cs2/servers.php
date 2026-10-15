<?php
// panel/servers.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================================================
   Language
========================================================= */

if (
    isset($_GET['lang']) &&
    in_array($_GET['lang'], ['fa', 'en'], true)
) {
    $_SESSION['lang'] = $_GET['lang'];
}

$lang = $_SESSION['lang'] ?? 'fa';
$is_fa = ($lang === 'fa');

/* =========================================================
   Translations
========================================================= */

$translations = [
    'fa' => [
        'title' => 'سرورهای من | پنل',
        'nav_title' => 'داشبورد مدیریت سرور',
        'page_title' => 'سرورهای من',
        'page_desc' => 'لیست سرورهای خریداری شده و وضعیت فعلی آن‌ها.',
        'search_placeholder' => 'جستجوی سرور...',
        'th_name' => 'SERVER NAME & IP',
        'th_game' => 'GAME',
        'th_status' => 'STATUS',
        'th_expire' => 'EXPIRE DATE',
        'th_slots' => 'SLOTS',
        'th_action' => 'ACTIONS',
        'empty_title' => 'سروری برای نمایش وجود ندارد',
        'empty_desc' => 'داشتن یک سرور عالی است، همین حالا یکی تهیه کنید!',
        'buy_btn' => 'خرید سرور جدید',
        'go_to_dashboard' => 'برو به داشبورد',
        'console_btn' => 'کنسول',
        'login_required' => 'لطفاً برای مشاهده سرورهای خود ابتدا از طریق استیم وارد حساب کاربری شوید.',
        'game_name' => 'CS2',
        'unknown_date' => 'نامشخص',
        'server_count' => 'سرور',
        'servers_count' => 'سرور',
        'node_offline' => 'نود آفلاین'
    ],
    'en' => [
        'title' => 'My Servers | Panel',
        'nav_title' => 'CS2 Admin Panel',
        'page_title' => 'My Servers',
        'page_desc' => 'List of your purchased servers and their current status.',
        'search_placeholder' => 'Search for a server...',
        'th_name' => 'SERVER NAME & IP',
        'th_game' => 'GAME',
        'th_status' => 'STATUS',
        'th_expire' => 'EXPIRE DATE',
        'th_slots' => 'SLOTS',
        'th_action' => 'ACTIONS',
        'empty_title' => 'No servers to show',
        'empty_desc' => 'It\'s nice to own a server, get one right now!',
        'buy_btn' => 'Buy New Server',
        'go_to_dashboard' => 'Go to dashboard',
        'console_btn' => 'Console',
        'login_required' => 'Please log in via Steam to view your servers.',
        'game_name' => 'CS2',
        'unknown_date' => 'N/A',
        'server_count' => 'server',
        'servers_count' => 'servers',
        'node_offline' => 'NODE OFFLINE'
    ]
];

$t = $translations[$lang];
$current_page = 'servers';
$page_title = $t['nav_title'];

/* =========================================================
   Database
========================================================= */

$host = '127.0.0.1';
$port = '3306';
$dbname = 'kyonxcbt_fragcity_services';
$dbUser = 'kyonxcbt_admin';
$dbPass = 'Atrisayeafsorde824@';

$pdo = null;
$servers = [];

/* =========================================================
   Session Steam ID
========================================================= */

$sessionSteamId =
    $_SESSION['steamid']
    ?? $_SESSION['steam_id']
    ?? $_SESSION['user']['steamid']
    ?? $_SESSION['user_steamid']
    ?? '';

$isLoggedIn = !empty($sessionSteamId);

/* =========================================================
   Database Connection
========================================================= */

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $dbUser,
        $dbPass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $servers = [];
}

/* =========================================================
   Get User Servers (Joined with registered_servers & agent_nodes)
========================================================= */

if ($isLoggedIn && $pdo) {
    try {
        $thresholdTime = date('Y-m-d H:i:s', time() - 60);

        $stmt = $pdo->prepare(
            "SELECT ps.*, rs.billing_type, rs.remaining_minutes, rs.status AS reg_status, rs.updated_at AS reg_updated_at,
                    an.is_ready AS node_is_ready, an.last_heartbeat AS node_last_heartbeat 
             FROM pending_servers ps
             LEFT JOIN registered_servers rs ON ps.port = rs.port AND ps.node_name = rs.node_name
             LEFT JOIN agent_nodes an ON ps.node_name = an.node_name
             WHERE ps.steam_id = ?
             ORDER BY ps.created_at DESC"
        );

        $stmt->execute([$sessionSteamId]);
        $servers = $stmt->fetchAll();

        // بررسی وضعیت نودها و به‌روزرسانی خودکار status به stopped در جدول registered_servers
        foreach ($servers as $key => $srv) {
            $nodeName = $srv['node_name'] ?? '';
            $srvPort = $srv['port'] ?? '';
            $regStatusRaw = strtolower(trim($srv['reg_status'] ?? ''));

            $nodeIsReady = intval($srv['node_is_ready'] ?? 0);
            $nodeLastHb = $srv['node_last_heartbeat'] ?? '';

            $isNodeOffline = (empty($nodeName) || $nodeIsReady !== 1 || empty($nodeLastHb) || $nodeLastHb < $thresholdTime);

            if ($isNodeOffline) {
                if ($regStatusRaw !== 'stopped' && !empty($srvPort) && !empty($nodeName)) {
                    try {
                        $stopStmt = $pdo->prepare("UPDATE registered_servers SET status = 'stopped' WHERE port = ? AND node_name = ?");
                        $stopStmt->execute([$srvPort, $nodeName]);
                        $servers[$key]['reg_status'] = 'stopped';
                    } catch (Exception $ex) {
                    }
                }
            } else {
                if ($regStatusRaw === 'running' && !empty($srv['reg_updated_at'])) {
                    if ((time() - strtotime($srv['reg_updated_at'])) > 60) {
                        try {
                            $stopStmt = $pdo->prepare("UPDATE registered_servers SET status = 'stopped' WHERE port = ? AND node_name = ?");
                            $stopStmt->execute([$srvPort, $nodeName]);
                            $servers[$key]['reg_status'] = 'stopped';
                        } catch (Exception $ex) {
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        $servers = [];
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>" dir="<?= $is_fa ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($t['title'], ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/panel.css">
    <link rel="stylesheet" href="css/servers.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/panel-motion.css">
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content-wrapper">
        <?php include 'header.php'; ?>

        <div class="main-content servers-page">
            <div class="page-header servers-page-header">
                <div>
                    <h1 class="page-title"><?= $t['page_title'] ?></h1>
                    <div class="page-desc"><?= $t['page_desc'] ?></div>
                </div>
            </div>

            <div class="servers-toolbar">
                <div class="servers-search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input
                        type="search"
                        id="serversSearch"
                        placeholder="<?= htmlspecialchars($t['search_placeholder'], ENT_QUOTES, 'UTF-8') ?>"
                        autocomplete="off"
                    >
                    <button type="button" id="clearServersSearch" class="servers-search-clear" aria-label="Clear search">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="servers-toolbar-meta">
                    <span id="serversVisibleCount" class="servers-count-label">
                        <?= number_format(count($servers)) ?>
                    </span>
                    <span>
                        <?= $is_fa
                            ? (count($servers) === 1 ? $t['server_count'] : $t['servers_count'])
                            : (count($servers) === 1 ? $t['server_count'] : $t['servers_count'])
                        ?>
                    </span>
                </div>
            </div>

            <div class="servers-card">
                <div class="servers-table-header">
                    <div><?= $t['th_name'] ?></div>
                    <div><?= $t['th_game'] ?></div>
                    <div><?= $t['th_status'] ?></div>
                    <div><?= $t['th_expire'] ?></div>
                    <div><?= $t['th_slots'] ?></div>
                    <div><?= $t['th_action'] ?></div>
                </div>

                <?php if (!$isLoggedIn): ?>
                    <div class="servers-empty-state">
                        <div class="servers-empty-icon servers-login-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <div class="empty-title">
                            <?= $t['login_required'] ?>
                        </div>
                    </div>
                <?php elseif (empty($servers)): ?>
                    <div class="servers-empty-state">
                        <div class="empty-graphic">
                            <div class="stack-box"></div>
                            <div class="stack-box"></div>
                            <div class="stack-box">
                                <div class="mini-dot"></div>
                                <div class="mini-line"></div>
                            </div>
                        </div>
                        <div class="empty-title"><?= $t['empty_title'] ?></div>
                        <div class="empty-desc servers-empty-description"><?= $t['empty_desc'] ?></div>
                        <a href="https://cs2.fragcity.ir/index.php?page=buyserveropen" class="dashboard-btn servers-buy-button">
                            <i class="fas fa-cart-plus"></i>
                            <?= $t['buy_btn'] ?>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="servers-list" id="serversList">
                        <?php foreach ($servers as $srv): ?>
                            <?php
                            $thresholdTimeVal = date('Y-m-d H:i:s', time() - 60);
                            $nodeIsReady = intval($srv['node_is_ready'] ?? 0);
                            $nodeLastHb = $srv['node_last_heartbeat'] ?? '';
                            $isNodeOffline = (empty($srv['node_name']) || $nodeIsReady !== 1 || empty($nodeLastHb) || $nodeLastHb < $thresholdTimeVal);

                            $statusRaw = ucfirst(strtolower($srv['reg_status'] ?? 'Stopped'));

                            $statusClass = 'stopped';
                            $statusText = $statusRaw;

                            if ($isNodeOffline) {
                                $statusClass = 'stopped';
                                $statusText = $t['node_offline'];
                            } else {
                                if (in_array($statusRaw, ['Ready', 'Running', 'Active'], true)) {
                                    $statusClass = 'online';
                                } elseif ($statusRaw === 'Pending' || $statusRaw === 'Starting') {
                                    $statusClass = 'pending';
                                } else {
                                    $statusClass = 'stopped';
                                }
                            }

                            $expireDate = !empty($srv['expire_date'])
                                ? date('Y-m-d H:i', strtotime($srv['expire_date']))
                                : $t['unknown_date'];

                            $serverUniqueId = $srv['server_id'];
                            $serverName = $srv['server_name'] ?? 'CS2 Server';
                            $serverIp = ($srv['server_ip'] ?? '0.0.0.0') . ':' . ($srv['port'] ?? '27015');
                            $slotsValue = $srv['slots'] ?? '10';

                            $searchText = strtolower(trim(($serverName ?? '') . ' ' . ($serverIp ?? '') . ' ' . ($statusText ?? '') . ' CS2'));
                            ?>

                            <div class="servers-table-row" data-server-row data-search="<?= htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8') ?>">
                                <div class="server-name-cell">
                                    <div class="server-game-icon">
                                        <img src="https://fragcity.ir/assets/images/logo/cs2.png" alt="CS2" loading="lazy" width="40" height="40">
                                    </div>
                                    <div class="server-name-info">
                                        <div class="server-name"><?= htmlspecialchars($serverName, ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="server-ip"><?= htmlspecialchars($serverIp, ENT_QUOTES, 'UTF-8') ?></div>
                                    </div>
                                </div>

                                <div class="server-game-cell">
                                    <span class="server-game-badge">
                                        <i class="fa-solid fa-gamepad"></i>
                                        <?= $t['game_name'] ?>
                                    </span>
                                </div>

                                <div class="server-status-cell">
                                    <span class="server-status-badge <?= $statusClass ?>">
                                        <span class="status-dot"></span>
                                        <?= htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </div>

                                <div class="server-expire-cell">
                                    <i class="fa-regular fa-calendar"></i>
                                    <span><?= htmlspecialchars($expireDate, ENT_QUOTES, 'UTF-8') ?></span>
                                </div>

                                <div class="server-slots-cell">
                                    <i class="fa-solid fa-users"></i>
                                    <span><?= htmlspecialchars($slotsValue, ENT_QUOTES, 'UTF-8') ?></span>
                                </div>

                                <div class="server-action-cell" style="display: flex; gap: 8px; align-items: center;">
                                    <a href="console.php?server_id=<?= urlencode($serverUniqueId) ?>" class="server-dashboard-btn" style="background: #4f46e5; color: #fff;">
                                        <i class="fas fa-terminal"></i>
                                        <span><?= $t['console_btn'] ?></span>
                                    </a>
                                    <a href="overview.php?server_id=<?= urlencode($serverUniqueId) ?>" class="server-dashboard-btn">
                                        <span><?= $t['go_to_dashboard'] ?></span>
                                        <i class="fas <?= $is_fa ? 'fa-arrow-left' : 'fa-arrow-right' ?>"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="servers-search-empty" id="serversSearchEmpty" hidden>
                        <div class="servers-search-empty-icon">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </div>
                        <div class="empty-title">
                            <?= $is_fa ? 'سروری با این مشخصات پیدا نشد' : 'No matching server found' ?>
                        </div>
                        <div class="empty-desc">
                            <?= $is_fa ? 'نام سرور، آی‌پی یا وضعیت را تغییر دهید.' : 'Try searching by server name, IP or status.' ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="js/servers.js"></script>
</body>
</html>
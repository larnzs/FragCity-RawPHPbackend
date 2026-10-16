<?php
require_once __DIR__ . '/../../settings/loader.php';
// Central JSON settings for this CS2 panel page.
$panelSettings=fragcity_settings('files.panels.cs2.config',array());
$panelProfile=isset($panelSettings['database_profile'])?$panelSettings['database_profile']:'main';
$panelAllowedLanguages=isset($panelSettings['allowed_languages'])&&is_array($panelSettings['allowed_languages'])?$panelSettings['allowed_languages']:array('fa','en');
$panelDefaultLanguage=isset($panelSettings['default_language'])?$panelSettings['default_language']:'fa';
if(session_status()===PHP_SESSION_NONE)session_start();
if(isset($_GET['lang'])&&in_array($_GET['lang'],$panelAllowedLanguages,true))$_SESSION['lang']=$_GET['lang'];
$lang=isset($_SESSION['lang'])?$_SESSION['lang']:$panelDefaultLanguage;$is_fa=($lang==='fa');

$translations = [
    'fa' => [
        'title' => 'گیم مود و کانفیگ | پنل مدیریت CS2',
        'nav_title' => 'داشبورد مدیریت سرور',
        'page_title' => 'مدیریت گیم‌مود سرورها',
        'page_desc' => 'ابتدا سرور مورد نظر خود را از لیست زیر انتخاب کرده و سپس کانفیگ جدید را اعمال کنید.',
        'server_section_title' => 'انتخاب سرور',
        'config_section_title' => 'انتخاب گیم‌مود و کانفیگ',
        'th_name' => 'SERVER NAME & IP',
        'th_game' => 'GAME',
        'th_status' => 'STATUS',
        'th_expire' => 'EXPIRE DATE',
        'th_slots' => 'SLOTS',
        'th_action' => 'ACTIONS',
        'selected_label' => 'در حال ویرایش',
        'select_btn' => 'انتخاب سرور',
        'login_required' => 'لطفاً برای مشاهده و ویرایش سرورها ابتدا از طریق استیم وارد حساب کاربری شوید.',
        'empty_title' => 'سروری برای نمایش وجود ندارد',
        'empty_desc' => 'داشتن یک سرور عالی است، همین حالا یکی تهیه کنید!',
        'buy_btn' => 'خرید سرور جدید',
        'table_name' => 'نام کانفیگ',
        'table_desc' => 'توضیحات گیم‌مود',
        'apply_btn' => 'اعمال روی سرور',
        'active_status' => 'فعال روی سرور',
        'success_msg' => 'کانفیگ سرور با موفقیت به‌روزرسانی شد!',
        'restart_success' => 'دستور ریستارت سرور با موفقیت ارسال شد.',
        'restart_notice' => 'برای اعمال نهایی گیم‌مود و کانفیگ جدید، لازم است سرور خود را ریستارت کنید.',
        'restart_btn' => 'ریستارت سرور',
        'node_offline' => 'نود آفلاین',
        'game_name' => 'CS2',
        'unknown_date' => 'نامشخص',
        'editing_for' => 'تنظیم کانفیگ برای سرور:'
    ],
    'en' => [
        'title' => 'Game Mode Config | CS2 Admin Panel',
        'nav_title' => 'CS2 Admin Panel',
        'page_title' => 'Server Game Mode Management',
        'page_desc' => 'Select a server from the list below, then apply a new config.',
        'server_section_title' => 'Select Server',
        'config_section_title' => 'Select Game Mode & Config',
        'th_name' => 'SERVER NAME & IP',
        'th_game' => 'GAME',
        'th_status' => 'STATUS',
        'th_expire' => 'EXPIRE DATE',
        'th_slots' => 'SLOTS',
        'th_action' => 'ACTIONS',
        'selected_label' => 'Editing Now',
        'select_btn' => 'Select Server',
        'login_required' => 'Please log in via Steam to view your servers.',
        'empty_title' => 'No servers to show',
        'empty_desc' => 'It\'s nice to own a server, get one right now!',
        'buy_btn' => 'Buy New Server',
        'table_name' => 'Config Name',
        'table_desc' => 'Description',
        'apply_btn' => 'Apply to Server',
        'active_status' => 'Active',
        'success_msg' => 'Server config updated successfully!',
        'restart_success' => 'Server restart command sent successfully.',
        'restart_notice' => 'Restarting the server is required to apply the game mode and new config.',
        'restart_btn' => 'Restart Server',
        'node_offline' => 'NODE OFFLINE',
        'game_name' => 'CS2',
        'unknown_date' => 'N/A',
        'editing_for' => 'Setting config for:'
    ]
];
$t = $translations[$lang];

/* =========================================================
   Database Connection & Session
========================================================= */
$pdo=fragcity_create_pdo($panelProfile);
$sessionSteamId=$_SESSION['steamid']??$_SESSION['steam_id']??$_SESSION['user']['steamid']??$_SESSION['user_steamid']??'';
$isLoggedIn=!empty($sessionSteamId);

$servers = [];
$active_server = null;
$success_message = '';
$error_message = '';

$all_configs=isset($panelSettings['all_configs'])&&is_array($panelSettings['all_configs'])?array_values(array_filter($panelSettings['all_configs'],'is_string')):array();

];

if ($isLoggedIn && $pdo) {
    try {
        $thresholdTime = date('Y-m-d H:i:s', time() - 60);
        
        // دریافت اطلاعات سرورها همراه با کانفیگ فعلی از جدول مستقل server_configs
        $stmt = $pdo->prepare(
            "SELECT rs.*, ps.slots, an.is_ready AS node_is_ready, an.last_heartbeat AS node_last_heartbeat, sc.current_config AS selected_config 
             FROM registered_servers rs
             LEFT JOIN pending_servers ps ON rs.server_id = ps.server_id
             LEFT JOIN agent_nodes an ON rs.node_name = an.node_name
             LEFT JOIN server_configs sc ON rs.server_id = sc.server_id
             WHERE rs.steam_id = ?
             ORDER BY rs.created_at DESC"
        );
        $stmt->execute([$sessionSteamId]);
        $servers = $stmt->fetchAll();

        $requested_server_id = $_GET['server_id'] ?? null;

        if (!empty($servers)) {
            if ($requested_server_id) {
                foreach ($servers as $srv) {
                    if ((string)$srv['server_id'] === (string)$requested_server_id) {
                        $active_server = $srv;
                        break;
                    }
                }
            }
            if (!$active_server) {
                $active_server = $servers[0];
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['action']) && $_POST['action'] === 'restart' && $active_server) {
                $success_message = $t['restart_success'];
            } elseif (isset($_POST['new_config']) && $active_server) {
                $new_config = trim($_POST['new_config']);

                if (in_array($new_config, $all_configs)) {
                    // درج یا به‌روزرسانی کانفیگ در جدول server_configs شامل steam_id
                    $update_stmt = $pdo->prepare("
                        INSERT INTO server_configs (server_id, steam_id, port, node_name, current_config) 
                        VALUES (?, ?, ?, ?, ?) 
                        ON DUPLICATE KEY UPDATE current_config = ?, steam_id = ?
                    ");
                    $update_stmt->execute([
                        $active_server['server_id'],
                        $sessionSteamId,
                        $active_server['port'] ?? 27015,
                        $active_server['node_name'] ?? 'Node-Tehran-01',
                        $new_config,
                        $new_config,
                        $sessionSteamId
                    ]);
                    
                    $active_server['selected_config'] = $new_config;
                    foreach ($servers as $idx => $s) {
                        if ((string)$s['server_id'] === (string)$active_server['server_id']) {
                            $servers[$idx]['selected_config'] = $new_config;
                        }
                    }
                    $success_message = $t['success_msg'];
                }
            }
        }
    } catch (Exception $e) {
        $error_message = "Database Error: " . $e->getMessage();
    }
}

$config_descriptions = [
    'comp.cfg' => $is_fa ? 'مسابقات رقابتی (Competitive) استاندارد' : 'Standard Competitive Match',
    'casual.cfg' => $is_fa ? 'بازی تفریحی (Casual) برای تعداد بالا' : 'Casual Match for larger groups',
    'deathmatch.cfg' => $is_fa ? 'حالت دث‌مچ کلاسیک (تیمی)' : 'Classic Team Deathmatch',
    'dm.cfg' => $is_fa ? 'حالت دث‌مچ (Free For All)' : 'Free For All Deathmatch',
    'awp.cfg' => $is_fa ? 'مخصوص نقشه‌های اسنایپ (AWP Only)' : 'AWP Only maps configuration',
    'aim.cfg' => $is_fa ? 'تمرین نشانه‌گیری در مپ‌های Aim' : 'Aim practice configuration',
    'retake.cfg' => $is_fa ? 'شبیه‌سازی بازپس‌گیری بمب‌سایت' : 'Retake Simulation',
    'wingman.cfg' => $is_fa ? 'مسابقات دو نفره (Wingman)' : 'Wingman 2v2 matches',
    'bhop.cfg' => $is_fa ? 'تنظیمات حرکت و پرش بانی‌هاپ' : 'BunnyHop movement settings',
    'surf.cfg' => $is_fa ? 'تنظیمات سر خوردن روی سطوح شیب‌دار' : 'Surf movement settings',
    '1v1.cfg' => $is_fa ? 'دوئل‌های یک به یک در مپ‌های آرنا' : '1v1 Arena Duels'
];

$db_selected_config = !empty($active_server['selected_config']) ? $active_server['selected_config'] : 'comp.cfg';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>" dir="<?= $is_fa ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($t['title'], ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/panel.css">
    <link rel="stylesheet" href="css/servers.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/panel-motion.css">
    <link rel="stylesheet" href="css/config.css">
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content-wrapper">
        <?php include 'header.php'; ?>

        <div class="main-content servers-page config-page">
            <div class="page-header servers-page-header config-page-header">
                <div>
                    <h1 class="page-title"><?= $t['page_title'] ?></h1>
                    <div class="page-desc"><?= $t['page_desc'] ?></div>
                </div>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="config-alert-success" style="background: rgba(46, 204, 113, 0.15); color: #2ecc71; padding: 15px 20px; border-radius: 12px; margin-bottom: 25px; border: 1px solid rgba(46, 204, 113, 0.3); display: flex; align-items: center; gap: 12px; font-size: 15px;">
                    <i class="fa-solid fa-check-circle"></i>
                    <span><?= htmlspecialchars($success_message) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="config-alert-error" style="background: rgba(239, 68, 68, 0.15); color: #f87171; padding: 15px 20px; border-radius: 12px; margin-bottom: 25px; border: 1px solid rgba(239, 68, 68, 0.3); display: flex; align-items: center; gap: 12px; font-size: 15px;">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span><?= htmlspecialchars($error_message) ?></span>
                </div>
            <?php endif; ?>

            <!-- ==========================================
                 بخش ۱: جدول انتخاب سرور
            =========================================== -->
            <div class="config-section-title" style="font-size: 16px; color: #00d2ff; font-weight: 600; margin: 25px 0 15px 0; display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-server"></i> <?= $t['server_section_title'] ?>
            </div>

            <div class="servers-card config-card">
                <div class="servers-table-header config-table-header">
                    <div><?= $t['th_name'] ?></div>
                    <div><?= $t['th_game'] ?></div>
                    <div><?= $t['th_status'] ?></div>
                    <div><?= $t['th_expire'] ?></div>
                    <div><?= $t['th_slots'] ?></div>
                    <div><?= $t['th_action'] ?></div>
                </div>

                <?php if (!$isLoggedIn): ?>
                    <div class="servers-empty-state config-empty-state">
                        <div class="servers-empty-icon servers-login-icon config-empty-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <div class="empty-title">
                            <?= $t['login_required'] ?>
                        </div>
                    </div>
                <?php elseif (empty($servers)): ?>
                    <div class="servers-empty-state config-empty-state">
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
                    <div class="servers-list" id="serversList" style="max-height: 255px; overflow-y: auto;">
                        <?php foreach ($servers as $srv): ?>
                            <?php
                            $thresholdTimeVal = date('Y-m-d H:i:s', time() - 60);
                            $nodeIsReady = intval($srv['node_is_ready'] ?? 0);
                            $nodeLastHb = $srv['node_last_heartbeat'] ?? '';
                            $isNodeOffline = (empty($srv['node_name']) || $nodeIsReady !== 1 || empty($nodeLastHb) || $nodeLastHb < $thresholdTimeVal);

                            $statusRaw = ucfirst(strtolower($srv['status'] ?? 'Stopped'));

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

                            $is_this_selected = ($active_server && (string)$srv['server_id'] === (string)$active_server['server_id']);
                            ?>

                            <div class="servers-table-row <?= $is_this_selected ? 'selected-server-row' : '' ?>" style="<?= $is_this_selected ? 'background: rgba(0, 210, 255, 0.05);' : '' ?>">
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

                                <div class="server-action-cell">
                                    <?php if ($is_this_selected): ?>
                                        <span class="server-dashboard-btn" style="background: rgba(0, 210, 255, 0.15); color: #00d2ff; border: 1px solid rgba(0, 210, 255, 0.3); cursor: default;">
                                            <span><i class="fa-solid fa-check"></i> <?= $t['selected_label'] ?></span>
                                        </span>
                                    <?php else: ?>
                                        <a href="config.php?server_id=<?= urlencode($serverUniqueId) ?>" class="server-dashboard-btn config-edit-btn">
                                            <span><?= $t['select_btn'] ?></span>
                                            <i class="fas <?= $is_fa ? 'fa-arrow-left' : 'fa-arrow-right' ?>"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ==========================================
                 بخش ۲: جدول لیست کانفیگ‌ها و اعلان ریستارت
            =========================================== -->
            <?php if ($active_server): ?>
                <div class="config-section-title" style="font-size: 16px; color: #00d2ff; font-weight: 600; margin: 35px 0 15px 0; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-sliders"></i> <?= $t['config_section_title'] ?>
                    <span style="color: #64748B; font-weight: 400; font-size: 13px; margin: 0 10px;">
                        (<?= $t['editing_for'] ?> <strong style="color: #fff;"><?= htmlspecialchars($active_server['server_name'] ?? 'Server') ?></strong>)
                    </span>
                </div>

                <!-- هشدار و دکمه ریستارت سرور -->
                <div class="config-alert-notice" style="background: rgba(0, 210, 255, 0.08); color: #38bdf8; padding: 16px 20px; border-radius: 12px; margin-bottom: 20px; border: 1px solid rgba(0, 210, 255, 0.25); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
                    <div style="display: flex; align-items: center; gap: 12px; font-size: 14px;">
                        <i class="fa-solid fa-triangle-exclamation" style="font-size: 18px; color: #00d2ff;"></i>
                        <span><?= $t['restart_notice'] ?></span>
                    </div>
                    <form method="POST" action="config.php?server_id=<?= urlencode($active_server['server_id']) ?>" style="margin: 0;">
                        <input type="hidden" name="action" value="restart">
                        <button type="submit" class="server-dashboard-btn" style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #f87171; padding: 10px 18px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; transition: all 0.2s;">
                            <i class="fa-solid fa-rotate-right"></i> <?= $t['restart_btn'] ?>
                        </button>
                    </form>
                </div>

                <div class="config-card" style="background: #111827; border: 1px solid #22304A; border-radius: 14px;">
                    <div class="config-table-header">
                        <div><?= $t['table_name'] ?></div>
                        <div><?= $t['table_desc'] ?></div>
                        <div><?= $t['th_status'] ?></div>
                        <div><?= $t['th_action'] ?></div>
                    </div>
                    <div>
                        <?php foreach ($all_configs as $cfg): ?>
                            <?php 
                            $is_active_cfg = ($cfg === $db_selected_config);
                            $desc = $config_descriptions[$cfg] ?? ($is_fa ? 'کانفیگ اجرایی ' . $cfg : 'Game execution config ' . $cfg);
                            ?>
                            <div style="display: grid; grid-template-columns: 2fr 1.5fr 1fr 1fr; min-width: 760px; padding: 14px 22px; border-bottom: 1px solid #1E293B; align-items: center; background: <?= $is_active_cfg ? 'rgba(46, 204, 113, 0.05)' : 'transparent' ?>;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <i class="fa-regular fa-file-code" style="color: #60A5FA; font-size: 1.1rem;"></i>
                                    <span style="font-family: monospace; color: #F8FAFC; font-weight: 600;"><?= htmlspecialchars($cfg) ?></span>
                                </div>
                                <div style="color: #94A3B8; font-size: 0.85rem;">
                                    <?= $desc ?>
                                </div>
                                <div>
                                    <?php if ($is_active_cfg): ?>
                                        <span style="color: #2ecc71; font-weight: 600; font-size: 0.85rem;"><?= $t['active_status'] ?></span>
                                    <?php else: ?>
                                        <span style="color: #64748B; font-size: 0.85rem;">-</span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php if (!$is_active_cfg): ?>
                                        <form method="POST" action="config.php?server_id=<?= urlencode($active_server['server_id']) ?>" style="margin: 0;">
                                            <input type="hidden" name="new_config" value="<?= htmlspecialchars($cfg) ?>">
                                            <button type="status" class="server-dashboard-btn config-edit-btn" style="background: #1E293B; border: 1px solid #334155; border-radius: 8px; color: #F8FAFC; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;"><?= $t['apply_btn'] ?></button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: #2ecc71; font-size: 0.85rem;"><i class="fa-solid fa-check"></i></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
    
    <script src="js/config.js"></script>
</body>
</html>
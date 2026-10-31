<?php
// pages/banlist.php

require_once __DIR__ . '/../class/SimpleAdminRepository.php';

$pageTitle = "لیست محرومیت‌ها | FragCity";

$isLoggedIn = (($_SESSION['user_logged_in'] ?? false) === true);
$isAdmin = $isLoggedIn && (string)($_SESSION['role'] ?? '') === 'admin';

// insmaster is a database permission, not a browser/session-provided flag.
// Resolve it against the authenticated SteamID on every request and fail closed.
$isInsMaster = false;
$viewerSteamId64 = trim((string)($_SESSION['steamid'] ?? ''));

if (
    $isLoggedIn
    && preg_match('/^[0-9]{17}$/D', $viewerSteamId64) === 1
    && isset($pdo)
    && $pdo instanceof PDO
) {
    try {
        $permissionStatement = $pdo->prepare(
            'SELECT insmaster FROM users WHERE steamid64 = ? LIMIT 1'
        );
        $permissionStatement->execute([$viewerSteamId64]);
        $isInsMaster = (int)$permissionStatement->fetchColumn() === 1;
    } catch (Throwable $permissionError) {
        error_log(
            '[FragCity] Ban List insmaster permission lookup failed (SQLSTATE '
            . (string)$permissionError->getCode() . ').'
        );
    }
}

// Keep existing administrator access while allowing explicitly flagged managers.
$canViewPlayerIps = $isAdmin || $isInsMaster;
$showSensitiveData = $canViewPlayerIps;

$type = is_string($_GET['type'] ?? null) ? $_GET['type'] : 'bans';
$allowedTypes = ['bans', 'unbans', 'mutes', 'unmutes', 'warns', 'playerips'];
if (!in_array($type, $allowedTypes, true) || ($type === 'playerips' && !$canViewPlayerIps)) {
    $type = 'bans';
}

$search = trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
$search = function_exists('mb_substr')
    ? mb_substr($search, 0, 120, 'UTF-8')
    : substr($search, 0, 120);

$pageNum = max(1, min(100000, (int)($_GET['page_num'] ?? 1)));
$perPage = (int)($_GET['per_page'] ?? 15);
$allowedPerPage = [10, 15, 25, 50, 100];
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 15;
}

$error = null;
$data = [];
$totalItems = 0;
$totalPages = 1;
$pageStartIndex = 0;
$pageEndIndex = 0;

try {
    $result = (new SimpleAdminRepository())->getBanlistRecords(
        $type,
        $search,
        $pageNum,
        $perPage,
        $showSensitiveData
    );

    $data = is_array($result['items'] ?? null) ? $result['items'] : [];
    $totalItems = max(0, (int)($result['total'] ?? 0));
    $totalPages = max(1, (int)($result['total_pages'] ?? 1));
    $pageNum = max(1, min($totalPages, (int)($result['page'] ?? 1)));
    $perPage = (int)($result['per_page'] ?? $perPage);

    $pageStartIndex = $totalItems > 0 ? (($pageNum - 1) * $perPage) + 1 : 0;
    $pageEndIndex = min(($pageNum - 1) * $perPage + count($data), $totalItems);
} catch (Throwable $e) {
    error_log('[FragCity] Ban List Linux API request failed: ' . get_class($e));
    $error = 'دریافت اطلاعات SimpleAdmin از طریق API فعلاً ممکن نیست. لطفاً اتصال API را بررسی کنید.';
    $data = [];
    $totalItems = 0;
    $totalPages = 1;
    $pageStartIndex = 0;
    $pageEndIndex = 0;
}

/* =========================================================
   Helper Functions
========================================================= */

function esc($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function formatDuration($minutes): string
{
    $minutes = (int)$minutes;
    if ($minutes <= 0) {
        return 'نامحدود';
    }

    $days = (int)floor($minutes / 1440);
    $hours = (int)floor(($minutes % 1440) / 60);
    $mins = $minutes % 60;
    $result = '';

    if ($days > 0) $result .= $days . ' روز ';
    if ($hours > 0) $result .= $hours . ' ساعت ';
    if ($mins > 0) $result .= $mins . ' دقیقه';

    return trim($result ?: '0 دقیقه');
}

function getUserAvatar($steamid): string
{
    $steamid = (string)$steamid;
    if ($steamid !== '') {
        $localAvatarPath = __DIR__ . '/../img/avatars/avatar_' . $steamid . '.jpg';
        if (file_exists($localAvatarPath)) {
            return 'img/avatars/avatar_' . $steamid . '.jpg?' . filemtime($localAvatarPath);
        }

        return 'https://avatars.steamstatic.com/' . rawurlencode($steamid) . '?size=medium';
    }

    return 'assets/images/default_avatar.png';
}

function renderPlayerCell(string $name, string $steamid): string
{
    $avatar = getUserAvatar($steamid);
    $steamShort = $steamid !== '' ? substr($steamid, -8) : '';

    return '
        <div class="ban-player">
            <img src="' . esc($avatar) . '" alt="' . esc($name) . '"
                 class="ban-avatar"
                 onerror="this.src=\'assets/images/default_avatar.png\'">
            <div class="ban-player-info">
                <span class="ban-player-name">' . esc($name) . '</span>
                ' . ($steamShort !== '' ? '
                    <span class="ban-steam-id">' . esc($steamShort) . '</span>
                ' : '') . '
            </div>
        </div>
    ';
}

function renderAdminCell(string $name, string $steamid): string
{
    $avatar = getUserAvatar($steamid);
    $steamShort = $steamid !== '' ? substr($steamid, -8) : '';

    return '
        <div class="ban-player ban-admin">
            <img src="' . esc($avatar) . '" alt="' . esc($name) . '"
                 class="ban-avatar"
                 onerror="this.src=\'assets/images/default_avatar.png\'">
            <div class="ban-player-info">
                <span class="ban-player-name">' . esc($name) . '</span>
                ' . ($steamShort !== '' ? '
                    <span class="ban-steam-id">' . esc($steamShort) . '</span>
                ' : '') . '
            </div>
        </div>
    ';
}

function renderTableCell($content, string $class = ''): string
{
    return '<td class="ban-table-cell ' . esc($class) . '">' . $content . '</td>';
}

/* =========================================================
   Tabs
========================================================= */

$tabs = [
    'bans' => ['icon' => 'fa-ban', 'label' => 'بن‌ها', 'tone' => 'danger'],
    'unbans' => ['icon' => 'fa-rotate-left', 'label' => 'رفع بن‌ها', 'tone' => 'success'],
    'mutes' => ['icon' => 'fa-volume-xmark', 'label' => 'میوت / گگ', 'tone' => 'danger'],
    'unmutes' => ['icon' => 'fa-volume-high', 'label' => 'رفع میوت', 'tone' => 'success'],
    'warns' => ['icon' => 'fa-triangle-exclamation', 'label' => 'اخطارها', 'tone' => 'warning'],
    'playerips' => ['icon' => 'fa-network-wired', 'label' => 'آی‌پی بازیکنان', 'tone' => 'info'],
];

if (!$canViewPlayerIps) {
    unset($tabs['playerips']);
}

function banlistBuildUrl(array $params = []): string
{
    $query = array_merge([
        'page' => 'banlist',
        'type' => $GLOBALS['type'] ?? 'bans',
        'q' => $GLOBALS['search'] ?? '',
        'per_page' => $GLOBALS['perPage'] ?? 15,
        'page_num' => $GLOBALS['pageNum'] ?? 1,
    ], $params);

    return '?' . http_build_query($query);
}
?>

<style>
    .banlist-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        justify-content: space-between;
        align-items: center;
        margin: 18px 0 16px;
        padding: 14px 16px;
        background: rgba(11, 18, 32, 0.88);
        border: 1px solid rgba(48, 48, 48, 0.68);
        border-radius: 20px;
        box-shadow: 0 14px 34px rgba(2, 6, 23, 0.18);
    }
    .banlist-search-form {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
        flex: 1 1 420px;
    }
    .banlist-search-input {
        min-width: 220px;
        flex: 1 1 260px;
        height: 48px;
        padding: 0 14px;
        border-radius: 13px;
        background: #111111;
        border: 1px solid #303030;
        color: #f8fafc;
        font: inherit;
        outline: none;
    }
    .banlist-search-input:focus {
        border-color: #F97316;
        box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.14);
    }
    .banlist-search-btn,
    .banlist-clear-btn,
    .ban-page-btn,
    .ban-size-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        height: 44px;
        padding: 0 14px;
        border-radius: 13px;
        border: 1px solid #303030;
        background: #111111;
        color: #cbd5e1;
        text-decoration: none;
        font-size: 0.86rem;
        font-weight: 700;
        transition: 0.2s ease;
    }
    .banlist-search-btn:hover,
    .banlist-clear-btn:hover,
    .ban-page-btn:hover,
    .ban-size-btn:hover {
        border-color: rgba(249, 115, 22, 0.48);
        background: rgba(23, 23, 23, 0.96);
        color: #fff;
    }
    .banlist-page-size {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        justify-content: flex-end;
    }
    .banlist-page-size span {
        color: #94a3b8;
        font-size: 0.82rem;
        font-weight: 700;
    }
    .ban-size-btn.active {
        border-color: rgba(249, 115, 22, 0.42);
        background: linear-gradient(135deg, rgba(249, 115, 22, 0.10), rgba(23, 23, 23, 0.96));
        color: #f8fafc;
    }
    .banlist-footer-controls {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        justify-content: space-between;
        align-items: center;
        margin-top: 16px;
        padding: 14px 0 0;
        border-top: 1px solid rgba(48, 48, 48, 0.55);
    }
    .banlist-range {
        color: #94a3b8;
        font-size: 0.85rem;
    }
    .banlist-pagination {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
        justify-content: flex-end;
    }
    .ban-page-btn.active {
        border-color: rgba(249, 115, 22, 0.42);
        background: linear-gradient(135deg, rgba(249, 115, 22, 0.10), rgba(23, 23, 23, 0.96));
        color: #f8fafc;
    }
    .ban-page-btn.disabled {
        opacity: 0.45;
        pointer-events: none;
    }
    .ban-tab {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .ban-tab.active {
        background: rgba(194, 65, 12, 0.14) !important;
        color: #FB923C !important;
        border-color: rgba(249, 115, 22, 0.30) !important;
    }
    @media (max-width: 768px) {
        .banlist-toolbar,
        .banlist-footer-controls {
            flex-direction: column;
            align-items: stretch;
        }
        .banlist-page-size,
        .banlist-pagination {
            justify-content: flex-start;
        }
        .banlist-search-input {
            min-width: 0;
            width: 100%;
        }
    }
</style>
<div class="banlist-page">

    <!-- =========================================
         Page Header
    ========================================== -->

    <div class="banlist-header">

        <div class="banlist-title-wrap">

            <div class="banlist-title-icon">

                <i class="fas fa-gavel"></i>

            </div>

            <div>

                <h1>
                    لیست محرومیت‌ها
                </h1>

                <p>
                    بن‌ها، میوت‌ها، اخطارها و سوابق رفع محرومیت
                </p>

            </div>

        </div>

        <div class="banlist-header-badge">

            <i class="fas fa-shield-halved"></i>

            FragCity Moderation

        </div>

    </div>


    <!-- =========================================
         Main Card
    ========================================== -->

    <div class="banlist-container">


        <!-- =====================================
             Tabs
        ====================================== -->

        <div class="banlist-tabs">

            <?php foreach (
                $tabs as $key => $tab
            ): ?>

<a
    href="<?= banlistBuildUrl(['type' => $key, 'page_num' => 1]) ?>"
    class="ban-tab <?= $type === $key ? 'active' : '' ?>"
    data-ban-tab
    data-type="<?= esc($key) ?>"
>
    <i class="fas <?= esc($tab['icon']) ?>"></i>
    <span><?= esc($tab['label']) ?></span>
</a>

            <?php endforeach; ?>

        </div>

        <div class="banlist-toolbar">
            <form class="banlist-search-form" method="get">
                <input type="hidden" name="page" value="banlist">
                <input type="hidden" name="type" value="<?= esc($type) ?>">
                <input type="hidden" name="per_page" value="<?= (int)$perPage ?>">
                <input type="hidden" name="page_num" value="1">
                <input
                    type="search"
                    name="q"
                    value="<?= esc($search) ?>"
                    class="banlist-search-input"
                    placeholder="<?= $showSensitiveData ? 'جستجو در نام، SteamID، IP، دلیل و ادمین...' : 'جستجو در نام، SteamID، دلیل و ادمین...' ?>"
                    autocomplete="off"
                >
                <button type="submit" class="banlist-search-btn">
                    <i class="fas fa-search"></i>
                    جستجو
                </button>
                <?php if ($search !== ''): ?>
                    <a href="<?= banlistBuildUrl(['q' => '', 'page_num' => 1]) ?>" class="banlist-clear-btn">
                        <i class="fas fa-times"></i>
                        پاک کردن
                    </a>
                <?php endif; ?>
            </form>
</div>


        <!-- =====================================
             Table Area
        ====================================== -->

        <div class="banlist-table-wrapper">


            <?php if ($error): ?>

                <div class="banlist-state error">

                    <div class="banlist-state-icon">

                        <i class="fas fa-circle-exclamation"></i>

                    </div>

                    <h3>
                        خطا در دریافت اطلاعات
                    </h3>

                    <p>
                        <?= esc($error) ?>
                    </p>

                </div>


            <?php elseif (empty($data)): ?>

                <div class="banlist-state empty">

                    <div class="banlist-state-icon">

                        <i class="fas fa-database"></i>

                    </div>

                    <h3>
                        موردی یافت نشد
                    </h3>

                    <p>
                        در این بخش هنوز اطلاعاتی ثبت نشده است.
                    </p>

                </div>


            <?php else: ?>


                <div class="ban-table-scroll">

                    <table class="ban-table">

                        <thead>

                            <tr>

                                <th class="col-rank">
                                    #
                                </th>


                                <?php if (
                                    $type === 'bans'
                                ): ?>

                                    <th>
                                        <i class="fas fa-user"></i>
                                        Player
                                    </th>

                                    <?php if ($showSensitiveData): ?>
                                    <th>
                                        <i class="fas fa-globe"></i>
                                        IP
                                    </th>
                                    <?php endif; ?>

                                    <th>
                                        <i class="fas fa-user-shield"></i>
                                        Admin
                                    </th>

                                    <th>
                                        <i class="fas fa-comment"></i>
                                        Reason
                                    </th>

                                    <th>
                                        <i class="fas fa-clock"></i>
                                        Duration
                                    </th>

                                    <th>
                                        <i class="fas fa-calendar-xmark"></i>
                                        Expires
                                    </th>

                                    <th>
                                        <i class="fas fa-calendar-plus"></i>
                                        Created
                                    </th>


                                <?php elseif (
                                    $type === 'unbans'
                                ): ?>

                                    <th>
                                        <i class="fas fa-user"></i>
                                        Player
                                    </th>

                                    <?php if ($showSensitiveData): ?>
                                    <th>
                                        <i class="fas fa-globe"></i>
                                        IP
                                    </th>
                                    <?php endif; ?>

                                    <th>
                                        <i class="fas fa-user-shield"></i>
                                        Admin
                                    </th>

                                    <th>
                                        <i class="fas fa-comment"></i>
                                        Ban Reason
                                    </th>

                                    <th>
                                        <i class="fas fa-rotate-left"></i>
                                        Unban Reason
                                    </th>

                                    <th>
                                        <i class="fas fa-calendar-check"></i>
                                        Unban Date
                                    </th>

                                    <th>
                                        <i class="fas fa-calendar-plus"></i>
                                        Ban Created
                                    </th>


                                <?php elseif (
                                    $type === 'mutes'
                                ): ?>

                                    <th>
                                        <i class="fas fa-user"></i>
                                        Player
                                    </th>

                                    <th>
                                        <i class="fas fa-user-shield"></i>
                                        Admin
                                    </th>

                                    <th>
                                        <i class="fas fa-comment"></i>
                                        Reason
                                    </th>

                                    <th>
                                        <i class="fas fa-tag"></i>
                                        Type
                                    </th>

                                    <th>
                                        <i class="fas fa-clock"></i>
                                        Duration
                                    </th>

                                    <th>
                                        <i class="fas fa-calendar-xmark"></i>
                                        Expires
                                    </th>

                                    <th>
                                        <i class="fas fa-calendar-plus"></i>
                                        Created
                                    </th>


                                <?php elseif (
                                    $type === 'unmutes'
                                ): ?>

                                    <th>
                                        <i class="fas fa-user"></i>
                                        Player
                                    </th>

                                    <th>
                                        <i class="fas fa-user-shield"></i>
                                        Admin
                                    </th>

                                    <th>
                                        <i class="fas fa-comment"></i>
                                        Mute Reason
                                    </th>

                                    <th>
                                        <i class="fas fa-tag"></i>
                                        Type
                                    </th>

                                    <th>
                                        <i class="fas fa-rotate-left"></i>
                                        Unmute Reason
                                    </th>

                                    <th>
                                        <i class="fas fa-calendar-check"></i>
                                        Unmute Date
                                    </th>

                                    <th>
                                        <i class="fas fa-calendar-plus"></i>
                                        Mute Created
                                    </th>


                                <?php elseif (
                                    $type === 'warns'
                                ): ?>

                                    <th>
                                        <i class="fas fa-user"></i>
                                        Player
                                    </th>

                                    <th>
                                        <i class="fas fa-user-shield"></i>
                                        Admin
                                    </th>

                                    <th>
                                        <i class="fas fa-comment"></i>
                                        Reason
                                    </th>

                                    <th>
                                        <i class="fas fa-clock"></i>
                                        Duration
                                    </th>

                                    <th>
                                        <i class="fas fa-calendar-xmark"></i>
                                        Expires
                                    </th>

                                    <th>
                                        <i class="fas fa-calendar-plus"></i>
                                        Created
                                    </th>


                                <?php elseif (
                                    $type === 'playerips'
                                ): ?>

                                    <th>
                                        <i class="fas fa-user"></i>
                                        Player
                                    </th>

                                    <?php if ($showSensitiveData): ?>
                                    <th>
                                        <i class="fas fa-globe"></i>
                                        IP
                                    </th>
                                    <?php endif; ?>

                                    <th>
                                        <i class="fas fa-calendar"></i>
                                        Last Used
                                    </th>

                                <?php endif; ?>

                            </tr>

                        </thead>


                        <tbody>

                            <?php
                            $rank = $pageStartIndex;

                            foreach (
                                $data as $row
                            ):
                            ?>

                                <tr>

                                    <td class="rank-cell">

                                        <?= $rank++ ?>

                                    </td>


                                    <?php if (
                                        $type === 'bans'
                                    ): ?>

                                        <?= renderTableCell(
                                            renderPlayerCell(
                                                $row['player_name'] ?? '',
                                                $row['player_steamid'] ?? ''
                                            )
                                        ) ?>

                                        <?php if ($showSensitiveData): ?>


                                        <?= renderTableCell(


                                            esc($row['player_ip'] ?? '---'),


                                            'mono-cell'


                                        ) ?>


                                        <?php endif; ?>

                                        <?= renderTableCell(
                                            renderAdminCell(
                                                $row['admin_name'] ?? '',
                                                $row['admin_steamid'] ?? ''
                                            )
                                        ) ?>

                                        <?= renderTableCell(
                                            esc(
                                                $row['reason']
                                                    ?: '---'
                                            )
                                        ) ?>

                                        <?= renderTableCell(
                                            esc(
                                                formatDuration(
                                                    $row['duration'] ?? 0
                                                )
                                            )
                                        ) ?>

                                        <?= renderTableCell(
                                            esc(
                                                $row['ends']
                                                    ?: '---'
                                            ),
                                            'mono-cell'
                                        ) ?>

                                        <?= renderTableCell(
                                            esc(
                                                $row['created']
                                                    ?: '---'
                                            ),
                                            'mono-cell'
                                        ) ?>


                                    <?php elseif (
                                        $type === 'unbans'
                                    ): ?>

                                        <?= renderTableCell(
                                            renderPlayerCell(
                                                $row['player_name'] ?? '',
                                                $row['player_steamid'] ?? ''
                                            )
                                        ) ?>

                                        <?php if ($showSensitiveData): ?>


                                        <?= renderTableCell(


                                            esc($row['player_ip'] ?? '---'),


                                            'mono-cell'


                                        ) ?>


                                        <?php endif; ?>

                                        <?= renderTableCell(
                                            renderAdminCell(
                                                $row['admin_name'] ?? '',
                                                $row['admin_steamid'] ?? ''
                                            )
                                        ) ?>

                                        <?= renderTableCell(
                                            esc(
                                                $row['ban_reason']
                                                    ?: '---'
                                            )
                                        ) ?>

                                        <?= renderTableCell(
                                            esc(
                                                $row['unban_reason']
                                                    ?: '---'
                                            )
                                        ) ?>

                                        <?= renderTableCell(
                                            esc(
                                                $row['unban_date']
                                                    ?: '---'
                                            ),
                                            'mono-cell'
                                        ) ?>

                                        <?= renderTableCell(
                                            esc(
                                                $row['ban_created']
                                                    ?: '---'
                                            ),
                                            'mono-cell'
                                        ) ?>


                                    <?php elseif (
                                        $type === 'mutes'
                                    ): ?>

                                        <?= renderTableCell(
                                            renderPlayerCell(
                                                $row['player_name'] ?? '',
                                                $row['player_steamid'] ?? ''
                                            )
                                        ) ?>

                                        <?= renderTableCell(
                                            renderAdminCell(
                                                $row['admin_name'] ?? '',
                                                $row['admin_steamid'] ?? ''
                                            )
                                        ) ?>

                                        <?= renderTableCell(
                                            esc(
                                                $row['reason']
                                                    ?: '---'
                                            )
                                        ) ?>

                                        <?= renderTableCell(
                                            esc(
                                                $row['type']
                                                    ?: '---'
                                            )
                                        ) ?>

                                        <?= renderTableCell(
                                            esc(
                                                formatDuration(
                                                    $row['duration'] ?? 0
                                                )
                                            )
                                        ) ?>

                                        <?= renderTableCell(
                                            esc(
                                                $row['ends']
                                                    ?: '---'
                                            ),
                                            'mono-cell'
                                        ) ?>

                                        <?= renderTableCell(
                                            esc(
                                                $row['created']
                                                    ?: '---'
                                            ),
                                            'mono-cell'
                                        ) ?>


                                    <?php elseif (
                                        $type === 'unmutes'
                                    ): ?>

                                        <?= renderTableCell(
                                            renderPlayerCell(
                                                $row['player_name'] ?? '',
                                                $row['player_steamid'] ?? ''
                                            )
                                        ) ?>

                                        <?= renderTableCell(
                                            renderAdminCell(
                                                $row['admin_name'] ?? '',
                                                $row['admin_steamid'] ?? ''
                                            )
                                        ) ?>

                                        <?= renderTableCell(
                                            esc(
                                                $row['mute_reason']
                                                    ?: '---'
                                            )
                                        ) ?>

                                        <?= renderTableCell(
                                            esc(
                                                $row['type']
                                                    ?: '---'
                                            )
                                        ) ?>

                                        <?= renderTableCell(
                                            esc(
                                                $row['unmute_reason']
                                                    ?: '---'
                                            )
                                        ) ?>

                                        <?= renderTableCell(
                                            esc(
                                                $row['unmute_date']
                                                    ?: '---'
                                            ),
                                            'mono-cell'
                                        ) ?>

                                        <?= renderTableCell(
                                            esc(
                                                $row['mute_created']
                                                    ?: '---'
                                            ),
                                            'mono-cell'
                                        ) ?>


                                    <?php elseif (
                                        $type === 'warns'
                                    ): ?>

                                        <?= renderTableCell(
                                            renderPlayerCell(
                                                $row['player_name'] ?? '',
                                                $row['player_steamid'] ?? ''
                                            )
                                        ) ?>

                                        <?= renderTableCell(
                                            renderAdminCell(
                                                $row['admin_name'] ?? '',
                                                $row['admin_steamid'] ?? ''
                                            )
                                        ) ?>

                                        <?= renderTableCell(
                                            esc(
                                                $row['reason']
                                                    ?: '---'
                                            )
                                        ) ?>

                                        <?= renderTableCell(
                                            esc(
                                                formatDuration(
                                                    $row['duration'] ?? 0
                                                )
                                            )
                                        ) ?>

                                        <?= renderTableCell(
                                            esc(
                                                $row['ends']
                                                    ?: '---'
                                            ),
                                            'mono-cell'
                                        ) ?>

                                        <?= renderTableCell(
                                            esc(
                                                $row['created']
                                                    ?: '---'
                                            ),
                                            'mono-cell'
                                        ) ?>


                                    <?php elseif (
                                        $type === 'playerips'
                                    ): ?>

                                        <?= renderTableCell(
                                            renderPlayerCell(
                                                $row['name'] ?? '',
                                                $row['steamid'] ?? ''
                                            )
                                        ) ?>

                                        <?= renderTableCell(
                                            esc($row['address'] ?? '---'),
                                            'mono-cell'
                                        ) ?>

                                        <?= renderTableCell(
                                            esc(
                                                $row['used_at']
                                                    ?: '---'
                                            ),
                                            'mono-cell'
                                        ) ?>

                                    <?php endif; ?>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>


            <?php
            $prevPage = max(1, $pageNum - 1);
            $nextPage = min($totalPages, $pageNum + 1);
            $windowStart = max(1, $pageNum - 2);
            $windowEnd = min($totalPages, $pageNum + 2);

            if ($windowEnd - $windowStart < 4) {
                $windowStart = max(1, min($windowStart, $totalPages - 4));
                $windowEnd = min($totalPages, max($windowEnd, 5));
            }
            ?>

            <div class="banlist-footer-controls">
                <div class="banlist-range">
                    نمایش
                    <?= $totalItems > 0 ? $pageStartIndex : 0 ?>
                    تا
                    <?= $pageEndIndex ?>
                    از
                    <?= number_format($totalItems) ?>
                    مورد
                </div>

                <div class="banlist-page-size">
                    <span>نمایش در هر صفحه:</span>
                    <?php foreach ([10, 15, 25, 50, 100] as $size): ?>
                        <a
                            href="<?= banlistBuildUrl(['per_page' => $size, 'page_num' => 1]) ?>"
                            class="ban-size-btn <?= (int)$perPage === $size ? 'active' : '' ?>"
                        >
                            <?= $size ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="banlist-pagination">
                    <a
                        class="ban-page-btn <?= $pageNum <= 1 ? 'disabled' : '' ?>"
                        href="<?= banlistBuildUrl(['page_num' => $prevPage]) ?>"
                        aria-label="Previous page"
                    >
                        <i class="fas fa-angle-right"></i>
                    </a>

                    <?php for ($p = $windowStart; $p <= $windowEnd; $p++): ?>
                        <a
                            class="ban-page-btn <?= $p === $pageNum ? 'active' : '' ?>"
                            href="<?= banlistBuildUrl(['page_num' => $p]) ?>"
                        >
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>

                    <a
                        class="ban-page-btn <?= $pageNum >= $totalPages ? 'disabled' : '' ?>"
                        href="<?= banlistBuildUrl(['page_num' => $nextPage]) ?>"
                        aria-label="Next page"
                    >
                        <i class="fas fa-angle-left"></i>
                    </a>
                </div>
            </div>

            <?php endif; ?>

        </div>

    </div>

</div>
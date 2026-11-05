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

$activeTab = $tabs[$type] ?? $tabs['bans'];

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


<div class="banlist-page">

    <!-- =========================================
         Page Header
    ========================================== -->

    <section class="banlist-hero" aria-labelledby="banlistHeroTitle">
        <div class="banlist-hero-art" aria-hidden="true">
            <span class="ban-hero-grid"></span>
            <span class="ban-hero-orbit ban-hero-orbit--outer"></span>
            <span class="ban-hero-orbit ban-hero-orbit--inner"></span>
            <span class="ban-hero-glow"></span>

            <div class="ban-hero-emblem">
                <span>FC / 02</span>
                <i class="fa-solid fa-gavel"></i>
            </div>

            <span class="ban-hero-tag ban-hero-tag--top">FRAGCITY / MODERATION</span>
            <span class="ban-hero-tag ban-hero-tag--bottom">CS2 // COMMUNITY</span>

            <div class="ban-hero-float-card ban-hero-float-card--top">
                <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                <span>FAIR PLAY</span>
            </div>
            <div class="ban-hero-float-card ban-hero-float-card--bottom">
                <span class="ban-hero-status-dot"></span>
                <span>SERVER RECORDS</span>
            </div>
        </div>

        <div class="banlist-hero-copy">
            <div class="banlist-hero-eyebrow">
                <span class="banlist-hero-eyebrow-mark" aria-hidden="true"></span>
                FRAGCITY <span class="banlist-hero-slash">/</span> MODERATION
            </div>

            <h1 id="banlistHeroTitle">سوابق سرورها، <span>همه یه‌جا</span></h1>

            <p>
                بن، رفع بن، میوت و اخطارها رو ببین؛ دنبال اسم بازیکن یا SteamID بگرد و سریع به سابقه‌ای که می‌خوای برس.
            </p>

            <div class="banlist-hero-meta">
                <span><i class="fa-solid fa-list-check" aria-hidden="true"></i> بخش فعلی: <strong><?= esc($activeTab['label']) ?></strong></span>
                <span><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> سوابق ثبت‌شده در سرورهای FragCity</span>
            </div>

            <div class="banlist-hero-bottom">
                <a class="banlist-hero-action" href="#banlistRecords">
                    <i class="fa-solid fa-folder-open" aria-hidden="true"></i>
                    دیدن سوابق
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                </a>
                <span class="banlist-hero-note">
                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                    اطلاعات حساس فقط برای افراد مجاز
                </span>
            </div>
        </div>
    </section>

    <section class="banlist-stats-grid" aria-label="آمار بخش فعلی">
        <article class="banlist-stat-card">
            <span class="banlist-stat-icon"><i class="fa-solid fa-database" aria-hidden="true"></i></span>
            <div class="banlist-stat-copy">
                <span>نتیجه‌های این بخش</span>
                <strong><?= number_format($totalItems) ?></strong>
            </div>
        </article>

        <article class="banlist-stat-card">
            <span class="banlist-stat-icon"><i class="fa-solid fa-eye" aria-hidden="true"></i></span>
            <div class="banlist-stat-copy">
                <span>نمایش در این صفحه</span>
                <strong><?= number_format(count($data)) ?></strong>
            </div>
        </article>

        <article class="banlist-stat-card">
            <span class="banlist-stat-icon"><i class="fa-solid fa-layer-group" aria-hidden="true"></i></span>
            <div class="banlist-stat-copy">
                <span>صفحه فعلی</span>
                <strong><?= number_format($pageNum) ?> <small>/ <?= number_format($totalPages) ?></small></strong>
            </div>
        </article>
    </section>

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
    aria-current="<?= $type === $key ? 'page' : 'false' ?>"
    data-ban-tab
    data-type="<?= esc($key) ?>"
>
    <i class="fas <?= esc($tab['icon']) ?>"></i>
    <span><?= esc($tab['label']) ?></span>
</a>

            <?php endforeach; ?>

        </div>

        <div class="banlist-toolbar">
            <div class="banlist-toolbar-heading">
                <span class="banlist-toolbar-icon" aria-hidden="true">
                    <i class="fa-solid fa-magnifying-glass-chart"></i>
                </span>
                <div class="banlist-toolbar-copy">
                    <span class="banlist-toolbar-kicker">RECORD FINDER</span>
                    <strong>دنبال چی می‌گردی؟</strong>
                    <small>اسم، SteamID، دلیل یا اطلاعات قابل‌دسترسی رو جستجو کن.</small>
                </div>
            </div>

            <form class="banlist-search-form" method="get" role="search" aria-label="جستجو در سوابق">
                <input type="hidden" name="page" value="banlist">
                <input type="hidden" name="type" value="<?= esc($type) ?>">
                <input type="hidden" name="per_page" value="<?= (int)$perPage ?>">
                <input type="hidden" name="page_num" value="1">

                <label class="banlist-search-field" for="banlistSearch">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input
                        id="banlistSearch"
                        type="search"
                        name="q"
                        value="<?= esc($search) ?>"
                        class="banlist-search-input"
                        placeholder="<?= $showSensitiveData ? 'اسم بازیکن، SteamID، IP، دلیل یا ادمین...' : 'اسم بازیکن، SteamID، دلیل یا ادمین...' ?>"
                        autocomplete="off"
                    >
                    <kbd>/</kbd>
                </label>

                <button type="submit" class="banlist-search-btn">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    بگرد
                </button>

                <?php if ($search !== ''): ?>
                    <a href="<?= banlistBuildUrl(['q' => '', 'page_num' => 1]) ?>" class="banlist-clear-btn">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        پاک کردن
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- =====================================
             Table Area
        ====================================== -->

        <div class="banlist-table-wrapper" id="banlistRecords">


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
<?php
// pages/banlist.php

require_once __DIR__ . '/../database/kyonxcbt_simpleadmin_plugin.php';

$pageTitle = "لیست محرومیت‌ها | FragCity";

$type = $_GET['type'] ?? 'bans';

$allowedTypes = [
    'bans',
    'unbans',
    'mutes',
    'unmutes',
    'warns',
    'playerips'
];

if (!in_array($type, $allowedTypes, true)) {
    $type = 'bans';
}

switch ($type) {

    case 'bans':

        $sql = "
            SELECT
                id,
                player_name,
                player_steamid,
                player_ip,
                admin_name,
                admin_steamid,
                reason,
                duration,
                ends,
                created
            FROM sa_bans
            WHERE status = 'ACTIVE'
            ORDER BY created DESC
        ";

        break;


    case 'unbans':

        $sql = "
            SELECT
                u.id AS unban_id,
                u.ban_id,
                u.reason AS unban_reason,
                u.date AS unban_date,
                b.player_name,
                b.player_steamid,
                b.player_ip,
                b.admin_name,
                b.admin_steamid,
                b.reason AS ban_reason,
                b.created AS ban_created
            FROM sa_unbans u
            JOIN sa_bans b
                ON u.ban_id = b.id
            ORDER BY u.date DESC
        ";

        break;


    case 'mutes':

        $sql = "
            SELECT
                id,
                player_name,
                player_steamid,
                admin_name,
                admin_steamid,
                reason,
                duration,
                ends,
                created,
                type
            FROM sa_mutes
            WHERE status = 'ACTIVE'
            ORDER BY created DESC
        ";

        break;


    case 'unmutes':

        $sql = "
            SELECT
                u.id AS unmute_id,
                u.mute_id,
                u.reason AS unmute_reason,
                u.date AS unmute_date,
                m.player_name,
                m.player_steamid,
                m.admin_name,
                m.admin_steamid,
                m.reason AS mute_reason,
                m.type,
                m.created AS mute_created
            FROM sa_unmutes u
            JOIN sa_mutes m
                ON u.mute_id = m.id
            ORDER BY u.date DESC
        ";

        break;


    case 'warns':

        $sql = "
            SELECT
                id,
                player_name,
                player_steamid,
                admin_name,
                admin_steamid,
                reason,
                duration,
                ends,
                created
            FROM sa_warns
            WHERE status = 'ACTIVE'
            ORDER BY created DESC
        ";

        break;


    case 'playerips':

        $sql = "
            SELECT
                steamid,
                name,
                address,
                used_at
            FROM sa_players_ips
            ORDER BY used_at DESC
        ";

        break;
}


$search = trim((string)($_GET['q'] ?? ''));
$pageNum = max(1, (int)($_GET['page_num'] ?? 1));
$perPage = (int)($_GET['per_page'] ?? 15);

$allowedPerPage = [10, 15, 25, 50, 100];
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 15;
}

$orderMap = [
    'bans'      => 'created',
    'unbans'    => 'unban_date',
    'mutes'     => 'created',
    'unmutes'   => 'unmute_date',
    'warns'     => 'created',
    'playerips' => 'used_at',
];

$searchBindings = [];
$searchSqlParts = [];

if ($search !== '') {
    $like = '%' . $search . '%';
    $searchBindings[':search'] = $like;

    switch ($type) {
        case 'bans':
            $searchSqlParts = [
                "base.player_name LIKE :search",
                "base.player_steamid LIKE :search",
                "base.player_ip LIKE :search",
                "base.admin_name LIKE :search",
                "base.admin_steamid LIKE :search",
                "base.reason LIKE :search",
            ];
            break;

        case 'unbans':
            $searchSqlParts = [
                "base.player_name LIKE :search",
                "base.player_steamid LIKE :search",
                "base.player_ip LIKE :search",
                "base.admin_name LIKE :search",
                "base.admin_steamid LIKE :search",
                "base.ban_reason LIKE :search",
                "base.unban_reason LIKE :search",
            ];
            break;

        case 'mutes':
            $searchSqlParts = [
                "base.player_name LIKE :search",
                "base.player_steamid LIKE :search",
                "base.admin_name LIKE :search",
                "base.admin_steamid LIKE :search",
                "base.reason LIKE :search",
                "base.type LIKE :search",
            ];
            break;

        case 'unmutes':
            $searchSqlParts = [
                "base.player_name LIKE :search",
                "base.player_steamid LIKE :search",
                "base.admin_name LIKE :search",
                "base.admin_steamid LIKE :search",
                "base.mute_reason LIKE :search",
                "base.unmute_reason LIKE :search",
                "base.type LIKE :search",
            ];
            break;

        case 'warns':
            $searchSqlParts = [
                "base.player_name LIKE :search",
                "base.player_steamid LIKE :search",
                "base.admin_name LIKE :search",
                "base.admin_steamid LIKE :search",
                "base.reason LIKE :search",
            ];
            break;

        case 'playerips':
            $searchSqlParts = [
                "base.name LIKE :search",
                "base.steamid LIKE :search",
                "CAST(base.address AS CHAR) LIKE :search",
            ];
            break;
    }
}

$searchWhere = $searchSqlParts
    ? ' WHERE (' . implode(' OR ', $searchSqlParts) . ')'
    : '';

$error = null;
$allData = [];
$data = [];
$totalItems = 0;
$totalPages = 1;
$pageStartIndex = 0;
$pageEndIndex = 0;
$orderColumn = $orderMap[$type] ?? 'created';

try {
    $db = new RemoteBanlistDatabase();

    $pagedSql = "
        SELECT *
        FROM (
            $sql
        ) AS base
        $searchWhere
        ORDER BY base.$orderColumn DESC
    ";

    $allData = $db->select(
        $pagedSql,
        $searchBindings
    );

    if (!is_array($allData)) {
        $allData = [];
    }

    $totalItems = count($allData);
    $totalPages = max(1, (int)ceil($totalItems / $perPage));
    $pageNum = min($pageNum, $totalPages);

    $offset = ($pageNum - 1) * $perPage;

    $data = array_slice(
        $allData,
        $offset,
        $perPage
    );

    $pageStartIndex = $totalItems > 0 ? $offset + 1 : 0;
    $pageEndIndex = min($offset + $perPage, $totalItems);
} catch (Exception $e) {
    $error = $e->getMessage();
    $allData = [];
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
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}


function formatDuration($minutes): string
{
    $minutes = (int)$minutes;

    if ($minutes <= 0) {
        return 'نامحدود';
    }

    $days = floor($minutes / 1440);

    $hours = floor(
        ($minutes % 1440) / 60
    );

    $mins = $minutes % 60;

    $result = '';

    if ($days > 0) {
        $result .= $days . ' روز ';
    }

    if ($hours > 0) {
        $result .= $hours . ' ساعت ';
    }

    if ($mins > 0) {
        $result .= $mins . ' دقیقه';
    }

    return trim(
        $result ?: '0 دقیقه'
    );
}


function int2ip($int): string
{
    return long2ip(
        (int)$int
    );
}


function getUserAvatar($steamid): string
{
    $steamid = (string)$steamid;

    if (
        $steamid !== ''
    ) {

        $localAvatarPath =
            __DIR__ .
            '/../img/avatars/avatar_' .
            $steamid .
            '.jpg';

        if (
            file_exists(
                $localAvatarPath
            )
        ) {

            return
                'img/avatars/avatar_' .
                $steamid .
                '.jpg?' .
                filemtime(
                    $localAvatarPath
                );

        }

        return
            'https://avatars.steamstatic.com/' .
            rawurlencode($steamid) .
            '?size=medium';
    }

    return 'assets/images/default_avatar.png';
}


function renderPlayerCell(
    string $name,
    string $steamid
): string {

    $avatar =
        getUserAvatar($steamid);

    $steamShort =
        $steamid !== ''
            ? substr(
                $steamid,
                -8
            )
            : '';

    return '
        <div class="ban-player">

            <img
                src="' . esc($avatar) . '"
                alt="' . esc($name) . '"
                class="ban-avatar"
                onerror="this.src=\'assets/images/default_avatar.png\'"
            >

            <div class="ban-player-info">

                <span class="ban-player-name">
                    ' . esc($name) . '
                </span>

                ' . (
                    $steamShort !== ''
                        ? '
                            <span class="ban-steam-id">
                                ' . esc($steamShort) . '
                            </span>
                        '
                        : ''
                ) . '

            </div>

        </div>
    ';
}


function renderAdminCell(
    string $name,
    string $steamid
): string {

    $avatar =
        getUserAvatar($steamid);

    $steamShort =
        $steamid !== ''
            ? substr(
                $steamid,
                -8
            )
            : '';

    return '
        <div class="ban-player ban-admin">

            <img
                src="' . esc($avatar) . '"
                alt="' . esc($name) . '"
                class="ban-avatar"
                onerror="this.src=\'assets/images/default_avatar.png\'"
            >

            <div class="ban-player-info">

                <span class="ban-player-name">
                    ' . esc($name) . '
                </span>

                ' . (
                    $steamShort !== ''
                        ? '
                            <span class="ban-steam-id">
                                ' . esc($steamShort) . '
                            </span>
                        '
                        : ''
                ) . '

            </div>

        </div>
    ';
}


function renderTableCell(
    $content,
    string $class = ''
): string {

    return '
        <td class="ban-table-cell ' .
        esc($class) .
        '">
            ' .
            $content .
            '
        </td>
    ';
}


/* =========================================================
   Tabs
========================================================= */

$tabs = [

    'bans' => [
        'icon'  => 'fa-ban',
        'label' => 'بن‌ها',
        'tone'  => 'danger'
    ],

    'unbans' => [
        'icon'  => 'fa-rotate-left',
        'label' => 'رفع بن‌ها',
        'tone'  => 'success'
    ],

    'mutes' => [
        'icon'  => 'fa-volume-xmark',
        'label' => 'میوت / گگ',
        'tone'  => 'danger'
    ],

    'unmutes' => [
        'icon'  => 'fa-volume-high',
        'label' => 'رفع میوت',
        'tone'  => 'success'
    ],

    'warns' => [
        'icon'  => 'fa-triangle-exclamation',
        'label' => 'اخطارها',
        'tone'  => 'warning'
    ],

    'playerips' => [
        'icon'  => 'fa-network-wired',
        'label' => 'آی‌پی بازیکنان',
        'tone'  => 'info'
    ]

];


function banlistBuildUrl(array $params = []): string
{
    $query = array_merge(
        [
            'page' => 'banlist',
            'type' => $GLOBALS['type'] ?? 'bans',
            'q' => $GLOBALS['search'] ?? '',
            'per_page' => $GLOBALS['perPage'] ?? 15,
            'page_num' => $GLOBALS['pageNum'] ?? 1,
        ],
        $params
    );

    foreach (['page_num', 'per_page', 'q', 'type', 'page'] as $key) {
        if (!array_key_exists($key, $query)) {
            continue;
        }
    }

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
        border: 1px solid rgba(51, 65, 85, 0.68);
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
        background: #0b1220;
        border: 1px solid #263653;
        color: #f8fafc;
        font: inherit;
        outline: none;
    }
    .banlist-search-input:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.14);
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
        border: 1px solid #263653;
        background: #0b1220;
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
        border-color: rgba(59, 130, 246, 0.48);
        background: rgba(15, 23, 42, 0.96);
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
        border-color: rgba(96, 165, 250, 0.42);
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.10), rgba(15, 23, 42, 0.96));
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
        border-top: 1px solid rgba(51, 65, 85, 0.55);
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
        border-color: rgba(96, 165, 250, 0.42);
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.10), rgba(15, 23, 42, 0.96));
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
        background: rgba(37, 99, 235, 0.14) !important;
        color: #60a5fa !important;
        border-color: rgba(96, 165, 250, 0.30) !important;
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
                    placeholder="جستجو در نام، SteamID، IP، دلیل و ادمین..."
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

                                    <th>
                                        <i class="fas fa-globe"></i>
                                        IP
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
                                    $type === 'unbans'
                                ): ?>

                                    <th>
                                        <i class="fas fa-user"></i>
                                        Player
                                    </th>

                                    <th>
                                        <i class="fas fa-globe"></i>
                                        IP
                                    </th>

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

                                    <th>
                                        <i class="fas fa-globe"></i>
                                        IP
                                    </th>

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

                                        <?= renderTableCell(
                                            esc(
                                                $row['player_ip']
                                                    ?: '---'
                                            ),
                                            'mono-cell'
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
                                        $type === 'unbans'
                                    ): ?>

                                        <?= renderTableCell(
                                            renderPlayerCell(
                                                $row['player_name'] ?? '',
                                                $row['player_steamid'] ?? ''
                                            )
                                        ) ?>

                                        <?= renderTableCell(
                                            esc(
                                                $row['player_ip']
                                                    ?: '---'
                                            ),
                                            'mono-cell'
                                        ) ?>

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
                                            esc(
                                                int2ip(
                                                    $row['address'] ?? 0
                                                )
                                            ),
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
<?php
// pages/leaderboard.php

$pageTitle = "Leaderboard | Top Players";

require_once __DIR__ . '/../database/kyonxcbt_ranks_plugin.php';

$db = new RemoteRanksDatabase();

$sort = $_GET['sort'] ?? 'points';
$order = $_GET['order'] ?? 'DESC';
$search = trim((string)($_GET['q'] ?? ''));
$pageNum = max(1, (int)($_GET['page_num'] ?? 1));
$perPage = (int)($_GET['per_page'] ?? 15);

$allowedSorts = [
    'points',
    'kills',
    'deaths',
    'assists',
    'headshots',
    'wins',
    'playtime'
];

if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'points';
}

$allowedPerPage = [10, 15, 25, 50, 100];
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 15;
}

$orderDir = ($order === 'ASC')
    ? 'ASC'
    : 'DESC';

$fieldMap = [
    'points'    => 'points',
    'kills'     => 'kills',
    'deaths'    => 'deaths',
    'assists'   => 'assists',
    'headshots' => 'headshots',
    'wins'      => 'wins',
    'playtime'  => 'playtime'
];

$orderField = $fieldMap[$sort];

$searchBindings = [];
$searchClause = '';

if ($search !== '') {
    $searchBindings[':search'] = '%' . $search . '%';
    $searchClause = " WHERE steam LIKE :search OR name LIKE :search";
}

$sql = "
    SELECT
        steam,
        name,
        value AS points,
        kills,
        deaths,
        assists,
        headshots,
        round_win AS wins,
        playtime
    FROM lvl_base
    $searchClause
    ORDER BY $orderField $orderDir
";

$db = new RemoteRanksDatabase();

$allPlayers = $db->select($sql, $searchBindings);
if (!is_array($allPlayers)) {
    $allPlayers = [];
}

$totalPlayers = count($allPlayers);
$totalKills = array_sum(array_map('intval', array_column($allPlayers, 'kills')));
$totalPoints = array_sum(array_map('intval', array_column($allPlayers, 'points')));
$totalPlaytime = array_sum(array_map('intval', array_column($allPlayers, 'playtime')));

$totalPages = max(1, (int)ceil(max(1, $totalPlayers) / $perPage));
$pageNum = min($pageNum, $totalPages);
$offset = ($pageNum - 1) * $perPage;

$players = array_slice($allPlayers, $offset, $perPage);
$topThreePlayers = array_slice($allPlayers, 0, 3);
$visiblePlayerCount = count($players);

$topMaxPoints = !empty($allPlayers)
    ? max(1, (int)($allPlayers[0]['points'] ?? 1))
    : 1;

/* =========================================================
   Helper Functions
========================================================= */

function leaderboardFormatPlaytime($seconds)
{
    $seconds = (int)$seconds;

    $hours = floor($seconds / 3600);

    $minutes = floor(
        ($seconds % 3600) / 60
    );

    if ($hours > 0) {
        return $hours . 'h ' . $minutes . 'm';
    }

    return $minutes . 'm';
}


function leaderboardSteamToSteamId64($steam)
{
    if (
        !preg_match(
            '/^STEAM_(\d+):(\d+):(\d+)$/',
            (string)$steam,
            $matches
        )
    ) {
        return null;
    }

    return (string)(
        ((int)$matches[3] * 2)
        + (int)$matches[2]
        + 76561197960265728
    );
}


function leaderboardGetUserAvatar($steam)
{
    $steam = (string)$steam;

    if (
        preg_match(
            '/^STEAM_1:\d+:\d+$/',
            $steam
        )
    ) {

        $steamid64 =
            leaderboardSteamToSteamId64(
                $steam
            );

        if ($steamid64) {

            $localPath64 =
                __DIR__ .
                '/../img/avatars/avatar_' .
                $steamid64 .
                '.jpg';

            if (
                file_exists(
                    $localPath64
                )
            ) {

                return
                    'img/avatars/avatar_' .
                    $steamid64 .
                    '.jpg?' .
                    filemtime(
                        $localPath64
                    );
            }
        }

        $localPathSteam =
            __DIR__ .
            '/../img/avatars/avatar_' .
            $steam .
            '.jpg';

        if (
            file_exists(
                $localPathSteam
            )
        ) {

            return
                'img/avatars/avatar_' .
                $steam .
                '.jpg?' .
                filemtime(
                    $localPathSteam
                );
        }

        if ($steamid64) {

            return
                'https://avatars.steamstatic.com/' .
                rawurlencode($steamid64) .
                '?size=medium';
        }

        return 'assets/images/default_avatar.png';
    }

    $localPath =
        __DIR__ .
        '/../img/avatars/avatar_' .
        $steam .
        '.jpg';

    if (
        file_exists(
            $localPath
        )
    ) {

        return
            'img/avatars/avatar_' .
            $steam .
            '.jpg?' .
            filemtime(
                $localPath
            );
    }

    if ($steam !== '') {

        return
            'https://avatars.steamstatic.com/' .
            rawurlencode($steam) .
            '?size=medium';
    }

    return 'assets/images/default_avatar.png';
}


function leaderboardGetSteamShort($steam)
{
    $steam = (string)$steam;

    if ($steam === '') {
        return '';
    }

    $lastPart =
        strrchr(
            $steam,
            ':'
        );

    $short =
        $lastPart !== false
            ? ltrim(
                $lastPart,
                ':'
            )
            : $steam;

    if (
        strlen($short) > 8
    ) {

        $short =
            substr(
                $short,
                -8
            );
    }

    return $short;
}


function leaderboardGetKd(
    $kills,
    $deaths
) {
    $kills =
        (float)$kills;

    $deaths =
        (float)$deaths;

    if (
        $deaths > 0
    ) {

        return round(
            $kills / $deaths,
            2
        );
    }

    return $kills;
}


function leaderboardGetKdClass($kd)
{
    $kd = (float)$kd;

    if ($kd >= 2) {
        return 'is-excellent';
    }

    if ($kd >= 1) {
        return 'is-good';
    }

    if ($kd >= 0.7) {
        return 'is-average';
    }

    return 'is-low';
}


function leaderboardGetAccuracyBadge($hsPercent)
{
    $hsPercent =
        (int)$hsPercent;

    if ($hsPercent >= 70) {

        return [
            'label' => 'SharpShooter',
            'icon'  => 'fa-crosshairs',
            'class' => 'badge-sharpshooter'
        ];
    }

    if ($hsPercent >= 50) {

        return [
            'label' => 'Accurate',
            'icon'  => 'fa-bullseye',
            'class' => 'badge-accurate'
        ];
    }

    if ($hsPercent >= 30) {

        return [
            'label' => 'Good Aim',
            'icon'  => 'fa-fire',
            'class' => 'badge-good-aim'
        ];
    }

    return null;
}


function leaderboardEscape($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================================
   Stats
========================================================= */

$totalPlayers = (int)$totalPlayers;
$totalKills = (int)$totalKills;
$totalPoints = (int)$totalPoints;
$totalPlaytime = (int)$totalPlaytime;

/* =========================================================
   Sort Labels
========================================================= */

$sortLabels = [

    'points' => [
        'label' => 'Points',
        'icon'  => 'fa-star'
    ],

    'kills' => [
        'label' => 'Kills',
        'icon'  => 'fa-skull-crossbones'
    ],

    'deaths' => [
        'label' => 'Deaths',
        'icon'  => 'fa-skull'
    ],

    'assists' => [
        'label' => 'Assists',
        'icon'  => 'fa-handshake'
    ],

    'headshots' => [
        'label' => 'Headshots',
        'icon'  => 'fa-crosshairs'
    ],

    'wins' => [
        'label' => 'Wins',
        'icon'  => 'fa-trophy'
    ],

    'playtime' => [
        'label' => 'Playtime',
        'icon'  => 'fa-clock'
    ]

];

$currentSortLabel =
    $sortLabels[$sort]['label']
    ?? 'Points';

$currentSortIcon =
    $sortLabels[$sort]['icon']
    ?? 'fa-star';


$topMaxPoints =
    !empty($allPlayers)
        ? max(
            1,
            (int)$allPlayers[0]['points']
        )
        : 1;


function leaderboardBuildUrl(array $params = []): string
{
    $query = [
        'page' => 'leaderboard',
        'sort' => $GLOBALS['sort'] ?? 'points',
        'order' => $GLOBALS['orderDir'] ?? 'DESC',
        'q' => $GLOBALS['search'] ?? '',
        'per_page' => $GLOBALS['perPage'] ?? 15,
        'page_num' => $GLOBALS['pageNum'] ?? 1,
    ];

    foreach ($params as $key => $value) {
        $query[$key] = $value;
    }

    return '?' . http_build_query($query);
}
?>

<style>
    .leaderboard-toolbar,
    .leaderboard-footer-controls {
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
    .leaderboard-search-form,
    .leaderboard-sort-form {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
    }
    .leaderboard-search-wrap {
        display: flex;
        flex: 1 1 380px;
        gap: 8px;
        align-items: center;
    }
    .leaderboard-search,
    .leaderboard-sort-select {
        height: 48px;
        padding: 0 14px;
        border-radius: 13px;
        background: #0b1220;
        border: 1px solid #263653;
        color: #f8fafc;
        font: inherit;
        outline: none;
    }
    .leaderboard-search {
        flex: 1 1 auto;
        min-width: 220px;
    }
    .leaderboard-search:focus,
    .leaderboard-sort-select:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.14);
    }
    .leaderboard-search-btn,
    .leaderboard-order-btn,
    .leaderboard-page-btn,
    .leaderboard-size-btn {
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
    .leaderboard-search-btn:hover,
    .leaderboard-order-btn:hover,
    .leaderboard-page-btn:hover,
    .leaderboard-size-btn:hover {
        border-color: rgba(59, 130, 246, 0.48);
        background: rgba(15, 23, 42, 0.96);
        color: #fff;
    }
    .leaderboard-toolbar-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
        justify-content: flex-end;
    }
    .sort-caption {
        color: #94a3b8;
        font-size: 0.82rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .leaderboard-page-size {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
        justify-content: flex-end;
    }
    .leaderboard-page-size span,
    .leaderboard-range {
        color: #94a3b8;
        font-size: 0.85rem;
        font-weight: 700;
    }
    .leaderboard-size-btn.active,
    .leaderboard-page-btn.active {
        border-color: rgba(96, 165, 250, 0.42);
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.10), rgba(15, 23, 42, 0.96));
        color: #f8fafc;
    }
    .leaderboard-page-btn.disabled {
        opacity: 0.45;
        pointer-events: none;
    }
    .leaderboard-paging {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
        justify-content: flex-end;
    }
    .leaderboard-toolbar .clear-search {
        height: 44px;
        width: 44px;
    }
    .leaderboard-result-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 10px 18px;
        align-items: center;
        justify-content: space-between;
    }
    @media (max-width: 900px) {
        .leaderboard-toolbar,
        .leaderboard-footer-controls {
            flex-direction: column;
            align-items: stretch;
        }
        .leaderboard-search-wrap,
        .leaderboard-toolbar-actions,
        .leaderboard-page-size,
        .leaderboard-paging {
            justify-content: flex-start;
        }
        .leaderboard-search {
            min-width: 0;
            width: 100%;
        }
    }
</style>
<div
    class="leaderboard-page"
    data-current-sort="<?= leaderboardEscape($sort) ?>"
    data-current-order="<?= leaderboardEscape($orderDir) ?>"
>

    <!-- =====================================================
         HEADER
    ====================================================== -->

    <header class="leaderboard-header">

        <div class="leaderboard-heading">

            <div class="leaderboard-heading-icon">

                <i class="fas fa-trophy"></i>

            </div>

            <div>

                <div class="leaderboard-eyebrow">
                    FragCity Competitive Rankings
                </div>

                <h1>
                    Leaderboard
                </h1>

                <p>
                    رتبه‌بندی بازیکنان بر اساس عملکرد واقعی در سرورهای FragCity
                </p>

            </div>

        </div>


        <div class="leaderboard-current-sort">

            <span>
                <i class="fas <?= leaderboardEscape($currentSortIcon) ?>"></i>
                مرتب‌سازی بر اساس
            </span>

            <strong>
                <?= leaderboardEscape($currentSortLabel) ?>
            </strong>

            <i class="fas <?= $orderDir === 'ASC'
                ? 'fa-arrow-up'
                : 'fa-arrow-down' ?>"></i>

        </div>

    </header>


    <!-- =====================================================
         STATS
    ====================================================== -->

    <section class="leaderboard-stats-grid">

        <article class="leaderboard-stat-card">

            <div class="leaderboard-stat-icon blue">
                <i class="fas fa-users"></i>
            </div>

            <div class="leaderboard-stat-copy">

                <span>
                    Total Players
                </span>

                <strong>
                    <?= number_format($totalPlayers) ?>
                </strong>

            </div>

        </article>


        <article class="leaderboard-stat-card">

            <div class="leaderboard-stat-icon cyan">
                <i class="fas fa-skull-crossbones"></i>
            </div>

            <div class="leaderboard-stat-copy">

                <span>
                    Total Kills
                </span>

                <strong>
                    <?= number_format($totalKills) ?>
                </strong>

            </div>

        </article>


        <article class="leaderboard-stat-card">

            <div class="leaderboard-stat-icon purple">
                <i class="fas fa-star"></i>
            </div>

            <div class="leaderboard-stat-copy">

                <span>
                    Total Points
                </span>

                <strong>
                    <?= number_format($totalPoints) ?>
                </strong>

            </div>

        </article>


        <article class="leaderboard-stat-card">

            <div class="leaderboard-stat-icon green">
                <i class="fas fa-clock"></i>
            </div>

            <div class="leaderboard-stat-copy">

                <span>
                    Total Playtime
                </span>

                <strong>
                    <?= leaderboardEscape(
                        leaderboardFormatPlaytime(
                            $totalPlaytime
                        )
                    ) ?>
                </strong>

            </div>

        </article>

    </section>


    <!-- =====================================================
         MAIN CONTAINER
    ====================================================== -->

    <section class="leaderboard-container">


        <!-- =================================================
             ONLY NEW TOOLBAR
        ================================================== -->

        <div class="leaderboard-toolbar">

            <form class="leaderboard-search-form" method="get">
                <input type="hidden" name="page" value="leaderboard">
                <input type="hidden" name="sort" value="<?= leaderboardEscape($sort) ?>">
                <input type="hidden" name="order" value="<?= leaderboardEscape($orderDir) ?>">
                <input type="hidden" name="per_page" value="<?= (int)$perPage ?>">
                <input type="hidden" name="page_num" value="1">

                <div class="leaderboard-search-wrap">
                    <i class="fas fa-search"></i>

                    <input
                        type="search"
                        name="q"
                        value="<?= leaderboardEscape($search) ?>"
                        class="leaderboard-search"
                        placeholder="جستجوی بازیکن..."
                        autocomplete="off"
                    >

                    <button type="submit" class="leaderboard-search-btn">
                        <i class="fas fa-search"></i>
                        جستجو
                    </button>

                    <?php if ($search !== ''): ?>
                        <a href="<?= leaderboardBuildUrl(['q' => '', 'page_num' => 1]) ?>" class="leaderboard-search-btn">
                            <i class="fas fa-times"></i>
                            پاک کردن
                        </a>
                    <?php endif; ?>
                </div>
            </form>


            <form class="leaderboard-sort-form" method="get">
                <input type="hidden" name="page" value="leaderboard">
                <input type="hidden" name="q" value="<?= leaderboardEscape($search) ?>">
                <input type="hidden" name="per_page" value="<?= (int)$perPage ?>">
                <input type="hidden" name="page_num" value="1">

                <div class="leaderboard-toolbar-actions">

                    <span class="sort-caption">
                        <i class="fas fa-filter"></i>
                        مرتب‌سازی بر اساس
                    </span>

                    <select
                        name="sort"
                        class="leaderboard-sort-select"
                        aria-label="مرتب‌سازی لیدربورد"
                        onchange="this.form.submit()"
                    >
                        <?php foreach (
                            $sortLabels
                            as $sortKey =>
                            $sortItem
                        ): ?>
                            <option
                                value="<?= leaderboardEscape($sortKey) ?>"
                                <?= $sort === $sortKey ? 'selected' : '' ?>
                            >
                                <?= leaderboardEscape($sortItem['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <a
                        href="<?= leaderboardBuildUrl([
                            'order' => $orderDir === 'ASC' ? 'DESC' : 'ASC',
                            'page_num' => 1
                        ]) ?>"
                        class="leaderboard-order-btn"
                        title="تغییر ترتیب مرتب‌سازی"
                    >
                        <i class="fas <?= $orderDir === 'ASC' ? 'fa-arrow-up' : 'fa-arrow-down' ?>"></i>
                        <span><?= $orderDir === 'ASC' ? 'صعودی' : 'نزولی' ?></span>
                    </a>

                </div>
            </form>

        </div>


        <!-- =================================================
             DYNAMIC CONTENT
        ================================================== -->

        <div
            class="leaderboard-dynamic-content"
            data-leaderboard-dynamic
        >


            <!-- =============================================
                 TOP 3
            ============================================== -->

            <?php if (!empty($topThreePlayers)): ?>

                <section class="leaderboard-podium">

                    <?php
                    $podiumIndexes = [
                        1,
                        0,
                        2
                    ];
                    ?>

                    <?php foreach (
                        $podiumIndexes
                        as $podiumIndex
                    ): ?>

                        <?php
                        $podiumPlayer =
                            $topThreePlayers[$podiumIndex]
                            ?? null;

                        if (!$podiumPlayer) {
                            continue;
                        }

                        $podiumRank =
                            $podiumIndex + 1;

                        $podiumAvatar =
                            leaderboardGetUserAvatar(
                                $podiumPlayer['steam']
                            );

                        $podiumKd =
                            leaderboardGetKd(
                                $podiumPlayer['kills'],
                                $podiumPlayer['deaths']
                            );

                        $podiumHsPercent =
                            $podiumPlayer['kills'] > 0
                                ? round(
                                    (
                                        $podiumPlayer['headshots'] /
                                        $podiumPlayer['kills']
                                    ) * 100
                                )
                                : 0;
                        ?>

                        <article
                            class="podium-card podium-rank-<?= (int)$podiumRank ?>"
                        >

                            <div class="podium-rank-badge">

                                <?php if (
                                    $podiumRank === 1
                                ): ?>

                                    🥇

                                <?php elseif (
                                    $podiumRank === 2
                                ): ?>

                                    🥈

                                <?php else: ?>

                                    🥉

                                <?php endif; ?>

                            </div>


                            <div class="podium-avatar-wrap">

                                <img
                                    src="<?= leaderboardEscape($podiumAvatar) ?>"
                                    alt="<?= leaderboardEscape(
                                        $podiumPlayer['name']
                                    ) ?>"
                                    class="podium-avatar"
                                    width="78"
                                    height="78"
                                    loading="eager"
                                    decoding="async"
                                    onerror="this.onerror=null;this.src='assets/images/default_avatar.png';"
                                >

                                <span class="podium-rank-number">
                                    #<?= (int)$podiumRank ?>
                                </span>

                            </div>


                            <h3>

                                <?= leaderboardEscape(
                                    $podiumPlayer['name']
                                    ?: 'Unknown'
                                ) ?>

                            </h3>


                            <div class="podium-steam">

                                <?= leaderboardEscape(
                                    leaderboardGetSteamShort(
                                        $podiumPlayer['steam']
                                    )
                                ) ?>

                            </div>


                            <strong class="podium-points">

                                <?= number_format(
                                    (int)$podiumPlayer['points']
                                ) ?>

                                <span>
                                    pts
                                </span>

                            </strong>


                            <div class="podium-mini-stats">

                                <span>

                                    <i class="fas fa-skull-crossbones"></i>

                                    <?= number_format(
                                        (int)$podiumPlayer['kills']
                                    ) ?>

                                </span>


                                <span>

                                    <i class="fas fa-crosshairs"></i>

                                    <?= (int)$podiumHsPercent ?>%

                                </span>


                                <span>

                                    <i class="fas fa-gauge-high"></i>

                                    <?= number_format(
                                        $podiumKd,
                                        2
                                    ) ?>

                                </span>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </section>

            <?php endif; ?>


            <!-- =============================================
                 RESULT BAR
            ============================================== -->

            <div
                class="leaderboard-result-bar"
                id="leaderboardResultBar"
            >

                <span>
                    <i class="fas fa-users"></i>
                    نمایش
                    <strong id="visiblePlayerCount">
                        <?= number_format($visiblePlayerCount) ?>
                    </strong>
                    بازیکن از
                    <strong>
                        <?= number_format($totalPlayers) ?>
                    </strong>
                </span>


                <span>

                    مرتب‌شده بر اساس

                    <strong>
                        <?= leaderboardEscape(
                            $currentSortLabel
                        ) ?>
                    </strong>

                </span>

            </div>


            <!-- =============================================
                 TABLE
            ============================================== -->

            <?php if (empty($players)): ?>

                <div class="leaderboard-empty-state">

                    <div class="leaderboard-empty-icon">

                        <i class="fas fa-database"></i>

                    </div>

                    <h3>
                        هیچ داده‌ای یافت نشد
                    </h3>

                    <p>
                        هنوز بازیکنی در سرورهای FragCity فعالیت نکرده است.
                    </p>

                </div>

            <?php else: ?>

                <div class="leaderboard-table-scroll">

                    <table class="leaderboard-table">

                        <thead>

                            <tr>

                                <th class="rank-column">
                                    #
                                </th>

                                <th>
                                    <i class="fas fa-user"></i>
                                    Player
                                </th>

                                <th>
                                    <i class="fas fa-star"></i>
                                    Points
                                </th>

                                <th>
                                    <i class="fas fa-skull-crossbones"></i>
                                    Kills
                                </th>

                                <th>
                                    <i class="fas fa-skull"></i>
                                    Deaths
                                </th>

                                <th>
                                    <i class="fas fa-gauge-high"></i>
                                    K/D
                                </th>

                                <th>
                                    <i class="fas fa-handshake"></i>
                                    Assists
                                </th>

                                <th>
                                    <i class="fas fa-crosshairs"></i>
                                    Headshots
                                </th>

                                <th>
                                    <i class="fas fa-trophy"></i>
                                    Wins
                                </th>

                                <th>
                                    <i class="fas fa-clock"></i>
                                    Playtime
                                </th>

                            </tr>

                        </thead>


                        <tbody id="leaderboardBody">

                            <?php
                            $rank = $offset + 1;

                            foreach (
                                $players as $player
                            ):

                                $currentRank =
                                    $rank++;

                                $avatarSrc =
                                    leaderboardGetUserAvatar(
                                        $player['steam']
                                    );

                                $steamShort =
                                    leaderboardGetSteamShort(
                                        $player['steam']
                                    );

                                $kd =
                                    leaderboardGetKd(
                                        $player['kills'],
                                        $player['deaths']
                                    );

                                $kdClass =
                                    leaderboardGetKdClass(
                                        $kd
                                    );

                                $hsPercent =
                                    $player['kills'] > 0
                                        ? round(
                                            (
                                                $player['headshots'] /
                                                $player['kills']
                                            ) * 100
                                        )
                                        : 0;

                                $hsPercent =
                                    max(
                                        0,
                                        min(
                                            100,
                                            $hsPercent
                                        )
                                    );

                                $accuracyBadge =
                                    leaderboardGetAccuracyBadge(
                                        $hsPercent
                                    );

                                $pointsPercent =
                                    $topMaxPoints > 0
                                        ? round(
                                            (
                                                $player['points'] /
                                                $topMaxPoints
                                            ) * 100
                                        )
                                        : 0;

                                $rankClass =
                                    $currentRank <= 3
                                        ? 'rank-top-' .
                                            $currentRank
                                        : '';

                                $playerSearchText =
                                    strtolower(
                                        trim(
                                            ($player['name'] ?? '') .
                                            ' ' .
                                            ($player['steam'] ?? '')
                                        )
                                    );

                            ?>

                                <tr
                                    class="<?= leaderboardEscape($rankClass) ?>"
                                    data-player-row
                                    data-search="<?= leaderboardEscape($playerSearchText) ?>"
                                >


                                    <!-- Rank -->

                                    <td class="rank-cell">

                                        <div class="rank-content">

                                            <?php if (
                                                $currentRank === 1
                                            ): ?>

                                                <span class="rank-medal">
                                                    🥇
                                                </span>

                                            <?php elseif (
                                                $currentRank === 2
                                            ): ?>

                                                <span class="rank-medal">
                                                    🥈
                                                </span>

                                            <?php elseif (
                                                $currentRank === 3
                                            ): ?>

                                                <span class="rank-medal">
                                                    🥉
                                                </span>

                                            <?php endif; ?>

                                            <span class="rank-number">

                                                #<?= (int)$currentRank ?>

                                            </span>

                                        </div>

                                    </td>


                                    <!-- Player -->

                                    <td class="player-cell">

                                        <div class="player-wrapper">

                                            <div class="player-avatar-wrap">

                                                <img
                                                    src="<?= leaderboardEscape($avatarSrc) ?>"
                                                    alt="<?= leaderboardEscape(
                                                        $player['name']
                                                        ?: 'Unknown'
                                                    ) ?>"
                                                    class="player-avatar"
                                                    width="42"
                                                    height="42"
                                                    loading="lazy"
                                                    decoding="async"
                                                    onerror="this.onerror=null;this.src='assets/images/default_avatar.png';"
                                                >

                                            </div>


                                            <div class="player-info">

                                                <div class="player-name-row">

                                                    <strong class="player-name">

                                                        <?= leaderboardEscape(
                                                            $player['name']
                                                            ?: 'Unknown'
                                                        ) ?>

                                                    </strong>


                                                    <?php if (
                                                        $accuracyBadge
                                                    ): ?>

                                                        <span
                                                            class="player-badge <?= leaderboardEscape(
                                                                $accuracyBadge['class']
                                                            ) ?>"
                                                        >

                                                            <i class="fas <?= leaderboardEscape(
                                                                $accuracyBadge['icon']
                                                            ) ?>"></i>

                                                            <?= leaderboardEscape(
                                                                $accuracyBadge['label']
                                                            ) ?>

                                                        </span>

                                                    <?php endif; ?>

                                                </div>


                                                <div class="player-meta">

                                                    <span class="player-steam">

                                                        <?= leaderboardEscape(
                                                            $steamShort
                                                        ) ?>

                                                    </span>


                                                    <button
                                                        type="button"
                                                        class="copy-steam-btn"
                                                        data-copy-steam="<?= leaderboardEscape(
                                                            $player['steam']
                                                        ) ?>"
                                                        title="Copy SteamID"
                                                    >

                                                        <i class="fas fa-copy"></i>

                                                    </button>

                                                </div>

                                            </div>

                                        </div>

                                    </td>


                                    <!-- Points -->

                                    <td class="points-cell">

                                        <div class="metric-progress-wrap">

                                            <div class="metric-number">

                                                <?= number_format(
                                                    (int)$player['points']
                                                ) ?>

                                            </div>

                                            <div class="metric-progress">

                                                <span
                                                    style="width:<?= (int)$pointsPercent ?>%"
                                                ></span>

                                            </div>

                                        </div>

                                    </td>


                                    <!-- Kills -->

                                    <td class="number-cell kills-cell">

                                        <?= number_format(
                                            (int)$player['kills']
                                        ) ?>

                                    </td>


                                    <!-- Deaths -->

                                    <td class="number-cell deaths-cell">

                                        <?= number_format(
                                            (int)$player['deaths']
                                        ) ?>

                                    </td>


                                    <!-- K/D -->

                                    <td class="kd-cell">

                                        <span
                                            class="kd-value <?= leaderboardEscape($kdClass) ?>"
                                        >

                                            <?= number_format(
                                                $kd,
                                                2
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- Assists -->

                                    <td class="number-cell">

                                        <?= number_format(
                                            (int)$player['assists']
                                        ) ?>

                                    </td>


                                    <!-- Headshots -->

                                    <td class="headshot-cell">

                                        <div class="headshot-value">

                                            <strong>

                                                <?= number_format(
                                                    (int)$player['headshots']
                                                ) ?>

                                            </strong>

                                            <span>

                                                <?= (int)$hsPercent ?>%

                                            </span>

                                        </div>


                                        <div class="headshot-progress">

                                            <span
                                                style="width:<?= (int)$hsPercent ?>%"
                                            ></span>

                                        </div>

                                    </td>


                                    <!-- Wins -->

                                    <td class="number-cell wins-cell">

                                        <?= number_format(
                                            (int)$player['wins']
                                        ) ?>

                                    </td>


                                    <!-- Playtime -->

                                    <td class="playtime-cell">

                                        <i class="fas fa-clock"></i>

                                        <?= leaderboardEscape(
                                            leaderboardFormatPlaytime(
                                                $player['playtime']
                                            )
                                        ) ?>

                                    </td>


                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

                <div class="leaderboard-footer-controls">
                    <div class="leaderboard-range">
                        نمایش
                        <?= $totalPlayers > 0 ? $offset + 1 : 0 ?>
                        تا
                        <?= min($offset + $perPage, $totalPlayers) ?>
                        از
                        <?= number_format($totalPlayers) ?>
                        بازیکن
                    </div>

                    <div class="leaderboard-page-size">
                        <span>نمایش در هر صفحه:</span>
                        <?php foreach ([10, 15, 25, 50, 100] as $size): ?>
                            <a
                                href="<?= leaderboardBuildUrl(['per_page' => $size, 'page_num' => 1]) ?>"
                                class="leaderboard-size-btn <?= (int)$perPage === $size ? 'active' : '' ?>"
                            >
                                <?= $size ?>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <div class="leaderboard-paging">
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

                        <a class="leaderboard-page-btn <?= $pageNum <= 1 ? 'disabled' : '' ?>" href="<?= leaderboardBuildUrl(['page_num' => $prevPage]) ?>">
                            <i class="fas fa-angle-right"></i>
                        </a>

                        <?php for ($p = $windowStart; $p <= $windowEnd; $p++): ?>
                            <a class="leaderboard-page-btn <?= $p === $pageNum ? 'active' : '' ?>" href="<?= leaderboardBuildUrl(['page_num' => $p]) ?>">
                                <?= $p ?>
                            </a>
                        <?php endfor; ?>

                        <a class="leaderboard-page-btn <?= $pageNum >= $totalPages ? 'disabled' : '' ?>" href="<?= leaderboardBuildUrl(['page_num' => $nextPage]) ?>">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    </div>
                </div>

            <?php endif; ?>


        </div>

    </section>


    <!-- =====================================================
         TOAST
    ====================================================== -->

    <div
        id="leaderboardToast"
        class="leaderboard-toast"
        role="status"
        aria-live="polite"
    >

        <i class="fas fa-check-circle"></i>

        <span>
            SteamID copied
        </span>

    </div>

</div>
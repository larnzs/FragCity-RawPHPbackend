<?php
// pages/leaderboard.php

$pageTitle = "جدول برترین بازیکن‌ها | FragCity";

require_once __DIR__ . '/../class/RanksRepository.php';

$sortInput = $_GET['sort'] ?? 'points';
$sort = is_string($sortInput) ? $sortInput : 'points';
$orderInput = $_GET['order'] ?? 'DESC';
$order = is_string($orderInput) ? $orderInput : 'DESC';
$searchInput = $_GET['q'] ?? '';
$search = is_string($searchInput) ? trim($searchInput) : '';
$pageInput = $_GET['page_num'] ?? 1;
$pageNum = is_scalar($pageInput) ? max(1, (int)$pageInput) : 1;
$perPageInput = $_GET['per_page'] ?? 15;
$perPage = is_scalar($perPageInput) ? (int)$perPageInput : 15;

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

$leaderboardError = false;
$leaderboardStats = [
    'total_players' => 0,
    'total_kills' => 0,
    'total_points' => 0,
    'total_playtime' => 0,
    'max_points' => 0,
];
$totalPlayers = 0;
$totalKills = 0;
$totalPoints = 0;
$totalPlaytime = 0;
$totalPages = 1;
$offset = 0;
$players = [];
$topThreePlayers = [];
$visiblePlayerCount = 0;
$topMaxPoints = 1;

try {
    $ranksRepository = new RanksRepository();
    $leaderboardResponse = $ranksRepository->getLeaderboard(
        $sort,
        $orderDir,
        $search,
        $pageNum,
        $perPage
    );

    $leaderboardStats = $leaderboardResponse['stats'];
    $pagination = $leaderboardResponse['pagination'];
    $totalPlayers = (int)$leaderboardStats['total_players'];
    $totalKills = (int)$leaderboardStats['total_kills'];
    $totalPoints = (int)$leaderboardStats['total_points'];
    $totalPlaytime = (int)$leaderboardStats['total_playtime'];
    $totalPages = (int)$pagination['total_pages'];
    $pageNum = (int)$pagination['page'];
    $offset = (int)$pagination['offset'];
    $players = $leaderboardResponse['players'];
    $topThreePlayers = $leaderboardResponse['top_three'];
    $visiblePlayerCount = count($players);
    $topMaxPoints = max(1, (int)$leaderboardStats['max_points']);
} catch (Throwable $e) {
    error_log('[FragCity] Leaderboard Ranks API is unavailable (' . get_class($e) . ').');
    $leaderboardError = true;
}

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
    $steam = trim((string)$steam);
    $steamid64 = preg_match('/^[0-9]{17}$/D', $steam)
        ? $steam
        : leaderboardSteamToSteamId64($steam);

    // User-uploaded avatars take priority over Steam's public avatar.
    $localCandidates = [];
    if ($steamid64 !== null && $steamid64 !== '') {
        $localCandidates[] = [
            'disk' => __DIR__ . '/../img/avatars/avatar_' . $steamid64 . '.jpg',
            'url' => '/img/avatars/avatar_' . rawurlencode($steamid64) . '.jpg',
        ];
    }
    if ($steam !== '' && !preg_match('/^[0-9]{17}$/D', $steam)) {
        $localCandidates[] = [
            'disk' => __DIR__ . '/../img/avatars/avatar_' . $steam . '.jpg',
            'url' => '/img/avatars/avatar_' . rawurlencode($steam) . '.jpg',
        ];
    }

    foreach ($localCandidates as $candidate) {
        if (is_file($candidate['disk'])) {
            return $candidate['url'] . '?v=' . (int)filemtime($candidate['disk']);
        }
    }

    // Steam CDN avatar URLs require an avatar hash, not a SteamID.
    if ($steamid64 !== null && $steamid64 !== '') {
        foreach (['webp', 'jpg', 'png'] as $extension) {
            $filename = 'steam_' . $steamid64 . '.' . $extension;
            $diskPath = __DIR__ . '/../img/steam-avatars/' . $filename;
            if (is_file($diskPath)) {
                return '/img/steam-avatars/' . rawurlencode($filename)
                    . '?v=' . (int)filemtime($diskPath);
            }
        }
    }

    return '/img/logoresponsive.png';
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
            'label' => 'نشونه‌زن',
            'icon'  => 'fa-crosshairs',
            'class' => 'badge-sharpshooter'
        ];
    }

    if ($hsPercent >= 50) {

        return [
            'label' => 'دقیق',
            'icon'  => 'fa-bullseye',
            'class' => 'badge-accurate'
        ];
    }

    if ($hsPercent >= 30) {

        return [
            'label' => 'هدشات‌زن',
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


/*
 * Warm the shared local Steam avatar cache in batches. Steam API credentials
 * remain server-side and are never exposed to browser code.
 */
$leaderboardAvatarSteamIds = [];
foreach (array_merge($topThreePlayers, $players) as $avatarPlayer) {
    if (!is_array($avatarPlayer)) {
        continue;
    }
    $avatarSteam = trim((string)($avatarPlayer['steam'] ?? ''));
    $avatarSteamId64 = preg_match('/^[0-9]{17}$/D', $avatarSteam)
        ? $avatarSteam
        : leaderboardSteamToSteamId64($avatarSteam);
    if (is_string($avatarSteamId64) && preg_match('/^[0-9]{17}$/D', $avatarSteamId64)) {
        $leaderboardAvatarSteamIds[$avatarSteamId64] = $avatarSteamId64;
    }
}

if ($leaderboardAvatarSteamIds !== []) {
    try {
        require_once __DIR__ . '/../class/SteamProfileService.php';
        SteamProfileService::cacheAvatarsForSteamIds(array_values($leaderboardAvatarSteamIds));
    } catch (Throwable $e) {
        error_log('[FragCity] Leaderboard Steam avatar cache update failed (' . get_class($e) . ').');
    }
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
        'label' => 'امتیاز',
        'icon'  => 'fa-star'
    ],

    'kills' => [
        'label' => 'کشت',
        'icon'  => 'fa-skull-crossbones'
    ],

    'deaths' => [
        'label' => 'مرگ',
        'icon'  => 'fa-skull'
    ],

    'assists' => [
        'label' => 'اسیست',
        'icon'  => 'fa-handshake'
    ],

    'headshots' => [
        'label' => 'هدشات',
        'icon'  => 'fa-crosshairs'
    ],

    'wins' => [
        'label' => 'برد',
        'icon'  => 'fa-trophy'
    ],

    'playtime' => [
        'label' => 'زمان بازی',
        'icon'  => 'fa-clock'
    ]

];

$currentSortLabel =
    $sortLabels[$sort]['label']
    ?? 'امتیاز';

$currentSortIcon =
    $sortLabels[$sort]['icon']
    ?? 'fa-star';


$topMaxPoints = max(1, (int)($leaderboardStats['max_points'] ?? 0));

$leaderboardGuyAssetPath = dirname(__DIR__) . '/img/webp/leaderboardguy.webp';
$leaderboardGuyAssetVersion = is_file($leaderboardGuyAssetPath) && function_exists('asset_version')
    ? (string) asset_version($leaderboardGuyAssetPath)
    : '1';


function leaderboardBuildUrl(array $params = []): string
{
    $query = [
        'sort' => $GLOBALS['sort'] ?? 'points',
        'order' => $GLOBALS['orderDir'] ?? 'DESC',
        'q' => $GLOBALS['search'] ?? '',
        'per_page' => $GLOBALS['perPage'] ?? 15,
        'page_num' => $GLOBALS['pageNum'] ?? 1,
    ];

    foreach ($params as $key => $value) {
        $query[$key] = $value;
    }

    return '/leaderboard/?' . http_build_query($query);
}
?>


<div
    class="leaderboard-page"
    data-current-sort="<?= leaderboardEscape($sort) ?>"
    data-current-order="<?= leaderboardEscape($orderDir) ?>"
>

    <!-- =====================================================
         HEADER
    ====================================================== -->

    <header class="leaderboard-header leaderboard-hero" aria-labelledby="leaderboardHeroTitle">
        <div class="leaderboard-hero-copy">
            <div class="leaderboard-eyebrow" data-fc-reveal="up">
                <span class="leaderboard-hero-eyebrow-mark" aria-hidden="true"></span>
                FRAGCITY <span>/</span> CS2 / رقابت
            </div>

            <h1 id="leaderboardHeroTitle" data-fc-reveal="up" data-fc-reveal-delay="70">
                ببین کی <span>اوله!</span>
            </h1>

            <p data-fc-reveal="up" data-fc-reveal-delay="130">
                ببین کی توی سرورها ترکونده! آمار بازیکن‌ها رو مقایسه کن و جای خودت رو بین بهترین‌های FragCity پیدا کن.
            </p>

            <div class="leaderboard-hero-meta" data-fc-reveal="up" data-fc-reveal-delay="180">
                <span><i class="fa-solid fa-users" aria-hidden="true"></i> <?= number_format($totalPlayers) ?> بازیکن توی جدول</span>
                <span><i class="fa-solid fa-chart-line" aria-hidden="true"></i> ۷ جور آمار</span>
                <span><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> آمار واقعی سرورها</span>
            </div>

            <div class="leaderboard-hero-bottom" data-fc-reveal="up" data-fc-reveal-delay="230">
                <a class="leaderboard-hero-action" href="#leaderboardTable">
                    <i class="fa-solid fa-ranking-star" aria-hidden="true"></i>
                    بریم سراغ جدول
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                </a>

                <div class="leaderboard-current-sort" aria-label="مرتب‌سازی فعلی">
                    <span>
                        <i class="fas <?= leaderboardEscape($currentSortIcon) ?>" aria-hidden="true"></i>
                        مرتب‌سازی با
                    </span>
                    <strong><?= leaderboardEscape($currentSortLabel) ?></strong>
                    <i class="fas <?= $orderDir === 'ASC' ? 'fa-arrow-up' : 'fa-arrow-down' ?>" aria-hidden="true"></i>
                </div>
            </div>
        </div>

        <div class="leaderboard-hero-art" aria-hidden="true">
            <span class="lb-hero-grid"></span>
            <span class="lb-hero-orbit lb-hero-orbit--outer"></span>
            <span class="lb-hero-orbit lb-hero-orbit--inner"></span>
            <span class="lb-hero-light"></span>

            <div class="lb-hero-emblem">
                <span>01</span>
                <i class="fa-solid fa-trophy"></i>
            </div>

            <?php if (is_file($leaderboardGuyAssetPath)): ?>
                <img
                    class="lb-hero-character"
                    src="/img/webp/leaderboardguy.webp?v=<?= rawurlencode($leaderboardGuyAssetVersion) ?>"
                    alt=""
                    loading="eager"
                    fetchpriority="high"
                    decoding="async"
                >
            <?php else: ?>
                <div class="lb-hero-character-placeholder">
                    <i class="fa-solid fa-user-ninja"></i>
                    <span>PLAYER ART SLOT</span>
                </div>
            <?php endif; ?>

            <span class="lb-hero-tag lb-hero-tag--top">FRAGCITY / ELITE</span>
            <span class="lb-hero-tag lb-hero-tag--bottom">CS2 // RANKED</span>

            <div class="lb-hero-float-card lb-hero-float-card--top">
                <i class="fa-solid fa-crown" aria-hidden="true"></i>
                <span>HALL OF FAME</span>
            </div>
            <div class="lb-hero-float-card lb-hero-float-card--bottom">
                <span class="lb-hero-float-number">#1</span>
                <span>OWN THE TOP</span>
            </div>
        </div>
    </header>


    <!-- =====================================================
         STATS
    ====================================================== -->

    <section class="leaderboard-stats-grid" id="leaderboardStats" aria-label="آمار کلی رتبه‌بندی">

        <article class="leaderboard-stat-card">

            <div class="leaderboard-stat-icon blue">
                <i class="fas fa-users"></i>
            </div>

            <div class="leaderboard-stat-copy">

                <span>
                    بازیکن‌ها
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
                    مجموع کشت‌ها
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
                    مجموع امتیازها
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
                    زمان بازی همه
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

    <section class="leaderboard-container" id="leaderboardTable">


        <!-- =================================================
             ONLY NEW TOOLBAR
        ================================================== -->

        <div class="leaderboard-toolbar">
            <div class="leaderboard-toolbar-heading">
                <span class="leaderboard-toolbar-icon" aria-hidden="true">
                    <i class="fa-solid fa-crosshairs"></i>
                </span>
                <div class="leaderboard-toolbar-copy">
                    <span class="leaderboard-toolbar-kicker">PLAYER FINDER</span>
                    <strong>بازیکن موردنظرت رو پیدا کن</strong>
                    <small>اسم بازیکن یا SteamID رو وارد کن و آمارش رو ببین.</small>
                </div>
            </div>

            <div class="leaderboard-toolbar-controls">
                <form class="leaderboard-search-form" method="get" role="search">
                    <input type="hidden" name="sort" value="<?= leaderboardEscape($sort) ?>">
                    <input type="hidden" name="order" value="<?= leaderboardEscape($orderDir) ?>">
                    <input type="hidden" name="per_page" value="<?= (int)$perPage ?>">
                    <input type="hidden" name="page_num" value="1">

                    <label class="leaderboard-search-field" for="leaderboardSearch">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <input
                            id="leaderboardSearch"
                            type="search"
                            name="q"
                            value="<?= leaderboardEscape($search) ?>"
                            placeholder="اسم بازیکن یا SteamID..."
                            autocomplete="off"
                            aria-label="جستجوی بازیکن بر اساس نام یا SteamID"
                        >
                        <kbd>/</kbd>
                    </label>

                    <button type="submit" class="leaderboard-search-btn">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        بگرد
                    </button>

                    <?php if ($search !== ''): ?>
                        <a
                            id="clearLeaderboardSearch"
                            href="<?= leaderboardBuildUrl(['q' => '', 'page_num' => 1]) ?>"
                            class="leaderboard-clear-btn"
                        >
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                            پاک کردن
                        </a>
                    <?php endif; ?>
                </form>

                <form class="leaderboard-sort-form" method="get">
                    <input type="hidden" name="q" value="<?= leaderboardEscape($search) ?>">
                    <input type="hidden" name="per_page" value="<?= (int)$perPage ?>">
                    <input type="hidden" name="page_num" value="1">

                    <div class="leaderboard-sort-field">
                        <label class="sort-caption" for="leaderboardSort">
                            <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                            مرتب‌سازی
                        </label>
                        <span class="leaderboard-select-wrap">
                            <select
                                id="leaderboardSort"
                                name="sort"
                                class="leaderboard-sort-select"
                                aria-label="مرتب‌سازی بازیکن‌ها"
                            >
                                <?php foreach ($sortLabels as $sortKey => $sortItem): ?>
                                    <option
                                        value="<?= leaderboardEscape($sortKey) ?>"
                                        <?= $sort === $sortKey ? 'selected' : '' ?>
                                    >
                                        <?= leaderboardEscape($sortItem['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                        </span>
                    </div>

                    <button
                        id="leaderboardOrder"
                        type="button"
                        class="leaderboard-order-btn"
                        data-order="<?= leaderboardEscape($orderDir) ?>"
                        title="تغییر ترتیب مرتب‌سازی"
                        aria-label="مرتب‌سازی <?= $orderDir === 'ASC' ? 'نزولی' : 'صعودی' ?>"
                    >
                        <i class="fa-solid <?= $orderDir === 'ASC' ? 'fa-arrow-up-wide-short' : 'fa-arrow-down-wide-short' ?>" aria-hidden="true"></i>
                        <span><?= $orderDir === 'ASC' ? 'صعودی' : 'نزولی' ?></span>
                    </button>
                </form>
            </div>
        </div>

        <!-- =================================================
             DYNAMIC CONTENT
        ================================================== -->

        <div
            class="leaderboard-dynamic-content"
            data-leaderboard-dynamic
        >

            <?php if ($leaderboardError): ?>
                <div class="leaderboard-empty-state" role="alert">
                    <div class="leaderboard-empty-icon">
                        <i class="fas fa-triangle-exclamation"></i>
                    </div>
                    <h3>فعلاً جدول لود نمی‌شه</h3>
                    <p>آمار سرورها فعلاً جواب نمی‌ده؛ یکم بعد دوباره امتحان کن.</p>
                </div>
            <?php else: ?>


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
                                    onerror="this.onerror=null;this.src='/img/logoresponsive.png';"
                                >

                                <span class="podium-rank-number">
                                    #<?= (int)$podiumRank ?>
                                </span>

                            </div>


                            <h3>

                                <?= leaderboardEscape(
                                    $podiumPlayer['name']
                                    ?: 'بازیکن ناشناس'
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
                                    امتیاز
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
                    <strong id="visibleبازیکنCount">
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
                        چیزی پیدا نکردیم
                    </h3>

                    <p>
                        هنوز آماری برای نمایش ثبت نشده.
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
                                    بازیکن
                                </th>

                                <th>
                                    <i class="fas fa-star"></i>
                                    امتیاز
                                </th>

                                <th>
                                    <i class="fas fa-skull-crossbones"></i>
                                    کشت
                                </th>

                                <th>
                                    <i class="fas fa-skull"></i>
                                    مرگ
                                </th>

                                <th>
                                    <i class="fas fa-gauge-high"></i>
                                    K/D
                                </th>

                                <th>
                                    <i class="fas fa-handshake"></i>
                                    اسیست
                                </th>

                                <th>
                                    <i class="fas fa-crosshairs"></i>
                                    هدشات
                                </th>

                                <th>
                                    <i class="fas fa-trophy"></i>
                                    برد
                                </th>

                                <th>
                                    <i class="fas fa-clock"></i>
                                    زمان بازی
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


                                    <!-- بازیکن -->

                                    <td class="player-cell">

                                        <div class="player-wrapper">

                                            <div class="player-avatar-wrap">

                                                <img
                                                    src="<?= leaderboardEscape($avatarSrc) ?>"
                                                    alt="<?= leaderboardEscape(
                                                        $player['name']
                                                        ?: 'بازیکن ناشناس'
                                                    ) ?>"
                                                    class="player-avatar"
                                                    width="42"
                                                    height="42"
                                                    loading="lazy"
                                                    decoding="async"
                                                    onerror="this.onerror=null;this.src='/img/logoresponsive.png';"
                                                >

                                            </div>


                                            <div class="player-info">

                                                <div class="player-name-row">

                                                    <strong class="player-name">

                                                        <?= leaderboardEscape(
                                                            $player['name']
                                                            ?: 'بازیکن ناشناس'
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
                                                        title="کپی SteamID"
                                                    >

                                                        <i class="fas fa-copy"></i>

                                                    </button>

                                                </div>

                                            </div>

                                        </div>

                                    </td>


                                    <!-- امتیاز -->

                                    <td class="points-cell">

                                        <div class="metric-progress-wrap">

                                            <div class="metric-number">

                                                <?= number_format(
                                                    (int)$player['points']
                                                ) ?>

                                            </div>

                                            <div
                                                class="metric-progress"
                                                role="progressbar"
                                                aria-label="درصد امتیاز این بازیکن نسبت به نفر اول"
                                                aria-valuemin="0"
                                                aria-valuemax="100"
                                                aria-valuenow="<?= (int)$pointsPercent ?>"
                                            >
                                                <span style="width:<?= (int)$pointsPercent ?>%"></span>
                                            </div>

                                        </div>

                                    </td>


                                    <!-- کشت -->

                                    <td class="number-cell kills-cell">

                                        <?= number_format(
                                            (int)$player['kills']
                                        ) ?>

                                    </td>


                                    <!-- مرگ -->

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


                                    <!-- اسیست -->

                                    <td class="number-cell">

                                        <?= number_format(
                                            (int)$player['assists']
                                        ) ?>

                                    </td>


                                    <!-- هدشات -->

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


                                        <div
                                            class="headshot-progress"
                                            role="progressbar"
                                            aria-label="درصد هدشات"
                                            aria-valuemin="0"
                                            aria-valuemax="100"
                                            aria-valuenow="<?= (int)$hsPercent ?>"
                                        >
                                            <span style="width:<?= (int)$hsPercent ?>%"></span>
                                        </div>

                                    </td>


                                    <!-- برد -->

                                    <td class="number-cell wins-cell">

                                        <?= number_format(
                                            (int)$player['wins']
                                        ) ?>

                                    </td>


                                    <!-- زمان بازی -->

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
            SteamID کپی شد
        </span>

    </div>

</div>
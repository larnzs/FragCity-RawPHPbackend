<?php
if (defined('TOP_HEADER_RENDERED')) {
    return;
}
define('TOP_HEADER_RENDERED', true);

$isLoggedIn = $isLoggedIn ?? isset($_SESSION['user_logged_in']);
$displayName = $displayName ?? ($_SESSION['username'] ?? '');
$displaySteamID = $displaySteamID ?? ($_SESSION['steamid'] ?? '');
$current_page = $current_page ?? 'home';
$headerCsrfToken = (string) ($authCsrfToken ?? ($_SESSION['auth_csrf_token'] ?? ''));

if (!function_exists('getUserAvatarHeader')) {
    function getUserAvatarHeader(string $steamid): string
    {
        if ($steamid !== '') {
            $avatarFile = 'avatar_' . preg_replace('/[^0-9]/', '', $steamid) . '.jpg';
            $avatarDiskPath = dirname(__DIR__) . '/img/avatars/' . $avatarFile;

            if (is_file($avatarDiskPath)) {
                return 'img/avatars/' . rawurlencode($avatarFile) . '?v=' . filemtime($avatarDiskPath);
            }

            $digits = preg_replace('/[^0-9]/', '', $steamid);

            // Prefer the locally cached Steam Community avatar after the user's
            // custom uploaded avatar. The two locations are intentionally separate.
            if (preg_match('/^[0-9]{17}$/D', (string)$digits)) {
                foreach (['jpg', 'png', 'webp'] as $extension) {
                    $steamAvatarFile = 'steam_' . $digits . '.' . $extension;
                    $steamAvatarDiskPath = dirname(__DIR__) . '/img/steam-avatars/' . $steamAvatarFile;
                    if (is_file($steamAvatarDiskPath)) {
                        return 'img/steam-avatars/' . rawurlencode($steamAvatarFile)
                            . '?v=' . filemtime($steamAvatarDiskPath);
                    }
                }
            }

            $profile = $_SESSION['steam_profile'] ?? [];
            $cache = is_string($digits) && $digits !== ''
                ? ($_SESSION['steam_profile_cache'][$digits]['player'] ?? [])
                : [];
            $steamAvatar = is_array($profile)
                && (string)($profile['steamid'] ?? '') === $digits
                ? (string)($profile['avatarfull'] ?? '')
                : (is_array($cache) ? (string)($cache['avatarfull'] ?? '') : '');
            if (filter_var($steamAvatar, FILTER_VALIDATE_URL)) {
                $parts = parse_url($steamAvatar);
                $host = strtolower((string)($parts['host'] ?? ''));
                $safeCdn = preg_match('/(^|\\.)steamstatic\\.com$/D', $host)
                    || preg_match('/(^|\\.)akamaihd\\.net$/D', $host);
                if (is_array($parts) && strtolower((string)($parts['scheme'] ?? '')) === 'https' && $safeCdn) {
                    return $steamAvatar;
                }
            }
        }

        return 'assets/images/default_avatar.png';
    }
}

$isPageActive = static function (array $pages) use ($current_page): string {
    return in_array($current_page, $pages, true) ? 'active' : '';
};

$morePages = ['buyserveropen', 'buyserver', 'launcher', 'leaderboard', 'leaderboard_vps', 'banlist', 'vip', 'status'];
$avatarSrc = $isLoggedIn ? getUserAvatarHeader((string) $displaySteamID) : '';
$steamShort = $displaySteamID !== '' ? substr((string) $displaySteamID, -8) : '';

// Read the same wallet balance source used by the former wallet page.
$headerWalletBalance = 0;
if ($isLoggedIn && trim((string)$displaySteamID) !== '') {
    try {
        require_once dirname(__DIR__) . '/class/customer_db.php';
        $headerWalletDb = new CustomerDB();
        $headerWalletRows = $headerWalletDb->select(
            'SELECT balance FROM user_wallets WHERE steamid = ? LIMIT 1',
            [(string)$displaySteamID]
        );
        $headerWalletBalance = max(0, (int)($headerWalletRows[0]['balance'] ?? 0));
    } catch (Throwable $walletError) {
        error_log('[FragCity] Header wallet balance unavailable.');
    }
}
$headerWalletBalanceLabel = number_format($headerWalletBalance) . ' تومان';

$headerSteamId64 = preg_match('/^[0-9]{17}$/D', (string)$displaySteamID)
    ? (string)$displaySteamID : '';
if ($headerSteamId64 === '' && preg_match('/^STEAM_[0-5]:([01]):([0-9]+)$/D', (string)$displaySteamID, $idMatch)) {
    $headerSteamId64 = (string)(76561197960265728 + ((int)$idMatch[2] * 2) + (int)$idMatch[1]);
}
$headerSteamProfile = $_SESSION['steam_profile'] ?? [];
if (!is_array($headerSteamProfile) || (string)($headerSteamProfile['steamid'] ?? '') !== $headerSteamId64) {
    $headerSteamProfile = $_SESSION['steam_profile_cache'][$headerSteamId64]['player'] ?? [];
}
if (!is_array($headerSteamProfile)) $headerSteamProfile = [];
if ($isLoggedIn && $headerSteamId64 !== '' && isset($pdo) && $pdo instanceof PDO) {
    try {
        $profileStmt = $pdo->prepare('SELECT steam_personaname, steam_profileurl, steam_avatar_url, steam_personastate, steam_communityvisibilitystate, steam_profilestate, steam_timecreated, steam_lastlogoff, steam_loccountrycode, steam_gameextrainfo FROM users WHERE steamid64 = ? LIMIT 1');
        $profileStmt->execute([$headerSteamId64]);
        $profileRow = $profileStmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($profileRow)) {
            $dbProfile = [
                'steamid' => $headerSteamId64,
                'personaname' => $profileRow['steam_personaname'] ?? '',
                'profileurl' => $profileRow['steam_profileurl'] ?? '',
                'avatarfull' => $profileRow['steam_avatar_url'] ?? '',
                'personastate' => $profileRow['steam_personastate'] ?? null,
                'communityvisibilitystate' => $profileRow['steam_communityvisibilitystate'] ?? null,
                'profilestate' => $profileRow['steam_profilestate'] ?? null,
                'timecreated' => $profileRow['steam_timecreated'] ?? null,
                'lastlogoff' => $profileRow['steam_lastlogoff'] ?? null,
                'loccountrycode' => $profileRow['steam_loccountrycode'] ?? '',
                'gameextrainfo' => $profileRow['steam_gameextrainfo'] ?? '',
            ];
            foreach ($dbProfile as $key => $value) {
                if ($value !== null && $value !== '') $headerSteamProfile[$key] = $value;
            }
        }
    } catch (Throwable $error) {
        error_log('[FragCity] Header Steam profile details unavailable.');
    }
}
$headerSteamPersonaName = trim((string)($headerSteamProfile['personaname'] ?? '')) ?: trim((string)$displayName);
if ($headerSteamPersonaName === '') $headerSteamPersonaName = 'Steam Player';
$headerVisibilityState = (int)($headerSteamProfile['communityvisibilitystate'] ?? 0);
$headerSteamVisibility = $headerVisibilityState === 3 ? 'عمومی' : ($headerVisibilityState === 1 ? 'خصوصی' : ($headerVisibilityState === 2 ? 'فقط دوستان' : 'نامشخص'));
$headerProfileState = isset($headerSteamProfile['profilestate']) ? ((int)$headerSteamProfile['profilestate'] === 1 ? 'تنظیم‌شده' : 'کامل نشده') : 'نامشخص';
$headerCreated = (int)($headerSteamProfile['timecreated'] ?? 0);
$headerCreatedLabel = $headerCreated > 0 ? date('Y-m-d', $headerCreated) : '—';
$headerLastLogoff = (int)($headerSteamProfile['lastlogoff'] ?? 0);
$headerLastLogoffLabel = $headerLastLogoff > 0 ? date('Y-m-d', $headerLastLogoff) : '—';
$headerCountry = strtoupper(trim((string)($headerSteamProfile['loccountrycode'] ?? '')));
if (!preg_match('/^[A-Z]{2}$/D', $headerCountry)) $headerCountry = '';
if ($isLoggedIn && $avatarSrc === 'assets/images/default_avatar.png') {
    $avatarCandidate = trim((string)($headerSteamProfile['avatarfull'] ?? ''));
    $avatarParts = $avatarCandidate !== '' ? parse_url($avatarCandidate) : false;
    $avatarHost = is_array($avatarParts) ? strtolower((string)($avatarParts['host'] ?? '')) : '';
    $avatarSafeHost = preg_match('/(^|\\.)steamstatic\\.com$/D', $avatarHost)
        || preg_match('/(^|\\.)akamaihd\\.net$/D', $avatarHost);
    if (is_array($avatarParts) && strtolower((string)($avatarParts['scheme'] ?? '')) === 'https' && $avatarSafeHost) {
        $avatarSrc = $avatarCandidate;
    }
}
$headerSteamProfileUrl = trim((string)($headerSteamProfile['profileurl'] ?? ''));
$steamUrlParts = $headerSteamProfileUrl !== '' ? parse_url($headerSteamProfileUrl) : false;
if (!is_array($steamUrlParts) || strtolower((string)($steamUrlParts['scheme'] ?? '')) !== 'https' || !in_array(strtolower((string)($steamUrlParts['host'] ?? '')), ['steamcommunity.com', 'www.steamcommunity.com'], true)) {
    $headerSteamProfileUrl = '';
}

// Resolve the authenticated player's CS2 FACEIT level through the shared,
// server-side cache. The API key remains in PHP and is never sent to the client.
$headerFaceitProfile = [];
$headerFaceitLevel = null;
$headerFaceitStatus = 'api_unavailable';
if ($isLoggedIn && preg_match('/^7656119[0-9]{10}$/D', $headerSteamId64)) {
    try {
        require_once __DIR__ . '/../class/FaceitApiClient.php';
        $headerFaceitRows = (new FaceitApiClient())->enrichPlayers([
            ['steam_id' => $headerSteamId64],
        ]);
        $headerFaceitProfile = is_array($headerFaceitRows[0] ?? null)
            ? $headerFaceitRows[0]
            : [];
        $candidateLevel = $headerFaceitProfile['faceit_level'] ?? null;
        $headerFaceitLevel = is_numeric($candidateLevel)
            && (int)$candidateLevel >= 1
            && (int)$candidateLevel <= 10
                ? (int)$candidateLevel
                : null;
        $headerFaceitStatus = (string)($headerFaceitProfile['faceit_status'] ?? 'api_unavailable');
    } catch (Throwable $faceitError) {
        error_log('[FragCity] Header FACEIT lookup unavailable.');
    }
}

$headerFaceitStatusLabel = [
    'unranked' => 'هنوز رتبه‌ای ثبت نشده',
    'no_cs2_profile' => 'پروفایل CS2 پیدا نشد',
    'profile_not_found' => 'اکانت FACEIT پیدا نشد',
    'api_not_configured' => 'اتصال FACEIT تنظیم نشده',
    'api_unavailable' => 'اطلاعات موقتاً در دسترس نیست',
    'steam_id_missing' => 'SteamID در دسترس نیست',
][$headerFaceitStatus] ?? 'اطلاعات FACEIT در دسترس نیست';

$headerFaceitEloCandidate = $headerFaceitProfile['faceit_elo'] ?? null;
$headerFaceitElo = is_numeric($headerFaceitEloCandidate) ? (int)$headerFaceitEloCandidate : null;
if ($headerFaceitElo !== null && ($headerFaceitElo < 100 || $headerFaceitElo > 100000)) {
    $headerFaceitElo = null;
}

$headerFaceitLevels = [
    1 => ['min' => 100, 'next' => 501],
    2 => ['min' => 501, 'next' => 751],
    3 => ['min' => 751, 'next' => 901],
    4 => ['min' => 901, 'next' => 1051],
    5 => ['min' => 1051, 'next' => 1201],
    6 => ['min' => 1201, 'next' => 1351],
    7 => ['min' => 1351, 'next' => 1531],
    8 => ['min' => 1531, 'next' => 1751],
    9 => ['min' => 1751, 'next' => 2001],
    10 => ['min' => 2001, 'next' => null],
];

if ($headerFaceitLevel === null && $headerFaceitElo !== null) {
    foreach ($headerFaceitLevels as $candidateLevel => $range) {
        if ($headerFaceitElo >= $range['min']) {
            $headerFaceitLevel = $candidateLevel;
        } else {
            break;
        }
    }
}

$headerFaceitDisplayLevel = $headerFaceitLevel ?? 0;
$headerFaceitRankColors = [
    0 => '#CDCDCD',
    1 => '#EEEEEE',
    2 => '#1CE400',
    3 => '#1CE400',
    4 => '#FFC800',
    5 => '#FFC800',
    6 => '#FFC800',
    7 => '#FFC800',
    8 => '#FF6309',
    9 => '#FF6309',
    10 => '#FE1F00',
];
$headerFaceitRankColor = $headerFaceitRankColors[$headerFaceitDisplayLevel] ?? '#CDCDCD';
$headerFaceitProgress = 0.0;

if ($headerFaceitDisplayLevel === 10) {
    $headerFaceitProgress = 100.0;
} elseif ($headerFaceitDisplayLevel > 0 && $headerFaceitElo !== null) {
    $headerFaceitRange = $headerFaceitLevels[$headerFaceitDisplayLevel];
    $headerFaceitRangeWidth = max(1, (int)$headerFaceitRange['next'] - (int)$headerFaceitRange['min']);
    $headerFaceitProgress = (($headerFaceitElo - (int)$headerFaceitRange['min']) / $headerFaceitRangeWidth) * 100;
    $headerFaceitProgress = max(0, min(100, $headerFaceitProgress));
}
$headerFaceitEloLabel = $headerFaceitElo !== null
    ? number_format($headerFaceitElo) . ' ELO'
    : '— ELO';

// Keep the core destinations visible at the top of the mobile drawer.
$mobileNavItems = [
    ['servers_vps', 'لیست سرورها', 'fa-server', ['servers_vps', 'servers']],
    ['skins', 'انتخاب اسکین', 'fa-palette', ['skins', 'skins_vps']],
    ['serverguide', 'راهنمای سرور', 'fa-book-open', ['serverguide']],
];

// The remaining destinations live under the same "More" grouping as desktop.
$mobileMoreNavItems = [
    ['buyserveropen', 'خرید سرور', 'fa-shopping-cart', ['buyserveropen', 'buyserver']],
    ['launcher', 'دانلود لانچر', 'fa-download', ['launcher']],
    ['leaderboard', 'لیدربورد', 'fa-trophy', ['leaderboard', 'leaderboard_vps']],
    ['banlist', 'لیست محرومیت‌ها', 'fa-gavel', ['banlist']],
    ['vip', 'VIP', 'fa-crown', ['vip']],
    ['status', 'وضعیت سرویس‌ها', 'fa-heart-pulse', ['status']],
];
?>

<header class="shock-header" id="siteHeader">
    <div class="shock-container">
        <div class="shock-brand">
            <a href="index.php" class="shock-logo-link" data-ajax-nav data-slug="home" aria-label="صفحه اصلی FragCity">
                <img src="/img/webp/newlogo.webp?v=20261102-2" onerror="this.onerror=null;this.src='/img/logoresponsive.png?v=20261102-2';" alt="FragCity" class="shock-logo-img">
            </a>
        </div>

        <nav class="shock-nav" aria-label="منوی اصلی">
            <a href="?page=servers_vps"
               class="shock-nav-item <?= $isPageActive(['servers_vps', 'servers']) ?>"
               data-ajax-nav data-slug="servers_vps">
                <i class="fa-solid fa-server" aria-hidden="true"></i>
                <span>لیست سرورها</span>
            </a>

            <a href="?page=skins"
               class="shock-nav-item <?= $isPageActive(['skins', 'skins_vps']) ?>"
               data-ajax-nav data-slug="skins">
                <i class="fa-solid fa-palette" aria-hidden="true"></i>
                <span>انتخاب اسکین</span>
            </a>

            <a href="?page=serverguide"
               class="shock-nav-item shock-nav-item--guide <?= $isPageActive(['serverguide']) ?>"
               data-ajax-nav data-slug="serverguide">
                <i class="fa-solid fa-book-open" aria-hidden="true"></i>
                <span>راهنمای سرور</span>
            </a>

            <div class="frag-dropdown" id="shockMoreDropdown">
                <button type="button"
                        class="shock-nav-item shock-nav-item--icon-only <?= $isPageActive($morePages) ?>"
                        id="shockMoreButton"
                        aria-label="بیشتر"
                        title="بیشتر"
                        aria-expanded="false"
                        aria-controls="shockMoreMenu">
                    <i class="fas fa-chevron-down frag-arrow" aria-hidden="true"></i>
                </button>

                <div class="frag-dropdown-menu" id="shockMoreMenu">
                    <a href="?page=buyserveropen" class="frag-drop-link <?= $isPageActive(['buyserveropen', 'buyserver']) ?>" data-ajax-nav data-slug="buyserveropen">
                        <i class="fas fa-shopping-cart"></i><span>خرید سرور</span>
                    </a>
                    <a href="?page=launcher" class="frag-drop-link <?= $isPageActive(['launcher']) ?>" data-ajax-nav data-slug="launcher">
                        <i class="fas fa-download"></i><span>دانلود لانچر</span>
                    </a>
                    <a href="?page=leaderboard" class="frag-drop-link <?= $isPageActive(['leaderboard', 'leaderboard_vps']) ?>" data-ajax-nav data-slug="leaderboard">
                        <i class="fas fa-trophy"></i><span>لیدربورد</span>
                    </a>
                    <a href="?page=banlist" class="frag-drop-link <?= $isPageActive(['banlist']) ?>" data-ajax-nav data-slug="banlist">
                        <i class="fas fa-gavel"></i><span>لیست محرومیت‌ها</span>
                    </a>
                    <a href="?page=vip" class="frag-drop-link <?= $isPageActive(['vip']) ?>" data-ajax-nav data-slug="vip">
                        <i class="fas fa-crown"></i><span>VIP</span>
                    </a>
                    <a href="?page=status" class="frag-drop-link <?= $isPageActive(['status']) ?>" data-ajax-nav data-slug="status">
                        <i class="fas fa-heart-pulse"></i><span>وضعیت سرویس‌ها</span>
                    </a>
                </div>
            </div>
        </nav>

        <div class="shock-user-area-wrapper">
            <button
                type="button"
                class="fc-motion-toggle" aria-label="Performance Mode"
                data-motion-toggle
                aria-pressed="false"
                title="حالت بدون انیمیشن"
            >
                <i class="fa-solid fa-gauge-high" aria-hidden="true"></i>
                <span class="fc-motion-toggle__label">Performance Mode</span>
            </button>
            <?php if ($isLoggedIn): ?>
                <div class="frag-user-area">
                    <div class="frag-dropdown" id="shockUserDropdown">
                        <button type="button"
                                class="frag-user-trigger"
                                id="shockUserButton"
                                aria-expanded="false"
                                aria-controls="shockUserMenu">
                            <img src="<?= htmlspecialchars($avatarSrc, ENT_QUOTES, 'UTF-8') ?>"
                                 class="frag-avatar"
                                 alt="آواتار <?= htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8') ?>"
                                 onerror="this.onerror=null;this.src='assets/images/default_avatar.png';">
                            <span class="frag-username"><?= htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8') ?></span>
                            <i class="fas fa-chevron-down frag-arrow" aria-hidden="true"></i>
                        </button>

                        <div class="frag-dropdown-menu" id="shockUserMenu">
                            <div class="frag-drop-header frag-drop-header--profile">
                                <span class="frag-drop-avatar-frame">
                                    <img src="<?= htmlspecialchars($avatarSrc, ENT_QUOTES, 'UTF-8') ?>"
                                         class="frag-drop-avatar"
                                         alt=""
                                         onerror="this.onerror=null;this.src='assets/images/default_avatar.png';">
                                </span>
                                <div class="frag-drop-info">
                                    <span class="frag-drop-eyebrow">اکانت استیم فعلی</span>
                                    <span class="frag-drop-name"><?= htmlspecialchars($headerSteamPersonaName, ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="frag-drop-steam">STEAMID64 · <?= htmlspecialchars($headerSteamId64 !== '' ? $headerSteamId64 : '—', ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <span class="frag-drop-profile-mark" aria-hidden="true">
                                    <i class="fa-solid fa-gamepad"></i>
                                </span>
                            </div>

                            <div class="frag-drop-steam-details">
                                <div class="frag-drop-detail-grid">
                                    <div class="frag-drop-detail"><span>وضعیت پروفایل</span><strong><?= htmlspecialchars($headerSteamVisibility, ENT_QUOTES, 'UTF-8') ?></strong></div>
                                    <div class="frag-drop-detail"><span>تنظیمات پروفایل</span><strong><?= htmlspecialchars($headerProfileState, ENT_QUOTES, 'UTF-8') ?></strong></div>
                                    <div class="frag-drop-detail"><span>تاریخ ساخت حساب</span><strong><?= htmlspecialchars($headerCreatedLabel, ENT_QUOTES, 'UTF-8') ?></strong></div>
                                    <div class="frag-drop-detail"><span>کشور</span><strong dir="ltr"><?= htmlspecialchars($headerCountry !== '' ? $headerCountry : '—', ENT_QUOTES, 'UTF-8') ?></strong></div>
                                    <div class="frag-drop-detail"><span>آخرین فعالیت</span><strong><?= htmlspecialchars($headerLastLogoffLabel, ENT_QUOTES, 'UTF-8') ?></strong></div>
                                </div>
                                <?php if ($headerSteamProfileUrl !== ''): ?>
                                    <a class="frag-drop-steam-profile-link" href="<?= htmlspecialchars($headerSteamProfileUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">
                                        <i class="fa-brands fa-steam" aria-hidden="true"></i>
                                        مشاهده حساب در Steam
                                        <i class="fa-solid fa-arrow-up-left-from-circle" aria-hidden="true"></i>
                                    </a>
                                <?php endif; ?>
                            </div>

                            <div
                                class="frag-drop-faceit"
                                data-status="<?= htmlspecialchars($headerFaceitStatus, ENT_QUOTES, 'UTF-8') ?>"
                                data-faceit-level="<?= $headerFaceitDisplayLevel ?>"
                                style="--faceit-rank-color: <?= htmlspecialchars($headerFaceitRankColor, ENT_QUOTES, 'UTF-8') ?>;"
                                aria-label="FACEIT level"
                            >
                                <div class="frag-drop-faceit__top">
                                    <span class="frag-drop-faceit__rank">
                                        <img
                                            src="/img/faceit/<?= $headerFaceitDisplayLevel ?>.svg"
                                            alt=""
                                            aria-hidden="true"
                                            width="27"
                                            height="27"
                                        >
                                        <strong><?= $headerFaceitDisplayLevel > 0 ? 'Level ' . $headerFaceitDisplayLevel : 'Unranked' ?></strong>
                                    </span>
                                    <strong class="frag-drop-faceit__elo"><?= htmlspecialchars($headerFaceitEloLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                                </div>
                                <div
                                    class="frag-drop-faceit__progress"
                                    role="progressbar"
                                    aria-label="FACEIT progress to next level"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                    aria-valuenow="<?= htmlspecialchars((string)round($headerFaceitProgress, 2), ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    <span style="width: <?= htmlspecialchars((string)round($headerFaceitProgress, 2), ENT_QUOTES, 'UTF-8') ?>%;"></span>
                                </div>
                            </div>

                            <div class="frag-drop-wallet-card">
                                <div class="frag-drop-wallet-card__balance">
                                    <span class="frag-drop-wallet-card__label">
                                        <i class="fa-solid fa-wallet" aria-hidden="true"></i>
                                        موجودی کیف پول
                                    </span>
                                    <strong dir="rtl"><?= htmlspecialchars($headerWalletBalanceLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                                </div>
                                <button
                                    type="button"
                                    class="frag-drop-wallet-card__add"
                                    data-fc-wallet-charge-open
                                    aria-label="افزایش موجودی کیف پول"
                                    title="افزایش موجودی"
                                >
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                                </button>
                            </div>

                            <div class="frag-drop-divider"></div>
                            <div class="frag-drop-actions">
                                <a href="?page=buyserveropen" class="frag-drop-link" data-ajax-nav data-slug="buyserveropen">
                                    <i class="fa-solid fa-server" aria-hidden="true"></i><span>سرورهای من</span>
                                </a>
                                <a href="?page=profile" class="frag-drop-link <?= $isPageActive(['profile']) ?>" data-ajax-nav data-slug="profile">
                                    <i class="fas fa-user" aria-hidden="true"></i><span>مشاهده پروفایل</span>
                                </a>
                            </div>
                            <div class="frag-drop-divider"></div>
                            <form action="index.php" method="post" class="frag-drop-logout-form">
                                <input type="hidden" name="auth_action" value="logout">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($headerCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="frag-drop-link logout">
                                    <i class="fas fa-sign-out-alt" aria-hidden="true"></i><span>خروج از حساب</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <a href="?page=login"
                   class="frag-login-btn <?= $isPageActive(['login', 'register']) ?>"
                   data-auth-modal-open
                   aria-haspopup="dialog"
                   aria-controls="fcLoginModal"
                   aria-expanded="false">
                    <i class="fa-brands fa-steam" aria-hidden="true"></i>
                    <span>ورود با استیم</span>
                </a>
            <?php endif; ?>

            <button type="button"
                    class="shock-mobile-toggle"
                    id="mobileNavOpen"
                    aria-label="باز کردن منو"
                    aria-expanded="false"
                    aria-controls="mobileNav">
                <i class="fas fa-bars" aria-hidden="true"></i>
            </button>
        </div>
    </div>
</header>

<div class="mobile-overlay" id="mobileOverlay" aria-hidden="true"></div>

<nav class="mobile-nav" id="mobileNav" aria-label="منوی موبایل" aria-hidden="true">
    <div class="mobile-nav-header">
        <a href="index.php" class="mobile-nav-logo" data-ajax-nav data-slug="home">
            <img src="/img/webp/newlogo.webp?v=20261102-2" onerror="this.onerror=null;this.src='/img/logoresponsive.png?v=20261102-2';" alt="FragCity">
            <span>FRAGCITY</span>
        </a>
        <button type="button" class="mobile-nav-close" id="mobileNavClose" aria-label="بستن منو">
            <i class="fas fa-times" aria-hidden="true"></i>
        </button>
    </div>

    <div class="mobile-nav-links">
        <button
            type="button"
            class="mobile-nav-link fc-motion-toggle-mobile" aria-label="Performance Mode"
            data-motion-toggle
            aria-pressed="false"
        >
            <i class="fa-solid fa-gauge-high" aria-hidden="true"></i>
            <span class="fc-motion-toggle__label">Performance Mode</span>
            <span class="fc-motion-toggle__state" aria-hidden="true"></span>
        </button>
        <?php foreach ($mobileNavItems as [$slug, $label, $icon, $activePages]): ?>
            <a href="?page=<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>"
               class="mobile-nav-link <?= $isPageActive($activePages) ?>"
               data-ajax-nav
               data-slug="<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>">
                <i class="fas <?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
            </a>
        <?php endforeach; ?>

        <details class="mobile-nav-more" <?= $isPageActive($morePages) !== '' ? 'open' : '' ?>>
            <summary class="mobile-nav-link mobile-nav-more-toggle <?= $isPageActive($morePages) ?>">
                <i class="fas fa-ellipsis-h" aria-hidden="true"></i>
                <span>بیشتر</span>
                <i class="fas fa-chevron-down mobile-nav-more-arrow" aria-hidden="true"></i>
            </summary>
            <div class="mobile-nav-more-menu">
                <?php foreach ($mobileMoreNavItems as [$slug, $label, $icon, $activePages]): ?>
                    <a href="?page=<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>"
                       class="mobile-nav-link <?= $isPageActive($activePages) ?>"
                       data-ajax-nav
                       data-slug="<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>">
                        <i class="fas <?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                        <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </details>

        <?php if ($isLoggedIn): ?>
            <a href="?page=profile" class="mobile-nav-link <?= $isPageActive(['profile']) ?>" data-ajax-nav data-slug="profile">
                <i class="fas fa-user-circle" aria-hidden="true"></i>
                <span><?= htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8') ?></span>
            </a>
            <div class="mobile-wallet-balance-row">
                <span>
                    <i class="fa-solid fa-wallet" aria-hidden="true"></i>
                    موجودی
                    <strong><?= htmlspecialchars($headerWalletBalanceLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                </span>
                <button type="button" data-fc-wallet-charge-open aria-label="افزایش موجودی کیف پول" title="افزایش موجودی">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                </button>
            </div>
            <form action="index.php" method="post" class="mobile-nav-logout-form">
                <input type="hidden" name="auth_action" value="logout">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($headerCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="mobile-nav-link logout-link">
                    <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                    <span>خروج</span>
                </button>
            </form>
        <?php else: ?>
            <a href="?page=login"
               class="mobile-nav-link mobile-steam-login-link <?= $isPageActive(['login', 'register']) ?>"
               data-auth-modal-open
               aria-haspopup="dialog"
               aria-controls="fcLoginModal"
               aria-expanded="false">
                <i class="fa-brands fa-steam" aria-hidden="true"></i>
                <span>ورود با استیم</span>
            </a>
        <?php endif; ?>
    </div>
</nav>

<?php if (!$isLoggedIn): ?>
    <div class="fc-auth-modal" id="fcLoginModal" aria-hidden="true" hidden>
        <div class="fc-auth-modal__backdrop" data-auth-modal-close></div>
        <section
            class="fc-auth-modal__dialog"
            role="dialog"
            aria-modal="true"
            aria-label="ورود به حساب FragCity"
            tabindex="-1"
        >
            <button
                type="button"
                class="fc-auth-modal__close"
                data-auth-modal-close
                aria-label="بستن پنجره ورود"
            >
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
            <div class="fc-auth-modal__content" id="fcLoginModalContent">
                <div class="fc-auth-modal__loading" role="status">
                    <span class="fc-auth-modal__spinner" aria-hidden="true"></span>
                    <span>در حال آماده‌سازی فرم ورود...</span>
                </div>
            </div>
        </section>
    </div>
<?php endif; ?>
<?php if ($isLoggedIn): ?>
    <div
        class="fc-wallet-charge-modal"
        id="fcWalletChargeModal"
        aria-hidden="true"
        hidden
    >
        <div class="fc-wallet-charge-modal__backdrop" data-fc-wallet-charge-close></div>
        <section
            class="fc-wallet-charge-modal__dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="fcWalletChargeTitle"
            tabindex="-1"
        >
            <button
                type="button"
                class="fc-wallet-charge-modal__close"
                data-fc-wallet-charge-close
                aria-label="بستن پنجره شارژ"
            >
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>

            <div class="fc-wallet-charge-modal__icon" aria-hidden="true">
                <i class="fa-solid fa-wallet"></i>
            </div>
            <div class="fc-wallet-charge-modal__heading">
                <span>FRAGCITY WALLET</span>
                <h2 id="fcWalletChargeTitle">افزایش موجودی</h2>
                <p>مبلغ مورد نظر برای شارژ کیف پول خود را وارد کنید.</p>
            </div>

            <form
                id="fcWalletChargeForm"
                action="/pages/zarinpal/zarinpal_wallet_request.php"
                method="POST"
                class="fc-wallet-charge-modal__form"
            >
                <label for="fcWalletChargeAmount">مبلغ شارژ (تومان)</label>
                <div class="fc-wallet-charge-modal__input-wrap">
                    <i class="fa-solid fa-coins" aria-hidden="true"></i>
                    <input
                        type="number"
                        id="fcWalletChargeAmount"
                        name="amount"
                        min="1000"
                        step="1000"
                        inputmode="numeric"
                        placeholder="حداقل ۱,۰۰۰ تومان"
                        required
                    >
                </div>
                <p class="fc-wallet-charge-modal__hint">حداقل مبلغ شارژ ۱,۰۰۰ تومان است.</p>
                <div class="fc-wallet-charge-modal__actions">
                    <button type="submit" class="fc-wallet-charge-modal__submit">
                        <i class="fa-solid fa-credit-card" aria-hidden="true"></i>
                        ادامه و پرداخت
                    </button>
                    <button type="button" class="fc-wallet-charge-modal__cancel" data-fc-wallet-charge-close>
                        انصراف
                    </button>
                </div>
            </form>
        </section>
    </div>
<?php endif; ?>

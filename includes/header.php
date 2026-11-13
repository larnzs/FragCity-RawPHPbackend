<?php
require_once __DIR__ . '/routes.php';

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
                return '/img/avatars/' . rawurlencode($avatarFile) . '?v=' . filemtime($avatarDiskPath);
            }

            $digits = preg_replace('/[^0-9]/', '', $steamid);

            // Prefer the locally cached Steam Community avatar after the user's
            // custom uploaded avatar. The two locations are intentionally separate.
            if (preg_match('/^[0-9]{17}$/D', (string)$digits)) {
                foreach (['jpg', 'png', 'webp'] as $extension) {
                    $steamAvatarFile = 'steam_' . $digits . '.' . $extension;
                    $steamAvatarDiskPath = dirname(__DIR__) . '/img/steam-avatars/' . $steamAvatarFile;
                    if (is_file($steamAvatarDiskPath)) {
                        return '/img/steam-avatars/' . rawurlencode($steamAvatarFile)
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

        return '/img/logoresponsive.png';
    }
}

$isMinecraftStorePage = $current_page === 'buyserveropen'
    && isset($_GET['game'])
    && is_string($_GET['game'])
    && $_GET['game'] === 'minecraft';

$isPageActive = static function (array $pages) use ($current_page, $isMinecraftStorePage): string {
    if (in_array('minecraft_store', $pages, true)) {
        return $isMinecraftStorePage ? 'active' : '';
    }

    return in_array($current_page, $pages, true) ? 'active' : '';
};

$morePages = ['serverguide', 'launcher', 'status'];
$avatarSrc = $isLoggedIn ? getUserAvatarHeader((string) $displaySteamID) : '';
$steamShort = $displaySteamID !== '' ? substr((string) $displaySteamID, -8) : '';

// Wallet balance is loaded from the authenticated Paymenter bridge.
// Never read or display the retired local user_wallets balance.
$headerWalletBalanceLabel = 'در حال دریافت موجودی...';

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
        $profileStmt = $pdo->prepare('SELECT steam_personaname, steam_profileurl, steam_avatar_url FROM users WHERE steamid64 = ? LIMIT 1');
        $profileStmt->execute([$headerSteamId64]);
        $profileRow = $profileStmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($profileRow)) {
            $dbProfile = [
                'steamid' => $headerSteamId64,
                'personaname' => $profileRow['steam_personaname'] ?? '',
                'profileurl' => $profileRow['steam_profileurl'] ?? '',
                'avatarfull' => $profileRow['steam_avatar_url'] ?? '',
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
if ($isLoggedIn && $avatarSrc === '/img/logoresponsive.png') {
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
$headerSteamProfileLink = $headerSteamProfileUrl !== ''
    ? $headerSteamProfileUrl
    : ($headerSteamId64 !== ''
        ? 'https://steamcommunity.com/profiles/' . rawurlencode($headerSteamId64)
        : '');


$headerVipStatusAvailable = false;
$headerVipActive = false;
$headerVipLevel = 0;
$headerVipExpiryLabel = '—';
$headerVipStatusLabel = 'وضعیت VIP در دسترس نیست';
$headerVipTierNames = [
    0 => 'بدون VIP',
    1 => 'Bronze',
    2 => 'Silver',
    3 => 'Gold',
    4 => 'Platinum',
    5 => 'Emerald',
    6 => 'Diamond',
];
$headerVipTierColors = [
    1 => '#CD7F32',
    2 => '#C0C0C0',
    3 => '#F5B042',
    4 => '#9CC9E8',
    5 => '#34D399',
    6 => '#93C5FD',
];
$headerVipTierColor = '#737373';

// Resolve membership only for the Steam account authenticated in this session.
if ($isLoggedIn && preg_match('/^7656119[0-9]{10}$/D', $headerSteamId64)) {
    try {
        require_once __DIR__ . '/../class/VipCoreRepository.php';
        $headerVipStatus = (new VipCoreRepository())->getStatus($headerSteamId64);
        $headerVipStatusAvailable = true;
        $headerVipActive = $headerVipStatus['active'] === true;
        $headerVipLevel = $headerVipActive ? max(0, min(6, (int)$headerVipStatus['level'])) : 0;
        $headerVipStatusLabel = $headerVipActive ? 'اشتراک فعال' : 'بدون اشتراک فعال';
        $headerVipTierColor = $headerVipTierColors[$headerVipLevel] ?? '#737373';

        if ($headerVipActive && (int)($headerVipStatus['expires'] ?? -1) === 0) {
            $headerVipExpiryLabel = 'دائمی';
        } elseif ($headerVipActive && is_numeric($headerVipStatus['expires'] ?? null)
            && (int)$headerVipStatus['expires'] > 0) {
            $headerVipExpiryLabel = (new DateTimeImmutable('@' . (int)$headerVipStatus['expires']))
                ->setTimezone(new DateTimeZone(date_default_timezone_get()))
                ->format('Y/m/d H:i');
        }
    } catch (Throwable $vipError) {
        error_log('[FragCity] Header VIP status unavailable; type=' . get_class($vipError));
        $headerVipStatusLabel = 'اطلاعات VIP موقتاً در دسترس نیست';
    }
}

$headerVipTierLabel = $headerVipStatusAvailable
    ? ($headerVipTierNames[$headerVipLevel] ?? 'نامشخص')
    : 'نامشخص';
$headerVipState = !$headerVipStatusAvailable
    ? 'unavailable'
    : ($headerVipActive ? 'active' : 'inactive');

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

// Keep the main destinations visible at the top of the mobile drawer.
$mobileNavItems = [
    ['servers_vps', 'لیست سرورها', 'fa-server', ['servers_vps', 'servers']],
    ['skins', 'انتخاب اسکین', 'fa-palette', ['skins', 'skins_vps']],
    ['store', 'فروشگاه', 'fa-store', []],
    ['leaderboard', 'لیدربورد', 'fa-trophy', ['leaderboard', 'leaderboard_vps']],
    ['banlist', 'لیست محرومیت‌ها', 'fa-gavel', ['banlist']],
    ['vip', 'VIP', 'fa-crown', ['vip']],
];

// Secondary destinations stay grouped under "More" on desktop and mobile.
$mobileMoreNavItems = [
    ['serverguide', 'راهنمای سرور', 'fa-book-open', ['serverguide']],
    ['launcher', 'دانلود لانچر', 'fa-download', ['launcher']],
    ['status', 'وضعیت سرویس‌ها', 'fa-heart-pulse', ['status']],
];
?>

<header class="shock-header" id="siteHeader">
    <div class="shock-container">
        <div class="shock-brand">
            <a href="<?= htmlspecialchars(fragcity_page_url('home'), ENT_QUOTES, 'UTF-8') ?>" class="shock-logo-link" data-ajax-nav data-slug="home" aria-label="صفحه اصلی FragCity">
                <img src="/img/webp/newlogo.webp?v=20261102-2" onerror="this.onerror=null;this.src='/img/logoresponsive.png?v=20261102-2';" alt="FragCity" class="shock-logo-img">
            </a>
            <a
                href="<?= htmlspecialchars(fragcity_page_url('support'), ENT_QUOTES, 'UTF-8') ?>"
                class="fc-support-toggle <?= $isPageActive(['support']) ?>"
                data-ajax-nav
                data-slug="support"
                aria-label="پشتیبانی"
                title="پشتیبانی"
            >
                <i class="fa-solid fa-headset" aria-hidden="true"></i>
                <span class="fc-support-toggle__label">پشتیبانی</span>
            </a>
            <a href="<?= htmlspecialchars(fragcity_billing_sso_url('/cart'), ENT_QUOTES, 'UTF-8') ?>"
               class="fc-header-cart-link"
               aria-label="سبد خرید"
               title="سبد خرید">
                <i class="fa-solid fa-shopping-cart" aria-hidden="true"></i>
                <span class="fc-header-cart-link__label">سبد خرید</span>
            </a>
        </div>

        <nav class="shock-nav" aria-label="منوی اصلی">
            <a href="<?= htmlspecialchars(fragcity_page_url('servers_vps'), ENT_QUOTES, 'UTF-8') ?>"
               class="shock-nav-item <?= $isPageActive(['servers_vps', 'servers']) ?>"
               data-ajax-nav data-slug="servers_vps">
                <i class="fa-solid fa-server" aria-hidden="true"></i>
                <span>لیست سرورها</span>
            </a>

            <a href="<?= htmlspecialchars(fragcity_page_url('skins'), ENT_QUOTES, 'UTF-8') ?>"
               class="shock-nav-item <?= $isPageActive(['skins', 'skins_vps']) ?>"
               data-ajax-nav data-slug="skins">
                <i class="fa-solid fa-palette" aria-hidden="true"></i>
                <span>انتخاب اسکین</span>
            </a>

            <a href="<?= htmlspecialchars(fragcity_billing_sso_url('/'), ENT_QUOTES, 'UTF-8') ?>"
               class="shock-nav-item"
               aria-label="فروشگاه">
                <i class="fas fa-store" aria-hidden="true"></i>
                <span>فروشگاه</span>
            </a>

            <a href="<?= htmlspecialchars(fragcity_page_url('leaderboard'), ENT_QUOTES, 'UTF-8') ?>"
               class="shock-nav-item <?= $isPageActive(['leaderboard', 'leaderboard_vps']) ?>"
               data-ajax-nav data-slug="leaderboard">
                <i class="fas fa-trophy" aria-hidden="true"></i>
                <span>لیدربورد</span>
            </a>

            <a href="<?= htmlspecialchars(fragcity_page_url('banlist'), ENT_QUOTES, 'UTF-8') ?>"
               class="shock-nav-item <?= $isPageActive(['banlist']) ?>"
               data-ajax-nav data-slug="banlist">
                <i class="fas fa-gavel" aria-hidden="true"></i>
                <span>لیست محرومیت‌ها</span>
            </a>

            <a href="<?= htmlspecialchars(fragcity_page_url('vip'), ENT_QUOTES, 'UTF-8') ?>"
               class="shock-nav-item shock-nav-item--vip <?= $isPageActive(['vip']) ?>"
               data-ajax-nav data-slug="vip">
                <i class="fas fa-crown" aria-hidden="true"></i>
                <span>VIP</span>
            </a>

            <div class="frag-dropdown" id="shockMoreDropdown">
                <button type="button"
                        class="shock-nav-item <?= $isPageActive($morePages) ?>"
                        id="shockMoreButton"
                        aria-label="بیشتر"
                        title="بیشتر"
                        aria-expanded="false"
                        aria-controls="shockMoreMenu">
                    <i class="fas fa-ellipsis" aria-hidden="true"></i>
                    <span>بیشتر</span>
                    <i class="fas fa-chevron-down frag-arrow" aria-hidden="true"></i>
                </button>

                <div class="frag-dropdown-menu" id="shockMoreMenu">
                    <a href="<?= htmlspecialchars(fragcity_page_url('serverguide'), ENT_QUOTES, 'UTF-8') ?>" class="frag-drop-link <?= $isPageActive(['serverguide']) ?>" data-ajax-nav data-slug="serverguide">
                        <i class="fas fa-book-open"></i><span>راهنمای سرور</span>
                    </a>
                    <a href="<?= htmlspecialchars(fragcity_page_url('launcher'), ENT_QUOTES, 'UTF-8') ?>" class="frag-drop-link <?= $isPageActive(['launcher']) ?>" data-ajax-nav data-slug="launcher">
                        <i class="fas fa-download"></i><span>دانلود لانچر</span>
                    </a>
                    <a href="<?= htmlspecialchars(fragcity_page_url('status'), ENT_QUOTES, 'UTF-8') ?>" class="frag-drop-link <?= $isPageActive(['status']) ?>" data-ajax-nav data-slug="status">
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
                                 onerror="this.onerror=null;this.src='/img/logoresponsive.png';">
                            <span class="frag-username"><?= htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8') ?></span>
                            <i class="fas fa-chevron-down frag-arrow" aria-hidden="true"></i>
                        </button>

                        <div class="frag-dropdown-menu" id="shockUserMenu">
                            <div class="frag-drop-header frag-drop-header--profile">
                                <span class="frag-drop-avatar-frame">
                                    <img src="<?= htmlspecialchars($avatarSrc, ENT_QUOTES, 'UTF-8') ?>"
                                         class="frag-drop-avatar"
                                         alt=""
                                         onerror="this.onerror=null;this.src='/img/logoresponsive.png';">
                                </span>
                                <div class="frag-drop-info">
                                    <span class="frag-drop-eyebrow">اکانت استیم فعلی</span>
                                    <span class="frag-drop-name"><?= htmlspecialchars($headerSteamPersonaName, ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="frag-drop-steam">STEAMID64 · <?= htmlspecialchars($headerSteamId64 !== '' ? $headerSteamId64 : '—', ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <?php if ($headerSteamProfileLink !== ''): ?>
                                    <a
                                        class="frag-drop-profile-mark"
                                        href="<?= htmlspecialchars($headerSteamProfileLink, ENT_QUOTES, 'UTF-8') ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        aria-label="مشاهده پروفایل در Steam"
                                        title="پروفایل Steam"
                                    >
                                        <i class="fa-brands fa-steam" aria-hidden="true"></i>
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


                            <section
                                class="frag-drop-vip"
                                data-state="<?= htmlspecialchars($headerVipState, ENT_QUOTES, 'UTF-8') ?>"
                                style="--vip-tier-color: <?= htmlspecialchars($headerVipTierColor, ENT_QUOTES, 'UTF-8') ?>;"
                                aria-label="وضعیت اشتراک VIP حساب Steam فعلی"
                            >
                                <div class="frag-drop-vip__top">
                                    <span class="frag-drop-vip__label">
                                        <i class="fas fa-crown" aria-hidden="true"></i>
                                        وضعیت اشتراک VIP
                                    </span>
                                    <strong class="frag-drop-vip__tier"><?= htmlspecialchars($headerVipTierLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                                </div>
                                <div class="frag-drop-vip__bottom">
                                    <span class="frag-drop-vip__state">
                                        <i class="fas <?= $headerVipState === 'active' ? 'fa-circle-check' : ($headerVipState === 'inactive' ? 'fa-circle-minus' : 'fa-circle-info') ?>" aria-hidden="true"></i>
                                        <?= htmlspecialchars($headerVipStatusLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <span class="frag-drop-vip__expiry">
                                        <small>اعتبار تا</small>
                                        <strong><?= htmlspecialchars($headerVipExpiryLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                                    </span>
                                </div>
                            </section>

                            <div class="frag-drop-wallet-card">
                                <div class="frag-drop-wallet-card__balance">
                                    <span class="frag-drop-wallet-card__label">
                                        <i class="fa-solid fa-wallet" aria-hidden="true"></i>
                                        موجودی کیف پول
                                    </span>
                                    <strong dir="rtl" data-fc-wallet-balance aria-live="polite"><?= htmlspecialchars($headerWalletBalanceLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                                </div>
                                <button
                                    type="button"
                                    class="frag-drop-wallet-card__add"
                                    data-fc-wallet-charge-open
                                    disabled
                                    aria-label="افزایش موجودی کیف پول"
                                    title="افزایش موجودی"
                                >
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                                </button>
                            </div>

                            <div class="frag-drop-divider"></div>
                            <div class="frag-drop-actions">
                                <a href="<?= htmlspecialchars(fragcity_page_url('orders'), ENT_QUOTES, 'UTF-8') ?>" class="frag-drop-link frag-drop-link--orders <?= $isPageActive(['orders']) ?>" data-ajax-nav data-slug="orders">
                                    <i class="fa-solid fa-receipt" aria-hidden="true"></i><span>سفارش‌های شما</span>
                                </a>
                                <a href="<?= htmlspecialchars(fragcity_page_url('myservers'), ENT_QUOTES, 'UTF-8') ?>" class="frag-drop-link <?= $isPageActive(['myservers']) ?>" data-ajax-nav data-slug="myservers">
                                    <i class="fa-solid fa-server" aria-hidden="true"></i><span>سرورهای من</span>
                                </a>
                                <a href="<?= htmlspecialchars(fragcity_page_url('profile'), ENT_QUOTES, 'UTF-8') ?>" class="frag-drop-link <?= $isPageActive(['profile']) ?>" data-ajax-nav data-slug="profile">
                                    <i class="fas fa-user" aria-hidden="true"></i><span>مشاهده پروفایل</span>
                                </a>
                            </div>
                            <div class="frag-drop-divider"></div>
                            <form action="/" method="post" class="frag-drop-logout-form">
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
                <a href="<?= htmlspecialchars(fragcity_page_url('login'), ENT_QUOTES, 'UTF-8') ?>"
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
        <a href="<?= htmlspecialchars(fragcity_page_url('home'), ENT_QUOTES, 'UTF-8') ?>" class="mobile-nav-logo" data-ajax-nav data-slug="home">
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
            <a href="<?= htmlspecialchars($slug === 'store' ? fragcity_billing_sso_url('/') : fragcity_page_url($slug), ENT_QUOTES, 'UTF-8') ?>"
               class="mobile-nav-link <?= $slug === 'vip' ? 'mobile-nav-link--vip ' : '' ?><?= $isPageActive($activePages) ?>"
               <?= $slug === 'store' ? '' : 'data-ajax-nav data-slug="' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '"' ?>>
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
                    <a href="<?= htmlspecialchars(
                        in_array('minecraft_store', $activePages, true)
                            ? fragcity_page_url('buyserveropen') . '?game=minecraft'
                            : fragcity_page_url($slug),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
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
            <a href="<?= htmlspecialchars(fragcity_page_url('myservers'), ENT_QUOTES, 'UTF-8') ?>" class="mobile-nav-link <?= $isPageActive(['myservers']) ?>" data-ajax-nav data-slug="myservers">
                <i class="fas fa-server" aria-hidden="true"></i>
                <span>سرورهای من</span>
            </a>
            <a href="<?= htmlspecialchars(fragcity_page_url('profile'), ENT_QUOTES, 'UTF-8') ?>" class="mobile-nav-link <?= $isPageActive(['profile']) ?>" data-ajax-nav data-slug="profile">
                <i class="fas fa-user-circle" aria-hidden="true"></i>
                <span><?= htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8') ?></span>
            </a>
            <div class="mobile-wallet-balance-row">
                <span>
                    <i class="fa-solid fa-wallet" aria-hidden="true"></i>
                    موجودی
                    <strong data-fc-wallet-balance aria-live="polite"><?= htmlspecialchars($headerWalletBalanceLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                </span>
                <button type="button" data-fc-wallet-charge-open disabled aria-label="افزایش موجودی کیف پول" title="افزایش موجودی">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                </button>
            </div>
            <form action="/" method="post" class="mobile-nav-logout-form">
                <input type="hidden" name="auth_action" value="logout">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($headerCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="mobile-nav-link logout-link">
                    <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                    <span>خروج</span>
                </button>
            </form>
        <?php else: ?>
            <a href="<?= htmlspecialchars(fragcity_page_url('login'), ENT_QUOTES, 'UTF-8') ?>"
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
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars((string) ($headerCsrfToken), ENT_QUOTES, 'UTF-8') ?>"
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
                <p class="fc-wallet-charge-modal__hint" role="status" aria-live="polite">در حال دریافت محدودیت‌های شارژ از Paymenter...</p>
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

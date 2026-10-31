<?php
if (defined('TOP_HEADER_RENDERED')) {
    return;
}
define('TOP_HEADER_RENDERED', true);

$isLoggedIn = $isLoggedIn ?? isset($_SESSION['user_logged_in']);
$displayName = $displayName ?? ($_SESSION['username'] ?? '');
$displaySteamID = $displaySteamID ?? ($_SESSION['steamid'] ?? '');
$current_page = $current_page ?? 'home';

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

$morePages = ['skins', 'skins_vps', 'launcher', 'leaderboard', 'leaderboard_vps', 'banlist', 'vip'];
$avatarSrc = $isLoggedIn ? getUserAvatarHeader((string) $displaySteamID) : '';
$steamShort = $displaySteamID !== '' ? substr((string) $displaySteamID, -8) : '';

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
$headerSteamState = isset($headerSteamProfile['personastate']) ? (int)$headerSteamProfile['personastate'] : -1;
$headerSteamStatus = [0 => 'آفلاین', 1 => 'آنلاین', 2 => 'مشغول', 3 => 'غایب', 4 => 'خواب', 5 => 'مایل به معامله', 6 => 'مایل به بازی'][$headerSteamState] ?? 'نامشخص';
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
$headerSteamGame = trim((string)($headerSteamProfile['gameextrainfo'] ?? ''));
$headerSteamProfileUrl = trim((string)($headerSteamProfile['profileurl'] ?? ''));
$steamUrlParts = $headerSteamProfileUrl !== '' ? parse_url($headerSteamProfileUrl) : false;
if (!is_array($steamUrlParts) || strtolower((string)($steamUrlParts['scheme'] ?? '')) !== 'https' || !in_array(strtolower((string)($steamUrlParts['host'] ?? '')), ['steamcommunity.com', 'www.steamcommunity.com'], true)) {
    $headerSteamProfileUrl = '';
}

$mobileNavItems = [
    ['servers_vps', 'لیست سرورها', 'fa-server', ['servers_vps', 'servers']],
    ['buyserveropen', 'خرید سرور', 'fa-shopping-cart', ['buyserveropen', 'buyserver']],
    ['skins', 'انتخاب اسکین', 'fa-palette', ['skins', 'skins_vps']],
    ['launcher', 'دانلود لانچر', 'fa-download', ['launcher']],
    ['leaderboard', 'لیدربورد', 'fa-trophy', ['leaderboard', 'leaderboard_vps']],
    ['banlist', 'لیست محرومیت‌ها', 'fa-gavel', ['banlist']],
    ['vip', 'VIP', 'fa-crown', ['vip']],
];
?>

<header class="shock-header" id="siteHeader">
    <div class="shock-container">
        <div class="shock-brand">
            <a href="index.php" class="shock-logo-link" data-ajax-nav data-slug="home" aria-label="صفحه اصلی FragCity">
                <picture class="shock-logo-picture">
                    <source
                        media="(max-width: 1100px)"
                        srcset="/img/logoresponsive.png"
                    >
                    <img src="/assets/images/logo/main.png" alt="FragCity" class="shock-logo-img">
                </picture>
            </a>
        </div>

        <nav class="shock-nav" aria-label="منوی اصلی">
            <a href="?page=servers_vps"
               class="shock-nav-item <?= $isPageActive(['servers_vps', 'servers']) ?>"
               data-ajax-nav data-slug="servers_vps">
                لیست سرورها
            </a>

            <a href="?page=buyserveropen"
               class="shock-nav-item <?= $isPageActive(['buyserveropen', 'buyserver']) ?>"
               data-ajax-nav data-slug="buyserveropen">
                خرید سرور
            </a>

            <div class="frag-dropdown" id="shockMoreDropdown">
                <button type="button"
                        class="shock-nav-item <?= $isPageActive($morePages) ?>"
                        id="shockMoreButton"
                        aria-expanded="false"
                        aria-controls="shockMoreMenu">
                    بیشتر
                    <i class="fas fa-chevron-down frag-arrow" aria-hidden="true"></i>
                </button>

                <div class="frag-dropdown-menu" id="shockMoreMenu">
                    <a href="?page=skins" class="frag-drop-link <?= $isPageActive(['skins', 'skins_vps']) ?>" data-ajax-nav data-slug="skins">
                        <i class="fas fa-palette"></i><span>انتخاب اسکین</span>
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
                </div>
            </div>
        </nav>

        <div class="shock-user-area-wrapper">
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
                                <div class="frag-drop-status" data-state="<?= $headerSteamState ?>">
                                    <span class="frag-drop-status-dot" aria-hidden="true"></span>
                                    <strong><?= htmlspecialchars($headerSteamStatus, ENT_QUOTES, 'UTF-8') ?></strong>
                                    <?php if ($headerSteamGame !== ''): ?>
                                        <span class="frag-drop-playing">در حال بازی: <?= htmlspecialchars($headerSteamGame, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </div>
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

                            <div class="frag-drop-divider"></div>
                            <a href="?page=profile" class="frag-drop-link <?= $isPageActive(['profile']) ?>" data-ajax-nav data-slug="profile">
                                <i class="fas fa-user"></i><span>مشاهده پروفایل</span>
                            </a>
                            <a href="?page=wallet"
                               class="frag-drop-link <?= $isPageActive(['wallet']) ?>"
                               data-ajax-nav
                               data-slug="wallet">
                                <i class="fas fa-wallet"></i>
                                <span>کیف پول شما</span>
                            </a>
                            <div class="frag-drop-divider"></div>
                            <a href="?logout=1" class="frag-drop-link logout">
                                <i class="fas fa-sign-out-alt"></i><span>خروج از حساب</span>
                            </a>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <a href="?page=login"
                   class="frag-login-btn <?= $isPageActive(['login', 'register']) ?>"
                   data-ajax-nav data-slug="login">
                    <i class="fas fa-user-lock" aria-hidden="true"></i>
                    <span>ورود / ثبت‌نام</span>
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
            <img src="assets/images/logo/main.png" alt="FragCity">
            <span>FRAGCITY</span>
        </a>
        <button type="button" class="mobile-nav-close" id="mobileNavClose" aria-label="بستن منو">
            <i class="fas fa-times" aria-hidden="true"></i>
        </button>
    </div>

    <div class="mobile-nav-links">
        <?php foreach ($mobileNavItems as [$slug, $label, $icon, $activePages]): ?>
            <a href="?page=<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>"
               class="mobile-nav-link <?= $isPageActive($activePages) ?>"
               data-ajax-nav
               data-slug="<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>">
                <i class="fas <?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
            </a>
        <?php endforeach; ?>

        <?php if ($isLoggedIn): ?>
            <a href="?page=profile" class="mobile-nav-link <?= $isPageActive(['profile']) ?>" data-ajax-nav data-slug="profile">
                <i class="fas fa-user-circle" aria-hidden="true"></i>
                <span><?= htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8') ?></span>
            </a>
            <a href="?logout=1" class="mobile-nav-link logout-link">
                <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                <span>خروج</span>
            </a>
        <?php else: ?>
            <a href="?page=login" class="mobile-nav-link <?= $isPageActive(['login', 'register']) ?>" data-ajax-nav data-slug="login">
                <i class="fas fa-user-lock" aria-hidden="true"></i>
                <span>ورود / ثبت‌نام</span>
            </a>
        <?php endif; ?>
    </div>
</nav>
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

$morePages = ['skins', 'skins_vps', 'launcher', 'usermanual', 'leaderboard', 'leaderboard_vps', 'banlist', 'vip'];
$avatarSrc = $isLoggedIn ? getUserAvatarHeader((string) $displaySteamID) : '';
$steamShort = $displaySteamID !== '' ? substr((string) $displaySteamID, -8) : '';

$mobileNavItems = [
    ['servers_vps', 'لیست سرورها', 'fa-server', ['servers_vps', 'servers']],
    ['buyserveropen', 'خرید سرور', 'fa-shopping-cart', ['buyserveropen', 'buyserver']],
    ['skins', 'انتخاب اسکین', 'fa-palette', ['skins', 'skins_vps']],
    ['launcher', 'دانلود لانچر', 'fa-download', ['launcher']],
    ['usermanual', 'راهنمای کاربر', 'fa-book', ['usermanual']],
    ['leaderboard', 'لیدربورد', 'fa-trophy', ['leaderboard', 'leaderboard_vps']],
    ['banlist', 'لیست محرومیت‌ها', 'fa-gavel', ['banlist']],
    ['vip', 'VIP', 'fa-crown', ['vip']],
    ['wallet', 'شارژ کیف پول', 'fa-wallet', ['wallet']],
];
?>

<header class="shock-header" id="siteHeader">
    <div class="shock-container">
        <div class="shock-brand">
            <a href="index.php" class="shock-logo-link" data-ajax-nav data-slug="home" aria-label="صفحه اصلی FragCity">
                <img src="assets/images/logo/main.png" alt="FragCity" class="shock-logo-img">
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
                    <a href="?page=usermanual" class="frag-drop-link <?= $isPageActive(['usermanual']) ?>" data-ajax-nav data-slug="usermanual">
                        <i class="fas fa-book"></i><span>راهنمای کاربر</span>
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
                            <div class="frag-drop-header">
                                <img src="<?= htmlspecialchars($avatarSrc, ENT_QUOTES, 'UTF-8') ?>"
                                     class="frag-drop-avatar"
                                     alt=""
                                     onerror="this.onerror=null;this.src='assets/images/default_avatar.png';">
                                <div class="frag-drop-info">
                                    <span class="frag-drop-name"><?= htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="frag-drop-steam"><?= htmlspecialchars($steamShort, ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
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

                <a href="?page=wallet"
                   class="action-icon-btn <?= $isPageActive(['wallet']) ?>"
                   data-ajax-nav data-slug="wallet"
                   title="شارژ کیف پول"
                   aria-label="شارژ کیف پول">
                    <i class="fas fa-wallet" aria-hidden="true"></i>
                </a>

                <!-- اصلاح مسیر دکمه پنل ادمینی به پوشه واقعی panels/cs2/index.php -->
                <div class="frag-dropdown" id="shockAdminPanelDropdown">
                    <button type="button"
                            class="shock-nav-item"
                            id="shockAdminPanelButton"
                            aria-expanded="false"
                            aria-controls="shockAdminPanelMenu">
                        <i class="fas fa-shield-halved" aria-hidden="true"></i>
                        <span class="admin-panel-label">پنل ادمینی سرور</span>
                        <i class="fas fa-chevron-down frag-arrow" aria-hidden="true"></i>
                    </button>

                    <div class="frag-dropdown-menu admin-panel-menu" id="shockAdminPanelMenu">
                        <a href="panels/cs2/index.php"
                           class="frag-drop-link admin-panel-game-link">
                            <img src="assets/images/logo/cs2.png" alt="" class="admin-panel-game-icon">
                            <span>Counter Strike 2</span>
                        </a>
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
            <!-- اصلاح لینک منوی موبایل -->
            <a href="panels/cs2/index.php" class="mobile-nav-link">
                <i class="fas fa-shield-halved" aria-hidden="true"></i>
                <span>پنل ادمینی سرور (CS2)</span>
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
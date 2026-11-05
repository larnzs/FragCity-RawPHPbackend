<?php

// steam/steam_login.php

require_once __DIR__ . '/../settings/session.php';
fragcity_start_session();

// Load runtime settings and constants before using the settings helpers/constants.
require_once __DIR__ . '/../class/config.php';
require_once __DIR__ . '/../class/SteamProfileService.php';
require_once __DIR__ . '/../class/RememberMeService.php';
require_once __DIR__ . '/openid.php';

$configuredDomain = defined('STEAM_DOMAIN_NAME')
    ? strtolower(trim((string) STEAM_DOMAIN_NAME))
    : '';

/*
 * Use the configured environment origin as the OpenID trust root.
 * This keeps the protocol, hostname and optional port identical to the
 * public callback URL. Do not infer the callback host from an untrusted
 * HTTP_HOST header or let the current proxy scheme silently change it.
 */
$siteBaseUrl = trim((string) fragcity_settings('app.site_base_url', ''));
$siteBaseParts = parse_url($siteBaseUrl);
$originScheme = is_array($siteBaseParts)
    ? strtolower((string) ($siteBaseParts['scheme'] ?? ''))
    : '';
$originHost = is_array($siteBaseParts)
    ? strtolower((string) ($siteBaseParts['host'] ?? ''))
    : '';
$originPort = is_array($siteBaseParts) && isset($siteBaseParts['port'])
    ? (int) $siteBaseParts['port']
    : null;

$domainIsValid = in_array($originScheme, ['http', 'https'], true)
    && $originHost !== ''
    && (
        $originHost === 'localhost'
        || filter_var($originHost, FILTER_VALIDATE_IP) !== false
        || filter_var($originHost, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false
    )
    && ($originPort === null || ($originPort >= 1 && $originPort <= 65535))
    && !isset($siteBaseParts['user'])
    && !isset($siteBaseParts['pass'])
    && !isset($siteBaseParts['query'])
    && !isset($siteBaseParts['fragment'])
    && (!isset($siteBaseParts['path']) || $siteBaseParts['path'] === '' || $siteBaseParts['path'] === '/');

$requestScheme = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off'
    ? 'https'
    : (strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))) === 'https' ? 'https' : 'http');
$requestHostHeader = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
$requestAuthority = parse_url($requestScheme . '://' . $requestHostHeader);
$requestHost = is_array($requestAuthority)
    ? strtolower((string) ($requestAuthority['host'] ?? ''))
    : '';
$requestPort = is_array($requestAuthority) && isset($requestAuthority['port'])
    ? (int) $requestAuthority['port']
    : null;
$defaultOriginPort = $originPort ?? ($originScheme === 'https' ? 443 : 80);
$defaultRequestPort = $requestPort ?? ($requestScheme === 'https' ? 443 : 80);
$requestMatchesConfiguredOrigin = $requestScheme === $originScheme
    && $requestHost !== ''
    && $requestHost === $originHost
    && $defaultRequestPort === $defaultOriginPort;

if (!$requestMatchesConfiguredOrigin) {
    $domainIsValid = false;
    error_log('[FragCity] Steam OpenID request origin does not match app.site_base_url; refusing mismatched callback.');
}

if ($configuredDomain !== '' && $configuredDomain !== $originHost) {
    $domainIsValid = false;
    error_log('[FragCity] Steam domain_name does not match app.site_base_url host.');
}

$openidOrigin = $domainIsValid
    ? $originScheme . '://' . $originHost . ($originPort !== null ? ':' . $originPort : '')
    : '';

if (!$domainIsValid) {
    error_log('[FragCity] Steam OpenID requires a valid app.site_base_url and matching Steam domain_name.');
    $_SESSION['auth_error'] = 'آدرس اصلی سایت با تنظیمات ورود Steam هماهنگ نیست؛ تنظیمات محیط سایت را بررسی کنید.';
    header('Location: /login/', true, 303);
    exit;
}


// آدرس‌های روت‌شده منطبق بر ساختار index.php شما

$steamRoutes = fragcity_settings('files.steam.steam_login', array());
$steamRoutes = is_array($steamRoutes) ? $steamRoutes : array();

/*
 * Build internal destinations relative to this repository's actual web path.
 * This supports both a Laragon subdirectory URL and a dedicated virtual host.
 * Configured routes are paths only; external redirect targets are rejected.
 */
$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/steam/steam_login.php'));
$siteBasePath = dirname(dirname($scriptName));
if ($siteBasePath === '/' || $siteBasePath === '.' || $siteBasePath === '\\') {
    $siteBasePath = '';
} else {
    $siteBasePath = '/' . trim($siteBasePath, '/');
}

$resolveSteamRoute = static function ($configuredRoute, string $fallback) use ($siteBasePath): string {
    $route = is_string($configuredRoute) ? trim($configuredRoute) : '';
    if (
        $route === ''
        || str_starts_with($route, '//')
        || preg_match('/^[a-z][a-z0-9+.-]*:/i', $route)
        || str_contains($route, "\r")
        || str_contains($route, "\n")
    ) {
        $route = $fallback;
    }

    return $siteBasePath . '/' . ltrim($route, '/');
};

$loginPage = $resolveSteamRoute($steamRoutes['login_page'] ?? null, 'index.php?page=login');
$completeRegistrationPage = $resolveSteamRoute(
    $steamRoutes['registration_complete_page'] ?? null,
    'index.php?page=register&steam=1'
);
$dashboardPage = $resolveSteamRoute($steamRoutes['dashboard_page'] ?? null, 'index.php');
$accountCompletionPage = $resolveSteamRoute(
    $steamRoutes['account_completion_page'] ?? null,
    'index.php?page=register&complete=1'
);

try {

    $openid = new LightOpenID($openidOrigin);

    

    if (!$openid->mode) {

        // Capture the explicit choice before leaving FragCity for Steam.
        // LightOpenID includes this query string in its exact return_to URL.
        $_SESSION['remember_login_requested'] =
            (string) ($_GET['remember_me'] ?? '') === '1';

        $openid->identity = 'https://steamcommunity.com/openid';

        header('Location: ' . $openid->authUrl());

        exit;

    } elseif ($openid->mode == 'cancel') {

        unset($_SESSION['remember_login_requested']);
        header('Location: ' . $loginPage);

        exit;

    } else {

        // Steam returns to the same return_to URL, including remember_me=1.
        // Restore from the callback query as well as the session so the
        // 30-day choice survives a browser/session edge case during redirect.
        $_SESSION['remember_login_requested'] =
            (string) ($_GET['remember_me'] ?? '') === '1'
            || !empty($_SESSION['remember_login_requested']);

        if ($openid->validate()) {

            $id = $openid->identity;

            preg_match('/\\Ahttps?:\\/\\/steamcommunity\\.com\\/openid\\/id\\/([0-9]{17})\\z/iD', $id, $matches);

            $steamid64 = $matches[1] ?? null;

            

            if (!$steamid64) {

                unset($_SESSION['remember_login_requested']);
                $_SESSION['auth_error'] = 'شناسه Steam نامعتبر است.';

                header('Location: ' . $loginPage);

                exit;

            }

            // Do not silently switch an authenticated FragCity session to a
            // different Steam identity. Log out explicitly before changing accounts.
            $activeSteamId = !empty($_SESSION['user_logged_in'])
                ? (string) ($_SESSION['steamid'] ?? '')
                : '';

            if (
                $activeSteamId !== ''
                && !hash_equals($activeSteamId, (string) $steamid64)
            ) {
                unset($_SESSION['remember_login_requested'], $_SESSION['steam_temp']);
                $_SESSION['auth_error'] = 'برای ورود به حساب Steam دیگر، ابتدا از حساب فعلی FragCity خارج شوید.';
                header('Location: ' . $dashboardPage, true, 303);
                exit;
            }

            // Use a single cached, server-side Steam summary implementation.
            $steamProfile = SteamProfileService::getPlayerSummary((string)$steamid64, true);

            

            $siteDatabaseProfile = fragcity_settings('files.index.database_profile', 'main');
            $siteDatabaseProfile = is_string($siteDatabaseProfile) && trim($siteDatabaseProfile) !== ''
                ? trim($siteDatabaseProfile)
                : 'main';
            $pdo = fragcity_create_pdo($siteDatabaseProfile);
            if (!$pdo) {
                error_log('[FragCity] Steam login could not connect to configured site database profile "' . $siteDatabaseProfile . '".');
                $_SESSION['auth_error'] = 'اتصال به دیتابیس سایت برقرار نشد. لطفاً کمی بعد دوباره تلاش کنید.';
                header('Location: ' . $loginPage, true, 303);
                exit;
            }

            try {
                $activeDatabaseName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
                error_log('[FragCity] Steam login database target confirmed (profile "' . $siteDatabaseProfile . '", database "' . $activeDatabaseName . '").');
            } catch (Throwable $databaseIdentityError) {
                error_log('[FragCity] Steam login connected, but active database identity could not be read.');
            }


            $stmt = $pdo->prepare("SELECT * FROM users WHERE steamid64 = ?");

            $stmt->execute([$steamid64]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            error_log('[FragCity] Steam login user lookup completed; match=' . ($user ? 'yes' : 'no') . '.');

            if ($user) {

                // Prevent session fixation when elevating an anonymous session to an authenticated one.
                session_regenerate_id(true);
                $_SESSION['auth_csrf_token'] = bin2hex(random_bytes(32));
                unset($_SESSION['steam_temp']);

                $_SESSION['user_logged_in'] = true;
                $_SESSION['steamid'] = $steamid64;

                $_SESSION['username'] = $user['username'] ?: ($steamProfile['personaname'] ?? $steamid64);

                $_SESSION['role'] = $user['role'] ?? 'user';

                // The session now belongs to this SteamID; activate and persist its public summary.
                $profileToStore = SteamProfileService::getPlayerSummary((string)$steamid64);
                if ($profileToStore !== []) {
                    try {
                        $profilePersisted = SteamProfileService::persistPlayerSummary($pdo, (string)$steamid64, $profileToStore);
                        error_log($profilePersisted
                            ? '[FragCity] Steam profile persistence completed for the matching users row.'
                            : '[FragCity] Steam profile persistence found no matching users row in the connected database.');
                    } catch (Throwable $profileStoreError) {
                        error_log('[FragCity] Steam profile persistence failed (' . get_class($profileStoreError) . ', code ' . (string)$profileStoreError->getCode() . '): ' . substr($profileStoreError->getMessage(), 0, 300));
                    }
                } else {
                    error_log('[FragCity] Steam profile persistence skipped: no usable Steam summary was available.');
                }

                $rememberSteamLogin = !empty($_SESSION['remember_login_requested']);
                RememberMeService::apply(
                    $pdo,
                    (string) $steamid64,
                    $rememberSteamLogin
                );
                error_log('[FragCity] Steam remember-me choice applied (enabled=' . ($rememberSteamLogin ? 'yes' : 'no') . ').');
                unset($_SESSION['remember_login_requested']);

                $usernameReady = preg_match('/^[a-z0-9][a-z0-9_-]{1,48}[a-z0-9]$/D', strtolower(trim((string) ($user['username'] ?? '')))) === 1;
                $firstNameReady = trim((string) ($user['first_name'] ?? '')) !== '';
                $lastNameReady = trim((string) ($user['last_name'] ?? '')) !== '';
                $emailReady = filter_var(trim((string) ($user['email'] ?? '')), FILTER_VALIDATE_EMAIL) !== false;
                $phoneReady = preg_match('/^09[0-9]{9}$/D', trim((string) ($user['phone'] ?? ''))) === 1;
                $paymenterLinked = (int) ($user['paymenter_user_id'] ?? 0) > 0
                    && (string) ($user['paymenter_sync_status'] ?? '') === 'synced';

                if (!$usernameReady || !$firstNameReady || !$lastNameReady || !$emailReady || !$phoneReady || !$paymenterLinked) {
                    $_SESSION['auth_error'] = 'برای فعال‌سازی حساب سرور، نام کاربری، نام، نام خانوادگی و اطلاعات تماس را تکمیل کنید.';
                    header('Location: ' . $accountCompletionPage, true, 303);
                    exit;
                }

                header('Location: ' . $dashboardPage, true, 303);
                exit;

            } else {

                $_SESSION['steam_temp'] = [

                    'steamid64' => $steamid64,

                    'personaname' => $steamProfile['personaname'] ?? $steamid64,

                    'avatar' => $steamProfile['avatarfull'] ?? '',

                ];

                // Store the new profile in the temp registration session only when no
                // other authenticated account is currently active.
                SteamProfileService::getPlayerSummary((string)$steamid64);

                header('Location: ' . $completeRegistrationPage);

                exit;

            }

        } else {

            unset($_SESSION['remember_login_requested']);
            $_SESSION['auth_error'] = 'اعتبارسنجی OpenID ناموفق بود.';

            header('Location: ' . $loginPage);

            exit;

        }

    }

} catch (Throwable $e) {

    error_log('[FragCity] Steam OpenID login failed: ' . $e->getMessage());
    unset($_SESSION['remember_login_requested']);
    $_SESSION['auth_error'] = 'ورود با استیم انجام نشد. لطفاً دوباره تلاش کنید.';

    header('Location: ' . $loginPage, true, 303);

    exit;

}
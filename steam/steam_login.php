<?php

// steam/steam_login.php

require_once __DIR__ . '/../settings/session.php';
fragcity_start_session();

// Load runtime settings and constants before using the settings helpers/constants.
require_once __DIR__ . '/../class/config.php';
require_once __DIR__ . '/../class/SteamProfileService.php';
require_once __DIR__ . '/openid.php';

$configuredDomain = defined('STEAM_DOMAIN_NAME')
    ? trim((string) STEAM_DOMAIN_NAME)
    : '';
$domain = $configuredDomain !== ''
    ? $configuredDomain
    : (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

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

try {

    $openid = new LightOpenID($domain);

    

    if (!$openid->mode) {

        $openid->identity = 'https://steamcommunity.com/openid';

        header('Location: ' . $openid->authUrl());

        exit;

    } elseif ($openid->mode == 'cancel') {

        header('Location: ' . $loginPage);

        exit;

    } else {

        if ($openid->validate()) {

            $id = $openid->identity;

            preg_match('/^https?:\/\/steamcommunity\.com\/openid\/id\/(\d{17,})/', $id, $matches);

            $steamid64 = $matches[1] ?? null;

            

            if (!$steamid64) {

                $_SESSION['auth_error'] = 'شناسه Steam نامعتبر است.';

                header('Location: ' . $loginPage);

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
                error_log('[FragCity] Steam login could not connect to the configured site database profile.');
                $_SESSION['auth_error'] = 'اتصال به دیتابیس سایت برقرار نشد. لطفاً کمی بعد دوباره تلاش کنید.';
                header('Location: ' . $loginPage, true, 303);
                exit;
            }


            $stmt = $pdo->prepare("SELECT * FROM users WHERE steamid64 = ?");

            $stmt->execute([$steamid64]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            error_log('[FragCity] Steam login user lookup completed; match=' . ($user ? 'yes' : 'no') . '.');

            if ($user) {

                // Prevent session fixation when elevating an anonymous session to an authenticated one.
                session_regenerate_id(true);
                $_SESSION['user_logged_in'] = true;

                $_SESSION['steamid'] = $steamid64;

                $_SESSION['username'] = $user['username'] ?: ($steamProfile['personaname'] ?? $steamid64);

                $_SESSION['role'] = $user['role'] ?? 'user';

                // The session now belongs to this SteamID; activate and persist its public summary.
                $profileToStore = SteamProfileService::getPlayerSummary((string)$steamid64);
                if ($profileToStore !== []) {
                    try {
                        SteamProfileService::persistPlayerSummary($pdo, (string)$steamid64, $profileToStore);
                    } catch (Throwable $profileStoreError) {
                        // Authentication must not fail because a profile migration is pending.
                        error_log('[FragCity] Steam profile persistence failed; check the users profile migration.');
                    }
                }

                header('Location: ' . $dashboardPage);

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

            $_SESSION['auth_error'] = 'اعتبارسنجی OpenID ناموفق بود.';

            header('Location: ' . $loginPage);

            exit;

        }

    }

} catch (Throwable $e) {

    error_log('[FragCity] Steam OpenID login failed: ' . $e->getMessage());
    $_SESSION['auth_error'] = 'ورود با استیم انجام نشد. لطفاً دوباره تلاش کنید.';

    header('Location: ' . $loginPage, true, 303);

    exit;

}
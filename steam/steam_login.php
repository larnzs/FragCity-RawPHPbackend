<?php

// steam/steam_login.php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Load runtime settings and constants before using the settings helpers/constants.
require_once __DIR__ . '/../settings/loader.php';
require_once __DIR__ . '/../class/config.php';
require_once __DIR__ . '/openid.php';

$configuredDomain = defined('STEAM_DOMAIN_NAME')
    ? trim((string) STEAM_DOMAIN_NAME)
    : '';
$domain = $configuredDomain !== ''
    ? $configuredDomain
    : (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

// آدرس‌های روت‌شده منطبق بر ساختار index.php شما

$steamRoutes = fragcity_settings('files.steam.steam_login', array());
$loginPage=isset($steamRoutes['login_page'])?$steamRoutes['login_page']:'index.php?page=login';
$completeRegistrationPage=isset($steamRoutes['registration_complete_page'])?$steamRoutes['registration_complete_page']:'index.php?page=register';
$dashboardPage=isset($steamRoutes['dashboard_page'])?$steamRoutes['dashboard_page']:'index.php';

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

            

            // دریافت اطلاعات پروفایل از Steam API

            $apiKey = defined('STEAM_API_KEY') ? STEAM_API_KEY : '';

            $steamProfile = [];

            if (!empty($apiKey)) {

                $apiUrl = "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v2/?key={$apiKey}&steamids={$steamid64}";

                $json = @file_get_contents($apiUrl);

                if ($json) {

                    $data = json_decode($json, true);

                    $player = $data['response']['players'][0] ?? [];

                    $steamProfile['personaname'] = $player['personaname'] ?? '';

                    $steamProfile['avatarfull'] = $player['avatarfull'] ?? '';

                }

            }

            

            $steamSettings=fragcity_settings('files.steam.steam_login',array());
            $profile=isset($steamSettings['database_profile'])?$steamSettings['database_profile']:'main';
            $pdo=fragcity_create_pdo($profile);
            if(!$pdo){$_SESSION['auth_error']='Database unavailable. Please try again later.';header('Location: '.$loginPage);exit;}


            $stmt = $pdo->prepare("SELECT * FROM users WHERE steamid64 = ?");

            $stmt->execute([$steamid64]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            

            if ($user) {

                // Prevent session fixation when elevating an anonymous session to an authenticated one.
                session_regenerate_id(true);
                $_SESSION['user_logged_in'] = true;

                $_SESSION['steamid'] = $steamid64;

                $_SESSION['username'] = $user['username'] ?: ($steamProfile['personaname'] ?? $steamid64);

                $_SESSION['role'] = $user['role'] ?? 'user';

                header('Location: ' . $dashboardPage);

                exit;

            } else {

                $_SESSION['steam_temp'] = [

                    'steamid64' => $steamid64,

                    'personaname' => $steamProfile['personaname'] ?? $steamid64,

                    'avatar' => $steamProfile['avatarfull'] ?? '',

                ];

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
<?php
// steam/steam_login.php
session_start();
require_once __DIR__ . '/openid.php';

$domain = defined('STEAM_DOMAIN_NAME') ? STEAM_DOMAIN_NAME : $_SERVER['HTTP_HOST'];

// آدرس‌های روت‌شده منطبق بر ساختار index.php شما
$loginPage = '/index.php?page=login';
$completeRegistrationPage = '/index.php?page=register&steam=1';
$dashboardPage = '/index.php'; // یا صفحه اصلی پس از ورود موفق

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
            
            // اتصال به دیتابیس
            $db_host = 'localhost';
            $db_port = '3306';
            $db_name = 'kyonxcbt_fragcity_services';
            $db_user = 'kyonxcbt_admin';
            $db_pass = 'Atrisayeafsorde824@';
            
            $pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo->prepare("SELECT * FROM users WHERE steamid64 = ?");
            $stmt->execute([$steamid64]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
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
} catch (ErrorException $e) {
    $_SESSION['auth_error'] = 'خطای ارتباط با استیم: ' . $e->getMessage();
    header('Location: ' . $loginPage);
    exit;
}
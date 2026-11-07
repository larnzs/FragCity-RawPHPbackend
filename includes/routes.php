<?php
/**
 * Canonical public URL map for FragCity routes.
 * Keep these slugs in sync with the Apache front-controller rules in .htaccess.
 */
if (!function_exists('fragcity_route_slugs')) {
    function fragcity_route_slugs(): array
    {
        return [
            'home' => '',
            'servers' => 'servers',
            'servers_vps' => 'servers',
            'skins' => 'skins',
            'skins_vps' => 'skins',
            'buyserver' => 'buy-server',
            'buyserveropen' => 'buy-server',
            'launcher' => 'launcher',
            'usermanual' => 'server-manual',
            'serverguide' => 'serverguide',
            'status' => 'status',
            'about' => 'about',
            'support' => 'support',
            'login' => 'login',
            'register' => 'register',
            'profile' => 'profile',
            'myservers' => 'my-servers',
            'orders' => 'orders',
            'vip' => 'vip',
            'leaderboard' => 'leaderboard',
            'leaderboard_vps' => 'leaderboard',
            'forgot_password' => 'forgot-password',
            'reset_password' => 'reset-password',
            'rules' => 'rules',
            'privacy' => 'privacy',
            'deadlock_servers_vps' => 'deadlock-servers',
            'banlist' => 'banlist',
            'donations' => 'donations',
        ];
    }
}

if (!function_exists('fragcity_page_url')) {
    function fragcity_page_url(string $page): string
    {
        $routes = fragcity_route_slugs();
        $slug = $routes[$page] ?? trim($page, '/');

        return $slug === '' ? '/' : '/' . rawurlencode($slug) . '/';
    }
}

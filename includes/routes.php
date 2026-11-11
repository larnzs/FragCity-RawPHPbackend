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

if (!function_exists('fragcity_billing_sso_url')) {
    /**
     * Build the Billing entry point for the one-time FragCity SSO bridge.
     * Only local Paymenter return paths are accepted.
     */
    function fragcity_billing_sso_url(string $returnTo = '/'): string
    {
        $returnTo = trim($returnTo);
        if (
            $returnTo === ''
            || !str_starts_with($returnTo, '/')
            || str_starts_with($returnTo, '//')
            || str_contains($returnTo, chr(92))
            || preg_match('/[\\r\\n\\x00-\\x1F\\x7F]/', $returnTo)
            || preg_match('/^[a-z][a-z0-9+.-]*:/i', $returnTo)
        ) {
            $returnTo = '/';
        }

        $parts = parse_url($returnTo);
        if (!is_array($parts) || isset($parts['scheme']) || isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            $returnTo = '/';
        }

        return 'https://billing.fragcity.ir/auth/fragcity/sso/start?redirect=' . rawurlencode($returnTo);
    }
}

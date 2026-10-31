<?php
declare(strict_types=1);

require_once __DIR__ . '/loader.php';

/**
 * Start a browser-session cookie for ordinary FragCity logins.
 *
 * Persistent authentication is handled separately by RememberMeService and
 * is enabled only when the user explicitly selects "Remember me".
 */
if (!function_exists('fragcity_start_session')) {
    function fragcity_start_session(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Keep server-side session records long enough for active browser tabs.
        // The browser cookie itself remains a session cookie (expires on close).
        $serverLifetime = 60 * 60 * 24 * 30;
        $forwardedProto = strtolower(trim(explode(
            ',',
            (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')
        )[0] ?? ''));

        $isHttps =
            (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || strtolower((string) ($_SERVER['REQUEST_SCHEME'] ?? '')) === 'https'
            || $forwardedProto === 'https';

        ini_set('session.gc_maxlifetime', (string) $serverLifetime);
        ini_set('session.cookie_lifetime', '0');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        $cookieOptions = [
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        session_set_cookie_params($cookieOptions);
        session_start();

        // Also convert cookies created by the previous 30-day default into
        // browser-session cookies when users next visit the updated website.
        if (!headers_sent()) {
            setcookie(session_name(), session_id(), [
                'expires' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }
}

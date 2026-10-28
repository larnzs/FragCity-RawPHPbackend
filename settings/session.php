<?php
declare(strict_types=1);

require_once __DIR__ . '/loader.php';

/**
 * Start a shared, persistent FragCity session.
 *
 * The browser cookie is renewed for 30 days on each request. The server-side
 * session lifetime matches it, while HttpOnly/SameSite and HTTPS-only cookies
 * protect the session token in normal production requests.
 */
if (!function_exists('fragcity_start_session')) {
    function fragcity_start_session(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $lifetime = 60 * 60 * 24 * 30;
        $forwardedProto = strtolower(trim(explode(
            ',',
            (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')
        )[0] ?? ''));

        $isHttps =
            (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
            || strtolower((string)($_SERVER['REQUEST_SCHEME'] ?? '')) === 'https'
            || $forwardedProto === 'https';

        ini_set('session.gc_maxlifetime', (string)$lifetime);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        $cookieOptions = [
            'lifetime' => $lifetime,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        session_set_cookie_params($cookieOptions);
        session_start();

        // Refresh an existing browser-session cookie so accounts already
        // logged in before this update also receive the persistent expiry.
        if (!headers_sent()) {
            setcookie(session_name(), session_id(), [
                'expires' => time() + $lifetime,
                'path' => '/',
                'domain' => '',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }
}

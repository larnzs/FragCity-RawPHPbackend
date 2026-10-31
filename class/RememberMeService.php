<?php
declare(strict_types=1);

/**
 * Persistent "remember me" authentication for FragCity.
 *
 * The browser receives only a random selector + validator. The database stores
 * a SHA-256 hash of the validator, never the validator itself.
 */
final class RememberMeService
{
    public const COOKIE_NAME = 'fragcity_remember';
    private const LIFETIME_SECONDS = 2592000; // 30 days

    private static function isSecureRequest(): bool
    {
        $forwardedProto = strtolower(trim(explode(
            ',',
            (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')
        )[0] ?? ''));

        return
            (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || strtolower((string) ($_SERVER['REQUEST_SCHEME'] ?? '')) === 'https'
            || $forwardedProto === 'https';
    }

    private static function cookieOptions(int $expires): array
    {
        return [
            'expires' => $expires,
            'path' => '/',
            'secure' => self::isSecureRequest(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    /**
     * @return array{selector:string, validator:string}|null
     */
    private static function parseCookieValue(string $value): ?array
    {
        if (!preg_match('/\A([a-f0-9]{24}):([a-f0-9]{64})\z/D', $value, $matches)) {
            return null;
        }

        return [
            'selector' => $matches[1],
            'validator' => $matches[2],
        ];
    }

    public static function clearCookie(): void
    {
        if (!headers_sent()) {
            setcookie(
                self::COOKIE_NAME,
                '',
                self::cookieOptions(time() - 3600)
            );
        }

        unset($_COOKIE[self::COOKIE_NAME]);
    }

    /**
     * Create a new persistent token when enabled, or revoke this browser's
     * existing token when the user leaves the switch disabled.
     */
    public static function apply(PDO $pdo, string $steamid64, bool $remember): void
    {
        if (!$remember) {
            self::revokeCurrent($pdo);
            return;
        }

        if (!preg_match('/\A[0-9]{17}\z/D', $steamid64)) {
            self::clearCookie();
            return;
        }

        // Replace only this browser's existing token. Other signed-in devices
        // remain untouched.
        self::revokeCurrent($pdo);

        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $expiresAt = time() + self::LIFETIME_SECONDS;

        try {
            $statement = $pdo->prepare(
                'INSERT INTO fragcity_remember_tokens
                    (steamid64, selector, token_hash, expires_at, created_at, last_used_at)
                 VALUES (?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
            );
            $statement->execute([
                $steamid64,
                $selector,
                hash('sha256', $validator),
                gmdate('Y-m-d H:i:s', $expiresAt),
            ]);
        } catch (Throwable $error) {
            self::clearCookie();
            error_log('[FragCity] Remember-me token could not be stored (' . get_class($error) . ', code ' . (string) $error->getCode() . ').');
            return;
        }

        $cookieValue = $selector . ':' . $validator;
        if (!headers_sent()) {
            setcookie(
                self::COOKIE_NAME,
                $cookieValue,
                self::cookieOptions($expiresAt)
            );
            $_COOKIE[self::COOKIE_NAME] = $cookieValue;
        }
    }

    /**
     * Validate and refresh the browser token.
     *
     * When $activeSteamId is supplied, the token must belong to that already
     * authenticated session; a token for a different account is revoked.
     *
     * @return array<string,mixed>|null
     */
    public static function restore(PDO $pdo, ?string $activeSteamId = null): ?array
    {
        $cookieValue = $_COOKIE[self::COOKIE_NAME] ?? null;
        if ($cookieValue === null || $cookieValue === '') {
            return null;
        }

        if (!is_string($cookieValue)) {
            self::clearCookie();
            return null;
        }

        $parts = self::parseCookieValue($cookieValue);
        if ($parts === null) {
            self::clearCookie();
            return null;
        }

        try {
            $tokenStatement = $pdo->prepare(
                'SELECT id, steamid64, token_hash, expires_at
                 FROM fragcity_remember_tokens
                 WHERE selector = ?
                 LIMIT 1'
            );
            $tokenStatement->execute([$parts['selector']]);
            $token = $tokenStatement->fetch(PDO::FETCH_ASSOC);

            if (!is_array($token)) {
                self::clearCookie();
                return null;
            }

            $storedHash = (string) ($token['token_hash'] ?? '');
            $presentedHash = hash('sha256', $parts['validator']);
            if ($storedHash === '' || !hash_equals($storedHash, $presentedHash)) {
                self::clearCookie();
                return null;
            }

            $tokenSteamId = (string) ($token['steamid64'] ?? '');
            if (
                !preg_match('/\A[0-9]{17}\z/D', $tokenSteamId)
                || ($activeSteamId !== null && !hash_equals($activeSteamId, $tokenSteamId))
            ) {
                $delete = $pdo->prepare('DELETE FROM fragcity_remember_tokens WHERE id = ?');
                $delete->execute([(int) $token['id']]);
                self::clearCookie();
                return null;
            }

            $expiresAt = DateTimeImmutable::createFromFormat(
                '!Y-m-d H:i:s',
                (string) ($token['expires_at'] ?? ''),
                new DateTimeZone('UTC')
            );
            if (!$expiresAt || $expiresAt->getTimestamp() <= time()) {
                $delete = $pdo->prepare('DELETE FROM fragcity_remember_tokens WHERE id = ?');
                $delete->execute([(int) $token['id']]);
                self::clearCookie();
                return null;
            }

            $userStatement = $pdo->prepare(
                'SELECT * FROM users WHERE steamid64 = ? LIMIT 1'
            );
            $userStatement->execute([$tokenSteamId]);
            $user = $userStatement->fetch(PDO::FETCH_ASSOC);

            if (!is_array($user)) {
                $delete = $pdo->prepare('DELETE FROM fragcity_remember_tokens WHERE id = ?');
                $delete->execute([(int) $token['id']]);
                self::clearCookie();
                return null;
            }

            // Sliding expiration: active use keeps the remembered device alive
            // for another 30 days, while idle devices naturally expire.
            $nextExpiry = time() + self::LIFETIME_SECONDS;
            $touch = $pdo->prepare(
                'UPDATE fragcity_remember_tokens
                 SET expires_at = ?, last_used_at = UTC_TIMESTAMP()
                 WHERE id = ?'
            );
            $touch->execute([
                gmdate('Y-m-d H:i:s', $nextExpiry),
                (int) $token['id'],
            ]);

            if (!headers_sent()) {
                setcookie(
                    self::COOKIE_NAME,
                    $cookieValue,
                    self::cookieOptions($nextExpiry)
                );
                $_COOKIE[self::COOKIE_NAME] = $cookieValue;
            }

            return $user;
        } catch (Throwable $error) {
            // A transient database problem must not delete a valid browser token.
            error_log('[FragCity] Remember-me validation failed (' . get_class($error) . ', code ' . (string) $error->getCode() . ').');
            return null;
        }
    }

    public static function revokeCurrent(PDO $pdo): void
    {
        $cookieValue = $_COOKIE[self::COOKIE_NAME] ?? null;
        if (is_string($cookieValue)) {
            $parts = self::parseCookieValue($cookieValue);
            if ($parts !== null) {
                try {
                    $statement = $pdo->prepare(
                        'DELETE FROM fragcity_remember_tokens WHERE selector = ?'
                    );
                    $statement->execute([$parts['selector']]);
                } catch (Throwable $error) {
                    error_log('[FragCity] Remember-me token revocation failed (' . get_class($error) . ', code ' . (string) $error->getCode() . ').');
                }
            }
        }

        self::clearCookie();
    }
}

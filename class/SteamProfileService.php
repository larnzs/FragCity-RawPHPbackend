<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

/** Server-side Steam Community lookup with session caching. The API key stays in PHP. */
final class SteamProfileService
{
    private const CACHE_TTL = 1800;

    public static function getPlayerSummary(string $steamid64, bool $forceRefresh = false): array
    {
        $steamid64 = trim($steamid64);
        if (!preg_match('/^[0-9]{17}$/D', $steamid64)) return [];

        $cached = self::getCachedPlayer($steamid64);
        if (
            !$forceRefresh && $cached !== []
            && isset($_SESSION['steam_profile_cache'][$steamid64]['fetched_at'])
            && (int)$_SESSION['steam_profile_cache'][$steamid64]['fetched_at'] >= time() - self::CACHE_TTL
        ) {
            self::syncSession($cached);
            return $cached;
        }

        $apiKey = defined('STEAM_API_KEY') ? trim((string)STEAM_API_KEY) : '';
        if ($apiKey === '') {
            self::syncSession($cached);
            return $cached;
        }

        $url = 'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v2/?'
            . http_build_query(['key' => $apiKey, 'steamids' => $steamid64]);
        $json = self::requestJson($url);
        if ($json === null) {
            self::syncSession($cached);
            return $cached;
        }

        $decoded = json_decode($json, true);
        $players = $decoded['response']['players'] ?? [];
        if (!is_array($players)) {
            self::syncSession($cached);
            return $cached;
        }

        $rawPlayer = null;
        foreach ($players as $candidate) {
            if (is_array($candidate) && (string)($candidate['steamid'] ?? '') === $steamid64) {
                $rawPlayer = $candidate;
                break;
            }
        }
        if (!is_array($rawPlayer)) {
            self::syncSession($cached);
            return $cached;
        }

        $player = self::normalizePlayer($rawPlayer, $steamid64);
        if ($player === []) {
            self::syncSession($cached);
            return $cached;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            if (!isset($_SESSION['steam_profile_cache']) || !is_array($_SESSION['steam_profile_cache'])) {
                $_SESSION['steam_profile_cache'] = [];
            }
            $_SESSION['steam_profile_cache'][$steamid64] = ['fetched_at' => time(), 'player' => $player];
        }

        self::syncSession($player);
        return $player;
    }

    /**
     * Save normalized public Steam profile fields for an existing user.
     * The caller catches schema/DB errors so OpenID authentication is not blocked.
     */
    public static function persistPlayerSummary(\PDO $pdo, string $steamid64, array $player): bool
    {
        if (!preg_match('/^[0-9]{17}$/D', $steamid64)
            || (string)($player['steamid'] ?? '') !== $steamid64) {
            return false;
        }

        $personaname = trim((string)($player['personaname'] ?? ''));
        $personaname = function_exists('mb_substr')
            ? mb_substr($personaname, 0, 128, 'UTF-8')
            : substr($personaname, 0, 128);
        $profileUrl = self::safeProfileUrl((string)($player['profileurl'] ?? ''));
        $avatarUrl = self::safeAvatarUrl((string)($player['avatarfull'] ?? ''));
        $avatarPath = $avatarUrl !== '' ? self::cacheAvatarImage($avatarUrl, $steamid64) : '';
        $country = strtoupper((string)($player['loccountrycode'] ?? ''));
        if (!preg_match('/^[A-Z]{2}$/D', $country)) $country = '';
        $game = trim((string)($player['gameextrainfo'] ?? ''));
        $game = function_exists('mb_substr') ? mb_substr($game, 0, 255, 'UTF-8') : substr($game, 0, 255);
        $gameId = (string)($player['gameid'] ?? '');
        if (!preg_match('/^[0-9]{1,20}$/D', $gameId)) $gameId = '';

        $stmt = $pdo->prepare(
            "UPDATE users SET
                steam_personaname = COALESCE(NULLIF(?, ''), steam_personaname),
                steam_profileurl = COALESCE(NULLIF(?, ''), steam_profileurl),
                steam_avatar_url = COALESCE(NULLIF(?, ''), steam_avatar_url),
                steam_avatar_path = COALESCE(NULLIF(?, ''), steam_avatar_path),
                steam_personastate = ?,
                steam_communityvisibilitystate = ?,
                steam_profilestate = ?,
                steam_timecreated = COALESCE(NULLIF(?, 0), steam_timecreated),
                steam_lastlogoff = COALESCE(NULLIF(?, 0), steam_lastlogoff),
                steam_loccountrycode = COALESCE(NULLIF(?, ''), steam_loccountrycode),
                steam_gameextrainfo = ?,
                steam_gameid = ?,
                steam_profile_updated_at = CURRENT_TIMESTAMP
             WHERE steamid64 = ?"
        );
        $stmt->execute([
            $personaname,
            $profileUrl,
            $avatarUrl,
            $avatarPath,
            self::boundedInt($player['personastate'] ?? 0, 0, 6),
            self::boundedInt($player['communityvisibilitystate'] ?? 0, 0, 3),
            self::boundedInt($player['profilestate'] ?? 0, 0, 1),
            self::boundedInt($player['timecreated'] ?? 0, 0, PHP_INT_MAX),
            self::boundedInt($player['lastlogoff'] ?? 0, 0, PHP_INT_MAX),
            $country,
            $game,
            $gameId,
            $steamid64,
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Cache the Steam CDN avatar locally after validating its host, size and raster format.
     * The custom avatar uploaded by the user lives under img/avatars and is not overwritten.
     */
    private static function cacheAvatarImage(string $url, string $steamid64): string
    {
        $url = self::safeAvatarUrl($url);
        if ($url === '' || !function_exists('getimagesizefromstring')) return '';

        $bytes = null;
        if (function_exists('curl_init')) {
            $handle = curl_init($url);
            if ($handle === false) return '';
            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 6,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER => ['Accept: image/jpeg,image/png,image/webp'],
                CURLOPT_USERAGENT => 'FragCity/1.0 (+https://fragcity.ir)',
            ]);
            $response = curl_exec($handle);
            $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
            curl_close($handle);
            if (is_string($response) && $status === 200) $bytes = $response;
        } else {
            return '';
        }

        if (!is_string($bytes) || $bytes === '' || strlen($bytes) > 2 * 1024 * 1024) return '';
        $image = @getimagesizefromstring($bytes);
        if (!is_array($image)) return '';
        $width = (int)($image[0] ?? 0);
        $height = (int)($image[1] ?? 0);
        $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = (string)($image['mime'] ?? '');
        if (!isset($types[$mime]) || $width < 1 || $height < 1 || $width > 4096 || $height > 4096 || $width * $height > 8000000) return '';

        $root = dirname(__DIR__);
        $directory = $root . '/img/steam-avatars';
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) return '';

        $filename = 'steam_' . $steamid64 . '.' . $types[$mime];
        $diskPath = $directory . '/' . $filename;
        $written = @file_put_contents($diskPath, $bytes, LOCK_EX);
        if ($written !== strlen($bytes)) return '';
        @chmod($diskPath, 0644);

        return 'img/steam-avatars/' . $filename;
    }

    private static function getCachedPlayer(string $steamid64): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) return [];
        $entry = $_SESSION['steam_profile_cache'][$steamid64] ?? null;
        if (is_array($entry) && is_array($entry['player'] ?? null)) return $entry['player'];

        $activeProfile = $_SESSION['steam_profile'] ?? null;
        if (is_array($activeProfile) && (string)($activeProfile['steamid'] ?? '') === $steamid64) {
            return $activeProfile;
        }
        return [];
    }

    private static function syncSession(array $player): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE || empty($player['steamid'])) return;

        // Never let a lookup for another SteamID overwrite the active account's
        // identity/avatar session fields. Cache entries remain per-SteamID.
        $sessionSteamId = (string)($_SESSION['steamid'] ?? '');
        if ($sessionSteamId === '') {
            $sessionSteamId = (string)($_SESSION['steam_temp']['steamid64'] ?? '');
        }
        if ($sessionSteamId === '' || $sessionSteamId !== (string)$player['steamid']) return;

        $_SESSION['steam_profile'] = $player;
        $_SESSION['steam_personaname'] = (string)($player['personaname'] ?? '');
        $_SESSION['steam_avatarfull'] = (string)($player['avatarfull'] ?? '');
        $_SESSION['steam_profile_url'] = (string)($player['profileurl'] ?? '');
        $_SESSION['steam_profile_visibility'] = (int)($player['communityvisibilitystate'] ?? 0);
        $_SESSION['steam_personastate'] = (int)($player['personastate'] ?? 0);
    }

    private static function normalizePlayer(array $raw, string $steamid64): array
    {
        if ((string)($raw['steamid'] ?? '') !== $steamid64) return [];
        $name = trim((string)($raw['personaname'] ?? ''));
        $name = function_exists('mb_substr') ? mb_substr($name, 0, 128, 'UTF-8') : substr($name, 0, 128);

        return [
            'steamid' => $steamid64,
            'personaname' => $name,
            'profileurl' => self::safeProfileUrl((string)($raw['profileurl'] ?? '')),
            'avatar' => self::safeAvatarUrl((string)($raw['avatar'] ?? '')),
            'avatarmedium' => self::safeAvatarUrl((string)($raw['avatarmedium'] ?? '')),
            'avatarfull' => self::safeAvatarUrl((string)($raw['avatarfull'] ?? '')),
            'personastate' => self::boundedInt($raw['personastate'] ?? 0, 0, 6),
            'communityvisibilitystate' => self::boundedInt($raw['communityvisibilitystate'] ?? 0, 0, 3),
            'profilestate' => self::boundedInt($raw['profilestate'] ?? 0, 0, 1),
            'timecreated' => self::boundedInt($raw['timecreated'] ?? 0, 0, PHP_INT_MAX),
            'lastlogoff' => self::boundedInt($raw['lastlogoff'] ?? 0, 0, PHP_INT_MAX),
            'loccountrycode' => strtoupper(preg_match('/^[a-zA-Z]{2}$/D', (string)($raw['loccountrycode'] ?? ''))
                ? (string)$raw['loccountrycode'] : ''),
            'gameextrainfo' => trim((string)($raw['gameextrainfo'] ?? '')),
            'gameid' => preg_match('/^[0-9]{1,20}$/D', (string)($raw['gameid'] ?? '')) ? (string)$raw['gameid'] : '',
        ];
    }

    private static function boundedInt($value, int $min, int $max): int
    {
        if (!is_numeric($value)) return $min;
        return max($min, min($max, (int)$value));
    }

    private static function safeProfileUrl(string $url): string
    {
        $url = trim($url);
        if (!filter_var($url, FILTER_VALIDATE_URL)) return '';
        $parts = parse_url($url);
        if (
            !is_array($parts)
            || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || strtolower((string)($parts['host'] ?? '')) !== 'steamcommunity.com'
        ) return '';
        return $url;
    }

    private static function safeAvatarUrl(string $url): string
    {
        $url = trim($url);
        if (!filter_var($url, FILTER_VALIDATE_URL)) return '';
        $parts = parse_url($url);
        $host = strtolower((string)($parts['host'] ?? ''));
        $allowedHost = preg_match('/(^|\\.)steamstatic\\.com$/D', $host)
            || preg_match('/(^|\\.)akamaihd\\.net$/D', $host);
        if (
            !is_array($parts)
            || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || !$allowedHost
        ) return '';
        return $url;
    }

    private static function requestJson(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $handle = curl_init($url);
            if ($handle === false) return null;
            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 6,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
                CURLOPT_USERAGENT => 'FragCity/1.0 (+https://fragcity.ir)',
            ]);
            $response = curl_exec($handle);
            $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
            curl_close($handle);
            return is_string($response) && $status === 200 ? $response : null;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET', 'timeout' => 6, 'ignore_errors' => false,
                'header' => "Accept: application/json\r\nUser-Agent: FragCity/1.0 (+https://fragcity.ir)\r\n",
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $response = @file_get_contents($url, false, $context);
        return is_string($response) ? $response : null;
    }
}

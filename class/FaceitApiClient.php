<?php
declare(strict_types=1);

require_once __DIR__ . '/../settings/loader.php';

/**
 * Server-side FACEIT Data API client for public CS2 player skill levels.
 * The API key never leaves PHP. Results are cached outside the document root.
 */
final class FaceitApiClient
{
    private const API_BASE = 'https://open.faceit.com/data/v4';
    private const CACHE_DIRECTORY = 'fragcity-faceit-cache-v1';
    private const CACHE_TTL_SECONDS = 900;
    private const NEGATIVE_CACHE_TTL_SECONDS = 600;
    private const ERROR_CACHE_TTL_SECONDS = 45;
    private const MAX_CONCURRENT_LOOKUPS = 12;

    private string $apiKey;
    private string $cacheDirectory;

    public function __construct()
    {
        $apiKey = trim((string)fragcity_settings('integrations.faceit_api.api_key', ''));
        if ($apiKey === '' || str_starts_with($apiKey, 'REPLACE_WITH_') || preg_match('/[\r\n]/', $apiKey)) {
            $apiKey = '';
        }

        $this->apiKey = $apiKey;
        $this->cacheDirectory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . self::CACHE_DIRECTORY;
    }

    /**
     * Add FACEIT rank metadata to trusted player rows.
     *
     * @param array<int|string, mixed> $players
     * @return array<int|string, mixed>
     */
    public function enrichPlayers(array $players): array
    {
        $profiles = [];
        $steamIds = [];

        foreach ($players as $player) {
            if (!is_array($player)) {
                continue;
            }

            $steamId = self::normalizeSteamId($player['steam_id'] ?? null);
            if ($steamId !== null) {
                $steamIds[$steamId] = $steamId;
            }
        }

        if ($this->apiKey !== '' && $steamIds !== []) {
            $missing = [];

            foreach ($steamIds as $steamId) {
                $cached = $this->readCache($steamId);
                if ($cached !== null) {
                    $profiles[$steamId] = $cached;
                } else {
                    $missing[] = $steamId;
                }
            }

            foreach (array_chunk($missing, self::MAX_CONCURRENT_LOOKUPS) as $batch) {
                foreach ($this->fetchBatch($batch) as $steamId => $profile) {
                    $profiles[$steamId] = $profile;
                    $this->writeCache($steamId, $profile);
                }
            }
        }

        foreach ($players as $index => $player) {
            if (!is_array($player)) {
                continue;
            }

            $steamId = self::normalizeSteamId($player['steam_id'] ?? null);
            if ($steamId === null) {
                $identityStatus = (string)($player['steam_id_status'] ?? 'steam_id_missing');
                $allowedIdentityStatuses = [
                    'steam_id_missing',
                    'not_found',
                    'ambiguous',
                    'simpleadmin_unavailable',
                ];
                $profile = self::emptyProfile(
                    in_array($identityStatus, $allowedIdentityStatuses, true)
                        ? $identityStatus
                        : 'steam_id_missing'
                );
            } elseif ($this->apiKey === '') {
                $profile = self::emptyProfile('api_not_configured');
            } else {
                $profile = $profiles[$steamId] ?? self::emptyProfile('api_unavailable');
            }

            $players[$index]['faceit_level'] = $profile['faceit_level'];
            $players[$index]['faceit_status'] = $profile['faceit_status'];
        }

        return $players;
    }

    private static function normalizeSteamId(mixed $value): ?string
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }

        $steamId = trim((string)$value);
        return preg_match('/^7656119[0-9]{10}$/D', $steamId) === 1 ? $steamId : null;
    }

    /** @return array{faceit_level:null,faceit_status:string} */
    private static function emptyProfile(string $status): array
    {
        return [
            'faceit_level' => null,
            'faceit_status' => $status,
        ];
    }

    /**
     * @param array<int, string> $steamIds
     * @return array<string, array{faceit_level:?int,faceit_status:string}>
     */
    private function fetchBatch(array $steamIds): array
    {
        $results = [];

        if ($steamIds === []) {
            return $results;
        }

        if (!function_exists('curl_init') || !function_exists('curl_multi_init')) {
            foreach ($steamIds as $steamId) {
                $results[$steamId] = self::emptyProfile('api_unavailable');
            }
            return $results;
        }

        $multiHandle = curl_multi_init();
        if ($multiHandle === false) {
            foreach ($steamIds as $steamId) {
                $results[$steamId] = self::emptyProfile('api_unavailable');
            }
            return $results;
        }

        $handles = [];

        foreach ($steamIds as $steamId) {
            $query = http_build_query(
                [
                    'game' => 'cs2',
                    'game_player_id' => $steamId,
                ],
                '',
                '&',
                PHP_QUERY_RFC3986
            );

            $handle = curl_init(self::API_BASE . '/players?' . $query);
            if ($handle === false) {
                $results[$steamId] = self::emptyProfile('api_unavailable');
                continue;
            }

            curl_setopt_array($handle, [
                CURLOPT_HTTPGET => true,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Authorization: Bearer ' . $this->apiKey,
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT_MS => 1800,
                CURLOPT_TIMEOUT_MS => 5000,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_USERAGENT => 'FragCity-ServerBrowser/1.0',
            ]);

            curl_multi_add_handle($multiHandle, $handle);
            $handles[$steamId] = $handle;
        }

        $running = 0;
        do {
            $multiStatus = curl_multi_exec($multiHandle, $running);
            if ($multiStatus !== CURLM_OK) {
                break;
            }

            if ($running > 0) {
                $selected = curl_multi_select($multiHandle, 1.0);
                if ($selected === -1) {
                    usleep(100000);
                }
            }
        } while ($running > 0);

        foreach ($handles as $steamId => $handle) {
            $body = curl_multi_getcontent($handle);
            $curlError = curl_errno($handle);
            $httpStatus = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);

            if ($curlError !== 0) {
                error_log('[FragCity FACEIT] Data API transport failed; cURL code=' . $curlError);
                $results[$steamId] = self::emptyProfile('api_unavailable');
            } elseif ($httpStatus === 404) {
                $results[$steamId] = self::emptyProfile('profile_not_found');
            } elseif ($httpStatus >= 200 && $httpStatus < 300 && is_string($body)) {
                $decoded = json_decode($body, true);
                if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
                    error_log('[FragCity FACEIT] Data API returned invalid JSON.');
                    $results[$steamId] = self::emptyProfile('api_unavailable');
                } else {
                    $game = $decoded['games']['cs2'] ?? null;
                    if (!is_array($game)) {
                        $results[$steamId] = self::emptyProfile('no_cs2_profile');
                    } else {
                        $rawLevel = $game['skill_level'] ?? null;
                        $level = is_numeric($rawLevel) ? (int)$rawLevel : 0;
                        $results[$steamId] = $level >= 1 && $level <= 10
                            ? [
                                'faceit_level' => $level,
                                'faceit_status' => 'available',
                            ]
                            : self::emptyProfile('unranked');
                    }
                }
            } else {
                error_log('[FragCity FACEIT] Data API request returned HTTP ' . $httpStatus . '.');
                $results[$steamId] = self::emptyProfile('api_unavailable');
            }

            curl_multi_remove_handle($multiHandle, $handle);
            unset($handles[$steamId]);
        }

        foreach ($handles as $steamId => $handle) {
            $results[$steamId] = self::emptyProfile('api_unavailable');
            curl_multi_remove_handle($multiHandle, $handle);
        }

        curl_multi_close($multiHandle);

        foreach ($steamIds as $steamId) {
            if (!isset($results[$steamId])) {
                $results[$steamId] = self::emptyProfile('api_unavailable');
            }
        }

        return $results;
    }

    /** @return array{faceit_level:?int,faceit_status:string}|null */
    private function readCache(string $steamId): ?array
    {
        $path = $this->cachePath($steamId);
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        $record = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($record)
            || !is_int($record['cached_at'] ?? null)
            || !is_array($record['profile'] ?? null)) {
            return null;
        }

        $status = (string)($record['profile']['faceit_status'] ?? '');
        $ttl = match ($status) {
            'available', 'unranked', 'no_cs2_profile' => self::CACHE_TTL_SECONDS,
            'profile_not_found' => self::NEGATIVE_CACHE_TTL_SECONDS,
            'api_unavailable' => self::ERROR_CACHE_TTL_SECONDS,
            default => 0,
        };

        if ($ttl < 1 || time() - $record['cached_at'] > $ttl) {
            return null;
        }

        $level = $record['profile']['faceit_level'] ?? null;
        return [
            'faceit_level' => is_int($level) && $level >= 1 && $level <= 10 ? $level : null,
            'faceit_status' => $status,
        ];
    }

    /** @param array{faceit_level:?int,faceit_status:string} $profile */
    private function writeCache(string $steamId, array $profile): void
    {
        if (!is_dir($this->cacheDirectory)
            && !@mkdir($this->cacheDirectory, 0700, true)
            && !is_dir($this->cacheDirectory)) {
            return;
        }

        $json = json_encode(
            [
                'cached_at' => time(),
                'profile' => $profile,
            ],
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        $path = $this->cachePath($steamId);
        $temporary = $path . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));

        if (@file_put_contents($temporary, $json, LOCK_EX) === false) {
            return;
        }

        @chmod($temporary, 0600);
        if (!@rename($temporary, $path)) {
            @unlink($temporary);
            return;
        }

        @chmod($path, 0600);
    }

    private function cachePath(string $steamId): string
    {
        return $this->cacheDirectory . DIRECTORY_SEPARATOR . hash('sha256', $steamId) . '.json';
    }
}

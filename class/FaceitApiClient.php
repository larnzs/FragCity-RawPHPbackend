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
    private const CACHE_TTL_SECONDS = 1800;
    private const NEGATIVE_CACHE_TTL_SECONDS = 600;
    private const ERROR_CACHE_TTL_SECONDS = 45;
    private const DEFAULT_RATE_LIMIT_BACKOFF_SECONDS = 60;
    private const MAX_RATE_LIMIT_BACKOFF_SECONDS = 3600;
    private const AUTH_ERROR_BACKOFF_SECONDS = 300;
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
     * A per-SteamID file lock prevents simultaneous PHP requests from
     * sending duplicate FACEIT lookups for the same player.
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
            $staleProfiles = [];
            $staleCachedAt = [];

            foreach ($steamIds as $steamId) {
                $record = $this->readCacheRecord($steamId);
                if ($record !== null && $this->isCacheFresh($record)) {
                    $profiles[$steamId] = $record['profile'];
                    continue;
                }

                if ($record !== null && self::hasUsableLevel($record['profile'])) {
                    $staleProfiles[$steamId] = $record['profile'];
                    $staleCachedAt[$steamId] = $record['cached_at'];
                }

                if ($record !== null && $this->isRetryBackoffActive($record)) {
                    $profiles[$steamId] = $record['profile'];
                    continue;
                }

                $missing[] = $steamId;
            }

            // Acquire locks in a stable order so overlapping batches cannot deadlock.
            sort($missing, SORT_STRING);
            $locks = [];
            try {
                $toFetch = [];

                foreach ($missing as $steamId) {
                    $lock = $this->acquireCacheLock($steamId);
                    if (is_resource($lock)) {
                        $locks[$steamId] = $lock;
                    }

                    // Another PHP request may have populated the cache while
                    // this request waited for the lock.
                    $record = $this->readCacheRecord($steamId);
                    if ($record !== null && $this->isCacheFresh($record)) {
                        $profiles[$steamId] = $record['profile'];
                        continue;
                    }

                    if ($record !== null && self::hasUsableLevel($record['profile'])) {
                        $staleProfiles[$steamId] = $record['profile'];
                        $staleCachedAt[$steamId] = $record['cached_at'];
                    }

                    if ($record !== null && $this->isRetryBackoffActive($record)) {
                        $profiles[$steamId] = $record['profile'];
                        continue;
                    }

                    $toFetch[] = $steamId;
                }

                foreach (array_chunk($toFetch, self::MAX_CONCURRENT_LOOKUPS) as $batch) {
                    $batchResponse = $this->fetchBatch($batch);
                    $batchProfiles = $batchResponse['profiles'];
                    $retryAfter = $batchResponse['retry_after'];

                    foreach ($batch as $steamId) {
                        $profile = $batchProfiles[$steamId] ?? self::emptyProfile('api_unavailable');

                        if ($profile['faceit_status'] === 'api_unavailable') {
                            $cooldown = max(
                                self::ERROR_CACHE_TTL_SECONDS,
                                (int)($retryAfter[$steamId] ?? self::ERROR_CACHE_TTL_SECONDS)
                            );
                            $retryAt = time() + min(self::MAX_RATE_LIMIT_BACKOFF_SECONDS, $cooldown);

                            if (isset($staleProfiles[$steamId]) && self::hasUsableLevel($staleProfiles[$steamId])) {
                                // Keep showing the last known Level during transient FACEIT outages.
                                $profiles[$steamId] = $staleProfiles[$steamId];
                                $this->writeCache(
                                    $steamId,
                                    $staleProfiles[$steamId],
                                    $staleCachedAt[$steamId] ?? time(),
                                    $retryAt
                                );
                            } else {
                                $profiles[$steamId] = $profile;
                                $this->writeCache($steamId, $profile, time(), $retryAt);
                            }
                            continue;
                        }

                        $profiles[$steamId] = $profile;
                        $this->writeCache($steamId, $profile);
                    }
                }
            } finally {
                foreach ($locks as $lock) {
                    @flock($lock, LOCK_UN);
                    @fclose($lock);
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

            $players[$index]['faceit_level'] = $profile['faceit_level'] ?? null;
            $players[$index]['faceit_elo'] = $profile['faceit_elo'] ?? null;
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

    private static function levelFromElo(int $elo): ?int
    {
        $thresholds = [
            1 => 100,
            2 => 501,
            3 => 751,
            4 => 901,
            5 => 1051,
            6 => 1201,
            7 => 1351,
            8 => 1531,
            9 => 1751,
            10 => 2001,
        ];

        $level = null;
        foreach ($thresholds as $candidateLevel => $minimumElo) {
            if ($elo >= $minimumElo) {
                $level = $candidateLevel;
            } else {
                break;
            }
        }

        return $level;
    }

    /** @return array{faceit_level:null,faceit_elo:null,faceit_status:string} */
    private static function emptyProfile(string $status): array
    {
        return [
            'faceit_level' => null,
            'faceit_elo' => null,
            'faceit_status' => $status,
        ];
    }

    /**
     * @param array{faceit_level:?int,faceit_elo?:?int,faceit_status:string} $profile
     */
    private static function hasUsableLevel(array $profile): bool
    {
        $level = $profile['faceit_level'] ?? null;
        $elo = $profile['faceit_elo'] ?? null;

        return (is_int($level) && $level >= 1 && $level <= 10)
            || (is_int($elo) && $elo >= 100 && $elo <= 100000);
    }

    /**
     * @param array<int, string> $steamIds
     * @return array{
     *   profiles:array<string,array{faceit_level:?int,faceit_elo:?int,faceit_status:string}>,
     *   retry_after:array<string,int>
     * }
     */
    private function fetchBatch(array $steamIds): array
    {
        $results = [];
        $retryAfterBySteamId = [];

        if ($steamIds === []) {
            return ['profiles' => $results, 'retry_after' => $retryAfterBySteamId];
        }

        if (!function_exists('curl_init') || !function_exists('curl_multi_init')) {
            foreach ($steamIds as $steamId) {
                $results[$steamId] = self::emptyProfile('api_unavailable');
                $retryAfterBySteamId[$steamId] = self::ERROR_CACHE_TTL_SECONDS;
            }
            return ['profiles' => $results, 'retry_after' => $retryAfterBySteamId];
        }

        $multiHandle = curl_multi_init();
        if ($multiHandle === false) {
            foreach ($steamIds as $steamId) {
                $results[$steamId] = self::emptyProfile('api_unavailable');
                $retryAfterBySteamId[$steamId] = self::ERROR_CACHE_TTL_SECONDS;
            }
            return ['profiles' => $results, 'retry_after' => $retryAfterBySteamId];
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
                $retryAfterBySteamId[$steamId] = self::ERROR_CACHE_TTL_SECONDS;
                continue;
            }

            $headerState = (object)['retry_after_seconds' => null];

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
                CURLOPT_HEADERFUNCTION => static function ($curl, string $headerLine) use ($headerState): int {
                    if (stripos($headerLine, 'Retry-After:') === 0) {
                        $value = trim(substr($headerLine, strlen('Retry-After:')));
                        if (ctype_digit($value)) {
                            $seconds = (int)$value;
                        } else {
                            $timestamp = strtotime($value);
                            $seconds = $timestamp === false ? 0 : $timestamp - time();
                        }

                        $headerState->retry_after_seconds = max(0, min(300, $seconds));
                    }

                    return strlen($headerLine);
                },
            ]);

            curl_multi_add_handle($multiHandle, $handle);
            $handles[$steamId] = [
                'handle' => $handle,
                'header_state' => $headerState,
            ];
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

        foreach ($handles as $steamId => $request) {
            $handle = $request['handle'];
            $body = curl_multi_getcontent($handle);
            $curlError = curl_errno($handle);
            $httpStatus = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
            $retryHeader = $request['header_state']->retry_after_seconds;

            if ($curlError !== 0) {
                error_log('[FragCity FACEIT] Data API transport failed; cURL code=' . $curlError);
                $results[$steamId] = self::emptyProfile('api_unavailable');
                $retryAfterBySteamId[$steamId] = self::ERROR_CACHE_TTL_SECONDS;
            } elseif ($httpStatus === 404) {
                $results[$steamId] = self::emptyProfile('profile_not_found');
            } elseif ($httpStatus === 429) {
                error_log('[FragCity FACEIT] Data API rate limit reached (HTTP 429).');
                $results[$steamId] = self::emptyProfile('api_unavailable');
                $retryAfterBySteamId[$steamId] = max(
                    self::DEFAULT_RATE_LIMIT_BACKOFF_SECONDS,
                    min(self::MAX_RATE_LIMIT_BACKOFF_SECONDS, (int)($retryHeader ?? 0))
                );
            } elseif ($httpStatus === 401 || $httpStatus === 403) {
                error_log('[FragCity FACEIT] Data API rejected the configured credentials (HTTP ' . $httpStatus . ').');
                $results[$steamId] = self::emptyProfile('api_unavailable');
                $retryAfterBySteamId[$steamId] = self::AUTH_ERROR_BACKOFF_SECONDS;
            } elseif ($httpStatus >= 200 && $httpStatus < 300 && is_string($body)) {
                $decoded = json_decode($body, true);
                if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
                    error_log('[FragCity FACEIT] Data API returned invalid JSON.');
                    $results[$steamId] = self::emptyProfile('api_unavailable');
                    $retryAfterBySteamId[$steamId] = self::ERROR_CACHE_TTL_SECONDS;
                } else {
                    $game = $decoded['games']['cs2'] ?? null;
                    if (!is_array($game)) {
                        $results[$steamId] = self::emptyProfile('no_cs2_profile');
                    } else {
                        $rawLevel = $game['skill_level'] ?? null;
                        $level = is_numeric($rawLevel) ? (int)$rawLevel : 0;

                        $rawElo = $game['faceit_elo'] ?? null;
                        $elo = is_numeric($rawElo) ? (int)$rawElo : null;
                        if ($elo !== null && ($elo < 100 || $elo > 100000)) {
                            $elo = null;
                        }

                        if ($level < 1 || $level > 10) {
                            $level = $elo !== null
                                ? (self::levelFromElo($elo) ?? 0)
                                : 0;
                        }

                        $results[$steamId] = ($level >= 1 && $level <= 10) || $elo !== null
                            ? [
                                'faceit_level' => $level >= 1 && $level <= 10 ? $level : null,
                                'faceit_elo' => $elo,
                                'faceit_status' => 'available',
                            ]
                            : self::emptyProfile('unranked');
                    }
                }
            } else {
                error_log('[FragCity FACEIT] Data API request returned HTTP ' . $httpStatus . '.');
                $results[$steamId] = self::emptyProfile('api_unavailable');
                $retryAfterBySteamId[$steamId] = self::ERROR_CACHE_TTL_SECONDS;
            }

            curl_multi_remove_handle($multiHandle, $handle);
            unset($handles[$steamId]);
        }

        foreach ($handles as $steamId => $request) {
            $handle = $request['handle'];
            $results[$steamId] = self::emptyProfile('api_unavailable');
            $retryAfterBySteamId[$steamId] = self::ERROR_CACHE_TTL_SECONDS;
            curl_multi_remove_handle($multiHandle, $handle);
        }

        curl_multi_close($multiHandle);

        foreach ($steamIds as $steamId) {
            if (!isset($results[$steamId])) {
                $results[$steamId] = self::emptyProfile('api_unavailable');
                $retryAfterBySteamId[$steamId] = self::ERROR_CACHE_TTL_SECONDS;
            }
        }

        return [
            'profiles' => $results,
            'retry_after' => $retryAfterBySteamId,
        ];
    }

    /**
     * @return array{
     *   cached_at:int,
     *   retry_after_at:int,
     *   profile:array{faceit_level:?int,faceit_elo?:?int,faceit_status:string}
     * }|null
     */
    private function readCacheRecord(string $steamId): ?array
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
        $allowedStatuses = [
            'available',
            'unranked',
            'no_cs2_profile',
            'profile_not_found',
            'api_unavailable',
        ];
        if (!in_array($status, $allowedStatuses, true)) {
            return null;
        }

        $level = $record['profile']['faceit_level'] ?? null;
        $profile = [
            'faceit_level' => is_int($level) && $level >= 1 && $level <= 10 ? $level : null,
            'faceit_status' => $status,
        ];

        // Older cache files do not contain ELO. Preserve that distinction so
        // they are refreshed once instead of being treated as complete data.
        if (array_key_exists('faceit_elo', $record['profile'])) {
            $elo = $record['profile']['faceit_elo'];
            $profile['faceit_elo'] = is_int($elo) && $elo >= 100 && $elo <= 100000
                ? $elo
                : null;
        }

        return [
            'cached_at' => $record['cached_at'],
            'retry_after_at' => is_int($record['retry_after_at'] ?? null) ? $record['retry_after_at'] : 0,
            'profile' => $profile,
        ];
    }

    /**
     * @param array{cached_at:int,retry_after_at:int,profile:array{faceit_level:?int,faceit_elo?:?int,faceit_status:string}} $record
     */
    private function isCacheFresh(array $record): bool
    {
        if (!array_key_exists('faceit_elo', $record['profile'])) {
            return false;
        }

        $ttl = match ($record['profile']['faceit_status']) {
            'available', 'unranked', 'no_cs2_profile' => self::CACHE_TTL_SECONDS,
            'profile_not_found' => self::NEGATIVE_CACHE_TTL_SECONDS,
            'api_unavailable' => self::ERROR_CACHE_TTL_SECONDS,
            default => 0,
        };

        return $ttl > 0 && time() - $record['cached_at'] <= $ttl;
    }

    /**
     * @param array{cached_at:int,retry_after_at:int,profile:array{faceit_level:?int,faceit_elo?:?int,faceit_status:string}} $record
     */
    private function isRetryBackoffActive(array $record): bool
    {
        return $record['retry_after_at'] > time();
    }

    /** @param array{faceit_level:?int,faceit_elo?:?int,faceit_status:string} $profile */
    private function writeCache(
        string $steamId,
        array $profile,
        ?int $cachedAt = null,
        ?int $retryAfterAt = null
    ): void {
        if (!$this->ensureCacheDirectory()) {
            return;
        }

        $record = [
            'cached_at' => $cachedAt ?? time(),
            'profile' => $profile,
        ];
        if ($retryAfterAt !== null && $retryAfterAt > 0) {
            $record['retry_after_at'] = $retryAfterAt;
        }

        $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
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

    /** @return resource|false */
    private function acquireCacheLock(string $steamId)
    {
        if (!$this->ensureCacheDirectory()) {
            return false;
        }

        $handle = @fopen($this->cachePath($steamId) . '.lock', 'c');
        if ($handle === false) {
            return false;
        }

        @chmod($this->cachePath($steamId) . '.lock', 0600);
        if (!@flock($handle, LOCK_EX)) {
            @fclose($handle);
            return false;
        }

        return $handle;
    }

    private function ensureCacheDirectory(): bool
    {
        return is_dir($this->cacheDirectory)
            || (@mkdir($this->cacheDirectory, 0700, true) && is_dir($this->cacheDirectory));
    }

    private function cachePath(string $steamId): string
    {
        return $this->cacheDirectory . DIRECTORY_SEPARATOR . hash('sha256', $steamId) . '.json';
    }
}

<?php
declare(strict_types=1);

/**
 * Tiny on-demand snapshot cache for the public Pterodactyl Server Browser.
 *
 * It refreshes only when a signed website request arrives after the TTL.
 * No Cron job, timer, or background process is required.
 */
final class PterodactylStandbySnapshotCache
{
    private string $cacheFile;
    private int $ttlSeconds;
    private int $maxStaleSeconds;

    public function __construct(array $config)
    {
        $this->cacheFile = trim((string)($config['cache_file'] ?? ''));
        if ($this->cacheFile === '' || $this->cacheFile[0] !== '/') {
            throw new RuntimeException('pterodactyl_standby.cache_file must be an absolute path.');
        }

        $this->ttlSeconds = max(3, min(120, (int)($config['cache_ttl_seconds'] ?? 10)));
        $this->maxStaleSeconds = max(
            $this->ttlSeconds,
            min(3600, (int)($config['cache_max_stale_seconds'] ?? 180))
        );
    }

    /**
     * @param callable(): array<int, array<string, mixed>> $refresh
     * @return array<string, mixed>
     */
    public function getOrRefresh(callable $refresh): array
    {
        $directory = dirname($this->cacheFile);
        if (!is_dir($directory)
            && !@mkdir($directory, 0750, true)
            && !is_dir($directory)) {
            throw new RuntimeException('Could not create Pterodactyl Standby cache directory.');
        }

        $lock = @fopen($this->cacheFile . '.lock', 'c');
        if (!is_resource($lock)) {
            throw new RuntimeException('Could not open Pterodactyl Standby cache lock.');
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException('Could not acquire Pterodactyl Standby cache lock.');
            }

            $cached = $this->readCache();
            if ($cached !== null && $cached['age_seconds'] <= $this->ttlSeconds) {
                return $this->toResponse($cached, false);
            }

            try {
                $servers = $refresh();
                if (!is_array($servers)) {
                    throw new RuntimeException('Pterodactyl discovery returned an invalid server list.');
                }

                $snapshot = [
                    'schema_version' => 1,
                    'generated_at' => gmdate('c'),
                    'servers' => array_values($servers),
                ];
                $this->writeCache($snapshot);

                return [
                    'generated_at' => $snapshot['generated_at'],
                    'cache_age_seconds' => 0,
                    'stale' => false,
                    'servers' => $snapshot['servers'],
                ];
            } catch (Throwable $e) {
                if ($cached !== null && $cached['age_seconds'] <= $this->maxStaleSeconds) {
                    error_log(
                        '[FragCity ServerBrowser] Live refresh failed; serving stale snapshot; type='
                        . get_class($e)
                    );
                    return $this->toResponse($cached, true);
                }
                throw $e;
            }
        } finally {
            @flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return array{generated_at:string,age_seconds:int,servers:array<int,array<string,mixed>>}|null */
    private function readCache(): ?array
    {
        if (!is_file($this->cacheFile) || !is_readable($this->cacheFile)) {
            return null;
        }

        $raw = @file_get_contents($this->cacheFile);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($decoded)
            || !is_string($decoded['generated_at'] ?? null)
            || !is_array($decoded['servers'] ?? null)) {
            return null;
        }

        $generatedTimestamp = strtotime($decoded['generated_at']);
        if ($generatedTimestamp === false) {
            return null;
        }

        return [
            'generated_at' => $decoded['generated_at'],
            'age_seconds' => max(0, time() - $generatedTimestamp),
            'servers' => array_values($decoded['servers']),
        ];
    }

    private function writeCache(array $snapshot): void
    {
        $json = json_encode(
            $snapshot,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        );

        $temporaryFile = $this->cacheFile . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));
        if (@file_put_contents($temporaryFile, $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Could not write Pterodactyl Standby cache.');
        }

        @chmod($temporaryFile, 0640);
        if (!@rename($temporaryFile, $this->cacheFile)) {
            @unlink($temporaryFile);
            throw new RuntimeException('Could not replace Pterodactyl Standby cache.');
        }
        @chmod($this->cacheFile, 0640);
    }

    /** @param array{generated_at:string,age_seconds:int,servers:array<int,array<string,mixed>>} $cached */
    private function toResponse(array $cached, bool $stale): array
    {
        return [
            'generated_at' => $cached['generated_at'],
            'cache_age_seconds' => $cached['age_seconds'],
            'stale' => $stale,
            'servers' => $cached['servers'],
        ];
    }
}

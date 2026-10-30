<?php
declare(strict_types=1);

require_once __DIR__ . '/../settings/loader.php';
require_once __DIR__ . '/FragCityServerBrowserApiClient.php';

/**
 * Website boundary for public server data.
 * This repository has no database access and never sends UDP packets.
 */
final class ServerBrowserRepository
{
    private FragCityServerBrowserApiClient $api;

    public function __construct()
    {
        $this->api = new FragCityServerBrowserApiClient();
    }

    public function getSnapshot(): array
    {
        $snapshot = $this->api->getSnapshot();

        return [
            'servers' => is_array($snapshot['servers'] ?? null) ? $snapshot['servers'] : [],
            'generated_at' => is_string($snapshot['generated_at'] ?? null)
                ? $snapshot['generated_at']
                : null,
            'cache_age_seconds' => isset($snapshot['cache_age_seconds'])
                ? max(0, (int)$snapshot['cache_age_seconds'])
                : null,
            'stale' => !empty($snapshot['stale']),
        ];
    }

    public function getServers(): array
    {
        return $this->getSnapshot()['servers'];
    }
}

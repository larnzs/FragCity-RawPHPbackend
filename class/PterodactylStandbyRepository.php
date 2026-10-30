<?php
declare(strict_types=1);

require_once __DIR__ . '/../settings/loader.php';
require_once __DIR__ . '/PterodactylStandbyApiClient.php';

/**
 * Website boundary for Pterodactyl Standby data.
 * This repository has no database access and never sends UDP packets.
 */
final class PterodactylStandbyRepository
{
    private PterodactylStandbyApiClient $api;

    public function __construct()
    {
        $this->api = new PterodactylStandbyApiClient();
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

    /**
     * Fetch one server's live A2S_PLAYER records through the signed Linux API.
     *
     * @return array{server_id:string,server_name:string,player_count:int,players:array,fetched_at:string}
     */
    public function getPlayers(string $serverId): array
    {
        $data = $this->api->getPlayers($serverId);
        $players = is_array($data['players'] ?? null) ? $data['players'] : [];

        return [
            'server_id' => (string)($data['server_id'] ?? $serverId),
            'server_name' => is_string($data['server_name'] ?? null) ? $data['server_name'] : '',
            'player_count' => max(0, (int)($data['player_count'] ?? count($players))),
            'players' => $players,
            'fetched_at' => is_string($data['fetched_at'] ?? null) ? $data['fetched_at'] : gmdate('c'),
        ];
    }

    public function getServers(): array
    {
        return $this->getSnapshot()['servers'];
    }
}

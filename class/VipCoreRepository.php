<?php
declare(strict_types=1);

require_once __DIR__ . '/../settings/loader.php';
require_once __DIR__ . '/FragCityLinuxApiClient.php';

/** Website adapter for the signed Linux VIPCore entitlement API. */
final class VipCoreRepository
{
    private FragCityLinuxApiClient $api;

    public function __construct()
    {
        $this->api = new FragCityLinuxApiClient();
    }

    public function getStatus(string $steamId64): array
    {
        $data = $this->api->request([
            'action' => 'get_status',
            'steamid64' => $steamId64,
        ], '/v1/vipcore');

        if (!is_array($data)
            || !is_bool($data['active'] ?? null)
            || !is_numeric($data['level'] ?? null)
            || !is_numeric($data['server_id'] ?? null)) {
            throw new RuntimeException('VIPCore API returned an invalid status payload.');
        }

        $expires = $data['expires'] ?? null;
        if ($expires !== null && !is_numeric($expires)) {
            throw new RuntimeException('VIPCore API returned an invalid expiry value.');
        }

        return [
            'active' => $data['active'],
            'level' => max(0, min(6, (int)$data['level'])),
            'group' => is_string($data['group'] ?? null) ? $data['group'] : null,
            'expires' => $expires === null ? null : max(0, (int)$expires),
            'server_id' => (int)$data['server_id'],
        ];
    }

    public function grantTestMembership(string $steamId64, string $playerName, int $level): array
    {
        $data = $this->api->request([
            'action' => 'grant_test_vip',
            'steamid64' => $steamId64,
            'player_name' => $playerName,
            'level' => $level,
        ], '/v1/vipcore');

        if (($data['granted'] ?? false) !== true
            || (int)($data['level'] ?? 0) !== $level
            || !is_numeric($data['expires'] ?? null)
            || !in_array((string)($data['group'] ?? ''), ['BRONZE', 'SILVER', 'GOLD'], true)) {
            throw new RuntimeException('VIPCore API returned an invalid grant payload.');
        }

        return [
            'granted' => true,
            'level' => $level,
            'group' => (string)$data['group'],
            'expires' => (int)$data['expires'],
            'duration_seconds' => (int)($data['duration_seconds'] ?? 0),
        ];
    }
}

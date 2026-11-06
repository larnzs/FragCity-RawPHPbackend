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

    public function getFreeTrialStatus(string $steamId64): array
    {
        $data = $this->api->request([
            'action' => 'get_free_trial_status',
            'steamid64' => $steamId64,
        ], '/v1/vipcore');

        if (!is_array($data)
            || !is_bool($data['available'] ?? null)
            || !is_bool($data['claimed'] ?? null)
            || !is_bool($data['blocked_by_active_membership'] ?? null)
            || !is_numeric($data['server_id'] ?? null)) {
            throw new RuntimeException('VIPCore API returned an invalid free-trial status payload.');
        }

        $claimedAt = $data['claimed_at'] ?? null;
        $expires = $data['expires'] ?? null;
        if (($claimedAt !== null && !is_numeric($claimedAt))
            || ($expires !== null && !is_numeric($expires))) {
            throw new RuntimeException('VIPCore API returned invalid free-trial timestamps.');
        }

        return [
            'available' => $data['available'],
            'claimed' => $data['claimed'],
            'blocked_by_active_membership' => $data['blocked_by_active_membership'],
            'claimed_at' => $claimedAt === null ? null : max(0, (int)$claimedAt),
            'expires' => $expires === null ? null : max(0, (int)$expires),
            'group' => is_string($data['group'] ?? null) ? $data['group'] : null,
            'server_id' => (int)$data['server_id'],
        ];
    }

    public function claimFreeSilverTrial(string $steamId64, string $playerName): array
    {
        $data = $this->api->request([
            'action' => 'claim_free_silver_trial',
            'steamid64' => $steamId64,
            'player_name' => $playerName,
        ], '/v1/vipcore');

        if (($data['granted'] ?? false) !== true
            || (int)($data['level'] ?? 0) !== 2
            || (string)($data['group'] ?? '') !== 'SILVER'
            || (int)($data['duration_seconds'] ?? 0) !== 259200
            || !is_numeric($data['expires'] ?? null)
            || !is_numeric($data['claimed_at'] ?? null)) {
            throw new RuntimeException('VIPCore API returned an invalid free Silver trial grant payload.');
        }

        return [
            'granted' => true,
            'level' => 2,
            'group' => 'SILVER',
            'claimed_at' => (int)$data['claimed_at'],
            'expires' => (int)$data['expires'],
            'duration_seconds' => 259200,
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

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

        $level = max(0, min(6, (int)$data['level']));
        $paidPurchase = $data['paid_purchase'] ?? null;
        if ($paidPurchase !== null) {
            if (!is_array($paidPurchase)
                || !is_string($paidPurchase['order_ref'] ?? null)
                || !preg_match('/^[a-f0-9]{32}$/D', $paidPurchase['order_ref'])
                || !is_numeric($paidPurchase['level'] ?? null)
                || (int)$paidPurchase['level'] !== $level
                || !is_numeric($paidPurchase['duration_seconds'] ?? null)
                || !in_array((int)$paidPurchase['duration_seconds'], [604800, 1296000, 2592000, 5184000, 7776000], true)
                || (($paidPurchase['tier_price_rial'] ?? null) !== null
                    && (!is_numeric($paidPurchase['tier_price_rial'])
                        || (int)$paidPurchase['tier_price_rial'] < 1
                        || (int)$paidPurchase['tier_price_rial'] > 10000000000000
                        || (int)$paidPurchase['tier_price_rial'] % 10 !== 0))
                || !is_numeric($paidPurchase['granted_at'] ?? null)
                || !is_numeric($paidPurchase['expires'] ?? null)
                || $expires === null
                || (int)$paidPurchase['expires'] !== (int)$expires) {
                throw new RuntimeException('VIPCore API returned an invalid paid-membership status payload.');
            }

            $paidPurchase = [
                'order_ref' => $paidPurchase['order_ref'],
                'level' => (int)$paidPurchase['level'],
                'duration_seconds' => (int)$paidPurchase['duration_seconds'],
                'tier_price_rial' => ($paidPurchase['tier_price_rial'] ?? null) === null
                    ? null
                    : (int)$paidPurchase['tier_price_rial'],
                'granted_at' => (int)$paidPurchase['granted_at'],
                'expires' => (int)$paidPurchase['expires'],
            ];
        }

        return [
            'active' => $data['active'],
            'level' => $level,
            'group' => is_string($data['group'] ?? null) ? $data['group'] : null,
            'expires' => $expires === null ? null : max(0, (int)$expires),
            'paid_purchase' => $paidPurchase,
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

    public function grantPaidMembership(
        string $orderRef,
        string $steamId64,
        string $playerName,
        int $level,
        int $durationSeconds,
        int $tierPriceRial,
        ?array $upgrade = null
    ): array {
        $payload = [
            'action' => 'grant_paid_membership',
            'order_ref' => $orderRef,
            'steamid64' => $steamId64,
            'player_name' => $playerName,
            'level' => $level,
            'duration_seconds' => $durationSeconds,
            'tier_price_rial' => $tierPriceRial,
        ];
        if ($upgrade !== null) {
            $payload['upgrade'] = $upgrade;
        }

        $data = $this->api->request($payload, '/v1/vipcore');

        if (($data['granted'] ?? false) !== true
            || (string)($data['order_ref'] ?? '') !== $orderRef
            || (int)($data['level'] ?? 0) !== $level
            || !is_numeric($data['expires'] ?? null)
            || !is_numeric($data['duration_seconds'] ?? null)
            || (int)$data['duration_seconds'] !== $durationSeconds
            || !in_array((string)($data['group'] ?? ''), ['BRONZE', 'SILVER', 'GOLD'], true)) {
            throw new RuntimeException('VIPCore API returned an invalid paid grant payload.');
        }

        return [
            'granted' => true,
            'order_ref' => $orderRef,
            'level' => $level,
            'group' => (string)$data['group'],
            'granted_at' => (int)($data['granted_at'] ?? 0),
            'expires' => (int)$data['expires'],
            'duration_seconds' => $durationSeconds,
        ];
    }

}

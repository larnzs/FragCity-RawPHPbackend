<?php
declare(strict_types=1);

require_once __DIR__ . '/../settings/loader.php';
require_once __DIR__ . '/FragCityLinuxApiClient.php';

/** Website boundary for the read-only SimpleAdmin player identity lookup. */
final class SimpleAdminRepository
{
    private FragCityLinuxApiClient $api;

    public function __construct()
    {
        $this->api = new FragCityLinuxApiClient();
    }

    /**
     * Resolve A2S player names to SteamID64 without requesting or exposing IPs.
     *
     * @param array<int|string, mixed> $players
     * @return array<int|string, mixed>
     */
    public function resolvePlayers(array $players): array
    {
        $names = [];
        foreach ($players as $player) {
            if (!is_array($player) || self::normalizeSteamId($player['steam_id'] ?? null) !== null) {
                continue;
            }
            $name = trim((string)($player['name'] ?? ''));
            if ($name !== '') {
                $names[$name] = $name;
            }
        }

        if ($names === []) {
            return $players;
        }

        $data = $this->api->request([
            'action' => 'resolve_player_names',
            'names' => array_values($names),
        ], '/v1/simpleadmin');

        $matches = [];
        foreach (($data['matches'] ?? []) as $match) {
            if (!is_array($match) || !is_string($match['name'] ?? null)) {
                continue;
            }
            $name = $match['name'];
            $status = is_string($match['status'] ?? null) ? $match['status'] : 'not_found';
            $steamId = self::normalizeSteamId($match['steam_id'] ?? null);

            $matches[$name] = ($status === 'resolved' && $steamId !== null)
                ? ['steam_id' => $steamId, 'status' => 'resolved']
                : [
                    'steam_id' => null,
                    'status' => in_array($status, ['ambiguous', 'not_found'], true) ? $status : 'not_found',
                ];
        }

        foreach ($players as $index => $player) {
            if (!is_array($player)) {
                continue;
            }
            if (self::normalizeSteamId($player['steam_id'] ?? null) !== null) {
                $players[$index]['steam_id_status'] = 'resolved';
                continue;
            }

            $name = trim((string)($player['name'] ?? ''));
            $match = $matches[$name] ?? ['steam_id' => null, 'status' => 'not_found'];
            $players[$index]['steam_id'] = $match['steam_id'];
            $players[$index]['steam_id_status'] = $match['status'];
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
}

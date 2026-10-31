<?php
declare(strict_types=1);

require_once __DIR__ . '/../settings/loader.php';
require_once __DIR__ . '/FragCityLinuxApiClient.php';

/** Website adapter for the signed, read-only SimpleAdmin API. */
final class SimpleAdminRepository
{
    private FragCityLinuxApiClient $api;

    public function __construct()
    {
        $this->api = new FragCityLinuxApiClient();
    }

    /**
     * Resolve A2S player names to SteamID64 without requesting or exposing IPs.
     * Identical live names are deliberately marked ambiguous instead of
     * assigning the same historical identity to multiple connected players.
     *
     * @param array<int|string, mixed> $players
     * @return array<int|string, mixed>
     */
    public function resolvePlayers(array $players): array
    {
        $nameCounts = [];
        $namesByKey = [];

        foreach ($players as $player) {
            if (!is_array($player)) {
                continue;
            }

            $name = trim((string)($player['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $key = self::normalizePlayerNameKey($name);
            $nameCounts[$key] = ($nameCounts[$key] ?? 0) + 1;

            if (self::normalizeSteamId($player['steam_id'] ?? null) === null
                && self::isSafeLookupName($name)) {
                $namesByKey[$key] = $name;
            }
        }

        // A2S names are not unique identifiers. Do not ask SimpleAdmin to
        // resolve a name that is shared by two live players on this server.
        foreach ($nameCounts as $key => $count) {
            if ($count > 1) {
                unset($namesByKey[$key]);
            }
        }

        $matchesByKey = [];
        if ($namesByKey !== []) {
            $data = $this->api->request([
                'action' => 'resolve_player_names',
                'names' => array_values($namesByKey),
            ], '/v1/simpleadmin');

            foreach (($data['matches'] ?? []) as $match) {
                if (!is_array($match) || !is_string($match['name'] ?? null)) {
                    continue;
                }

                $key = self::normalizePlayerNameKey($match['name']);
                $status = is_string($match['status'] ?? null) ? $match['status'] : 'not_found';
                $steamId = self::normalizeSteamId($match['steam_id'] ?? null);

                $matchesByKey[$key] = ($status === 'resolved' && $steamId !== null)
                    ? ['steam_id' => $steamId, 'status' => 'resolved']
                    : [
                        'steam_id' => null,
                        'status' => in_array($status, ['ambiguous', 'not_found'], true)
                            ? $status
                            : 'not_found',
                    ];
            }
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
            if ($name === '') {
                $match = ['steam_id' => null, 'status' => 'not_found'];
            } else {
                $key = self::normalizePlayerNameKey($name);
                if (($nameCounts[$key] ?? 0) > 1) {
                    $match = ['steam_id' => null, 'status' => 'ambiguous'];
                } elseif (!self::isSafeLookupName($name)) {
                    $match = ['steam_id' => null, 'status' => 'not_found'];
                } else {
                    $match = $matchesByKey[$key] ?? [
                        'steam_id' => null,
                        'status' => 'not_found',
                    ];
                }
            }

            $players[$index]['steam_id'] = $match['steam_id'];
            $players[$index]['steam_id_status'] = $match['status'];
        }

        return $players;
    }

    /**
     * Fetch one paginated Ban List dataset from SimpleAdmin.
     * IP fields are requested only for a signed-in FragCity administrator.
     *
     * @return array{items:array,total:int,page:int,per_page:int,total_pages:int}
     */
    public function getBanlistRecords(
        string $type,
        string $search,
        int $page,
        int $perPage,
        bool $includeSensitive
    ): array {
        $data = $this->api->request([
            'action' => 'get_records',
            'type' => $type,
            'search' => $search,
            'page' => $page,
            'per_page' => $perPage,
            'include_sensitive' => $includeSensitive,
        ], '/v1/simpleadmin');

        $items = $data['items'] ?? null;
        if (!is_array($items)
            || !is_numeric($data['total'] ?? null)
            || !is_numeric($data['page'] ?? null)
            || !is_numeric($data['per_page'] ?? null)
            || !is_numeric($data['total_pages'] ?? null)) {
            throw new RuntimeException('SimpleAdmin API returned an invalid Ban List payload.');
        }

        return [
            'items' => $items,
            'total' => max(0, (int)$data['total']),
            'page' => max(1, (int)$data['page']),
            'per_page' => max(1, (int)$data['per_page']),
            'total_pages' => max(1, (int)$data['total_pages']),
        ];
    }

    private static function isSafeLookupName(string $name): bool
    {
        return preg_match('//u', $name) === 1
            && !str_contains($name, "\0")
            && mb_strlen($name, 'UTF-8') <= 64;
    }

    private static function normalizePlayerNameKey(string $name): string
    {
        $name = trim($name);
        if (class_exists('Normalizer')) {
            $normalized = Normalizer::normalize($name, Normalizer::FORM_C);
            if (is_string($normalized)) {
                $name = $normalized;
            }
        }

        return function_exists('mb_strtolower')
            ? mb_strtolower($name, 'UTF-8')
            : strtolower($name);
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

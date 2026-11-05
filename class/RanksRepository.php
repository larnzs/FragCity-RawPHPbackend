<?php
declare(strict_types=1);

require_once __DIR__ . '/FragCityLinuxApiClient.php';

/** Website-side adapter for the signed, read-only Linux Ranks API. */
final class RanksRepository
{
    private FragCityLinuxApiClient $api;

    public function __construct()
    {
        $this->api = new FragCityLinuxApiClient();
    }

    public function ping(): array
    {
        $data = $this->api->request(['action' => 'ping_db'], '/v1/ranks');

        if (($data['database'] ?? null) !== 'ok'
            || ($data['table'] ?? null) !== 'lvl_base'
            || !is_numeric($data['row_count'] ?? null)) {
            throw new RuntimeException('Ranks API returned an invalid database health payload.');
        }

        return [
            'database' => 'ok',
            'table' => 'lvl_base',
            'row_count' => (int)$data['row_count'],
        ];
    }

    public function getLeaderboard(
        string $sort = 'points',
        string $order = 'DESC',
        string $search = '',
        int $page = 1,
        int $perPage = 15
    ): array {
        $data = $this->api->request([
            'action' => 'get_leaderboard',
            'sort' => $sort,
            'order' => $order,
            'search' => $search,
            'page' => $page,
            'per_page' => $perPage,
        ], '/v1/ranks');

        $stats = $data['stats'] ?? null;
        $pagination = $data['pagination'] ?? null;
        $topThree = $data['top_three'] ?? null;
        $players = $data['players'] ?? null;

        if (!is_array($stats) || !is_array($pagination)
            || !is_array($topThree) || !is_array($players)) {
            throw new RuntimeException('Ranks API returned an incomplete leaderboard payload.');
        }

        foreach (['total_players', 'total_kills', 'total_points', 'total_playtime', 'max_points'] as $field) {
            if (!is_numeric($stats[$field] ?? null)) {
                throw new RuntimeException('Ranks API returned invalid leaderboard statistics.');
            }
        }
        foreach (['page', 'per_page', 'total_pages', 'offset'] as $field) {
            if (!is_numeric($pagination[$field] ?? null)) {
                throw new RuntimeException('Ranks API returned invalid pagination metadata.');
            }
        }

        return [
            'stats' => [
                'total_players' => max(0, (int)$stats['total_players']),
                'total_kills' => max(0, (int)$stats['total_kills']),
                'total_points' => max(0, (int)$stats['total_points']),
                'total_playtime' => max(0, (int)$stats['total_playtime']),
                'max_points' => max(0, (int)$stats['max_points']),
            ],
            'pagination' => [
                'page' => max(1, (int)$pagination['page']),
                'per_page' => max(1, (int)$pagination['per_page']),
                'total_pages' => max(1, (int)$pagination['total_pages']),
                'offset' => max(0, (int)$pagination['offset']),
            ],
            'top_three' => array_map([$this, 'normalizePlayer'], $topThree),
            'players' => array_map([$this, 'normalizePlayer'], $players),
        ];
    }

    public function getPlayer(string $steamId64): array
    {
        $data = $this->api->request([
            'action' => 'get_player',
            'steamid64' => $steamId64,
        ], '/v1/ranks');

        if (!is_bool($data['found'] ?? null) || !is_numeric($data['global_place'] ?? null)) {
            throw new RuntimeException('Ranks API returned an invalid player payload.');
        }

        if ($data['found'] === false) {
            return ['found' => false, 'player' => null, 'global_place' => 0];
        }

        if (!is_array($data['player'] ?? null)) {
            throw new RuntimeException('Ranks API returned a missing player row.');
        }

        return [
            'found' => true,
            'player' => $this->normalizePlayer($data['player']),
            'global_place' => max(0, (int)$data['global_place']),
        ];
    }

    private function normalizePlayer(mixed $row): array
    {
        if (!is_array($row)
            || !is_string($row['steam'] ?? null)
            || !is_string($row['name'] ?? null)) {
            throw new RuntimeException('Ranks API returned an invalid player row.');
        }

        $normalized = ['steam' => $row['steam'], 'name' => $row['name']];
        foreach (['points', 'kills', 'deaths', 'assists', 'headshots', 'wins', 'playtime'] as $field) {
            if (!is_numeric($row[$field] ?? null)) {
                throw new RuntimeException('Ranks API returned an invalid player statistic.');
            }
            $normalized[$field] = (int)$row[$field];
        }

        return $normalized;
    }
}

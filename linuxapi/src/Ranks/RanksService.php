<?php
declare(strict_types=1);

/**
 * Read-only adapter for the existing CS2 Ranks plugin table.
 * No plugin-owned schema changes and no caller-provided SQL identifiers.
 */
final class RanksService
{
    private const SORT_FIELDS = [
        'points' => 'value',
        'kills' => 'kills',
        'deaths' => 'deaths',
        'assists' => 'assists',
        'headshots' => 'headshots',
        'wins' => 'round_win',
        'playtime' => 'playtime',
    ];

    private const PAGE_SIZES = [10, 15, 25, 50, 100];
    private const STEAM_ID64_BASE = 76561197960265728;
    private const MAX_ACCOUNT_ID = 4294967295;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function allowedSorts(): array
    {
        return array_keys(self::SORT_FIELDS);
    }

    public static function allowedPageSizes(): array
    {
        return self::PAGE_SIZES;
    }

    public static function normalizeSort(mixed $sort): string
    {
        if (!is_string($sort) || !isset(self::SORT_FIELDS[$sort])) {
            throw new InvalidArgumentException('Invalid Ranks sort field.');
        }

        return $sort;
    }

    public static function normalizeOrder(mixed $order): string
    {
        if (!is_string($order)) {
            throw new InvalidArgumentException('Invalid Ranks sort order.');
        }

        $normalized = strtoupper($order);
        if (!in_array($normalized, ['ASC', 'DESC'], true)) {
            throw new InvalidArgumentException('Invalid Ranks sort order.');
        }

        return $normalized;
    }

    public static function normalizePageSize(int $perPage): int
    {
        if (!in_array($perPage, self::PAGE_SIZES, true)) {
            throw new InvalidArgumentException('Invalid Ranks page size.');
        }

        return $perPage;
    }

    /** Convert a stored Steam2 identifier (STEAM_1:Y:Z) to its SteamID64. */
    public static function steam2ToSteamId64(string $steam2): string
    {
        if (!preg_match('/^STEAM_[0-5]:([01]):([0-9]{1,10})$/D', $steam2, $matches)) {
            throw new InvalidArgumentException('Invalid Steam2 identifier.');
        }

        $accountId = ((int)$matches[2] * 2) + (int)$matches[1];
        if ($accountId < 0 || $accountId > self::MAX_ACCOUNT_ID) {
            throw new InvalidArgumentException('Steam2 account ID is outside the valid range.');
        }

        return (string)(self::STEAM_ID64_BASE + $accountId);
    }

    /** Convert the website identity into the exact Steam2 key used by lvl_base. */
    public static function steamId64ToSteam2(string $steamId64): string
    {
        if (!preg_match('/^[0-9]{17}$/D', $steamId64)) {
            throw new InvalidArgumentException('Invalid SteamID64.');
        }

        $accountId = (int)$steamId64 - self::STEAM_ID64_BASE;
        if ($accountId < 0 || $accountId > self::MAX_ACCOUNT_ID) {
            throw new InvalidArgumentException('SteamID64 is outside the supported account range.');
        }

        $authServer = $accountId % 2;
        $authId = intdiv($accountId - $authServer, 2);

        return 'STEAM_1:' . $authServer . ':' . $authId;
    }

    public function ping(): array
    {
        $count = (int)$this->pdo->query('SELECT COUNT(*) FROM `lvl_base`')->fetchColumn();

        return [
            'database' => 'ok',
            'table' => 'lvl_base',
            'row_count' => $count,
        ];
    }

    /**
     * Server-side filtered, sorted and paginated leaderboard data.
     * Stats cover the complete filtered result set; Top 3 uses the same sort.
     */
    public function getLeaderboard(
        string $sort,
        string $order,
        string $search,
        int $page,
        int $perPage
    ): array {
        $sort = self::normalizeSort($sort);
        $order = self::normalizeOrder($order);
        $perPage = self::normalizePageSize($perPage);

        if ($page < 1 || $page > 1000000) {
            throw new InvalidArgumentException('Invalid Ranks page number.');
        }

        $search = trim($search);
        $searchLength = function_exists('mb_strlen') ? mb_strlen($search, 'UTF-8') : strlen($search);
        if ($searchLength > 80 || preg_match('/[\x00-\x1F\x7F]/', $search)) {
            throw new InvalidArgumentException('Invalid Ranks search value.');
        }

        $where = '';
        $searchBindings = [];
        if ($search !== '') {
            // Escape LIKE metacharacters so user text is treated literally.
            $escaped = strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']);
            $pattern = '%' . $escaped . '%';
            $where = " WHERE `steam` LIKE :steam_search ESCAPE '!'"
                . " OR `name` LIKE :name_search ESCAPE '!'";
            $searchBindings = [
                ':steam_search' => $pattern,
                ':name_search' => $pattern,
            ];
        }

        $statsStatement = $this->pdo->prepare(
            'SELECT COUNT(*) AS total_players,
                    COALESCE(SUM(`kills`), 0) AS total_kills,
                    COALESCE(SUM(`value`), 0) AS total_points,
                    COALESCE(SUM(`playtime`), 0) AS total_playtime,
                    COALESCE(MAX(`value`), 0) AS max_points
             FROM `lvl_base`' . $where
        );
        foreach ($searchBindings as $placeholder => $value) {
            $statsStatement->bindValue($placeholder, $value, PDO::PARAM_STR);
        }
        $statsStatement->execute();
        $statsRow = $statsStatement->fetch(PDO::FETCH_ASSOC) ?: [];

        $totalPlayers = (int)($statsRow['total_players'] ?? 0);
        $totalPages = max(1, (int)ceil($totalPlayers / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $orderField = self::SORT_FIELDS[$sort];
        $topThree = $this->selectPlayers($where, $searchBindings, $orderField, $order, 3);
        $players = $this->selectPlayers($where, $searchBindings, $orderField, $order, $perPage, $offset);

        return [
            'stats' => [
                'total_players' => $totalPlayers,
                'total_kills' => (int)($statsRow['total_kills'] ?? 0),
                'total_points' => (int)($statsRow['total_points'] ?? 0),
                'total_playtime' => (int)($statsRow['total_playtime'] ?? 0),
                'max_points' => (int)($statsRow['max_points'] ?? 0),
            ],
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $totalPages,
                'offset' => $offset,
            ],
            'top_three' => $topThree,
            'players' => $players,
        ];
    }

    /** Fetch one player's row and global place by the website's SteamID64. */
    public function getPlayer(string $steamId64): array
    {
        $steam2 = self::steamId64ToSteam2($steamId64);
        $statement = $this->pdo->prepare(
            'SELECT `steam`, `name`, `value` AS `points`,
                    `kills`, `deaths`, `assists`, `headshots`,
                    `round_win` AS `wins`, `playtime`
             FROM `lvl_base`
             WHERE `steam` = :steam
             LIMIT 1'
        );
        $statement->execute([':steam' => $steam2]);
        $player = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($player)) {
            return [
                'found' => false,
                'player' => null,
                'global_place' => 0,
            ];
        }

        // Preserve the existing profile-page rule: players with strictly
        // more points determine the place, so ties share the same place.
        $placeStatement = $this->pdo->prepare(
            'SELECT COUNT(*) + 1
             FROM `lvl_base`
             WHERE `value` > :points'
        );
        $placeStatement->execute([':points' => (int)$player['points']]);

        return [
            'found' => true,
            'player' => $player,
            'global_place' => (int)$placeStatement->fetchColumn(),
        ];
    }

    private function selectPlayers(
        string $where,
        array $searchBindings,
        string $orderField,
        string $order,
        int $limit,
        ?int $offset = null
    ): array {
        // Both identifiers are selected exclusively from the fixed allowlist above.
        $sql = 'SELECT `steam`, `name`, `value` AS `points`,
                       `kills`, `deaths`, `assists`, `headshots`,
                       `round_win` AS `wins`, `playtime`
                FROM `lvl_base`' . $where
            . ' ORDER BY `' . $orderField . '` ' . $order
            . ', `steam` ASC, `name` ASC
                LIMIT :row_limit';

        if ($offset !== null) {
            $sql .= ' OFFSET :row_offset';
        }

        $statement = $this->pdo->prepare($sql);
        foreach ($searchBindings as $placeholder => $value) {
            $statement->bindValue($placeholder, $value, PDO::PARAM_STR);
        }
        $statement->bindValue(':row_limit', $limit, PDO::PARAM_INT);
        if ($offset !== null) {
            $statement->bindValue(':row_offset', $offset, PDO::PARAM_INT);
        }
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}

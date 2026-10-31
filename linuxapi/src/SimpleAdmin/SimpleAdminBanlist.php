<?php
declare(strict_types=1);

/**
 * Read-only, allowlisted Ban List queries for the SimpleAdmin MySQL schema.
 * No client-provided SQL, table names, columns or sort expressions are accepted.
 * IPs are included only for a signed request from the FragCity admin backend.
 */
final class SimpleAdminBanlist
{
    private const ALLOWED_TYPES = ['bans', 'unbans', 'mutes', 'unmutes', 'warns', 'playerips'];
    private const ALLOWED_PAGE_SIZES = [10, 15, 25, 50, 100];
    private const MAX_SEARCH_LENGTH = 120;

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int}
     */
    public function getRecords(
        mixed $typeInput,
        mixed $searchInput,
        mixed $pageInput,
        mixed $perPageInput,
        mixed $includeSensitiveInput
    ): array {
        if (!is_string($typeInput) || !in_array($typeInput, self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException('Invalid SimpleAdmin dataset.');
        }
        if (!is_string($searchInput)) {
            throw new InvalidArgumentException('Invalid search term.');
        }
        if (!is_int($pageInput) || $pageInput < 1 || $pageInput > 100000) {
            throw new InvalidArgumentException('Invalid page number.');
        }
        if (!is_int($perPageInput) || !in_array($perPageInput, self::ALLOWED_PAGE_SIZES, true)) {
            throw new InvalidArgumentException('Invalid page size.');
        }
        if (!is_bool($includeSensitiveInput)) {
            throw new InvalidArgumentException('Invalid sensitive-data flag.');
        }

        $search = trim($searchInput);
        $searchLength = function_exists('mb_strlen') ? mb_strlen($search, 'UTF-8') : strlen($search);
        if ($searchLength > self::MAX_SEARCH_LENGTH
            || preg_match('//u', $search) !== 1
            || preg_match('/[\x00-\x1F\x7F]/', $search)) {
            throw new InvalidArgumentException('Invalid search term.');
        }

        $includeSensitive = $includeSensitiveInput;
        $type = $typeInput;
        if ($type === 'playerips' && !$includeSensitive) {
            throw new InvalidArgumentException('Player IP history requires administrator access.');
        }

        $spec = $this->querySpec($type, $includeSensitive);
        $whereSql = '';
        $searchParams = [];

        if ($search !== '') {
            $conditions = [];
            $like = '%' . $search . '%';
            foreach ($spec['search_columns'] as $index => $column) {
                $placeholder = ':search' . $index;
                $conditions[] = $column . ' LIKE ' . $placeholder;
                $searchParams[$placeholder] = $like;
            }
            $whereSql = ' WHERE (' . implode(' OR ', $conditions) . ')';
        }

        $countSql = 'SELECT COUNT(*) FROM (' . $spec['select_sql'] . ') AS base' . $whereSql;
        $countStatement = $this->pdo->prepare($countSql);
        $countStatement->execute($searchParams);
        $total = max(0, (int)$countStatement->fetchColumn());

        $totalPages = max(1, (int)ceil($total / $perPageInput));
        $page = min($pageInput, $totalPages);
        $offset = ($page - 1) * $perPageInput;

        $recordsSql = 'SELECT * FROM (' . $spec['select_sql'] . ') AS base'
            . $whereSql . ' ORDER BY ' . $spec['order_by']
            . ' LIMIT :page_limit OFFSET :page_offset';

        $recordsStatement = $this->pdo->prepare($recordsSql);
        foreach ($searchParams as $placeholder => $value) {
            $recordsStatement->bindValue($placeholder, $value, PDO::PARAM_STR);
        }
        $recordsStatement->bindValue(':page_limit', $perPageInput, PDO::PARAM_INT);
        $recordsStatement->bindValue(':page_offset', $offset, PDO::PARAM_INT);
        $recordsStatement->execute();

        $items = $recordsStatement->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($items)) {
            $items = [];
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPageInput,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * @return array{select_sql:string,search_columns:array<int,string>,order_by:string}
     */
    private function querySpec(string $type, bool $includeSensitive): array
    {
        $banIp = $includeSensitive ? 'b.player_ip' : 'NULL';
        $playerIpHistory = 'INET_NTOA(address)';

        switch ($type) {
            case 'bans':
                return [
                    'select_sql' => "
                        SELECT b.id AS id, b.player_name AS player_name,
                            CAST(b.player_steamid AS CHAR) AS player_steamid,
                            {$banIp} AS player_ip, b.admin_name AS admin_name,
                            CAST(b.admin_steamid AS CHAR) AS admin_steamid,
                            b.reason AS reason, b.duration AS duration, b.ends AS ends,
                            b.created AS created, b.status AS status, b.server_id AS server_id,
                            s.hostname AS server_hostname
                        FROM sa_bans b
                        LEFT JOIN sa_servers s ON s.id = b.server_id
                        WHERE b.status = 'ACTIVE'
                    ",
                    'search_columns' => array_values(array_filter([
                        'base.player_name', 'base.player_steamid',
                        $includeSensitive ? 'base.player_ip' : null,
                        'base.admin_name', 'base.admin_steamid', 'base.reason', 'base.server_hostname',
                    ])),
                    'order_by' => 'base.created DESC, base.id DESC',
                ];

            case 'unbans':
                return [
                    'select_sql' => "
                        SELECT u.id AS unban_id, u.ban_id AS ban_id,
                            u.reason AS unban_reason, u.date AS unban_date,
                            b.player_name AS player_name,
                            CAST(b.player_steamid AS CHAR) AS player_steamid,
                            {$banIp} AS player_ip, b.admin_name AS admin_name,
                            CAST(b.admin_steamid AS CHAR) AS admin_steamid,
                            b.reason AS ban_reason, b.created AS ban_created,
                            ua.player_name AS unban_admin_name,
                            CAST(ua.player_steamid AS CHAR) AS unban_admin_steamid,
                            b.server_id AS server_id, s.hostname AS server_hostname
                        FROM sa_unbans u
                        INNER JOIN sa_bans b ON b.id = u.ban_id
                        LEFT JOIN sa_admins ua ON ua.id = u.admin_id
                        LEFT JOIN sa_servers s ON s.id = b.server_id
                    ",
                    'search_columns' => array_values(array_filter([
                        'base.player_name', 'base.player_steamid',
                        $includeSensitive ? 'base.player_ip' : null,
                        'base.admin_name', 'base.admin_steamid', 'base.ban_reason',
                        'base.unban_reason', 'base.unban_admin_name',
                        'base.unban_admin_steamid', 'base.server_hostname',
                    ])),
                    'order_by' => 'base.unban_date DESC, base.unban_id DESC',
                ];

            case 'mutes':
                return [
                    'select_sql' => "
                        SELECT m.id AS id, m.player_name AS player_name,
                            CAST(m.player_steamid AS CHAR) AS player_steamid,
                            m.admin_name AS admin_name,
                            CAST(m.admin_steamid AS CHAR) AS admin_steamid,
                            m.reason AS reason, m.duration AS duration, m.ends AS ends,
                            m.created AS created, m.type AS type, m.status AS status,
                            m.server_id AS server_id, s.hostname AS server_hostname
                        FROM sa_mutes m
                        LEFT JOIN sa_servers s ON s.id = m.server_id
                        WHERE m.status = 'ACTIVE'
                    ",
                    'search_columns' => [
                        'base.player_name', 'base.player_steamid', 'base.admin_name',
                        'base.admin_steamid', 'base.reason', 'base.type', 'base.server_hostname',
                    ],
                    'order_by' => 'base.created DESC, base.id DESC',
                ];

            case 'unmutes':
                return [
                    'select_sql' => "
                        SELECT u.id AS unmute_id, u.mute_id AS mute_id,
                            u.reason AS unmute_reason, u.date AS unmute_date,
                            m.player_name AS player_name,
                            CAST(m.player_steamid AS CHAR) AS player_steamid,
                            m.admin_name AS admin_name,
                            CAST(m.admin_steamid AS CHAR) AS admin_steamid,
                            m.reason AS mute_reason, m.type AS type, m.created AS mute_created,
                            ua.player_name AS unmute_admin_name,
                            CAST(ua.player_steamid AS CHAR) AS unmute_admin_steamid,
                            m.server_id AS server_id, s.hostname AS server_hostname
                        FROM sa_unmutes u
                        INNER JOIN sa_mutes m ON m.id = u.mute_id
                        LEFT JOIN sa_admins ua ON ua.id = u.admin_id
                        LEFT JOIN sa_servers s ON s.id = m.server_id
                    ",
                    'search_columns' => [
                        'base.player_name', 'base.player_steamid', 'base.admin_name',
                        'base.admin_steamid', 'base.mute_reason', 'base.unmute_reason',
                        'base.type', 'base.unmute_admin_name', 'base.unmute_admin_steamid',
                        'base.server_hostname',
                    ],
                    'order_by' => 'base.unmute_date DESC, base.unmute_id DESC',
                ];

            case 'warns':
                return [
                    'select_sql' => "
                        SELECT w.id AS id, w.player_name AS player_name,
                            CAST(w.player_steamid AS CHAR) AS player_steamid,
                            w.admin_name AS admin_name,
                            CAST(w.admin_steamid AS CHAR) AS admin_steamid,
                            w.reason AS reason, w.duration AS duration, w.ends AS ends,
                            w.created AS created, w.status AS status, w.server_id AS server_id,
                            s.hostname AS server_hostname
                        FROM sa_warns w
                        LEFT JOIN sa_servers s ON s.id = w.server_id
                        WHERE w.status = 'ACTIVE'
                    ",
                    'search_columns' => [
                        'base.player_name', 'base.player_steamid', 'base.admin_name',
                        'base.admin_steamid', 'base.reason', 'base.server_hostname',
                    ],
                    'order_by' => 'base.created DESC, base.id DESC',
                ];

            case 'playerips':
                if (!$includeSensitive) {
                    throw new InvalidArgumentException('Player IP history requires administrator access.');
                }
                return [
                    'select_sql' => "
                        SELECT CAST(steamid AS CHAR) AS steamid, name AS name,
                            {$playerIpHistory} AS address, used_at AS used_at
                        FROM sa_players_ips
                    ",
                    'search_columns' => ['base.steamid', 'base.name', 'base.address'],
                    'order_by' => 'base.used_at DESC, base.steamid DESC',
                ];
        }

        throw new InvalidArgumentException('Unsupported SimpleAdmin dataset.');
    }
}

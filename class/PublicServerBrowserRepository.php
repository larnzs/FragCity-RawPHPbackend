<?php
declare(strict_types=1);

/**
 * Public Server Browser reads only validated CS2 rows from the website database.
 */
final class PublicServerBrowserRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getServers(): array
    {
        $sql = '
            SELECT
                p.id,
                t.host,
                t.region,
                p.port,
                p.server_name,
                p.map_name,
                p.players,
                p.max_players,
                p.vac,
                p.password,
                p.query_rtt_ms,
                p.last_queried_at
            FROM public_server_scan_ports AS p
            INNER JOIN public_server_scan_targets AS t
                ON t.id = p.target_id
            WHERE t.enabled = 1
              AND p.port BETWEEN t.port_start AND t.port_end
              AND p.app_id = 730
              AND p.last_seen_online_at IS NOT NULL
              AND p.last_queried_at >= (UTC_TIMESTAMP() - INTERVAL 10 MINUTE)
            ORDER BY p.players DESC, p.server_name ASC, t.host ASC, p.port ASC
        ';

        $statement = $this->pdo->query($sql);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $servers = [];

        foreach ($rows as $row) {
            $name = trim((string)($row['server_name'] ?? ''));
            $mode = 'Community';
            if (preg_match('/\bmode\s*[:=]\s*([^|,\r\n]+)/iu', $name, $matches) === 1) {
                $candidate = trim((string)$matches[1]);
                if ($candidate !== '') {
                    $mode = function_exists('mb_substr')
                        ? mb_substr($candidate, 0, 80, 'UTF-8')
                        : substr($candidate, 0, 80);
                }
            }

            $host = (string)$row['host'];
            $port = (int)$row['port'];

            $servers[] = [
                'id' => (string)$row['id'],
                'name' => $name !== '' ? $name : 'CS2 Community Server',
                'host' => $host,
                'game_port' => $port,
                'query_port' => $port,
                'mode' => $mode,
                'map' => trim((string)($row['map_name'] ?? '')) ?: 'Unknown',
                'status' => (string)($row['status'] ?? '') === 'online' ? 'online' : 'offline',
                'players' => max(0, (int)$row['players']),
                'max_players' => max(0, (int)$row['max_players']),
                'query_rtt_ms' => $row['query_rtt_ms'] !== null
                    ? round((float)$row['query_rtt_ms'], 2)
                    : null,
                'vac' => $row['vac'] !== null ? (bool)$row['vac'] : null,
                'password' => $row['password'] !== null ? (bool)$row['password'] : null,
                'region' => trim((string)($row['region'] ?? '')),
                'country' => '',
            ];
        }

        return $servers;
    }
}

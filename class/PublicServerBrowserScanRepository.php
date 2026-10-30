<?php
declare(strict_types=1);

/**
 * Database persistence for the public A2S scanner.
 *
 * This class contains no UDP/network code and can be reused by any CLI runner
 * that has PDO access to the website's MySQL/MariaDB database.
 */
final class PublicServerBrowserScanRepository
{
    private PDO $pdo;
    private ?PDOStatement $updateStatement = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function acquireLock(): bool
    {
        return (int)$this->pdo
            ->query("SELECT GET_LOCK('fragcity_public_server_browser_scan', 0)")
            ->fetchColumn() === 1;
    }

    public function releaseLock(): void
    {
        $this->pdo->query("SELECT RELEASE_LOCK('fragcity_public_server_browser_scan')");
    }

    /** @return array<int, array<string, mixed>> */
    public function getEnabledTargets(): array
    {
        return $this->pdo->query(
            'SELECT id, host, port_start, port_end, query_timeout_ms,
                    retry_offline_seconds, refresh_online_seconds
             FROM public_server_scan_targets
             WHERE enabled = 1
             ORDER BY id ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Add rows for newly configured ports without changing existing scan state.
     *
     * @param array<string, mixed> $target
     */
    public function ensureTargetInventory(array $target): void
    {
        $targetId = (int)($target['id'] ?? 0);
        $portStart = (int)($target['port_start'] ?? 0);
        $portEnd = (int)($target['port_end'] ?? 0);

        $inventoryQuery = $this->pdo->prepare(
            'SELECT COUNT(*) AS total, MIN(port) AS min_port, MAX(port) AS max_port
             FROM public_server_scan_ports
             WHERE target_id = :target_id
               AND port BETWEEN :port_start AND :port_end'
        );
        $inventoryQuery->execute([
            ':target_id' => $targetId,
            ':port_start' => $portStart,
            ':port_end' => $portEnd,
        ]);
        $inventory = $inventoryQuery->fetch(PDO::FETCH_ASSOC) ?: [];
        $expected = $portEnd - $portStart + 1;

        if ((int)($inventory['total'] ?? 0) === $expected
            && (int)($inventory['min_port'] ?? 0) === $portStart
            && (int)($inventory['max_port'] ?? 0) === $portEnd) {
            return;
        }

        foreach (array_chunk(range($portStart, $portEnd), 250) as $portBatch) {
            $valueSql = [];
            $parameters = [];

            foreach ($portBatch as $offset => $port) {
                $targetParameter = ':target_' . $offset;
                $portParameter = ':port_' . $offset;
                $valueSql[] = '(' . $targetParameter . ', ' . $portParameter . ", 'pending')";
                $parameters[$targetParameter] = $targetId;
                $parameters[$portParameter] = $port;
            }

            $insert = $this->pdo->prepare(
                'INSERT IGNORE INTO public_server_scan_ports (target_id, port, status)
                 VALUES ' . implode(', ', $valueSql)
            );
            $insert->execute($parameters);
        }
    }

    /**
     * @return array<int, array{id: int, port: int}>
     */
    public function getDuePorts(
        int $targetId,
        int $portStart,
        int $portEnd,
        int $limit
    ): array {
        $statement = $this->pdo->prepare(
            'SELECT id, port
             FROM public_server_scan_ports
             WHERE target_id = :target_id
               AND port BETWEEN :port_start AND :port_end
               AND (next_query_at IS NULL OR next_query_at <= UTC_TIMESTAMP())
             ORDER BY
                (next_query_at IS NOT NULL) ASC,
                next_query_at ASC,
                id ASC
             LIMIT :row_limit'
        );
        $statement->bindValue(':target_id', $targetId, PDO::PARAM_INT);
        $statement->bindValue(':port_start', $portStart, PDO::PARAM_INT);
        $statement->bindValue(':port_end', $portEnd, PDO::PARAM_INT);
        $statement->bindValue(':row_limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        $rows = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = [
                'id' => (int)$row['id'],
                'port' => (int)$row['port'],
            ];
        }
        return $rows;
    }

    /**
     * Persist one parsed A2S result and schedule its next query.
     *
     * @param array<string, mixed> $result
     */
    public function saveResult(
        int $rowId,
        array $result,
        int $retryOfflineSeconds,
        int $refreshOnlineSeconds
    ): string {
        $status = (string)($result['status'] ?? 'error');
        $info = is_array($result['info'] ?? null) ? $result['info'] : [];
        $isOnline = $status === 'online' && (int)($info['app_id'] ?? 0) === 730;

        if ($isOnline) {
            $nextQueryAt = gmdate('Y-m-d H:i:s', time() + $refreshOnlineSeconds);
            $storedStatus = 'online';
        } else {
            $nextQueryAt = gmdate('Y-m-d H:i:s', time() + $retryOfflineSeconds);
            $storedStatus = in_array($status, ['offline', 'not_cs2', 'error'], true)
                ? $status
                : 'error';
        }

        if ($this->updateStatement === null) {
            $this->updateStatement = $this->pdo->prepare(
                'UPDATE public_server_scan_ports
                 SET status = :status,
                     app_id = COALESCE(:app_id, app_id),
                     server_name = COALESCE(:server_name, server_name),
                     map_name = COALESCE(:map_name, map_name),
                     players = :players,
                     max_players = COALESCE(:max_players, max_players),
                     bots = COALESCE(:bots, bots),
                     vac = COALESCE(:vac, vac),
                     password = COALESCE(:password, password),
                     query_rtt_ms = :query_rtt_ms,
                     last_error = :last_error,
                     last_queried_at = UTC_TIMESTAMP(),
                     next_query_at = :next_query_at,
                     last_seen_online_at = CASE
                         WHEN :seen_online = 1 THEN UTC_TIMESTAMP()
                         ELSE last_seen_online_at
                     END
                 WHERE id = :id'
            );
        }

        $this->updateStatement->execute([
            ':status' => $storedStatus,
            ':app_id' => isset($info['app_id']) ? (int)$info['app_id'] : null,
            ':server_name' => $isOnline ? (string)($info['name'] ?? '') : null,
            ':map_name' => $isOnline ? (string)($info['map'] ?? '') : null,
            ':players' => $isOnline ? max(0, (int)($info['players'] ?? 0)) : 0,
            ':max_players' => $isOnline ? max(0, (int)($info['max_players'] ?? 0)) : null,
            ':bots' => $isOnline ? max(0, (int)($info['bots'] ?? 0)) : null,
            ':vac' => $isOnline ? (int)!empty($info['vac']) : null,
            ':password' => $isOnline ? (int)!empty($info['password']) : null,
            ':query_rtt_ms' => $isOnline && isset($info['query_rtt_ms'])
                ? round((float)$info['query_rtt_ms'], 2)
                : null,
            ':last_error' => $isOnline
                ? null
                : substr((string)($result['error'] ?? 'query_failed'), 0, 64),
            ':next_query_at' => $nextQueryAt,
            ':seen_online' => $isOnline ? 1 : 0,
            ':id' => $rowId,
        ]);

        return $storedStatus;
    }
}

<?php
declare(strict_types=1);

/**
 * Scheduled public server scan. Run by CLI cron only.
 *
 * It reads configured IP/port ranges from the site's main database, sends
 * bounded concurrent UDP A2S_INFO requests, and stores per-port retry times.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

set_time_limit(30);
require_once dirname(__DIR__) . '/settings/loader.php';
require_once dirname(__DIR__) . '/class/PublicServerBrowserA2SScanner.php';

$indexSettings = fragcity_settings('files.index', []);
$profile = is_array($indexSettings)
    ? trim((string)($indexSettings['database_profile'] ?? 'main'))
    : 'main';
$profile = $profile !== '' ? $profile : 'main';

$pdo = fragcity_create_pdo($profile);
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "FAIL: could not connect to the configured main website database.\n");
    exit(2);
}

if (!function_exists('stream_socket_client') || !function_exists('stream_select')) {
    fwrite(STDERR, "FAIL: PHP stream_socket_client and stream_select are required for UDP scans.\n");
    exit(2);
}

$lockAcquired = (int)$pdo->query(
    "SELECT GET_LOCK('fragcity_public_server_browser_scan', 0)"
)->fetchColumn();

if ($lockAcquired !== 1) {
    echo "SKIP: another public server scan is already running.\n";
    exit(0);
}

$startedAt = microtime(true);
$processed = 0;
$online = 0;
$offline = 0;
$notCs2 = 0;
$errors = 0;

try {
    $targets = $pdo->query(
        'SELECT id, host, port_start, port_end, query_timeout_ms,
                retry_offline_seconds, refresh_online_seconds
         FROM public_server_scan_targets
         WHERE enabled = 1
         ORDER BY id ASC'
    )->fetchAll(PDO::FETCH_ASSOC);

    $maxPerRun = 256;
    $batchSize = 32;

    foreach ($targets as $target) {
        $targetId = (int)$target['id'];
        $host = trim((string)$target['host']);
        $portStart = (int)$target['port_start'];
        $portEnd = (int)$target['port_end'];

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false
            || $portStart < 1 || $portEnd > 65535 || $portStart > $portEnd
            || ($portEnd - $portStart) > 4095) {
            fwrite(STDERR, 'WARN: skipped invalid target #' . $targetId . PHP_EOL);
            continue;
        }

        // Seed newly added or extended ranges without rewriting existing scan state.
        $inventoryQuery = $pdo->prepare(
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

        if ((int)($inventory['total'] ?? 0) !== $expected
            || (int)($inventory['min_port'] ?? 0) !== $portStart
            || (int)($inventory['max_port'] ?? 0) !== $portEnd) {
            foreach (array_chunk(range($portStart, $portEnd), 250) as $missingPortBatch) {
                $valueSql = [];
                $parameters = [];

                foreach ($missingPortBatch as $offset => $port) {
                    $targetParameter = ':target_' . $offset;
                    $portParameter = ':port_' . $offset;
                    $valueSql[] = '(' . $targetParameter . ', ' . $portParameter . ', "pending")';
                    $parameters[$targetParameter] = $targetId;
                    $parameters[$portParameter] = $port;
                }

                $insert = $pdo->prepare(
                    'INSERT IGNORE INTO public_server_scan_ports (target_id, port, status)
                     VALUES ' . implode(', ', $valueSql)
                );
                $insert->execute($parameters);
            }
        }

        $remaining = $maxPerRun - $processed;
        if ($remaining <= 0) {
            break;
        }

        $dueQuery = $pdo->prepare(
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
        $dueQuery->bindValue(':target_id', $targetId, PDO::PARAM_INT);
        $dueQuery->bindValue(':port_start', $portStart, PDO::PARAM_INT);
        $dueQuery->bindValue(':port_end', $portEnd, PDO::PARAM_INT);
        $dueQuery->bindValue(':row_limit', $remaining, PDO::PARAM_INT);
        $dueQuery->execute();

        $dueRows = $dueQuery->fetchAll(PDO::FETCH_ASSOC);
        if ($dueRows === []) {
            continue;
        }

        $scanner = new PublicServerBrowserA2SScanner((int)$target['query_timeout_ms']);
        $offlineRetry = max(300, min(86400, (int)$target['retry_offline_seconds']));
        $onlineRefresh = max(30, min(3600, (int)$target['refresh_online_seconds']));

        foreach (array_chunk($dueRows, $batchSize) as $batch) {
            $ports = array_map(
                static fn(array $row): int => (int)$row['port'],
                $batch
            );

            $batchResults = $scanner->queryBatch($host, $ports);
            $updates = $pdo->prepare(
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

            foreach ($batch as $row) {
                $port = (int)$row['port'];
                $result = $batchResults[$port] ?? [
                    'status' => 'offline',
                    'error' => 'no_result',
                ];
                $status = (string)($result['status'] ?? 'error');
                $info = is_array($result['info'] ?? null) ? $result['info'] : [];
                $isOnline = $status === 'online' && (int)($info['app_id'] ?? 0) === 730;

                if ($isOnline) {
                    $next = gmdate('Y-m-d H:i:s', time() + $onlineRefresh);
                    ++$online;
                } else {
                    $next = gmdate('Y-m-d H:i:s', time() + $offlineRetry);
                    if ($status === 'not_cs2') {
                        ++$notCs2;
                    } elseif ($status === 'offline') {
                        ++$offline;
                    } else {
                        ++$errors;
                    }
                }

                $updates->execute([
                    ':status' => $isOnline
                        ? 'online'
                        : (in_array($status, ['offline', 'not_cs2', 'error'], true) ? $status : 'error'),
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
                    ':last_error' => $isOnline ? null : substr((string)($result['error'] ?? 'query_failed'), 0, 64),
                    ':next_query_at' => $next,
                    ':seen_online' => $isOnline ? 1 : 0,
                    ':id' => (int)$row['id'],
                ]);
            }

            $processed += count($batch);
        }
    }

    $elapsed = round(microtime(true) - $startedAt, 2);
    echo 'PASS: scanned ' . $processed
        . ' port(s); online=' . $online
        . ', offline=' . $offline
        . ', non_cs2=' . $notCs2
        . ', errors=' . $errors
        . '; elapsed=' . $elapsed . "s\n";
} catch (Throwable $e) {
    error_log('[FragCity Public Server Browser] ' . get_class($e) . ': ' . $e->getMessage());
    fwrite(STDERR, 'FAIL: public server scan stopped: ' . $e->getMessage() . PHP_EOL);
    exit(1);
} finally {
    $pdo->query("SELECT RELEASE_LOCK('fragcity_public_server_browser_scan')");
}

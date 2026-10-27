<?php
declare(strict_types=1);

/**
 * Private server-to-server bridge for legacy FragCity Agent operations.
 *
 * This file runs on the SHARED-HOSTING WEBSITE, not on the Linux API host.
 * It opens the site's local database through settings/loader.php. The Linux
 * API reaches it only over signed HTTPS; no MySQL connection to Linux is made.
 */

require_once __DIR__ . '/../settings/loader.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

const FRAGCITY_BRIDGE_PATH = '/api/internal_agent_bridge.php';

function fragcityBridgeRespond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

function fragcityBridgeHeader(string $name): string
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    $value = $_SERVER[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function fragcityBridgeString(mixed $value, int $maxLength = 255): string
{
    if (!is_string($value) && !is_int($value) && !is_float($value)) {
        return '';
    }
    $value = trim((string)$value);
    return strlen($value) <= $maxLength ? $value : '';
}

function fragcityBridgeInt(mixed $value, int $min, int $max): ?int
{
    if (is_int($value)) {
        $number = $value;
    } elseif (is_string($value) && preg_match('/^-?[0-9]{1,12}$/D', $value)) {
        $number = (int)$value;
    } else {
        return null;
    }
    return ($number >= $min && $number <= $max) ? $number : null;
}

function fragcityBridgeToken(PDO $pdo, string $token): array
{
    if (!preg_match('/^[A-Fa-f0-9]{32,128}$/D', $token)) {
        fragcityBridgeRespond(401, ['status' => 'error', 'message' => 'Unauthorized']);
    }

    $stmt = $pdo->prepare(
        'SELECT t.token_id, t.username
         FROM agent_tokens t
         INNER JOIN users u ON (u.username = t.username OR u.steamid64 = t.username)
         WHERE t.token = ? AND t.expires_at > NOW() AND u.insmaster = 1
         LIMIT 1'
    );
    $stmt->execute([$token]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$record) {
        fragcityBridgeRespond(401, ['status' => 'error', 'message' => 'Unauthorized']);
    }

    $pdo->prepare('UPDATE agent_tokens SET last_used = NOW() WHERE token_id = ?')
        ->execute([(int)$record['token_id']]);

    return $record;
}

function fragcityBridgeNodeName(array $data): string
{
    $name = fragcityBridgeString($data['node_name'] ?? $data['NodeName'] ?? '', 64);
    if ($name === '' || !preg_match('/^[A-Za-z0-9_. -]{1,64}$/D', $name)) {
        fragcityBridgeRespond(400, ['status' => 'error', 'message' => 'Invalid node_name']);
    }
    return $name;
}

function fragcityBridgeLogin(PDO $pdo, array $data, string $clientIp): never
{
    $ipHash = hash('sha256', $clientIp);
    $count = $pdo->prepare(
        'SELECT COUNT(*) FROM fragcity_api_bridge_login_attempts
         WHERE ip_hash = ? AND attempted_at > (UTC_TIMESTAMP() - INTERVAL 15 MINUTE)'
    );
    $count->execute([$ipHash]);
    if ((int)$count->fetchColumn() >= 10) {
        fragcityBridgeRespond(429, ['status' => 'error', 'message' => 'Too many login attempts']);
    }
    $pdo->prepare('INSERT INTO fragcity_api_bridge_login_attempts (ip_hash, attempted_at) VALUES (?, UTC_TIMESTAMP())')
        ->execute([$ipHash]);

    $loginId = fragcityBridgeString($data['login_id'] ?? '', 190);
    $password = $data['password'] ?? null;
    if ($loginId === '' || !is_string($password) || $password === '' || strlen($password) > 1024) {
        fragcityBridgeRespond(400, ['status' => 'error', 'message' => 'Username and password are required']);
    }

    if (preg_match('/^[0-9]{17}$/D', $loginId)) {
        $field = 'steamid64';
    } elseif (preg_match('/^09[0-9]{9}$/D', $loginId)) {
        $field = 'phone';
    } else {
        $field = 'username';
    }

    // The column name comes only from the fixed allowlist above.
    $stmt = $pdo->prepare(
        'SELECT username, steamid64, password_hash, insmaster
         FROM users WHERE ' . $field . ' = ? LIMIT 1'
    );
    $stmt->execute([$loginId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !is_string($user['password_hash'] ?? null)
        || !password_verify($password, $user['password_hash'])
        || (int)($user['insmaster'] ?? 0) !== 1) {
        fragcityBridgeRespond(401, ['status' => 'error', 'message' => 'Invalid credentials or insufficient permission']);
    }

    $token = bin2hex(random_bytes(32));
    $username = trim((string)($user['username'] ?? ''));
    if ($username === '') {
        $username = trim((string)($user['steamid64'] ?? ''));
    }
    if ($username === '') {
        fragcityBridgeRespond(500, ['status' => 'error', 'message' => 'Unable to create agent session']);
    }

    $insert = $pdo->prepare(
        'INSERT INTO agent_tokens (token, username, expires_at, created_at, last_used)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY), NOW(), NOW())'
    );
    $insert->execute([$token, $username]);

    fragcityBridgeRespond(200, [
        'status' => 'success',
        'message' => 'Login successful.',
        'token' => $token,
        'username' => $username,
    ]);
}

function fragcityBridgeHeartbeat(PDO $pdo, array $data): never
{
    $nodeName = fragcityBridgeNodeName($data);
    $ip = fragcityBridgeString($data['IpAddress'] ?? $data['ip_address'] ?? $data['ip'] ?? '', 45);
    if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) === false) {
        $ip = '';
    }
    $cpuRaw = $data['CpuUsage'] ?? $data['cpu_usage'] ?? null;
    $ramRaw = $data['RamUsage'] ?? $data['ram_usage'] ?? null;
    $cpu = is_numeric($cpuRaw) ? max(0.0, min(100.0, (float)$cpuRaw)) : null;
    $ram = is_numeric($ramRaw) ? max(0.0, min(100.0, (float)$ramRaw)) : null;
    $readyRaw = $data['IsReady'] ?? $data['is_ready'] ?? true;
    $ready = filter_var($readyRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    $ready = $ready === null ? 1 : (int)$ready;

    $stmt = $pdo->prepare(
        'SELECT node_id, cpu_usage, ram_usage FROM agent_nodes WHERE node_name = ? LIMIT 1'
    );
    $stmt->execute([$nodeName]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $cpu = ($cpu !== null && $cpu > 0) ? $cpu : (float)($existing['cpu_usage'] ?? 0);
        $ram = ($ram !== null && $ram > 0) ? $ram : (float)($existing['ram_usage'] ?? 0);
        $update = $pdo->prepare(
            'UPDATE agent_nodes
             SET ip_address = COALESCE(NULLIF(?, \'\'), ip_address),
                 cpu_usage = ?, ram_usage = ?, is_ready = ?, last_heartbeat = NOW()
             WHERE node_id = ?'
        );
        $update->execute([$ip, $cpu, $ram, $ready, (int)$existing['node_id']]);
    } else {
        $insert = $pdo->prepare(
            'INSERT INTO agent_nodes (node_name, ip_address, cpu_usage, ram_usage, is_ready, last_heartbeat)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $insert->execute([$nodeName, $ip, $cpu ?? 0, $ram ?? 0, $ready]);
    }

    fragcityBridgeRespond(200, ['status' => 'success', 'message' => 'Heartbeat logged successfully.']);
}

function fragcityBridgeGetServers(PDO $pdo, array $data): never
{
    $action = fragcityBridgeString($data['action'] ?? '', 64);
    if ($action === 'get_pending') {
        $nodeName = fragcityBridgeNodeName($data);
        $stmt = $pdo->prepare(
            'SELECT server_id, steam_id, node_name, server_name, server_ip, port, slots,
                    modes, tickrate, rcon_password, server_password, gslt_token, insecure,
                    paid_price, payment_token, time_left, expire_date, status, created_at
             FROM pending_servers
             WHERE status = \'Pending\' AND (node_name IS NULL OR node_name = \'\' OR node_name = ?)
             ORDER BY server_id ASC LIMIT 5'
        );
        $stmt->execute([$nodeName]);
        fragcityBridgeRespond(200, ['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($action === 'update_status') {
        $serverId = fragcityBridgeInt($data['server_id'] ?? null, 1, 2147483647);
        $status = fragcityBridgeString($data['status'] ?? '', 32);
        $nodeName = fragcityBridgeNodeName($data);
        $allowedStatuses = ['Pending', 'Running', 'Completed', 'Failed', 'Stopped', 'Suspended'];
        if ($serverId === null || !in_array($status, $allowedStatuses, true)) {
            fragcityBridgeRespond(400, ['status' => 'error', 'message' => 'Invalid server_id or status']);
        }
        $ip = fragcityBridgeString($data['server_ip'] ?? $data['ip_address'] ?? '', 45);
        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) === false) {
            fragcityBridgeRespond(400, ['status' => 'error', 'message' => 'Invalid server IP']);
        }

        if ($ip !== '') {
            $stmt = $pdo->prepare(
                'UPDATE pending_servers
                 SET status = ?, node_name = ?, server_ip = ?
                 WHERE server_id = ? AND (node_name IS NULL OR node_name = \'\' OR node_name = ?)'
            );
            $stmt->execute([$status, $nodeName, $ip, $serverId, $nodeName]);
        } else {
            $stmt = $pdo->prepare(
                'UPDATE pending_servers SET status = ?, node_name = ?
                 WHERE server_id = ? AND (node_name IS NULL OR node_name = \'\' OR node_name = ?)'
            );
            $stmt->execute([$status, $nodeName, $serverId, $nodeName]);
        }

        fragcityBridgeRespond($stmt->rowCount() > 0 ? 200 : 404, [
            'status' => $stmt->rowCount() > 0 ? 'success' : 'error',
            'message' => $stmt->rowCount() > 0 ? 'Server status updated' : 'Server not found or assigned to another node',
        ]);
    }

    if ($action === 'sync_metrics') {
        $nodeName = fragcityBridgeNodeName($data);
        $publicIp = fragcityBridgeString($data['ip_address'] ?? $data['ip'] ?? '', 45);
        if ($publicIp !== '' && filter_var($publicIp, FILTER_VALIDATE_IP) === false) {
            fragcityBridgeRespond(400, ['status' => 'error', 'message' => 'Invalid node IP']);
        }
        $cpu = is_numeric($data['cpu_usage'] ?? null) ? max(0.0, min(100.0, (float)$data['cpu_usage'])) : 0.0;
        $ram = is_numeric($data['ram_usage'] ?? null) ? max(0.0, min(100.0, (float)$data['ram_usage'])) : 0.0;
        $servers = $data['servers'] ?? [];
        if (!is_array($servers) || count($servers) > 100) {
            fragcityBridgeRespond(400, ['status' => 'error', 'message' => 'Invalid servers list']);
        }

        $pdo->beginTransaction();
        try {
            $node = $pdo->prepare(
                'INSERT INTO agent_nodes (node_name, ip_address, cpu_usage, ram_usage, is_ready, last_heartbeat)
                 VALUES (?, ?, ?, ?, 1, NOW())
                 ON DUPLICATE KEY UPDATE
                   ip_address = VALUES(ip_address), cpu_usage = VALUES(cpu_usage),
                   ram_usage = VALUES(ram_usage), is_ready = 1, last_heartbeat = NOW()'
            );
            $node->execute([$nodeName, $publicIp, $cpu, $ram]);

            $lookup = $pdo->prepare('SELECT server_name, server_ip, port, node_name FROM pending_servers WHERE server_id = ? LIMIT 1');
            $updatePending = $pdo->prepare(
                'UPDATE pending_servers SET node_name = ?, server_ip = ?, port = COALESCE(?, port), status = \'Running\'
                 WHERE server_id = ? AND (node_name IS NULL OR node_name = \'\' OR node_name = ?)'
            );
            $upsertGame = $pdo->prepare(
                'INSERT INTO game_servers (ip, port, server_name, status)
                 VALUES (?, ?, ?, \'Running\')
                 ON DUPLICATE KEY UPDATE server_name = VALUES(server_name), status = \'Running\', updated_at = CURRENT_TIMESTAMP'
            );
            $findRuntime = $pdo->prepare('SELECT id FROM server_runtime_stats WHERE server_id = ? LIMIT 1');
            $updateRuntime = $pdo->prepare(
                'UPDATE server_runtime_stats SET current_players = ?, max_players = ?, current_map = ?, is_online = ?, last_updated = NOW()
                 WHERE server_id = ?'
            );
            $insertRuntime = $pdo->prepare(
                'INSERT INTO server_runtime_stats (server_id, current_players, max_players, current_map, is_online, last_updated)
                 VALUES (?, ?, ?, ?, ?, NOW())'
            );

            $processed = 0;
            foreach ($servers as $server) {
                if (!is_array($server)) {
                    continue;
                }
                $serverId = fragcityBridgeInt($server['server_id'] ?? null, 1, 2147483647);
                $port = fragcityBridgeInt($server['port'] ?? null, 1, 65535);
                $ip = fragcityBridgeString($server['ip'] ?? $server['server_ip'] ?? $publicIp, 45);
                if ($serverId === null || $port === null || ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) === false)) {
                    continue;
                }
                // A valid Agent token alone must not let one node publish runtime data
                // for a server assigned to another node. pending_servers is the ownership
                // record for this legacy telemetry path.
                $lookup->execute([$serverId]);
                $pendingServer = $lookup->fetch(PDO::FETCH_ASSOC);
                if (!$pendingServer) {
                    continue;
                }
                $assignedNode = trim((string)($pendingServer['node_name'] ?? ''));
                if ($assignedNode !== '' && $assignedNode !== $nodeName) {
                    continue;
                }

                $serverName = fragcityBridgeString($server['server_name'] ?? '', 128);
                if ($serverName === '') {
                    $serverName = trim((string)($pendingServer['server_name'] ?? '')) ?: 'GameServer';
                }
                $players = fragcityBridgeInt($server['current_players'] ?? 0, 0, 1000) ?? 0;
                $maxPlayers = fragcityBridgeInt($server['max_players'] ?? 0, 0, 1000) ?? 0;
                $map = fragcityBridgeString($server['current_map'] ?? '', 128);
                $online = filter_var($server['is_online'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;

                $updatePending->execute([$nodeName, $ip, $port, $serverId, $nodeName]);
                if ($ip !== '') {
                    $upsertGame->execute([$ip, $port, $serverName]);
                }
                $findRuntime->execute([$serverId]);
                if ($findRuntime->fetchColumn()) {
                    $updateRuntime->execute([$players, $maxPlayers, $map, $online, $serverId]);
                } else {
                    $insertRuntime->execute([$serverId, $players, $maxPlayers, $map, $online]);
                }
                $processed++;
            }
            $pdo->commit();
            fragcityBridgeRespond(200, ['status' => 'success', 'processed' => $processed]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    fragcityBridgeRespond(400, ['status' => 'error', 'message' => 'Unknown action']);
}

function fragcityBridgeRcon(PDO $pdo, array $data): never
{
    $action = fragcityBridgeString($data['action'] ?? '', 64);
    if ($action === 'get_pending_commands') {
        $nodeName = fragcityBridgeNodeName($data);
        $stmt = $pdo->prepare(
            'SELECT c.id, c.server_ip, c.server_port, c.steam_id, c.user_command
             FROM rcon_command_logs c
             JOIN registered_servers rs
               ON c.server_port = rs.port
              AND c.server_ip = rs.server_ip
             WHERE rs.node_name = ? AND c.status = \'pending\'
             ORDER BY c.id ASC LIMIT 50'
        );
        $stmt->execute([$nodeName]);
        fragcityBridgeRespond(200, ['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($action === 'update_command_result') {
        $commandId = fragcityBridgeInt($data['command_id'] ?? null, 1, 2147483647);
        $response = fragcityBridgeString($data['server_response'] ?? '', 8000);
        $status = fragcityBridgeString($data['status'] ?? 'success', 16);
        if ($commandId === null || !in_array($status, ['success', 'error'], true)) {
            fragcityBridgeRespond(400, ['status' => 'error', 'message' => 'Invalid command result']);
        }
        $nodeName = fragcityBridgeNodeName($data);
        $stmt = $pdo->prepare(
            'UPDATE rcon_command_logs c
             INNER JOIN registered_servers rs
               ON rs.port = c.server_port
              AND rs.server_ip = c.server_ip
              AND rs.node_name = ?
             SET c.server_response = ?, c.status = ?
             WHERE c.id = ? AND c.status = \'pending\''
        );
        $stmt->execute([$nodeName, $response, $status, $commandId]);
        if ($stmt->rowCount() < 1) {
            fragcityBridgeRespond(404, [
                'status' => 'error',
                'message' => 'Pending command not found for this node',
            ]);
        }
        fragcityBridgeRespond(200, ['status' => 'success', 'message' => 'Command result updated successfully']);
    }

    if ($action === 'queue_command') {
        $serverIp = fragcityBridgeString($data['server_ip'] ?? '', 45);
        $serverPort = fragcityBridgeInt($data['port'] ?? null, 1, 65535);
        $steamId = fragcityBridgeString($data['steam_id'] ?? '', 20);
        $command = fragcityBridgeString($data['user_command'] ?? '', 1024);
        if ($serverIp === '' || filter_var($serverIp, FILTER_VALIDATE_IP) === false
            || $serverPort === null || $command === '') {
            fragcityBridgeRespond(400, ['status' => 'error', 'message' => 'Invalid command parameters']);
        }
        $stmt = $pdo->prepare(
            'INSERT INTO rcon_command_logs (server_ip, server_port, steam_id, user_command, server_response, status, created_at)
             VALUES (?, ?, ?, ?, \'\', \'pending\', NOW())'
        );
        $stmt->execute([$serverIp, $serverPort, $steamId, $command]);
        fragcityBridgeRespond(200, [
            'status' => 'success',
            'command_id' => (int)$pdo->lastInsertId(),
            'message' => 'Command queued successfully',
        ]);
    }

    fragcityBridgeRespond(400, ['status' => 'error', 'message' => 'Unknown action']);
}

function fragcityBridgeServerConfigs(PDO $pdo, array $data): never
{
    if (fragcityBridgeString($data['action'] ?? '', 64) !== 'get_node_configs') {
        fragcityBridgeRespond(400, ['status' => 'error', 'message' => 'Unknown action']);
    }
    $nodeName = fragcityBridgeNodeName($data);
    $stmt = $pdo->prepare(
        'SELECT rs.server_id, rs.port, rs.node_name, rs.steam_id,
                COALESCE(sc.current_config, \'comp.cfg\') AS current_config
         FROM registered_servers rs
         LEFT JOIN server_configs sc ON rs.server_id = sc.server_id
         WHERE rs.node_name = ?'
    );
    $stmt->execute([$nodeName]);
    fragcityBridgeRespond(200, ['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function fragcityBridgeServerPorts(PDO $pdo, array $data): never
{
    $action = fragcityBridgeString($data['action'] ?? '', 64);
    $nodeName = fragcityBridgeNodeName($data);

    if ($action === 'get_ports') {
        $stmt = $pdo->prepare('SELECT port, status FROM server_ports WHERE node_name = ? ORDER BY port ASC');
        $stmt->execute([$nodeName]);
        fragcityBridgeRespond(200, ['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($action === 'register_ports') {
        $ports = $data['ports'] ?? null;
        if (!is_array($ports) || count($ports) < 1 || count($ports) > 2048) {
            fragcityBridgeRespond(400, ['status' => 'error', 'message' => 'Invalid ports array']);
        }
        $insert = $pdo->prepare('INSERT IGNORE INTO server_ports (node_name, port, status) VALUES (?, ?, \'inactive\')');
        $count = 0;
        foreach ($ports as $portRaw) {
            $port = fragcityBridgeInt($portRaw, 1024, 65535);
            if ($port === null) {
                fragcityBridgeRespond(400, ['status' => 'error', 'message' => 'Invalid port']);
            }
            $insert->execute([$nodeName, $port]);
            $count += $insert->rowCount() > 0 ? 1 : 0;
        }
        fragcityBridgeRespond(200, ['status' => 'success', 'message' => $count . ' new ports registered.']);
    }

    if ($action === 'update_port_status') {
        $updates = $data['updates'] ?? null;
        if (!is_array($updates) || count($updates) > 2048) {
            fragcityBridgeRespond(400, ['status' => 'error', 'message' => 'Invalid updates array']);
        }
        $allowedStatus = ['active', 'inactive', 'reserved', 'in_use', 'available'];
        $stmt = $pdo->prepare('UPDATE server_ports SET status = ? WHERE node_name = ? AND port = ?');
        $count = 0;
        foreach ($updates as $update) {
            if (!is_array($update)) {
                fragcityBridgeRespond(400, ['status' => 'error', 'message' => 'Invalid port update']);
            }
            $port = fragcityBridgeInt($update['port'] ?? null, 1024, 65535);
            $status = fragcityBridgeString($update['status'] ?? '', 24);
            if ($port === null || !in_array($status, $allowedStatus, true)) {
                fragcityBridgeRespond(400, ['status' => 'error', 'message' => 'Invalid port update']);
            }
            $stmt->execute([$status, $nodeName, $port]);
            $count += $stmt->rowCount() > 0 ? 1 : 0;
        }
        fragcityBridgeRespond(200, ['status' => 'success', 'message' => 'Updated status for ' . $count . ' ports.']);
    }

    fragcityBridgeRespond(400, ['status' => 'error', 'message' => 'Unknown action']);
}

function fragcityBridgeCommands(PDO $pdo, array $data): never
{
    $action = fragcityBridgeString($data['action'] ?? '', 64);
    if ($action === 'get_commands') {
        $nodeName = fragcityBridgeNodeName($data);
        $stmt = $pdo->prepare(
            'SELECT id, server_id, action, port, steam_id, status, created_at
             FROM agent_commands WHERE status = \'action_queued\' AND node_name = ?
             ORDER BY id ASC LIMIT 20'
        );
        $stmt->execute([$nodeName]);
        fragcityBridgeRespond(200, ['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($action === 'update_command_status') {
        $commandId = fragcityBridgeInt($data['id'] ?? $data['command_id'] ?? null, 1, 2147483647);
        $status = fragcityBridgeString($data['status'] ?? '', 24);
        $nodeName = fragcityBridgeNodeName($data);
        if ($commandId === null || !in_array($status, ['action_queued', 'processing', 'in_progress', 'completed', 'failed'], true)) {
            fragcityBridgeRespond(400, ['status' => 'error', 'message' => 'Invalid command status']);
        }
        $stmt = $pdo->prepare('UPDATE agent_commands SET status = ? WHERE id = ? AND node_name = ?');
        $stmt->execute([$status, $commandId, $nodeName]);
        fragcityBridgeRespond($stmt->rowCount() > 0 ? 200 : 404, [
            'status' => $stmt->rowCount() > 0 ? 'success' : 'error',
            'message' => $stmt->rowCount() > 0 ? 'Command status updated' : 'Command not found for this node',
        ]);
    }

    fragcityBridgeRespond(400, ['status' => 'error', 'message' => 'Unknown action']);
}

function fragcityBridgeBulkSync(PDO $pdo, array $data): never
{
    $tableMap = [
        'matchzy_stats_maps' => 'kyonxcbt_matchzy_plugin',
        'matchzy_stats_matches' => 'kyonxcbt_matchzy_plugin',
        'matchzy_stats_players' => 'kyonxcbt_matchzy_plugin',
        'lvl_base' => 'kyonxcbt_ranks_plugin',
        'lvl_base_geoip' => 'kyonxcbt_ranks_plugin',
        'lvl_base_hits' => 'kyonxcbt_ranks_plugin',
        'sa_admins' => 'kyonxcbt_simpleadmin_plugin',
        'sa_admins_flags' => 'kyonxcbt_simpleadmin_plugin',
        'sa_bans' => 'kyonxcbt_simpleadmin_plugin',
        'sa_groups' => 'kyonxcbt_simpleadmin_plugin',
        'sa_groups_flags' => 'kyonxcbt_simpleadmin_plugin',
        'sa_groups_servers' => 'kyonxcbt_simpleadmin_plugin',
        'sa_migrations' => 'kyonxcbt_simpleadmin_plugin',
        'sa_mutes' => 'kyonxcbt_simpleadmin_plugin',
        'sa_players_ips' => 'kyonxcbt_simpleadmin_plugin',
        'sa_servers' => 'kyonxcbt_simpleadmin_plugin',
        'sa_unbans' => 'kyonxcbt_simpleadmin_plugin',
        'sa_unmutes' => 'kyonxcbt_simpleadmin_plugin',
        'sa_warns' => 'kyonxcbt_simpleadmin_plugin',
        'vipcore_test' => 'kyonxcbt_vip_plugin',
        'vip_servers' => 'kyonxcbt_vip_plugin',
        'vip_users' => 'kyonxcbt_vip_plugin',
        'wp_player_agents' => 'kyonxcbt_weaponpaints_plugin',
        'wp_player_gloves' => 'kyonxcbt_weaponpaints_plugin',
        'wp_player_knife' => 'kyonxcbt_weaponpaints_plugin',
        'wp_player_music' => 'kyonxcbt_weaponpaints_plugin',
        'wp_player_pins' => 'kyonxcbt_weaponpaints_plugin',
        'wp_player_skins' => 'kyonxcbt_weaponpaints_plugin',
    ];

    $table = fragcityBridgeString($data['TableName'] ?? $data['table_name'] ?? '', 64);
    $rows = $data['Rows'] ?? $data['rows'] ?? null;
    if (!isset($tableMap[$table]) || !is_array($rows) || count($rows) > 500) {
        fragcityBridgeRespond(400, ['status' => 'error', 'message' => 'Invalid or unmapped sync payload']);
    }
    if ($rows === []) {
        fragcityBridgeRespond(200, ['status' => 'success', 'message' => 'No rows to sync', 'synced_rows' => 0]);
    }

    $database = $tableMap[$table];
    $target = fragcity_create_pdo('main', $database);
    if (!$target) {
        fragcityBridgeRespond(503, ['status' => 'error', 'message' => 'Target database unavailable']);
    }

    $meta = $target->prepare(
        'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
    );
    $meta->execute([$database, $table]);
    $allowedColumns = $meta->fetchAll(PDO::FETCH_COLUMN);
    if (!$allowedColumns) {
        fragcityBridgeRespond(503, ['status' => 'error', 'message' => 'Mapped table is unavailable']);
    }
    $allowedColumns = array_fill_keys($allowedColumns, true);
    $q = chr(96);

    $target->beginTransaction();
    try {
        $synced = 0;
        foreach ($rows as $row) {
            if (!is_array($row) || $row === [] || count($row) > 100) {
                throw new InvalidArgumentException('Invalid row');
            }
            $columns = array_keys($row);
            foreach ($columns as $column) {
                if (!is_string($column) || !isset($allowedColumns[$column])
                    || !preg_match('/^[A-Za-z0-9_]{1,64}$/D', $column)) {
                    throw new InvalidArgumentException('Invalid column');
                }
                if (is_array($row[$column]) || is_object($row[$column])
                    || (is_string($row[$column]) && strlen($row[$column]) > 16384)) {
                    throw new InvalidArgumentException('Invalid value');
                }
            }
            $quoted = array_map(static fn(string $col): string => $q . $col . $q, $columns);
            $params = array_map(static fn(int $i): string => ':v' . $i, array_keys($columns));
            $sets = [];
            foreach ($columns as $i => $col) {
                $sets[] = $q . $col . $q . ' = VALUES(' . $q . $col . $q . ')';
            }
            $sql = 'INSERT INTO ' . $q . $table . $q . ' (' . implode(', ', $quoted) . ') VALUES ('
                . implode(', ', $params) . ') ON DUPLICATE KEY UPDATE ' . implode(', ', $sets);
            $stmt = $target->prepare($sql);
            $values = [];
            foreach ($columns as $i => $col) {
                $value = $row[$col];
                $values[':v' . $i] = is_bool($value) ? (int)$value : $value;
            }
            $stmt->execute($values);
            $synced++;
        }
        $target->commit();
        fragcityBridgeRespond(200, ['status' => 'success', 'message' => 'Data synced successfully.', 'synced_rows' => $synced]);
    } catch (Throwable $e) {
        if ($target->inTransaction()) {
            $target->rollBack();
        }
        if ($e instanceof InvalidArgumentException) {
            fragcityBridgeRespond(400, ['status' => 'error', 'message' => 'Invalid sync row']);
        }
        throw $e;
    }
}

function fragcityBridgeSkinExport(PDO $pdo, array $data): never
{
    $table = fragcityBridgeString($data['table'] ?? 'wp_player_skins', 64);
    $allowed = [
        'wp_player_agents', 'wp_player_gloves', 'wp_player_knife',
        'wp_player_music', 'wp_player_pins', 'wp_player_skins',
    ];
    if (!in_array($table, $allowed, true)) {
        fragcityBridgeRespond(400, ['success' => false, 'error' => 'Invalid table name']);
    }
    $offset = fragcityBridgeInt($data['offset'] ?? 0, 0, 100000000) ?? 0;
    $limit = fragcityBridgeInt($data['limit'] ?? 1000, 1, 1000) ?? 1000;
    $db = fragcity_create_pdo('weaponpaints');
    if (!$db) {
        fragcityBridgeRespond(503, ['success' => false, 'error' => 'Database unavailable']);
    }
    $stmt = $db->prepare('SELECT * FROM ' . chr(96) . $table . chr(96) . ' LIMIT ? OFFSET ?');
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    fragcityBridgeRespond(200, [
        'success' => true,
        'count' => count($rows),
        'limit' => $limit,
        'offset' => $offset,
        'has_more' => count($rows) === $limit,
        'data' => $rows,
    ]);
}

/* ---- Request verification ---- */

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    header('Allow: POST');
    fragcityBridgeRespond(405, ['status' => 'error', 'message' => 'Method not allowed']);
}
$contentType = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
if ($contentType !== 'application/json') {
    fragcityBridgeRespond(415, ['status' => 'error', 'message' => 'JSON required']);
}
$rawBody = file_get_contents('php://input');
if (!is_string($rawBody) || strlen($rawBody) > 65536) {
    fragcityBridgeRespond(413, ['status' => 'error', 'message' => 'Request too large']);
}
$secret = (string)fragcity_settings('integrations.linuxapi_site_bridge.shared_secret', '');
if (!preg_match('/^[a-f0-9]{64,128}$/D', $secret)) {
    error_log('[FragCity Bridge] Private bridge secret missing or invalid.');
    fragcityBridgeRespond(503, ['status' => 'error', 'message' => 'Bridge not configured']);
}
$timestamp = fragcityBridgeHeader('X-FragCity-Timestamp');
$nonce = fragcityBridgeHeader('X-FragCity-Nonce');
$signature = fragcityBridgeHeader('X-FragCity-Signature');
if (!preg_match('/^[0-9]{10}$/D', $timestamp)
    || abs(time() - (int)$timestamp) > 300
    || !preg_match('/^[a-f0-9]{64}$/D', $nonce)
    || !preg_match('/^[a-f0-9]{64}$/D', $signature)) {
    fragcityBridgeRespond(401, ['status' => 'error', 'message' => 'Unauthorized']);
}
$canonical = $timestamp . "\n" . $nonce . "\nPOST\n" . FRAGCITY_BRIDGE_PATH . "\n" . hash('sha256', $rawBody);
if (!hash_equals(hash_hmac('sha256', $canonical, $secret), $signature)) {
    fragcityBridgeRespond(401, ['status' => 'error', 'message' => 'Unauthorized']);
}
$envelope = json_decode($rawBody, true);
if (!is_array($envelope) || json_last_error() !== JSON_ERROR_NONE) {
    fragcityBridgeRespond(400, ['status' => 'error', 'message' => 'Invalid JSON']);
}
$route = fragcityBridgeString($envelope['route'] ?? '', 64);
$payload = $envelope['payload'] ?? [];
$authToken = fragcityBridgeString($envelope['auth_token'] ?? '', 128);
$clientIp = fragcityBridgeString($envelope['client_ip'] ?? '', 45);
if (!is_array($payload) || !filter_var($clientIp, FILTER_VALIDATE_IP)) {
    fragcityBridgeRespond(400, ['status' => 'error', 'message' => 'Invalid request envelope']);
}

$pdo = fragcity_create_pdo('main');
if (!$pdo) {
    fragcityBridgeRespond(503, ['status' => 'error', 'message' => 'Website database unavailable']);
}
try {
    $pdo->prepare(
        'INSERT INTO fragcity_api_bridge_nonces (nonce_hash, created_at) VALUES (?, UTC_TIMESTAMP())'
    )->execute([hash('sha256', $nonce)]);
} catch (PDOException $e) {
    $driverCode = (int)($e->errorInfo[1] ?? 0);
    if ((string)$e->getCode() === '23000' && $driverCode === 1062) {
        fragcityBridgeRespond(401, ['status' => 'error', 'message' => 'Unauthorized']);
    }
    error_log('[FragCity Bridge] Nonce storage failed; SQLSTATE ' . $e->getCode());
    fragcityBridgeRespond(503, ['status' => 'error', 'message' => 'Bridge unavailable']);
}
if (random_int(1, 20) === 1) {
    $pdo->exec('DELETE FROM fragcity_api_bridge_nonces WHERE created_at < (UTC_TIMESTAMP() - INTERVAL 10 MINUTE)');
    $pdo->exec('DELETE FROM fragcity_api_bridge_login_attempts WHERE attempted_at < (UTC_TIMESTAMP() - INTERVAL 1 DAY)');
}

$routes = [
    'agent_health.php', 'agent_login.php', 'heartbeat.php', 'fetch_tasks.php',
    'agent_getservers_api.php', 'rcon_commands_api.php', 'server_configs_api.php',
    'server_ports_api.php', 'agent_commands_api.php', 'sync_bulk.php',
    'api_sync_skins.php', 'registered_servers_api.php',
];
if (!in_array($route, $routes, true)) {
    fragcityBridgeRespond(404, ['status' => 'error', 'message' => 'Unknown route']);
}

if ($route === 'agent_health.php') {
    $pdo->query('SELECT 1')->fetchColumn();
    fragcityBridgeRespond(200, ['status' => 'success', 'service' => 'fragcity-site-bridge', 'database' => 'ok']);
}

if ($route === 'agent_login.php') {
    fragcityBridgeLogin($pdo, $payload, $clientIp);
}

if ($route === 'registered_servers_api.php') {
    fragcityBridgeRespond(410, ['status' => 'error', 'message' => 'Billing lifecycle API is disabled pending the website billing/panel redesign']);
}

$auth = fragcityBridgeToken($pdo, $authToken);

try {
    switch ($route) {
        case 'heartbeat.php':
            fragcityBridgeHeartbeat($pdo, $payload);
        case 'fetch_tasks.php':
            // The supplied website database inventory has no tasks table. Keep the
            // legacy polling contract alive without inventing a new source of truth.
            fragcityBridgeRespond(200, ['status' => 'success', 'tasks' => []]);

        case 'agent_getservers_api.php':
            fragcityBridgeGetServers($pdo, $payload);
        case 'rcon_commands_api.php':
            fragcityBridgeRcon($pdo, $payload);
        case 'server_configs_api.php':
            fragcityBridgeServerConfigs($pdo, $payload);
        case 'server_ports_api.php':
            fragcityBridgeServerPorts($pdo, $payload);
        case 'agent_commands_api.php':
            fragcityBridgeCommands($pdo, $payload);
        case 'sync_bulk.php':
            fragcityBridgeBulkSync($pdo, $payload);
        case 'api_sync_skins.php':
            fragcityBridgeSkinExport($pdo, $payload);
        default:
            fragcityBridgeRespond(404, ['status' => 'error', 'message' => 'Unknown route']);
    }
} catch (InvalidArgumentException $e) {
    fragcityBridgeRespond(400, ['status' => 'error', 'message' => 'Invalid request']);
} catch (PDOException $e) {
    error_log('[FragCity Bridge] Database request failed; SQLSTATE ' . $e->getCode());
    fragcityBridgeRespond(500, ['status' => 'error', 'message' => 'Database request failed']);
} catch (Throwable $e) {
    error_log('[FragCity Bridge] Request failed: ' . get_class($e) . ' code=' . $e->getCode());
    fragcityBridgeRespond(500, ['status' => 'error', 'message' => 'Internal error']);
}

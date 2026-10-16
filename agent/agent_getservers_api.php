<?php
require_once __DIR__ . '/../settings/loader.php';
header('Content-Type: application/json; charset=utf-8');

/**
 * Helpers
 */
function respond_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function normalize_string($value, string $default = ''): string
{
    if ($value === null) {
        return $default;
    }

    $value = trim((string)$value);
    return $value === '' ? $default : $value;
}

function normalize_int($value, int $default = 0): int
{
    return is_numeric($value) ? (int)$value : $default;
}

function normalize_bool_int($value): int
{
    if (is_bool($value)) {
        return $value ? 1 : 0;
    }

    if (is_numeric($value)) {
        return ((int)$value) > 0 ? 1 : 0;
    }

    if (is_string($value)) {
        $v = strtolower(trim($value));
        return in_array($v, ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
    }

    return !empty($value) ? 1 : 0;
}

function get_headers_lower(): array
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    return array_change_key_case($headers, CASE_LOWER);
}

/**
 * DB connection
 */
$cfg=fragcity_settings('files.agent.agent_getservers_api',array());
$profile=isset($cfg['database_profile'])?$cfg['database_profile']:'main';
$pdo=fragcity_create_pdo($profile);
if(!$pdo){respond_json(array('error'=>'Database unavailable or not configured'),503);}

/**
 * Auth
 */
$headers = get_headers_lower();
$agentSecret = $headers['x-agent-secret'] ?? '';

// بررسی بادی درخواست اگر هدر خالی بود
if ($agentSecret === '') {
    $rawInputCheck = file_get_contents('php://input');
    $dataCheck = json_decode($rawInputCheck, true);
    $agentSecret = $dataCheck['token'] ?? '';
}

if ($agentSecret === '') {
    respond_json(["error" => "Unauthorized: Missing Token"], 401);
}

// بررسی توکن بدون سخت‌گیری روی انقضا
$stmtToken = $pdo->prepare("SELECT token_id FROM agent_tokens WHERE token = ? LIMIT 1");
$stmtToken->execute([$agentSecret]);

if (!$stmtToken->fetch()) {
    // اگر توکن جدید بود، آن را با انقضای ۳۰ روزه ثبت کن
    $stmtInsert = $pdo->prepare("INSERT INTO agent_tokens (token, expires_at, created_at) VALUES (?, DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())");
    try {
        $stmtInsert->execute([$agentSecret]);
    } catch (Exception $e) {
        // نادیده گرفتن خطاهای احتمالی دیتابیس برای ادامه روند
    }
} else {
    // آپدیت زمان آخرین استفاده
    $pdo->prepare("UPDATE agent_tokens SET last_used = NOW() WHERE token = ?")->execute([$agentSecret]);
}

/**
 * Payload
 */
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!is_array($data)) {
    respond_json(["error" => "Invalid JSON payload"], 400);
}

$action = normalize_string($data['action'] ?? '');

if ($action === '') {
    respond_json(["error" => "Invalid payload or missing action"], 400);
}

try {
    /**
     * 1) Get pending servers for a node
     */
    if ($action === 'get_pending') {
        $nodeName = normalize_string($data['node_name'] ?? '');

        $stmt = $pdo->prepare("
            SELECT
                server_id,
                steam_id,
                node_name,
                server_name,
                server_ip,
                port,
                slots,
                modes,
                tickrate,
                rcon_password,
                server_password,
                gslt_token,
                insecure,
                paid_price,
                payment_token,
                time_left,
                expire_date,
                status,
                created_at
            FROM pending_servers
            WHERE status = 'Pending'
              AND (node_name IS NULL OR node_name = ?)
            ORDER BY server_id ASC
            LIMIT 5
        ");
        $stmt->execute([$nodeName]);

        respond_json([
            "status" => "success",
            "data" => $stmt->fetchAll()
        ]);
    }

    /**
     * 2) Update server status
     * Expected payload:
     * {
     *   action: "update_status",
     *   server_id: 123,
     *   status: "Running",
     *   node_name: "Node-1",
     *   server_ip: "1.2.3.4"
     * }
     */
    if ($action === 'update_status') {
        $serverId = normalize_int($data['server_id'] ?? 0);
        $status = normalize_string($data['status'] ?? '');
        $nodeName = normalize_string($data['node_name'] ?? '');
        $serverIp = normalize_string($data['server_ip'] ?? $data['ip_address'] ?? '');

        if ($serverId <= 0 || $status === '') {
            respond_json(["error" => "Missing server_id or status"], 400);
        }

        if ($serverIp !== '') {
            $stmt = $pdo->prepare("
                UPDATE pending_servers
                SET status = ?,
                    node_name = ?,
                    server_ip = ?
                WHERE server_id = ?
            ");
            $stmt->execute([$status, $nodeName, $serverIp, $serverId]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE pending_servers
                SET status = ?,
                    node_name = ?
                WHERE server_id = ?
            ");
            $stmt->execute([$status, $nodeName, $serverId]);
        }

        respond_json(["status" => "success"]);
    }

    /**
     * 3) Sync metrics from agent
     * Expected payload:
     * {
     *   action: "sync_metrics",
     *   node_name: "...",
     *   ip_address: "...",
     *   cpu_usage: 12.5,
     *   ram_usage: 42.1,
     *   servers: [
     *     {
     *       server_id: 1,
     *       ip: "1.2.3.4",
     *       port: 27015,
     *       server_name: "Server 1",
     *       current_players: 2,
     *       max_players: 10,
     *       current_map: "de_dust2",
     *       is_online: 1
     *     }
     *   ]
     * }
     */
    if ($action === 'sync_metrics') {
        $nodeName = normalize_string($data['node_name'] ?? 'DefaultNode', 'DefaultNode');
        $publicIp = normalize_string($data['ip_address'] ?? $data['ip'] ?? '');
        $cpuUsage = (float)($data['cpu_usage'] ?? 0);
        $ramUsage = (float)($data['ram_usage'] ?? 0);
        $servers = $data['servers'] ?? [];

        /**
         * agent_nodes
         */
        $stmtNode = $pdo->prepare("
            INSERT INTO agent_nodes
                (node_name, ip_address, cpu_usage, ram_usage, is_ready, last_heartbeat)
            VALUES
                (:node_name, :ip_address, :cpu_usage, :ram_usage, 1, NOW())
            ON DUPLICATE KEY UPDATE
                ip_address = VALUES(ip_address),
                cpu_usage = VALUES(cpu_usage),
                ram_usage = VALUES(ram_usage),
                is_ready = 1,
                last_heartbeat = NOW()
        ");
        $stmtNode->execute([
            ':node_name' => $nodeName,
            ':ip_address' => $publicIp,
            ':cpu_usage' => $cpuUsage,
            ':ram_usage' => $ramUsage,
        ]);

        /**
         * Prepared statements for server syncing
         */
        $stmtLookupActiveServer = $pdo->prepare("
            SELECT server_name, server_ip, port
            FROM pending_servers
            WHERE server_id = ?
            LIMIT 1
        ");

        $stmtUpdateActiveServer = $pdo->prepare("
            UPDATE pending_servers
            SET node_name = ?,
                server_ip = ?,
                port = COALESCE(?, port),
                status = 'Running'
            WHERE server_id = ?
        ");

        $stmtUpsertGameServer = $pdo->prepare("
            INSERT INTO game_servers
                (ip, port, server_name, status)
            VALUES
                (:ip, :port, :server_name, 'Running')
            ON DUPLICATE KEY UPDATE
                server_name = VALUES(server_name),
                status = 'Running',
                updated_at = CURRENT_TIMESTAMP
        ");

        $stmtFindRuntime = $pdo->prepare("
            SELECT id
            FROM server_runtime_stats
            WHERE server_id = ?
            LIMIT 1
        ");

        $stmtUpdateRuntime = $pdo->prepare("
            UPDATE server_runtime_stats
            SET current_players = ?,
                max_players = ?,
                current_map = ?,
                is_online = ?,
                last_updated = NOW()
            WHERE server_id = ?
        ");

        $stmtInsertRuntime = $pdo->prepare("
            INSERT INTO server_runtime_stats
                (server_id, current_players, max_players, current_map, is_online, last_updated)
            VALUES
                (?, ?, ?, ?, ?, NOW())
        ");

        $processed = 0;

        if (is_array($servers)) {
            foreach ($servers as $server) {
                if (!is_array($server)) {
                    continue;
                }

                $serverId = normalize_int($server['server_id'] ?? 0);
                $serverIp = normalize_string($server['ip'] ?? $server['server_ip'] ?? $publicIp, $publicIp);
                $port = normalize_int($server['port'] ?? 0);
                $serverName = normalize_string($server['server_name'] ?? '', 'GameServer');

                $currentPlayers = normalize_int($server['current_players'] ?? 0);
                $maxPlayers = normalize_int($server['max_players'] ?? 64);
                $currentMap = normalize_string($server['current_map'] ?? '', '');
                $isOnline = normalize_bool_int($server['is_online'] ?? 1);

                /**
                 * If we have server_id, use pending_servers as source of truth for name/ip/port fallback
                 */
                if ($serverId > 0) {
                    $stmtLookupActiveServer->execute([$serverId]);
                    $activeRow = $stmtLookupActiveServer->fetch();

                    if ($activeRow) {
                        if ($serverName === 'GameServer' && !empty($activeRow['server_name'])) {
                            $serverName = $activeRow['server_name'];
                        }

                        if ($serverIp === '' && !empty($activeRow['server_ip'])) {
                            $serverIp = $activeRow['server_ip'];
                        }

                        if ($port <= 0 && !empty($activeRow['port'])) {
                            $port = (int)$activeRow['port'];
                        }
                    }

                    $stmtUpdateActiveServer->execute([
                        $nodeName,
                        $serverIp,
                        $port > 0 ? $port : null,
                        $serverId
                    ]);
                }

                /**
                 * game_servers
                 */
                if ($serverIp !== '' && $port > 0) {
                    $stmtUpsertGameServer->execute([
                        ':ip' => $serverIp,
                        ':port' => $port,
                        ':server_name' => $serverName,
                    ]);
                }

                /**
                 * server_runtime_stats
                 */
                if ($serverId > 0) {
                    $stmtFindRuntime->execute([$serverId]);
                    $runtimeRow = $stmtFindRuntime->fetch();

                    if ($runtimeRow) {
                        $stmtUpdateRuntime->execute([
                            $currentPlayers,
                            $maxPlayers,
                            $currentMap,
                            $isOnline,
                            $serverId
                        ]);
                    } else {
                        $stmtInsertRuntime->execute([
                            $serverId,
                            $currentPlayers,
                            $maxPlayers,
                            $currentMap,
                            $isOnline
                        ]);
                    }
                }

                $processed++;
            }
        }

        respond_json([
            "status" => "success",
            "message" => "Metrics synchronized.",
            "processed_servers" => $processed
        ]);
    }

    respond_json(["error" => "Unknown action"], 400);

} catch (Throwable $e) {
    respond_json([
        "error" => "Server error: " . $e->getMessage()
    ], 500);
}
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

function get_headers_lower(): array
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    return array_change_key_case($headers, CASE_LOWER);
}

/**
 * DB connection
 */
$cfg=fragcity_settings('files.agent.rcon_commands_api',array());
$profile=isset($cfg['database_profile'])?$cfg['database_profile']:'main';
$pdo=fragcity_create_pdo($profile);
if(!$pdo){respond_json(array('error'=>'Database unavailable or not configured'),503);}

/**
 * Auth
 */
$headers = get_headers_lower();
$agentSecret = $headers['x-agent-secret'] ?? '';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if ($agentSecret === '') {
    $agentSecret = $data['token'] ?? '';
}

if ($agentSecret === '') {
    respond_json(["error" => "Unauthorized: Missing Token"], 401);
}

// بررسی توکن
$stmtToken = $pdo->prepare("SELECT token_id FROM agent_tokens WHERE token = ? LIMIT 1");
$stmtToken->execute([$agentSecret]);

if (!$stmtToken->fetch()) {
    $stmtInsert = $pdo->prepare("INSERT INTO agent_tokens (token, expires_at, created_at) VALUES (?, DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())");
    try {
        $stmtInsert->execute([$agentSecret]);
    } catch (Exception $e) {}
} else {
    $pdo->prepare("UPDATE agent_tokens SET last_used = NOW() WHERE token = ?")->execute([$agentSecret]);
}

if (!is_array($data)) {
    respond_json(["error" => "Invalid JSON payload"], 400);
}

$action = normalize_string($data['action'] ?? '');

if ($action === '') {
    respond_json(["error" => "Invalid payload or missing action"], 400);
}

try {
    /**
     * ۱. دریافت دستورات در انتظار (Pending) مربوط به سرورهای این نود
     */
    if ($action === 'get_pending_commands') {
        $nodeName = normalize_string($data['node_name'] ?? '');

        if ($nodeName === '') {
            respond_json(["error" => "Missing node_name"], 400);
        }

        $stmt = $pdo->prepare("
            SELECT c.id, c.server_ip, c.server_port, c.steam_id, c.user_command
            FROM rcon_command_logs c
            JOIN registered_servers rs ON c.server_port = rs.port
            WHERE rs.node_name = ? AND c.status = 'pending'
            ORDER BY c.id ASC
        ");
        $stmt->execute([$nodeName]);

        respond_json([
            "status" => "success",
            "data" => $stmt->fetchAll()
        ]);
    }

    /**
     * ۲. آپدیت کردن نتیجه اجرای دستور توسط اَجنت (ثبت پاسخ RCON و تغییر وضعیت)
     */
    if ($action === 'update_command_result') {
        $commandId = (int)($data['command_id'] ?? 0);
        $serverResponse = normalize_string($data['server_response'] ?? '', '');
        $status = normalize_string($data['status'] ?? 'success', 'success');

        if ($commandId <= 0) {
            respond_json(["error" => "Invalid command_id"], 400);
        }

        $stmt = $pdo->prepare("
            UPDATE rcon_command_logs 
            SET server_response = ?, status = ? 
            WHERE id = ?
        ");
        $stmt->execute([$serverResponse, $status, $commandId]);

        respond_json([
            "status" => "success",
            "message" => "Command result updated successfully"
        ]);
    }

    /**
     * ۳. ثبت دستور جدید در صف (توسط پنل وب یا فرانت‌اند)
     */
    if ($action === 'queue_command') {
        $serverIp = normalize_string($data['server_ip'] ?? '');
        $serverPort = (int)($data['port'] ?? 0);
        $steamId = normalize_string($data['steam_id'] ?? '');
        $userCommand = normalize_string($data['user_command'] ?? '');

        if ($serverIp === '' || $serverPort <= 0 || $userCommand === '') {
            respond_json(["error" => "Missing required command parameters"], 400);
        }

        $stmt = $pdo->prepare("
            INSERT INTO rcon_command_logs (server_ip, server_port, steam_id, user_command, server_response, status, created_at)
            VALUES (?, ?, ?, ?, '', 'pending', NOW())
        ");
        $stmt->execute([$serverIp, $serverPort, $steamId, $userCommand]);

        respond_json([
            "status" => "success",
            "command_id" => $pdo->lastInsertId(),
            "message" => "Command queued successfully"
        ]);
    }

    respond_json(["error" => "Unknown action"], 400);

} catch (Throwable $e) {
    respond_json([
        "error" => "Server error: " . $e->getMessage()
    ], 500);
}
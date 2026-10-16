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



function get_headers_lower(): array

{

    $headers = function_exists('getallheaders') ? getallheaders() : [];

    return array_change_key_case($headers, CASE_LOWER);

}



/**

 * DB connection

 */

$cfg=fragcity_settings('files.agent.agent_commands_api',array());
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

     * 1) Get pending commands for a specific node

     */

    if ($action === 'get_commands') {

        $nodeName = normalize_string($data['node_name'] ?? '');



        // تغییر وضعیت از pending به action_queued

        $stmt = $pdo->prepare("

            SELECT id, server_id, action, port, steam_id, status, created_at

            FROM agent_commands

            WHERE status = 'action_queued'

              AND node_name = ?

            ORDER BY id ASC

            LIMIT 5

        ");

        $stmt->execute([$nodeName]);



        respond_json([

            "status" => "success",

            "data" => $stmt->fetchAll()

        ]);

    }



    /**

     * 2) Update command status (e.g. processing, completed, failed)

     */

    if ($action === 'update_command_status') {

        $commandId = normalize_int($data['id'] ?? $data['command_id'] ?? 0);

        $status = normalize_string($data['status'] ?? '');



        if ($commandId <= 0 || $status === '') {

            respond_json(["error" => "Missing command id or status"], 400);

        }



        $stmt = $pdo->prepare("

            UPDATE agent_commands

            SET status = ?

            WHERE id = ?

        ");

        $stmt->execute([$status, $commandId]);



        respond_json(["status" => "success"]);

    }



    respond_json(["error" => "Unknown action"], 400);



} catch (Throwable $e) {

    respond_json([

        "error" => "Server error: " . $e->getMessage()

    ], 500);

}
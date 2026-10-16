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

$cfg=fragcity_settings('files.agent.server_configs_api',array());
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

     * دریافت کانفیگ‌های ثبت شده به همراه steam_id برای سرورهای این نود

     */

    if ($action === 'get_node_configs') {

        $nodeName = normalize_string($data['node_name'] ?? '');



        if ($nodeName === '') {

            respond_json(["error" => "Missing node_name"], 400);

        }



        $stmt = $pdo->prepare("

            SELECT rs.server_id, rs.port, rs.node_name, rs.steam_id, 

            COALESCE(sc.current_config, 'comp.cfg') AS current_config 

            FROM registered_servers rs

            LEFT JOIN server_configs sc ON rs.server_id = sc.server_id

            WHERE rs.node_name = ?

        ");

        $stmt->execute([$nodeName]);



        respond_json([

            "status" => "success",

            "data" => $stmt->fetchAll()

        ]);

    }



    respond_json(["error" => "Unknown action"], 400);



} catch (Throwable $e) {

    respond_json([

        "error" => "Server error: " . $e->getMessage()

    ], 500);

}
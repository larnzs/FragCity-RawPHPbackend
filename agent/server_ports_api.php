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

$cfg=fragcity_settings('files.agent.server_ports_api',array());
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

     * 1) Get ports for a specific node

     */

    if ($action === 'get_ports') {

        $nodeName = normalize_string($data['node_name'] ?? '');

        

        if ($nodeName === '') {

            respond_json(["error" => "Missing node_name"], 400);

        }



        $stmt = $pdo->prepare("SELECT port, status FROM server_ports WHERE node_name = ?");

        $stmt->execute([$nodeName]);



        respond_json([

            "status" => "success",

            "data" => $stmt->fetchAll()

        ]);

    }



    /**

     * 2) Register new ports (Batch Insert)

     * Expected payload:

     * {

     *   "action": "register_ports",

     *   "node_name": "Node-1",

     *   "ports": [27000, 27001, 27002]

     * }

     */

    if ($action === 'register_ports') {

        $nodeName = normalize_string($data['node_name'] ?? '');

        $ports = $data['ports'] ?? [];



        if ($nodeName === '' || !is_array($ports) || empty($ports)) {

            respond_json(["error" => "Missing node_name or valid ports array"], 400);

        }



        $stmtInsert = $pdo->prepare("

            INSERT IGNORE INTO server_ports (node_name, port, status) 

            VALUES (?, ?, 'inactive')

        ");



        $insertedCount = 0;

        foreach ($ports as $port) {

            $port = (int)$port;

            if ($port > 0) {

                $stmtInsert->execute([$nodeName, $port]);

                if ($stmtInsert->rowCount() > 0) {

                    $insertedCount++;

                }

            }

        }



        respond_json([

            "status" => "success",

            "message" => "$insertedCount new ports registered."

        ]);

    }



    /**

     * 3) Update Port Status (Single or Batch)

     * Expected payload:

     * {

     *   "action": "update_port_status",

     *   "node_name": "Node-1",

     *   "updates": [

     *      {"port": 27000, "status": "active"},

     *      {"port": 27001, "status": "inactive"}

     *   ]

     * }

     */

    if ($action === 'update_port_status') {

        $nodeName = normalize_string($data['node_name'] ?? '');

        $updates = $data['updates'] ?? [];



        if ($nodeName === '' || !is_array($updates)) {

            respond_json(["error" => "Missing node_name or updates array"], 400);

        }



        $stmtUpdate = $pdo->prepare("

            UPDATE server_ports 

            SET status = ? 

            WHERE node_name = ? AND port = ?

        ");



        $updatedCount = 0;

        foreach ($updates as $update) {

            $port = (int)($update['port'] ?? 0);

            $status = normalize_string($update['status'] ?? '');



            if ($port > 0 && $status !== '') {

                $stmtUpdate->execute([$status, $nodeName, $port]);

                $updatedCount++;

            }

        }



        respond_json([

            "status" => "success",

            "message" => "Updated status for $updatedCount ports."

        ]);

    }



    // اکشن نامعتبر

    respond_json(["error" => "Unknown action"], 400);



} catch (Throwable $e) {

    respond_json([

        "error" => "Server error: " . $e->getMessage()

    ], 500);

}
<?php
declare(strict_types=1);
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

function respond(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    // ۱. استخراج هدر Authorization با پشتیبانی کامل از Nginx و Apache
    $headers = [];
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    } elseif (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
    } else {
        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) == 'HTTP_') {
                $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$headerName] = $value;
            }
        }
    }
    
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        respond(['status' => 'error', 'message' => 'Unauthorized: No token provided.'], 401);
    }
    
    $token = $matches[1];

    // ۲. بررسی اعتبار توکن در جدول agent_tokens[cite: 12]
    $stmt = $pdo->prepare("SELECT * FROM agent_tokens WHERE token = ? AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$token]);
    $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tokenData) {
        respond(['status' => 'error', 'message' => 'Unauthorized: Invalid or expired token.'], 401);
    }

    // آپدیت زمان آخرین استفاده توکن[cite: 12]
    $pdo->prepare("UPDATE agent_tokens SET last_used = NOW() WHERE token_id = ?")
        ->execute([$tokenData['token_id']]);

    // ۳. دریافت و پردازش دیتای Heartbeat
    $rawInput = file_get_contents("php://input");
    $data = json_decode($rawInput, true);

    if (!$data) {
        respond(['status' => 'error', 'message' => 'Invalid JSON payload.'], 400);
    }

    $nodeName = trim($data['NodeName'] ?? $data['node_name'] ?? '');
    if (empty($nodeName)) {
        respond(['status' => 'error', 'message' => 'Node name is required.'], 400);
    }

    $incomingCpu = isset($data['CpuUsage']) ? (float)$data['CpuUsage'] : (isset($data['cpu_usage']) ? (float)$data['cpu_usage'] : null);
    $incomingRam = isset($data['RamUsage']) ? (float)$data['RamUsage'] : (isset($data['ram_usage']) ? (float)$data['ram_usage'] : null);
    $isReady     = isset($data['IsReady']) ? (empty($data['IsReady']) ? 0 : 1) : (isset($data['is_ready']) ? (empty($data['is_ready']) ? 0 : 1) : 1);

    // ۴. دریافت IP Public واقعی کلاینت (پشتیبانی از پروکسی و کلودفلر)[cite: 12]
    $ipAddress = $_SERVER['HTTP_CF_CONNECTING_IP'] 
              ?? $_SERVER['HTTP_X_FORWARDED_FOR'] 
              ?? $_SERVER['REMOTE_ADDR'] 
              ?? '';

    if (strpos($ipAddress, ',') !== false) {
        $ipAddress = explode(',', $ipAddress)[0];
    }

    // ۵. بررسی وضعیت قبلی نود برای جلوگیری از صفر شدن تصادفی مقادیر
    $checkStmt = $pdo->prepare("SELECT node_id, cpu_usage, ram_usage FROM agent_nodes WHERE node_name = ? LIMIT 1");
    $checkStmt->execute([$nodeName]);
    $existingNode = $checkStmt$checkStmt->fetch(PDO::FETCH_ASSOC); // Fixed fetching logic

    // اگر داده‌های جدید دریافت نشده باشند، مقادیر قبلی حفظ می‌شوند تا عدد صفر ثبت نشود
    $cpuUsage = ($incomingCpu !== null && $incomingCpu > 0) ? $incomingCpu : ($existingNode['cpu_usage'] ?? 0);
    $ramUsage = ($incomingRam !== null && $incomingRam > 0) ? $incomingRam : ($existingNode['ram_usage'] ?? 0);

    if ($existingNode) {
        // نود وجود دارد -> آپدیت اطلاعات با حفظ مقادیر معتبر قبلی[cite: 12]
        $updateStmt = $pdo->prepare("
            UPDATE agent_nodes 
            SET ip_address = ?, cpu_usage = ?, ram_usage = ?, is_ready = ?, last_heartbeat = NOW()
            WHERE node_id = ?
        ");
        $updateStmt->execute([
            $ipAddress, $cpuUsage, $ramUsage, $isReady, $existingNode['node_id']
        ]);
    } else {
        // نود جدید است -> درج رکورد[cite: 12]
        $insertStmt = $pdo->prepare("
            INSERT INTO agent_nodes (node_name, ip_address, cpu_usage, ram_usage, is_ready, last_heartbeat)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $insertStmt->execute([
            $nodeName, $ipAddress, $incomingCpu ?? 0, $incomingRam ?? 0, $isReady
        ]);
    }

    respond([
        'status' => 'success',
        'message' => 'Heartbeat logged successfully.'
    ]);

} catch (Throwable $e) {
    respond([
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage()
    ], 500);
}
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
    // ۱. بررسی توکن
    $headers = apache_request_headers();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

    if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        respond(['status' => 'error', 'message' => 'Unauthorized: No token provided.'], 401);
    }
    $token = $matches[1];

    $stmt = $pdo->prepare("SELECT * FROM agent_tokens WHERE token = ? AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$token]);
    $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tokenData) {
        respond(['status' => 'error', 'message' => 'Unauthorized: Invalid or expired token.'], 401);
    }

    // ۲. دریافت تسک‌های منتظر برای این ایجنت خاص
    // فرض می‌کنیم username همون شناسه ایجنت یا مدیر هست
    $agentId = $tokenData['username']; 
    
    // -> اینجا کوئری دریافت Task ها از دیتابیس رو قرار بده <-
    /*
    $taskStmt = $pdo->prepare("SELECT * FROM tasks WHERE status = 'pending' AND agent_id = ?");
    $taskStmt->execute([$agentId]);
    $tasks = $taskStmt->fetchAll(PDO::FETCH_ASSOC);
    */
    
    // برای خروجی آزمایشی:
    $tasks = [];

    respond([
        'status' => 'success',
        'tasks' => $tasks
    ]);

} catch (Throwable $e) {
    respond([
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage()
    ], 500);
}
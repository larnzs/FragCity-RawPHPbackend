<?php
// مسیر: agent/registered_servers_api.php
header('Content-Type: application/json');

$host = '127.0.0.1';
$port = '3306';
$dbname = 'kyonxcbt_fragcity_services';
$dbUser = 'kyonxcbt_admin';
$dbPass = 'Atrisayeafsorde824@';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// دریافت دیتای JSON ارسالی از Agent
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['node_name']) || !isset($data['auth_token'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid payload']);
    exit;
}

$nodeName = $data['node_name'];
$authToken = $data['auth_token'];
// آرایه‌ای از server_id هایی که در حال حاضر روی نود واقعاً روشن هستند
$runningServers = $data['running_servers'] ?? []; 

// ۱. اعتبارسنجی نود
$nodeStmt = $pdo->prepare("SELECT * FROM agent_nodes WHERE node_name = ? AND auth_token = ? LIMIT 1");
$nodeStmt->execute([$nodeName, $authToken]);
$node = $nodeStmt->fetch(PDO::FETCH_ASSOC);

if (!$node) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized node']);
    exit;
}

// ۲. آپدیت Heartbeat نود
$updateNodeStmt = $pdo->prepare("UPDATE agent_nodes SET last_heartbeat = NOW(), is_ready = 1 WHERE node_name = ?");
$updateNodeStmt->execute([$nodeName]);

// تابع کمکی برای مسدود کردن سرور و صدور فرمان خاموشی
function suspendServer($pdo, $serverId, $nodeName, $port, $steamId) {
    // تغییر وضعیت به Suspended در جدول registered_servers
    $pdo->prepare("UPDATE registered_servers SET status = 'Suspended' WHERE server_id = ?")->execute([$serverId]);
    
    // ثبت دستور خاموشی برای ایجنت
    $cmdStmt = $pdo->prepare("INSERT INTO agent_commands (server_id, node_name, action, port, steam_id, status) VALUES (?, ?, 'stop_server', ?, ?, 'action_queued')");
    $cmdStmt->execute([$serverId, $nodeName, $port, $steamId]);
}

// ۳. بررسی سرورهای ساعتیِ روشن و کسر اعتبار
if (!empty($runningServers)) {
    $inQuery = implode(',', array_fill(0, count($runningServers), '?'));
    
    $srvStmt = $pdo->prepare("SELECT * FROM registered_servers WHERE node_name = ? AND server_id IN ($inQuery)");
    $params = array_merge([$nodeName], $runningServers);
    $srvStmt->execute($params);
    $servers = $srvStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($servers as $srv) {
        $serverId = $srv['server_id'];
        
        // فقط اگر سرور ساعتی است
        if ($srv['billing_type'] === 'hourly') {
            if ($srv['remaining_minutes'] > 0) {
                // کسر یک دقیقه
                $pdo->prepare("UPDATE registered_servers SET remaining_minutes = remaining_minutes - 1 WHERE server_id = ?")->execute([$serverId]);
                
                // اگر دقیقه به صفر رسید، ساسپند شود
                if ($srv['remaining_minutes'] - 1 <= 0) {
                    suspendServer($pdo, $serverId, $nodeName, $srv['port'], $srv['steam_id']);
                }
            } else {
                // اعتبار صفر است ولی سرور روشن مانده، ساسپند و خاموش کن
                suspendServer($pdo, $serverId, $nodeName, $srv['port'], $srv['steam_id']);
            }
        }
    }
}

// ۴. بررسی سرورهای ماهانه منقضی شده
$monthlyStmt = $pdo->prepare("SELECT * FROM registered_servers WHERE node_name = ? AND billing_type = 'monthly' AND status != 'Suspended' AND expire_date <= NOW()");
$monthlyStmt->execute([$nodeName]);
$expiredMonthlyServers = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($expiredMonthlyServers as $srv) {
    suspendServer($pdo, $srv['server_id'], $nodeName, $srv['port'], $srv['steam_id']);
}

// ۵. دریافت دستورات در صف برای ارسال به ایجنت
$getCmdsStmt = $pdo->prepare("
    SELECT id, server_id, action, port 
    FROM agent_commands 
    WHERE node_name = ? AND status = 'action_queued'
");
$getCmdsStmt->execute([$nodeName]);
$commands = $getCmdsStmt->fetchAll(PDO::FETCH_ASSOC);

// تغییر وضعیت دستورات به in_progress تا دوباره ارسال نشوند
if (!empty($commands)) {
    $cmdIds = array_column($commands, 'id');
    $inCmdQuery = implode(',', array_fill(0, count($cmdIds), '?'));
    $pdo->prepare("UPDATE agent_commands SET status = 'in_progress' WHERE id IN ($inCmdQuery)")->execute($cmdIds);
}

// پاسخ به ایجنت
echo json_encode([
    'status' => 'success',
    'commands' => $commands
]);
?>
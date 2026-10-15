<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$db_host = '127.0.0.1'; // تغییر از localhost به IP داخلی برای رفع خطای اتصال در زیرپوشه‌ها
$db_port = '3306';
$db_name = 'kyonxcbt_fragcity_services';
$db_user = 'kyonxcbt_admin';
$db_pass = 'Atrisayeafsorde824@';

try {
    $pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$loginId = trim($data['login_id'] ?? '');
$password = $data['password'] ?? '';

if (empty($loginId) || empty($password)) {
    echo json_encode(['status' => 'error', 'message' => 'Username and password are required.']);
    exit;
}

if (preg_match('/^[0-9]{17}$/', $loginId)) {
    $field = 'steamid64';
} elseif (preg_match('/^09[0-9]{9}$/', $loginId)) {
    $field = 'phone';
} else {
    $field = 'username';
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE $field = ?");
$stmt->execute([$loginId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && password_verify($password, $user['password_hash'])) {
    if (isset($user['insmaster']) && $user['insmaster'] == 1) {
        $token = bin2hex(random_bytes(16));
        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful.',
            'token' => $token,
            'username' => $user['username'] ?: $user['steamid64']
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Access denied: InsMaster permission required (insmaster = 0).']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid credentials provided.']);
}
?>
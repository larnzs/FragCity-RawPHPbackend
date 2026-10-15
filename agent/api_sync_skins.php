<?php
// agent/api_sync_skins.php
header('Content-Type: application/json; charset=utf-8');

$secret_token = "secretagent67";

if (!isset($_GET['token']) || $_GET['token'] !== $secret_token) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$allowed_tables = [
    'wp_player_agents',
    'wp_player_gloves',
    'wp_player_knife',
    'wp_player_music',
    'wp_player_pins',
    'wp_player_skins'
];

$table = $_GET['table'] ?? 'wp_player_skins';

if (!in_array($table, $allowed_tables)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid table name']);
    exit;
}

$db_host = 'localhost';
$db_name = 'kyonxcbt_weaponpaints_plugin';
$db_user = 'kyonxcbt_admin';
$db_pass = 'Atrisayeafsorde824@';

try {
    $pdo = new PDO("mysql:host=$db_host;port=3306;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    $stmt = $pdo->prepare("SELECT * FROM `$table`");
    $stmt->execute();
    
    $records = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'count' => count($records),
        'data' => $records
    ]);

} catch (PDOException $e) {
    // بازگرداندن خطای دیتابیس به صورت JSON (بدون خطای 500 سنگین)
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage(),
        'table' => $table
    ]);
}
<?php
$host = '89.39.208.82'; // یا آدرس سرور دیتابیس (مثلاً localhost)
$db   = 'kyonxcbt_fragcity_services';
$user = 'kyonxcbt_admin';
$pass = 'Atrisayeafsorde824@';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed.']));
}
?>
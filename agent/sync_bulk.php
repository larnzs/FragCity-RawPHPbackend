<?php

declare(strict_types=1);
require_once __DIR__ . '/../settings/loader.php';

require_once 'db.php';



header('Content-Type: application/json; charset=utf-8');



function respond(array $payload, int $statusCode = 200): never

{

    http_response_code($statusCode);

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;

}



try {

    // ۱. بررسی هدر Authorization و اعتبار توکن ایجنت

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



    // آپدیت زمان آخرین استفاده توکن

    $pdo->prepare("UPDATE agent_tokens SET last_used = NOW() WHERE token_id = ?")

        ->execute([$tokenData['token_id']]);



    // ۲. دریافت اطلاعات ارسالی از ایجنت (حاوی نام جدول و ردیف‌های داده)

    $rawInput = file_get_contents("php://input");

    $data = json_decode($rawInput, true);



    if (!$data || !isset($data['TableName']) || !isset($data['Rows'])) {

        respond(['status' => 'error', 'message' => 'Invalid JSON payload or missing parameters.'], 400);

    }



    $tableName = $data['TableName'];

    $rows = $data['Rows'];



    // ۳. نگاشت دقیق نام جدول‌ها به دیتابیس‌های پیشونددار روی هاست (مطابق تصاویر دیتابیس‌های شما)

    $syncCfg=fragcity_settings('files.agent.sync_bulk',array());
$databaseMapping=isset($syncCfg['database_mapping'])&&is_array($syncCfg['database_mapping'])?$syncCfg['database_mapping']:array();



    if (!isset($databaseMapping[$tableName])) {

        respond(['status' => 'error', 'message' => "Unknown or unmapped table: $tableName"], 400);

    }



    $targetDb = $databaseMapping[$tableName];



    // ۴. برقراری اتصال به دیتابیس مقصد روی هاست با استفاده از اطلاعات فایل db.php شما

    // (از همان مشخصات اتصال موجود در db.php استفاده می‌کنیم اما دیتابیس را روی دیتابیس پلاگین تنظیم می‌کنیم)

    $pluginProfile=isset($syncCfg['plugin_database_profile'])?$syncCfg['plugin_database_profile']:'plugin_sync';
    $pluginPdo=fragcity_create_pdo($pluginProfile,$targetDb);
    if(!$pluginPdo)respond(array('status'=>'error','message'=>'Plugin database unavailable or not configured.'),503);

// ۵. درج یا آپدیت داده‌ها به صورت دسته‌ای (Bulk Upsert)

    if (!empty($rows)) {

        $pluginPdo->beginTransaction();



        foreach ($rows as $row) {

            $columns = array_keys($row);

            $fieldsList = '`' . implode('`, `', $columns) . '`';

            $placeholders = ':' . implode(', :', $columns);



            $updates = [];

            foreach ($columns as $col) {

                // ساخت دستور آپدیت در صورت وجود تکرار کلید

                $updates[] = "`$col` = VALUES(`$col`)";

            }

            $updatesSql = implode(', ', $updates);



            $sql = "INSERT INTO `{$tableName}` ({$fieldsList}) VALUES ({$placeholders}) ON DUPLICATE KEY UPDATE {$updatesSql}";

            

            $stmt = $pluginPdo->prepare($sql);

            $stmt->execute($row);

        }



        $pluginPdo->commit();

    }



    respond([

        'status' => 'success',

        'message' => 'Data synced successfully.',

        'synced_rows' => count($rows)

    ]);



} catch (Throwable $e) {

    if (isset($pluginPdo) && $pluginPdo->inTransaction()) {

        $pluginPdo->rollBack();

    }

    respond([

        'status' => 'error',

        'message' => 'Server error: ' . $e->getMessage()

    ], 500);

}
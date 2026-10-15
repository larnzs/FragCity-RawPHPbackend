<?php
// agent/webhook_api.php

/**
 * ارسال دستور مستقیم (Webhook) به ایجنت روی نود مورد نظر
 * 
 * @param string $nodeIp آی‌پی پابلیک نود (مثلاً 217.18.90.147)
 * @param string $accessToken توکن دسترسی ایجنت (همان AccessToken ذخیره شده در دیتابیس)
 * @param string $action نام دستور (مثلاً start_server)
 * @param int $serverId شناسه سرور (ID)
 * @param int $port پورت سرور
 * @return array آرایه‌ای شامل وضعیت (status) و پیام (message)
 */
function sendCommandToAgentWebhook($nodeIp, $accessToken, $action, $serverId, $port) {
    // پورت 27000 که در C# تنظیم کردیم
    $url = "http://{$nodeIp}:27000/api/execute";

    // ساخت بدنه درخواست به صورت JSON
    $payload = json_encode([
        'action'   => $action,
        'serverId' => (int)$serverId,
        'port'     => (int)$port
    ]);

    // راه‌اندازی cURL
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken
        ],
        CURLOPT_TIMEOUT        => 5, // حداکثر زمان انتظار 5 ثانیه برای واکنش سریع
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    // مدیریت خطاهای اتصال (مثلاً خاموش بودن ایجنت یا بسته بودن پورت)
    if ($curlErr) {
        return [
            'status'  => 'error',
            'message' => 'ارتباط با نود برقرار نشد: ' . $curlErr
        ];
    }

    // مدیریت خطاهای HTTP (مثلاً 401 خطای توکن نامعتبر)
    if ($httpCode !== 200) {
        return [
            'status'  => 'error',
            'message' => "ایجنت خطای {$httpCode} برگرداند.",
            'raw'     => $response
        ];
    }

    // تبدیل پاسخ JSON ایجنت به آرایه PHP
    $decoded = json_decode($response, true);
    
    return $decoded ?: [
        'status'  => 'success', 
        'message' => $response
    ];
}
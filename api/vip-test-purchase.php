<?php
declare(strict_types=1);

/** Authenticated website endpoint for the temporary no-payment VIPCore test flow. */
require_once dirname(__DIR__) . '/settings/session.php';
require_once dirname(__DIR__) . '/class/FragCityLinuxApiClient.php';
require_once dirname(__DIR__) . '/class/VipCoreRepository.php';

fragcity_start_session();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

$respond = static function (array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    $respond(['success' => false, 'error' => 'method_not_allowed'], 405);
}

if (($_SESSION['user_logged_in'] ?? false) !== true) {
    $respond(['success' => false, 'error' => 'authentication_required'], 401);
}

$steamId64 = (string)($_SESSION['steamid'] ?? '');
if (!preg_match('/^[0-9]{17}$/D', $steamId64)) {
    $respond(['success' => false, 'error' => 'invalid_session'], 401);
}

$sessionCsrf = $_SESSION['auth_csrf_token'] ?? '';
$submittedCsrf = (string)($_SERVER['HTTP_X_FRAGCITY_CSRF'] ?? '');
if (!is_string($sessionCsrf)
    || !preg_match('/^[a-f0-9]{64}$/D', $sessionCsrf)
    || !hash_equals($sessionCsrf, $submittedCsrf)) {
    $respond(['success' => false, 'error' => 'csrf_failed'], 419);
}

$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength < 1 || $contentLength > 8192) {
    $respond(['success' => false, 'error' => 'invalid_request'], 400);
}

$rawBody = file_get_contents('php://input');
if (!is_string($rawBody) || $rawBody === '' || strlen($rawBody) > 8192) {
    $respond(['success' => false, 'error' => 'invalid_request'], 400);
}

try {
    $payload = json_decode($rawBody, true, 16, JSON_THROW_ON_ERROR);
} catch (Throwable) {
    $respond(['success' => false, 'error' => 'invalid_request'], 400);
}

if (!is_array($payload)) {
    $respond(['success' => false, 'error' => 'invalid_request'], 400);
}

$level = filter_var(
    $payload['level'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1, 'max_range' => 3]]
);
if ($level === false) {
    $respond(['success' => false, 'error' => 'invalid_vip_tier'], 422);
}

// Bound accidental or repeated test grants at the website session boundary too.
$attempt = $_SESSION['vip_test_purchase_attempt'] ?? [];
$attemptCount = is_array($attempt) ? (int)($attempt['count'] ?? 0) : 0;
$attemptTime = is_array($attempt) ? (int)($attempt['time'] ?? 0) : 0;
if ($attemptTime <= 0 || (time() - $attemptTime) > 600) {
    $attemptCount = 0;
}
if ($attemptCount >= 3) {
    $respond(['success' => false, 'error' => 'rate_limited'], 429);
}
$_SESSION['vip_test_purchase_attempt'] = [
    'count' => $attemptCount + 1,
    'time' => time(),
];

$playerName = trim((string)($_SESSION['username'] ?? 'FragCity Player'));
if ($playerName === '') {
    $playerName = 'FragCity Player';
}
$playerName = function_exists('mb_substr')
    ? mb_substr($playerName, 0, 64, 'UTF-8')
    : substr($playerName, 0, 64);

try {
    $result = (new VipCoreRepository())->grantTestMembership(
        $steamId64,
        $playerName,
        (int)$level
    );

    $tierName = [1 => 'Bronze', 2 => 'Silver', 3 => 'Gold'][(int)$level];
    $durationMinutes = max(1, (int)round($result['duration_seconds'] / 60));
    $respond([
        'success' => true,
        'data' => [
            'level' => (int)$level,
            'group' => $result['group'],
            'expires' => $result['expires'],
            'duration_seconds' => $result['duration_seconds'],
            'message' => $tierName . ' برای ' . $durationMinutes . ' دقیقه در VIPCore ثبت شد.',
        ],
    ]);
} catch (FragCityLinuxApiException $e) {
    $messages = [
        'membership_already_active' => 'این حساب در حال حاضر اشتراک VIP فعال دارد؛ اشتراک قبلی تغییر نکرد.',
        'test_mode_disabled' => 'حالت آزمایشی VIP روی سرور فعال نشده است.',
        'vipcore_disabled' => 'اتصال VIPCore روی سرور فعال نشده است.',
        'vipcore_not_configured' => 'تنظیمات اتصال VIPCore کامل نیست.',
        'invalid_request' => 'درخواست ثبت VIP معتبر نیست.',
        'invalid_vip_tier' => 'این سطح VIP برای ثبت عمومی مجاز نیست.',
        'vipcore_unavailable' => 'دیتابیس VIPCore فعلاً در دسترس نیست.',
    ];
    $safeCode = array_key_exists($e->errorCode, $messages) ? $e->errorCode : 'vipcore_unavailable';
    $status = match ($safeCode) {
        'membership_already_active' => 409,
        'invalid_request', 'invalid_vip_tier' => 422,
        'test_mode_disabled', 'vipcore_disabled', 'vipcore_not_configured',
        'vipcore_unavailable' => 503,
        default => $e->httpStatus >= 400 && $e->httpStatus < 500 ? $e->httpStatus : 502,
    };
    $respond([
        'success' => false,
        'error' => $safeCode,
        'message' => $messages[$safeCode],
    ], $status);
} catch (Throwable $e) {
    error_log(
        '[FragCity VIP test purchase] Failed; steam_hash='
        . substr(hash('sha256', $steamId64), 0, 16)
        . '; type=' . get_class($e)
    );
    $respond([
        'success' => false,
        'error' => 'vipcore_unavailable',
        'message' => 'ثبت آزمایشی VIP فعلاً ممکن نیست. اتصال سرور را بررسی کنید.',
    ], 503);
}

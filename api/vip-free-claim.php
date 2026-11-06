<?php
declare(strict_types=1);

/** Authenticated one-time Silver trial claim for the current FragCity Steam account. */
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
if ($contentLength < 2 || $contentLength > 2048) {
    $respond(['success' => false, 'error' => 'invalid_request'], 400);
}

$rawBody = file_get_contents('php://input');
if (!is_string($rawBody) || $rawBody === '' || strlen($rawBody) > 2048) {
    $respond(['success' => false, 'error' => 'invalid_request'], 400);
}

try {
    $payload = json_decode($rawBody, true, 8, JSON_THROW_ON_ERROR);
} catch (Throwable) {
    $respond(['success' => false, 'error' => 'invalid_request'], 400);
}

// The browser cannot choose a SteamID, VIP level, or duration.
// Identity comes from the authenticated session; group and duration are fixed by the Linux API.
if (!is_array($payload) || $payload !== []) {
    $respond(['success' => false, 'error' => 'invalid_request'], 400);
}

$playerName = trim((string)($_SESSION['username'] ?? 'FragCity Player'));
if ($playerName === '') {
    $playerName = 'FragCity Player';
}
$playerName = function_exists('mb_substr')
    ? mb_substr($playerName, 0, 64, 'UTF-8')
    : substr($playerName, 0, 64);

try {
    $result = (new VipCoreRepository())->claimFreeSilverTrial($steamId64, $playerName);

    $respond([
        'success' => true,
        'data' => [
            'level' => 2,
            'group' => 'SILVER',
            'claimed_at' => $result['claimed_at'],
            'expires' => $result['expires'],
            'duration_seconds' => (int)$result['duration_seconds'],
            'message' => 'عضویت Silver به مدت ۳ روز برای حساب Steam شما فعال شد.',
        ],
    ]);
} catch (FragCityLinuxApiException $e) {
    $messages = [
        'free_trial_already_claimed' => 'هدیه سه‌روزه Silver قبلاً برای این حساب Steam دریافت شده است.',
        'membership_already_active' => 'این حساب در حال حاضر اشتراک VIP فعال دارد؛ ابتدا باید اشتراک فعلی پایان یابد.',
        'vipcore_disabled' => 'اتصال VIPCore روی سرور فعال نیست.',
        'vipcore_not_configured' => 'تنظیمات اتصال VIPCore کامل نیست.',
        'vipcore_unavailable' => 'دیتابیس VIPCore یا جدول ثبت هدیه فعلاً در دسترس نیست.',
        'invalid_request' => 'درخواست دریافت هدیه معتبر نیست.',
    ];
    $safeCode = array_key_exists($e->errorCode, $messages)
        ? $e->errorCode
        : 'vipcore_unavailable';
    $status = match ($safeCode) {
        'free_trial_already_claimed', 'membership_already_active' => 409,
        'invalid_request' => 422,
        'vipcore_disabled', 'vipcore_not_configured', 'vipcore_unavailable' => 503,
        default => $e->httpStatus >= 400 && $e->httpStatus < 500 ? $e->httpStatus : 502,
    };
    $respond([
        'success' => false,
        'error' => $safeCode,
        'message' => $messages[$safeCode],
    ], $status);
} catch (Throwable $e) {
    error_log(
        '[FragCity VIP free trial] Claim failed; steam_hash='
        . substr(hash('sha256', $steamId64), 0, 16)
        . '; type=' . get_class($e)
    );
    $respond([
        'success' => false,
        'error' => 'vipcore_unavailable',
        'message' => 'ثبت هدیه VIP فعلاً ممکن نیست. اتصال سرویس را بررسی کنید.',
    ], 503);
}

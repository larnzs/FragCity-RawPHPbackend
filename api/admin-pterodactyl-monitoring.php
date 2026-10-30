<?php
declare(strict_types=1);

/**
 * Admin-only AJAX endpoint for live Pterodactyl node and CS2 server monitoring.
 * Secrets and Pterodactyl credentials remain server-side.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

$respond = static function (int $status, array $payload): never {
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
};

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    header('Allow: GET');
    $respond(405, ['success' => false, 'error' => 'method_not_allowed']);
}

if (($_SESSION['user_logged_in'] ?? false) !== true
    || (string)($_SESSION['role'] ?? '') !== 'admin') {
    $respond(403, ['success' => false, 'error' => 'admin_access_required']);
}

require_once dirname(__DIR__) . '/settings/loader.php';
require_once dirname(__DIR__) . '/class/PterodactylMonitoringRepository.php';

try {
    $snapshot = (new PterodactylMonitoringRepository())->getSnapshot();
    $respond(200, [
        'success' => true,
        'data' => $snapshot,
    ]);
} catch (Throwable $e) {
    error_log('[FragCity] Admin Pterodactyl monitoring request failed: ' . get_class($e));
    $respond(503, [
        'success' => false,
        'error' => 'monitoring_temporarily_unavailable',
    ]);
}

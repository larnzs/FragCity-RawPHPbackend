<?php
declare(strict_types=1);

/**
 * Same-origin, read-only JSON endpoint for the public FragCity status page.
 * Uptime Kuma credentials and its management interface are never exposed.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=30, stale-while-revalidate=30');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

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

$fetchSite = strtolower(trim((string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')));
if ($fetchSite === 'cross-site') {
    $respond(403, ['success' => false, 'error' => 'same_origin_required']);
}

require_once dirname(__DIR__) . '/class/UptimeKumaStatusRepository.php';

try {
    $snapshot = (new UptimeKumaStatusRepository())->getSnapshot();
    $respond(200, [
        'success' => true,
        'data' => $snapshot,
    ]);
} catch (UptimeKumaStatusNotConfiguredException $e) {
    $respond(503, [
        'success' => false,
        'error' => 'status_not_configured',
    ]);
} catch (Throwable $e) {
    error_log('[FragCity] Public status snapshot failed: ' . get_class($e));
    $respond(503, [
        'success' => false,
        'error' => 'status_temporarily_unavailable',
    ]);
}

<?php
declare(strict_types=1);

/**
 * Public read-only AJAX endpoint for the current CS2 Server Browser player list.
 * Accepts only the opaque server ID from a sanitized Server Browser snapshot.
 * The signed Linux API resolves the allowlisted Pterodactyl Allocation; this
 * endpoint never accepts or forwards a visitor-supplied hostname or port.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
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

$serverId = strtolower(trim((string)($_GET['server_id'] ?? '')));
if (!preg_match('/^[a-f0-9]{16}$/D', $serverId)) {
    $respond(400, ['success' => false, 'error' => 'invalid_server_id']);
}

require_once dirname(__DIR__) . '/class/PterodactylStandbyRepository.php';

try {
    $players = (new PterodactylStandbyRepository())->getPlayers($serverId);
    $respond(200, [
        'success' => true,
        'data' => $players,
    ]);
} catch (Throwable $e) {
    error_log('[FragCity] Public Server Browser player request failed: ' . get_class($e));
    $respond(503, [
        'success' => false,
        'error' => 'players_temporarily_unavailable',
    ]);
}

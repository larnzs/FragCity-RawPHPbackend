<?php
declare(strict_types=1);

require_once __DIR__ . '/../settings/session.php';
fragcity_start_session();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$respond = static function (int $status, array $payload): never {
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
};

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    header('Allow: POST');
    $respond(405, ['success' => false, 'error' => 'method_not_allowed']);
}

if (
    !isset($_SESSION['user_logged_in']) ||
    $_SESSION['user_logged_in'] !== true
) {
    $respond(401, ['success' => false, 'error' => 'authentication_required']);
}

$steamid = (string)($_SESSION['steamid'] ?? '');
if (!preg_match('/^[0-9]{17}$/D', $steamid)) {
    $respond(401, ['success' => false, 'error' => 'invalid_player_session']);
}

$sessionToken = $_SESSION['wp_csrf_token'] ?? '';
$submittedToken = $_POST['wp_csrf_token'] ?? '';
if (
    !is_string($sessionToken) ||
    !preg_match('/^[a-f0-9]{64}$/D', $sessionToken) ||
    !is_string($submittedToken) ||
    !hash_equals($sessionToken, $submittedToken)
) {
    $respond(403, ['success' => false, 'error' => 'csrf_validation_failed']);
}

require_once __DIR__ . '/../class/WeaponPaintsRepository.php';

try {
    $repository = new WeaponPaintsRepository();
    $deleted = $repository->clearSkinInventory($steamid);

    $respond(200, [
        'success' => true,
        'deleted' => true,
        'deleted_inventory' => $deleted['deleted_inventory'],
        'deleted_active' => $deleted['deleted_active'],
    ]);
} catch (InvalidArgumentException $e) {
    $respond(400, ['success' => false, 'error' => 'invalid_inventory_request']);
} catch (Throwable $e) {
    error_log('[FragCity] WeaponPaints bulk inventory clear failed: ' . $e->getMessage());
    $respond(503, ['success' => false, 'error' => 'inventory_clear_unavailable']);
}

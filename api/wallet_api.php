<?php
declare(strict_types=1);

/**
 * Retired legacy wallet withdrawal fragment.
 *
 * The original file was an unreferenced switch-case snippet without PHP tags.
 * It was not a working API endpoint and is intentionally fail-closed.
 */
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
http_response_code(410);

echo json_encode([
    'success' => false,
    'error' => 'legacy_wallet_api_retired',
    'message' => 'این مسیر قدیمی غیرفعال است.',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

exit;

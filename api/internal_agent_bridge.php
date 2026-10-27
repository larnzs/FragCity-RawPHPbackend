<?php
declare(strict_types=1);

/**
 * Retired compatibility endpoint for the old Windows Agent API.
 *
 * The old Agent/instance-maker tables are not part of the active website
 * schema. This endpoint intentionally performs no database access and no
 * authentication-token creation. The Linux API no longer forwards Agent
 * operations to this URL.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

http_response_code(410);
echo json_encode([
    'status' => 'error',
    'message' => 'legacy_agent_api_retired',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

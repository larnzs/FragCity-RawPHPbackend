<?php
declare(strict_types=1);

/**
 * Read-only Paymenter FragCityAPI bridge preflight.
 *
 * Run from the website repository root:
 *   php tools/paymenter-api-preflight.php
 *
 * Sends one signed ping. Does not create or modify any user or server records.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once dirname(__DIR__) . '/settings/loader.php';
require_once dirname(__DIR__) . '/class/PaymenterApiClient.php';

try {
    $result = (new PaymenterApiClient())->testConnection();

    echo "PAYMENTER FRAGCITY BRIDGE: PASS\n";
    echo "TLS + HMAC authentication + bridge route: OK\n";
    echo 'HTTP status: ' . $result['http_status'] . "\n";
    echo 'Service: ' . $result['service'] . "\n";
    echo 'Version: ' . $result['version'] . "\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'PAYMENTER FRAGCITY BRIDGE: FAIL — ' . $e->getMessage() . "\n");
    exit(1);
}

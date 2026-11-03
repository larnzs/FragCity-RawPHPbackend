<?php
declare(strict_types=1);

/**
 * Read-only Paymenter API preflight.
 *
 * Run from the website repository root:
 *   php tools/paymenter-api-preflight.php
 *
 * This performs one GET request for a single product page. It does not create
 * or modify users, orders, invoices, services, payments, or game servers.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once dirname(__DIR__) . '/settings/loader.php';
require_once dirname(__DIR__) . '/class/PaymenterApiClient.php';

try {
    $result = (new PaymenterApiClient())->testConnection();

    echo "PAYMENTER API: PASS\n";
    echo "TLS + API authentication + products.view: OK\n";
    echo 'HTTP status: ' . $result['http_status'] . "\n";
    echo 'Products returned in sample page: ' . $result['visible_products'] . "\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'PAYMENTER API: FAIL — ' . $e->getMessage() . "\n");
    exit(1);
}

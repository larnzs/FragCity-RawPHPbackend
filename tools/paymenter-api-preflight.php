<?php
declare(strict_types=1);

/**
 * Read-only FragCity <-> Paymenter bridge preflight.
 *
 * Run from the website repository root:
 *   php tools/paymenter-api-preflight.php
 *
 * Checks the local account-link columns and sends one signed, non-mutating
 * Paymenter ping. It does not create or modify user or server records.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once dirname(__DIR__) . '/settings/loader.php';
require_once dirname(__DIR__) . '/class/PaymenterApiClient.php';

try {
    $databaseProfile = fragcity_settings('files.index.database_profile', 'main');
    $pdo = fragcity_create_pdo(is_string($databaseProfile) ? $databaseProfile : 'main');
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('FragCity main database connection is unavailable.');
    }

    $schemaCheck = $pdo->query(
        "SELECT COLUMN_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'users'
           AND COLUMN_NAME IN (
               'paymenter_user_id',
               'paymenter_sync_status',
               'paymenter_synced_at',
               'paymenter_sync_error'
           )"
    );

    $columns = $schemaCheck->fetchAll(PDO::FETCH_COLUMN);
    $requiredColumns = [
        'paymenter_user_id',
        'paymenter_sync_status',
        'paymenter_synced_at',
        'paymenter_sync_error',
    ];
    $missingColumns = array_values(array_diff($requiredColumns, $columns));

    if ($missingColumns !== []) {
        throw new RuntimeException(
            'FragCity users migration is incomplete. Missing columns: ' . implode(', ', $missingColumns)
        );
    }

    echo "FRAGCITY USER SYNC SCHEMA: PASS\n";

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

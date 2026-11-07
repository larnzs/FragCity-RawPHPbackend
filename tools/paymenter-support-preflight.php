<?php
declare(strict_types=1);

/**
 * Read-only preflight for FragCity Support <-> Paymenter.
 *
 * Run from the FragCity repository root:
 *   php tools/paymenter-support-preflight.php
 *   php tools/paymenter-support-preflight.php 7656119XXXXXXXXXX
 *
 * This checks the custom HMAC identity bridge and native Paymenter Admin API
 * read permissions. Supplying a SteamID64 additionally performs a read-only
 * identity lookup. It never creates or changes tickets, invoices or users.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once dirname(__DIR__) . '/settings/loader.php';
require_once dirname(__DIR__) . '/class/PaymenterSupportApiClient.php';

try {
    $client = new PaymenterSupportApiClient();

    $bridge = $client->testBridgeConnection();
    echo "SUPPORT IDENTITY BRIDGE: PASS\n";
    echo "TLS + HMAC + signed ping: OK\n";
    echo 'Extension: ' . $bridge['service'] . "\n";
    echo 'Version: ' . $bridge['version'] . "\n";

    $permissions = $client->testNativeApiAccess();
    echo "PAYMENTER ADMIN API: PASS\n";
    echo "View Tickets: PASS\n";
    echo "View Ticket Messages: PASS\n";
    echo "View Invoices: PASS\n";
    echo "View Invoice Items: PASS\n";
    echo "Write permissions are configured in Paymenter but are not tested by this read-only preflight.\n";

    $steamId64 = isset($argv[1]) && is_string($argv[1]) ? trim($argv[1]) : '';
    if ($steamId64 !== '') {
        $paymenterUserId = $client->resolvePaymenterUserId($steamId64);
        echo "STEAM IDENTITY LOOKUP: PASS\n";
        echo 'Paymenter user ID: ' . $paymenterUserId . "\n";
    }

    echo "RESULT: PASS\n";
    exit(0);
} catch (Throwable $exception) {
    $safeErrors = [
        'invalid_steamid',
        'paymenter_unreachable',
        'paymenter_support_invalid_response',
        'paymenter_support_invalid_identity_response',
        'fragcity_account_not_linked',
        'linked_paymenter_user_missing',
        'identity_lookup_failed',
        'paymenter_api_permission_denied',
        'paymenter_support_validation_failed',
        'paymenter_support_rate_limited',
        'paymenter_support_request_failed',
        'Paymenter support base URL must be an HTTPS origin without a path.',
        'Paymenter Admin API key is missing or invalid.',
        'Paymenter support HMAC secret is missing or invalid.',
        'PHP cURL extension is required for Paymenter support integration.',
    ];

    $message = $exception->getMessage();
    if (!in_array($message, $safeErrors, true)) {
        $message = 'preflight_failed';
    }

    fwrite(STDERR, "RESULT: FAIL — " . $message . "\n");
    exit(1);
}

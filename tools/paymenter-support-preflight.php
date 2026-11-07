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
    echo "View Ticket Messages + ticket conversation include: PASS\n";
    echo "View Invoices: PASS\n";
    echo "View Invoice Items: PASS\n";
    echo "Write permissions are configured in Paymenter but are not tested by this read-only preflight.\n";

    $steamId64 = isset($argv[1]) && is_string($argv[1]) ? trim($argv[1]) : '';
    if ($steamId64 !== '') {
        $indexSettings = fragcity_settings('files.index', []);
        $databaseProfile = is_array($indexSettings)
            ? (string) ($indexSettings['database_profile'] ?? 'main')
            : 'main';
        $pdo = fragcity_create_pdo($databaseProfile);
        if (!$pdo instanceof PDO) {
            throw new RuntimeException('fragcity_account_unavailable');
        }

        $statement = $pdo->prepare(
            'SELECT email, phone, paymenter_user_id
             FROM users
             WHERE steamid64 = ?
             LIMIT 1'
        );
        $statement->execute([$steamId64]);
        $account = $statement->fetch(PDO::FETCH_ASSOC);
        $localPaymenterUserId = filter_var(
            is_array($account) ? ($account['paymenter_user_id'] ?? null) : null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        if (!is_array($account) || $localPaymenterUserId === false) {
            throw new RuntimeException('paymenter_support_identity_not_synced');
        }

        $paymenterUserId = $client->verifyCustomerIdentity(
            $steamId64,
            (string) ($account['email'] ?? ''),
            (string) ($account['phone'] ?? ''),
            (int) $localPaymenterUserId
        );
        echo "STEAM IDENTITY LOOKUP: PASS\n";
        echo "FRAGCITY / PAYMENTER EMAIL + PHONE MATCH: PASS\n";
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
        'paymenter_support_identity_not_synced',
        'paymenter_support_identity_mismatch',
        'fragcity_account_unavailable',
        'identity_lookup_failed',
        'paymenter_api_permission_denied',
        'paymenter_support_validation_failed',
        'paymenter_support_rate_limited',
        'paymenter_support_request_failed',
        'support_api_not_configured',
        'source_ip_not_allowed',
        'invalid_ip_allowlist_configuration',
        'invalid_signature_headers',
        'invalid_signature',
        'request_replayed',
        'invalid_json_body',
        'request_too_large',
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

<?php
declare(strict_types=1);

/**
 * Read-only preflight for FragCity server invoices.
 *
 * Usage:
 *   php tools/paymenter-invoices-preflight.php 7656119XXXXXXXXXX
 *
 * The SteamID64 is used only by this CLI tool to look up the authenticated
 * account's local Paymenter link and email. No invoices or users are changed.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once dirname(__DIR__) . '/settings/loader.php';
require_once dirname(__DIR__) . '/class/PaymenterInvoiceApiClient.php';

$steamId64 = isset($argv[1]) && is_string($argv[1]) ? trim($argv[1]) : '';
if (!preg_match('/^[0-9]{17}$/D', $steamId64)) {
    fwrite(STDERR, "Usage: php tools/paymenter-invoices-preflight.php 7656119XXXXXXXXXX\n");
    exit(2);
}

try {
    $indexSettings = fragcity_settings('files.index', []);
    $databaseProfile = is_array($indexSettings)
        ? (string) ($indexSettings['database_profile'] ?? 'main')
        : 'main';

    $pdo = fragcity_create_pdo($databaseProfile);
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('fragcity_account_unavailable');
    }

    $statement = $pdo->prepare(
        'SELECT email, paymenter_user_id, paymenter_sync_status
         FROM users
         WHERE steamid64 = ?
         LIMIT 1'
    );
    $statement->execute([$steamId64]);
    $account = $statement->fetch(PDO::FETCH_ASSOC);

    $paymenterUserId = filter_var(
        is_array($account) ? ($account['paymenter_user_id'] ?? null) : null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    $email = mb_strtolower(trim((string) ($account['email'] ?? '')));

    if (
        !is_array($account)
        || $paymenterUserId === false
        || (string) ($account['paymenter_sync_status'] ?? '') !== 'synced'
        || !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        throw new RuntimeException('paymenter_invoice_identity_not_synced');
    }

    $client = new PaymenterInvoiceApiClient();
    $result = $client->listInvoices((int) $paymenterUserId, $email, 100);

    echo "PAYMENTER SERVER INVOICE API: PASS\n";
    echo "View Invoices: PASS\n";
    echo "View Invoice Items: PASS\n";
    echo "View Users (owner email verification): PASS\n";
    echo "FragCity Paymenter ID + email ownership check: PASS\n";
    echo 'Verified invoices: ' . (int) ($result['count'] ?? 0) . "\n";
    echo "RESULT: PASS\n";
    exit(0);
} catch (Throwable $exception) {
    $safeErrors = [
        'fragcity_account_unavailable',
        'paymenter_invoice_identity_not_synced',
        'paymenter_invoices_not_configured',
        'paymenter_invoice_curl_required',
        'paymenter_invoice_unreachable',
        'paymenter_invoice_invalid_response',
        'paymenter_invoice_invalid_query',
        'paymenter_api_permission_denied',
        'paymenter_invoice_resource_not_found',
        'paymenter_invoice_validation_failed',
        'paymenter_invoice_rate_limited',
        'paymenter_invoice_request_failed',
        'paymenter_invoice_owner_unverified',
        'paymenter_invoice_identity_mismatch',
        'paymenter_invoice_items_unavailable',
    ];

    $message = $exception->getMessage();
    if (!in_array($message, $safeErrors, true)) {
        $message = 'preflight_failed';
    }

    fwrite(STDERR, "RESULT: FAIL — " . $message . "\n");
    exit(1);
}

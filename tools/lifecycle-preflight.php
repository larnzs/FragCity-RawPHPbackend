<?php
declare(strict_types=1);

/**
 * Static lifecycle contract check. This script does not contact Paymenter,
 * Pterodactyl, MySQL or the network, and it never mutates either checkout.
 *
 * Usage:
 *   php tools/lifecycle-preflight.php --paymenter-root="W:/path/FragCity-Paymenter"
 * or:
 *   FRAGCITY_PAYMENTER_ROOT="/opt/fragcity-paymenter" php tools/lifecycle-preflight.php
 */

$websiteRoot = dirname(__DIR__);
$paymenterRoot = getenv('FRAGCITY_PAYMENTER_ROOT') ?: '';

foreach (($argv ?? []) as $argument) {
    if (str_starts_with($argument, '--paymenter-root=')) {
        $paymenterRoot = substr($argument, strlen('--paymenter-root='));
    }
}

$paymenterRoot = trim($paymenterRoot);
$checks = 0;
$failures = [];

$check = static function (bool $passed, string $label) use (&$checks, &$failures): void {
    $checks++;
    if ($passed) {
        echo "[PASS] {$label}\n";
        return;
    }

    $failures[] = $label;
    echo "[FAIL] {$label}\n";
};

$read = static function (string $root, string $relativePath) use ($check): string {
    $path = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
        . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);

    if (!is_file($path) || !is_readable($path)) {
        $check(false, "File exists/readable: {$relativePath}");
        return '';
    }

    return (string) file_get_contents($path);
};

$syntax = static function (string $source, string $label) use ($check): void {
    if ($source === '') {
        $check(false, "PHP syntax: {$label} (source unavailable)");
        return;
    }

    try {
        token_get_all($source, TOKEN_PARSE);
        $check(true, "PHP syntax: {$label}");
    } catch (ParseError $error) {
        $check(false, "PHP syntax: {$label} — " . $error->getMessage());
    }
};

$uniqueNamedFunctions = static function (string $source, string $label) use ($check): void {
    try {
        $tokens = token_get_all($source, TOKEN_PARSE);
    } catch (ParseError $error) {
        $check(false, "Named function scan: {$label} — " . $error->getMessage());
        return;
    }

    $names = [];
    $duplicates = [];

    for ($i = 0, $count = count($tokens); $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
            continue;
        }

        $j = $i + 1;
        while ($j < $count && is_array($tokens[$j])
            && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG, T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG], true)
        ) {
            $j++;
        }

        if ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
            $name = strtolower($tokens[$j][1]);
            if (isset($names[$name])) {
                $duplicates[] = $name;
            }
            $names[$name] = true;
        }
    }

    $check($duplicates === [], "No duplicate named functions: {$label}"
        . ($duplicates === [] ? '' : ' (' . implode(', ', array_unique($duplicates)) . ')'));
};

$websiteFiles = [
    'api/admin-lifecycle.php',
    'class/PaymenterApiClient.php',
    'pages/lifecycle.php',
    'index.php',
    'tools/lifecycle-preflight.php',
];
$websiteSources = [];
foreach ($websiteFiles as $relative) {
    $source = $read($websiteRoot, $relative);
    $websiteSources[$relative] = $source;
    $syntax($source, $relative);
}
$uniqueNamedFunctions($websiteSources['class/PaymenterApiClient.php'] ?? '', 'PaymenterApiClient.php');
$uniqueNamedFunctions($websiteSources['api/admin-lifecycle.php'] ?? '', 'admin-lifecycle.php');

$client = $websiteSources['class/PaymenterApiClient.php'] ?? '';
$proxy = $websiteSources['api/admin-lifecycle.php'] ?? '';
$page = $websiteSources['pages/lifecycle.php'] ?? '';
$js = $read($websiteRoot, 'js/lifecycle.js');

$check(str_contains($proxy, "'role'] ?? '') !== 'admin'")
    && str_contains($proxy, 'HTTP_X_FRAGCITY_CSRF')
    && str_contains($proxy, 'hash_equals($sessionCsrf, $submittedCsrf)'),
    'Website admin session and CSRF boundary');
$check(str_contains($proxy, "'revoke_recreation'")
    && str_contains($client, 'adminLifecycleRevokeRecreation')
    && str_contains($client, '/admin/lifecycle/revoke-recreation'),
    'Recovery revocation is wired from website proxy to Paymenter client');
$check(str_contains($page, "\$authorizationStatus === 'in_progress'")
    && str_contains($page, 'data-lifecycle-revoke')
    && str_contains($page, "\$mapping['last_reconciled_at']"),
    'Dashboard exposes claim state, revocation and last reconciliation');
$check(str_contains($js, "'revoke_recreation'")
    && str_contains($js, 'data-lifecycle-revoke')
    && str_contains($js, 'window.confirm'),
    'Dashboard JavaScript confirms reconciliation and handles revocation');

if ($paymenterRoot === '') {
    echo "\n[SKIP] Paymenter source checks. Pass --paymenter-root or FRAGCITY_PAYMENTER_ROOT for the full cross-repository check.\n";
} else {
    $paymenterFiles = [
        'extensions/Others/FragCityAPI/FragCityAPI.php',
        'extensions/Others/FragCityAPI/routes.php',
        'extensions/Servers/FragCityCS2/FragCityCS2.php',
        'extensions/Others/FragCityAPI/database/migrations/2026_11_04_000002_create_fragcity_identity_and_service_lifecycle_tables.php',
        'extensions/Others/FragCityAPI/database/migrations/2026_11_04_000003_add_lifecycle_reconciliation_and_recreation_authorization_columns.php',
        'extensions/Others/FragCityAPI/database/migrations/2026_11_04_000004_add_recreation_authorization_claim_columns.php',
    ];

    $paymenterSources = [];
    foreach ($paymenterFiles as $relative) {
        $source = $read($paymenterRoot, $relative);
        $paymenterSources[$relative] = $source;
        $syntax($source, $relative);
    }

    $bridge = $paymenterSources['extensions/Others/FragCityAPI/FragCityAPI.php'] ?? '';
    $routes = $paymenterSources['extensions/Others/FragCityAPI/routes.php'] ?? '';
    $cs2 = $paymenterSources['extensions/Servers/FragCityCS2/FragCityCS2.php'] ?? '';
    $identityMigration = $paymenterSources['extensions/Others/FragCityAPI/database/migrations/2026_11_04_000002_create_fragcity_identity_and_service_lifecycle_tables.php'] ?? '';
    $authMigration = $paymenterSources['extensions/Others/FragCityAPI/database/migrations/2026_11_04_000003_add_lifecycle_reconciliation_and_recreation_authorization_columns.php'] ?? '';
    $claimMigration = $paymenterSources['extensions/Others/FragCityAPI/database/migrations/2026_11_04_000004_add_recreation_authorization_claim_columns.php'] ?? '';

    $uniqueNamedFunctions($bridge, 'FragCityAPI.php');
    $uniqueNamedFunctions($cs2, 'FragCityCS2.php');

    $check(str_contains($bridge, "version: '1.9.0'")
        && str_contains($bridge, 'function upgraded(')
        && str_contains($bridge, 'ExtensionHelper::runMigrations'),
        'Paymenter bridge version and extension migration hooks');
    $check(str_contains($bridge, "ExtensionHelper::callService(\$service, 'reconcileCustomerLifecycle')")
        && str_contains($bridge, 'private function reconcileLifecycleBatch'),
        'Scheduled and administrator reconciliation use lifecycle engine');
    $check(str_contains($routes, 'adminLifecycleList')
        && str_contains($routes, 'adminLifecycleDetail')
        && str_contains($routes, 'adminLifecycleReconcile')
        && str_contains($routes, 'adminLifecycleAuthorizeRecreation')
        && str_contains($routes, 'adminLifecycleRevokeRecreation'),
        'All five administrator lifecycle routes are registered');
    $check(str_contains($cs2, 'function reconcileCustomerLifecycle')
        && str_contains($cs2, 'function claimRecreationAuthorization')
        && str_contains($cs2, 'function releaseRecreationAuthorizationClaim')
        && str_contains($cs2, 'function consumeRecreationAuthorization'),
        'Lifecycle reconciliation and claim/consume/release methods exist');

    $reconcileStart = strpos($cs2, 'public function reconcileCustomerLifecycle');
    $reconcileEnd = strpos($cs2, 'private function markLifecycleReconciled', $reconcileStart === false ? 0 : $reconcileStart);
    $reconcileBody = ($reconcileStart !== false && $reconcileEnd !== false)
        ? substr($cs2, $reconcileStart, $reconcileEnd - $reconcileStart)
        : '';
    $check($reconcileBody !== ''
        && !str_contains($reconcileBody, 'ensurePterodactylClientUser(')
        && !str_contains($reconcileBody, 'parent::createServer')
        && !str_contains($reconcileBody, "'DELETE'"),
        'Reconciliation does not create/repair Client or create/delete Server');

    $ownerStart = strpos($cs2, 'private function assertPterodactylServerOwner');
    $ownerEnd = strpos($cs2, 'public function suspendServer', $ownerStart === false ? 0 : $ownerStart);
    $ownerBody = ($ownerStart !== false && $ownerEnd !== false)
        ? substr($cs2, $ownerStart, $ownerEnd - $ownerStart)
        : '';
    $check(str_contains($cs2, 'if ($response->status() === 404)')
        && str_contains($cs2, 'Pterodactyl Application API request failed with HTTP')
        && str_contains($cs2, 'Automatic Client recreation is blocked')
        && $ownerBody !== ''
        && !str_contains($ownerBody, 'ensurePterodactylClientUser(')
        && !str_contains($cs2, '$legacyPaymenterLink'),
        '404 vs transient errors, missing Client guard, read-only ownership checks and no blind legacy ID migration');

    $summaryStart = strpos($cs2, 'private function getCustomerPanelSummaryLocked');
    $summaryEnd = $summaryStart === false
        ? false
        : min(
            ...array_filter([
                strpos($cs2, "\n    private function ", $summaryStart + 1),
                strpos($cs2, "\n    public function ", $summaryStart + 1),
            ], static fn ($position): bool => $position !== false)
        );
    $summaryBody = ($summaryStart !== false && $summaryEnd !== false)
        ? substr($cs2, $summaryStart, $summaryEnd - $summaryStart)
        : '';
    $readOnlyStart = strpos($cs2, 'private function getVerifiedPterodactylClientUserReadOnly');
    $readOnlyEnd = $readOnlyStart === false
        ? false
        : strpos($cs2, "\n    private function ", $readOnlyStart + 1);
    $readOnlyBody = ($readOnlyStart !== false && $readOnlyEnd !== false)
        ? substr($cs2, $readOnlyStart, $readOnlyEnd - $readOnlyStart)
        : '';
    $check($summaryBody !== ''
        && str_contains($summaryBody, 'getVerifiedPterodactylClientUserReadOnly(')
        && !str_contains($summaryBody, 'ensurePterodactylClientUser(')
        && $readOnlyBody !== ''
        && str_contains($readOnlyBody, "requestApplicationApi('GET'")
        && !str_contains($readOnlyBody, "requestApplicationApi('POST'")
        && !str_contains($readOnlyBody, "requestApplicationApi('DELETE'")
        && !str_contains($readOnlyBody, 'ensurePterodactylClientUser('),
        'Customer inventory verifies the approved Client mapping without remote writes');
    $check(str_contains($cs2, '$this->claimRecreationAuthorization($service)')
        && str_contains($cs2, '$this->releaseRecreationAuthorizationClaim(')
        && str_contains($cs2, '$this->consumeRecreationAuthorization(')
        && !str_contains($cs2, "desired_state' => 'recreate_authorized'"),
        'One-shot recovery grant is claimed, released on error and consumed only after verification');

    $revokeStart = strpos($bridge, 'public function adminLifecycleRevokeRecreation');
    $detailStart = strpos($bridge, 'private function adminLifecycleDetailPayload', $revokeStart === false ? 0 : $revokeStart);
    $revokeBody = ($revokeStart !== false && $detailStart !== false)
        ? substr($bridge, $revokeStart, $detailStart - $revokeStart)
        : '';
    $check($revokeBody !== ''
        && str_contains($revokeBody, 'recreation_authorization_claim_expires_at')
        && str_contains($revokeBody, '$claimInProgress'),
        'Administrator cannot revoke an actively claimed CreateJob authorization');

    $check(str_contains($identityMigration, 'fragcity_service_servers')
        && str_contains($authMigration, 'recreation_authorization_id')
        && str_contains($authMigration, 'recreation_authorization_reason')
        && str_contains($claimMigration, 'recreation_authorization_claimed_at')
        && str_contains($claimMigration, 'recreation_authorization_claim_expires_at'),
        'Lifecycle schema and recovery claim migrations contain required columns');
}

echo "\nChecks: {$checks}; failures: " . count($failures) . "\n";
if ($failures !== []) {
    exit(1);
}

echo "Static lifecycle preflight completed. This is not a live Paymenter/Pterodactyl integration test.\n";

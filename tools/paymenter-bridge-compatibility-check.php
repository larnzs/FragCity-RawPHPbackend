<?php
declare(strict_types=1);

$root = dirname(__DIR__);

$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        throw new RuntimeException('Required file is missing: ' . $relative);
    }
    $contents = file_get_contents($path);
    if (!is_string($contents)) {
        throw new RuntimeException('Could not read: ' . $relative);
    }
    return $contents;
};

$client = $read('class/PaymenterApiClient.php');
$sync = $read('class/PaymenterAccountSyncService.php');
$endpoint = $read('api/myservers.php');
$page = $read('pages/myservers.php');
$javascript = $read('js/myservers.js');
$header = $read('includes/header.php');
$index = $read('index.php');

$checks = [];
$checkResult = static function (string $name, bool $passed) use (&$checks): void {
    $checks[$name] = $passed;
};

$expectedRoutes = [
    '/api/v1/fragcity/bridge/ping',
    '/api/v1/fragcity/bridge/users/sync',
    '/api/v1/fragcity/bridge/servers/list',
    '/api/v1/fragcity/bridge/servers/password',
    '/api/v1/fragcity/bridge/servers/username',
    '/api/v1/fragcity/bridge/orders/cs2',
];

foreach ($expectedRoutes as $route) {
    $checkResult('Bridge client supports ' . $route, str_contains($client, $route));
}

$unsupportedClientTerms = [
    '/servers/' . 'client-api',
    '/servers/' . 'console',
    '/servers/' . 'files',
    '/admin/' . 'lifecycle',
    'connectCustomer' . 'ClientApi',
    'disconnectCustomer' . 'ClientApi',
    'getCustomer' . 'ConsoleCredentials',
    'customer' . 'FileAction',
    'admin' . 'Lifecycle',
    'customer_uuid',
];

$unsupportedClientAbsent = true;
foreach ($unsupportedClientTerms as $term) {
    if (str_contains($client, $term)) {
        $unsupportedClientAbsent = false;
    }
}
$checkResult('Paymenter API client retains the supported customer bridge methods', $unsupportedClientAbsent);
$checkResult('CS2 order client sends a verified ZarinPal receipt and resource/Egg configuration',
    str_contains($client, 'createCs2ServerOrder')
    && str_contains($client, "'amount_toman' => $amountToman")
    && str_contains($client, "'amount_rial' => $amountRial")
    && str_contains($client, "'memory_mb' => $memoryMb")
    && str_contains($client, "'cpu_percent' => $cpuPercent")
    && str_contains($client, "'feature_limits' => [")
    && str_contains($client, "'databases' => $databases")
    && str_contains($client, "'additional_allocations' => $additionalAllocations")
    && str_contains($client, "'backups' => $backups")
    && str_contains($client, "'egg' => $egg"));
$checkResult('CS2 order client surfaces native Config Option mapping and pricing errors safely',
    str_contains($client, "'cs2_config_option_mapping_invalid'")
    && str_contains($client, "'cs2_config_option_price_missing'")
);

$unsupportedEndpointTerms = [
    'connect_' . 'client_api',
    'disconnect_' . 'client_api',
    'console_' . 'connect',
    'customer' . 'FileAction',
    'file_' . 'action',
];
$unsupportedEndpointAbsent = true;
foreach ($unsupportedEndpointTerms as $term) {
    if (str_contains($endpoint, $term)) {
        $unsupportedEndpointAbsent = false;
    }
}
$checkResult('My Servers API exposes only supported password and username actions', $unsupportedEndpointAbsent
    && str_contains($endpoint, "'set_panel_password'")
    && str_contains($endpoint, "'set_panel_username'"));

$checkResult('My Servers keeps the supported Network/IP/Port tab', str_contains($page, "allowedTabs = ['overview', 'network', 'access'];")
    && str_contains($page, "activeTab === 'network'")
    && str_contains($page, 'fc-network-primary'));

$unsupportedPageTerms = [
    'fc' . 'Console',
    'fcFile' . 'Manager',
    'fc-client-api-card',
    'client_api_connected',
    'data-client-api',
    "activeTab === 'console'",
    "activeTab === 'files'",
];
$unsupportedPageAbsent = true;
foreach ($unsupportedPageTerms as $term) {
    if (str_contains($page, $term)) {
        $unsupportedPageAbsent = false;
    }
}
$checkResult('My Servers does not render unsupported Console, File Manager or Client API features', $unsupportedPageAbsent);

$unsupportedJsTerms = ['client_' . 'api', 'console_' . 'connect', 'file_' . 'action'];
$unsupportedJsAbsent = true;
foreach ($unsupportedJsTerms as $term) {
    if (str_contains($javascript, $term)) {
        $unsupportedJsAbsent = false;
    }
}
$checkResult('My Servers JavaScript keeps password and username actions only', $unsupportedJsAbsent
    && str_contains($javascript, 'submitUsernameForm')
    && str_contains($javascript, 'submitPasswordForm'));

$checkResult('Website account sync does not require Lifecycle identity fields', !str_contains($sync, 'customer_uuid'));

$checkResult('Website no longer exposes the incompatible Lifecycle page', !str_contains($header, 'lifecycle')
    && !str_contains($index, 'lifecycle')
    && !is_file($root . '/pages/lifecycle.php')
    && !is_file($root . '/api/admin-lifecycle.php'));

foreach ($checks as $name => $passed) {
    echo ($passed ? 'PASS: ' : 'FAIL: ') . $name . PHP_EOL;
}

$failed = count(array_filter($checks, static fn (bool $passed): bool => !$passed));
if ($failed > 0) {
    fwrite(STDERR, $failed . ' compatibility check(s) failed.' . PHP_EOL);
    exit(1);
}

echo 'All ' . count($checks) . ' Paymenter bridge compatibility checks passed.' . PHP_EOL;

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
$storeApi = $read('api/server-store.php');
$page = $read('pages/myservers.php');
$javascript = $read('js/myservers.js');
$header = $read('includes/header.php');
$index = $read('index.php');
$storePage = $read('pages/buyserveropen.php');
$storeJs = $read('js/buyserveropen.js');
$storeCallback = $read('pages/zarinpal/zarinpal_cs2_callback.php');
$storeMigration = $read('database/migrations/20261110_fragcity_cs2_checkout_orders.sql');

$checks = [];
$checkResult = static function (string $name, bool $passed) use (&$checks): void {
    $checks[$name] = $passed;
};

$expectedRoutes = [
    '/api/v1/fragcity/bridge/ping',
    '/api/v1/fragcity/bridge/catalog/cs2',
    '/api/v1/fragcity/bridge/wallet/balance',
    '/api/v1/fragcity/bridge/wallet/topups/zarinpal',
    '/api/v1/fragcity/bridge/wallet/topups/prepare',
    '/api/v1/fragcity/bridge/users/sync',
    '/api/v1/fragcity/bridge/servers/list',
    '/api/v1/fragcity/bridge/servers/password',
    '/api/v1/fragcity/bridge/servers/username',
    '/api/v1/fragcity/bridge/orders/cs2',
    '/api/v1/fragcity/bridge/quotes/cs2',
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
$checkResult('Server storefront catalog and wallet API keep the HMAC bridge server-side',
    str_contains($storeApi, "getCs2Catalog()")
    && str_contains($storeApi, "getCustomerWalletBalance(\$steamId64)")
    && str_contains($storeApi, "\$_SESSION['steamid']")
    && str_contains($storeApi, "\$_SERVER['HTTP_X_FRAGCITY_CSRF']")
    && str_contains($storeApi, "paymenter_sync_status")
    && !str_contains($storeApi, "shared_secret' =>")
    && !str_contains($storeApi, "admin_api_key' =>")
);
$checkResult('CS2 quotes are calculated by Paymenter, never from browser-submitted prices',
    str_contains($client, 'quoteCs2ServerOrder')
    && str_contains($client, "'/api/v1/fragcity/bridge/quotes/cs2'")
    && str_contains($storeApi, "if (\$action === 'quote')")
    && str_contains($storeApi, "quoteCs2ServerOrder")
    && str_contains($storeApi, "\$_SESSION['steamid']")
    && str_contains($storeApi, "\$_SERVER['HTTP_X_FRAGCITY_CSRF']")
    && !str_contains($storeApi, "'amount_toman' => \$payload")
    && !str_contains($storeApi, "'amount_rial' => \$payload")
);

$checkResult('CS2 storefront quote and checkout stay behind the authenticated FragCity PHP API',
    str_contains($storeApi, "if (\$action === 'quote')")
    && str_contains($storeApi, "if (\$action === 'checkout')")
    && str_contains($storeApi, "quoteCs2ServerOrder")
    && str_contains($storeApi, "createCs2ServerOrder")
    && str_contains($storeApi, "\$_SESSION['steamid']")
    && str_contains($storeApi, "\$_SERVER['HTTP_X_FRAGCITY_CSRF']")
    && str_contains($storeApi, "fragcity_cs2_checkout_orders")
    && str_contains($storeApi, "'expected_amount_rial' => \$quote['amount_rial']")
);
$checkResult('ZarinPal CS2 callback verifies the stored Rial amount before submitting the Paymenter order',
    str_contains($storeCallback, "'amount' => (int) \$order['amount_rial']")
    && str_contains($storeCallback, "'authority' => \$authority")
    && str_contains($storeCallback, "\$gatewayCode !== 100 && \$gatewayCode !== 101")
    && str_contains($storeCallback, "payment_verified")
    && str_contains($storeCallback, "createCs2ServerOrder")
    && !str_contains($storeCallback, "SET status = 'cancelled'")
);
$checkResult('CS2 storefront checkout ledger has stable, unique reference and receipt fields',
    str_contains($storeMigration, 'CREATE TABLE IF NOT EXISTS fragcity_cs2_checkout_orders')
    && str_contains($storeMigration, 'UNIQUE KEY uq_fragcity_cs2_checkout_order_ref (order_ref)')
    && str_contains($storeMigration, 'UNIQUE KEY uq_fragcity_cs2_checkout_authority (authority)')
    && str_contains($storeMigration, 'UNIQUE KEY uq_fragcity_cs2_checkout_ref_id (ref_id)')
);
$checkResult('Buy Server page renders a disabled-until-quote secure checkout',
    str_contains($storePage, 'data-api-url="/api/server-store.php"')
    && str_contains($storePage, 'data-csrf-token=')
    && str_contains($storePage, 'id="serverStoreQuoteStatus"')
    && str_contains($storePage, 'id="serverStoreQuoteLines"')
    && str_contains($storePage, 'id="serverStoreSubmit"')
    && str_contains($storePage, 'name="payment_method"')
    && !str_contains($storePage, 'serverStoreCapacityPricing')
    && !str_contains($storePage, 'serverStoreRamPricing')
);
$checkResult('Buy Server JavaScript uses live Paymenter values and no local price calculation',
    str_contains($storeJs, "searchParams.set('action', 'catalog')")
    && str_contains($storeJs, "action: 'quote'")
    && str_contains($storeJs, "action: 'checkout'")
    && str_contains($storeJs, 'expected_amount_rial: Number(quote.amount_rial)')
    && str_contains($storeJs, 'game_mode_manager')
    && str_contains($storeJs, 'quoteCs2ServerOrder') === false
    && !str_contains($storeJs, 'extraSlotPrice')
    && !str_contains($storeJs, 'monthlyPrice')
);

$checkResult('CS2 server name is backend-generated and no longer required in the storefront',
    !str_contains($storePage, 'serverStoreName')
    && !str_contains($storePage, 'data-server-summary="server-name"')
    && !str_contains($storeJs, 'serverNameInput')
    && !str_contains($storeJs, 'server_name:')
    && substr_count($storeApi, "\$serverName = 'FragCity CS2';") === 2
    && !str_contains($storeApi, "payload['server_name']")
);

$checkResult('CS2 storefront and bridge consistently support the new 3-day base term',
    str_contains($storePage, "['days' => 3, 'label' => '۳ روزه', 'caption' => '۷۲ ساعت']")
    && !str_contains($storePage, "['days' => 1, 'label' => '۱ روزه'")
    && substr_count($storeApi, '[3, 7, 15, 30]') === 3
    && substr_count($client, '[3, 7, 15, 30]') === 2
    && str_contains($storeApi, "'duration_days' => \$quote['duration_days']")
);

$checkResult('Player Slots validation uses the selected Paymenter child value rather than the slider index',
    str_contains($storeJs, 'const players = readRangeValue(playersInput, 10);')
    && !str_contains($storeJs, 'const players = Number(playersInput.value);')
    && str_contains($storeJs, 'input.dataset.catalogValues = JSON.stringify(values);')
);

$checkResult('Player Slot quick presets resolve actual catalog values into slider indexes',
    str_contains($storeJs, 'setRangeToCatalogValue(playersInput, value)')
    && str_contains($storeJs, 'playersInput.value = String(value);')
    && str_contains($storeJs, 'button.dataset.playerPreset')
);

$checkResult('Storefront catalog response omits Paymenter internal product and Config Option IDs',
    str_contains($storeApi, "'config_options' => \$safeOptions")
    && str_contains($storeApi, "'plans' => \$safePlans")
    && !str_contains($storeApi, "'id' => (int) (\$plan['id']")
    && !str_contains($storeApi, "'id' => (int) (\$option['id']")
);
$checkResult('CS2 order client sends a verified ZarinPal receipt and resource/Egg configuration',
    str_contains($client, 'createCs2ServerOrder')
    && str_contains($client, "'amount_toman' => \$amountToman")
    && str_contains($client, "'amount_rial' => \$amountRial")
    && str_contains($client, "'memory_mb' => \$memoryMb")
    && str_contains($client, "'cpu_percent' => \$cpuPercent")
    && str_contains($client, "'feature_limits' => [")
    && str_contains($client, "'databases' => \$databases")
    && str_contains($client, "'additional_allocations' => \$additionalAllocations")
    && str_contains($client, "'backups' => \$backups")
    && str_contains($client, "'expected_amount_rial' => (int) \$expectedAmountRial")
    && str_contains($client, "'egg' => \$egg"));
$checkResult('CS2 order client surfaces native Config Option mapping and pricing errors safely',
    str_contains($client, "'cs2_config_option_mapping_invalid'")
    && str_contains($client, "'cs2_config_option_price_missing'")
);
$checkResult('Paymenter Wallet top-ups reserve capacity before ZarinPal payment creation',
    str_contains($client, 'prepareCustomerWalletTopup')
    && str_contains($client, "'/api/v1/fragcity/bridge/wallet/topups/prepare'")
    && str_contains($client, 'wallet_topup_reservation_expired')
    && str_contains($client, 'wallet_topup_reservation_missing')
);
$checkResult('Paymenter Wallet top-ups and wallet purchases use the private HMAC bridge',
    str_contains($client, 'createCustomerWalletTopup')
    && str_contains($client, '/api/v1/fragcity/bridge/wallet/topups/zarinpal')
    && str_contains($client, 'wallet_insufficient_credit')
);
$checkResult('CS2 catalogue and Paymenter wallet bridge methods are available server-side',
    str_contains($client, 'getCs2Catalog')
    && str_contains($client, 'getCustomerWalletBalance')
    && str_contains($client, 'wallet_enabled')
    && str_contains($client, 'minimum_deposit')
    && str_contains($client, "'/api/v1/fragcity/bridge/catalog/cs2'")
    && str_contains($client, "'/api/v1/fragcity/bridge/wallet/balance'")
);
$checkResult('Resource values are bounded locally and allowlisted by Paymenter Config Option children',
    str_contains($client, '$memoryMb > 1048576')
    && str_contains($client, '$diskMb > 1048576')
    && str_contains($client, '$cpuPercent > 6400')
    && !str_contains($client, '!in_array($memoryMb, [1024, 1280, 1536], true)')
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

<?php
declare(strict_types=1);

/**
 * FragCity server storefront API.
 *
 * Public action:
 *   GET  /api/server-store.php?action=catalog
 *
 * Authenticated actions:
 *   POST /api/server-store.php  {"action":"quote", ...selection}
 *   POST /api/server-store.php  {"action":"checkout", ...selection, checkout_ref, payment_method, expected_amount_rial}
 *   POST /api/server-store.php  {"action":"wallet_balance"}
 *
 * Verified ZarinPal callbacks are handled by pages/zarinpal/zarinpal_cs2_callback.php.
 *
 * The browser never calls Paymenter directly and never receives its HMAC
 * secret. Customer identity is always derived from the FragCity PHP session.
 */

require_once dirname(__DIR__) . '/settings/session.php';
require_once dirname(__DIR__) . '/settings/loader.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

$respond = static function (array $payload, int $status = 200, array $headers = []): never {
    http_response_code($status);
    foreach ($headers as $name => $value) {
        header($name . ': ' . $value);
    }
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
};

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? ''));
$action = '';

if ($method === 'GET') {
    $action = is_string($_GET['action'] ?? null) ? trim($_GET['action']) : '';
} elseif ($method === 'POST') {
    fragcity_start_session();

    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength > 8192) {
        $respond(['success' => false, 'error' => 'invalid_request'], 413);
    }

    $rawBody = file_get_contents('php://input');
    if (!is_string($rawBody) || strlen($rawBody) > 8192) {
        $respond(['success' => false, 'error' => 'invalid_request'], 400);
    }

    try {
        $payload = $rawBody === ''
            ? []
            : json_decode($rawBody, true, 16, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        $respond(['success' => false, 'error' => 'invalid_request'], 400);
    }

    if (!is_array($payload)) {
        $respond(['success' => false, 'error' => 'invalid_request'], 400);
    }

    $action = is_string($payload['action'] ?? null)
        ? trim($payload['action'])
        : '';
} else {
    header('Allow: GET, POST');
    $respond(['success' => false, 'error' => 'method_not_allowed'], 405);
}

if ($action === 'catalog') {
    if ($method !== 'GET') {
        $respond(['success' => false, 'error' => 'method_not_allowed'], 405);
    }

    // Catalog data is public storefront information. The private bridge secret
    // remains server-side and only safe presentation fields leave this endpoint.
    $cacheTtl = 20;
    $cacheData = null;
    $cachePath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'fragcity-cs2-catalog-'
        . hash('sha256', (string) fragcity_settings('integrations.paymenter_backend.base_url', ''))
        . '.json';
    $cacheHandle = @fopen($cachePath, 'c+');

    try {
        if ($cacheHandle !== false) {
            @flock($cacheHandle, LOCK_EX);
            $cachedRaw = stream_get_contents($cacheHandle);
            if (is_string($cachedRaw) && $cachedRaw !== '') {
                $cached = json_decode($cachedRaw, true);
                if (
                    is_array($cached)
                    && is_numeric($cached['expires_at'] ?? null)
                    && (int) $cached['expires_at'] > time()
                    && is_array($cached['data'] ?? null)
                ) {
                    $cacheData = $cached['data'];
                }
            }
        }

        if ($cacheData === null) {
            require_once dirname(__DIR__) . '/class/PaymenterApiClient.php';
            $catalog = (new PaymenterApiClient())->getCs2Catalog();

            $safePlans = [];
            foreach (($catalog['plans'] ?? []) as $plan) {
                if (!is_array($plan)) {
                    continue;
                }

                $days = filter_var($plan['duration_days'] ?? null, FILTER_VALIDATE_INT);
                if ($days === false || !in_array($days, [1, 7, 15, 30], true)) {
                    continue;
                }

                $safePlans[] = [
                    'duration_days' => (int) $days,
                    'name' => (string) ($plan['name'] ?? ''),
                    'billing_period' => (int) ($plan['billing_period'] ?? 0),
                    'billing_unit' => (string) ($plan['billing_unit'] ?? ''),
                    'available' => (bool) ($plan['available'] ?? false),
                    'price' => is_numeric($plan['price'] ?? null)
                        ? number_format((float) $plan['price'], 2, '.', '')
                        : null,
                    'setup_fee' => is_numeric($plan['setup_fee'] ?? null)
                        ? number_format((float) $plan['setup_fee'], 2, '.', '')
                        : null,
                    'currency_code' => strtoupper((string) ($plan['currency_code'] ?? $catalog['currency_code'] ?? '')),
                ];
            }

            $safeOptions = [];
            foreach (($catalog['config_options'] ?? []) as $option) {
                if (!is_array($option)) {
                    continue;
                }

                $safeChildren = [];
                foreach (($option['children'] ?? []) as $child) {
                    if (!is_array($child)) {
                        continue;
                    }

                    $prices = [];
                    foreach (($child['prices'] ?? []) as $days => $price) {
                        $dayKey = (string) $days;
                        if (!in_array($dayKey, ['1', '7', '15', '30'], true) || !is_array($price)) {
                            continue;
                        }
                        $prices[$dayKey] = [
                            'available' => (bool) ($price['available'] ?? false),
                            'price' => is_numeric($price['price'] ?? null)
                                ? number_format((float) $price['price'], 2, '.', '')
                                : null,
                            'setup_fee' => is_numeric($price['setup_fee'] ?? null)
                                ? number_format((float) $price['setup_fee'], 2, '.', '')
                                : null,
                        ];
                    }

                    $safeChildren[] = [
                        'name' => (string) ($child['name'] ?? ''),
                        'env_variable' => (string) ($child['env_variable'] ?? ''),
                        'prices' => $prices,
                    ];
                }

                $safeOptions[] = [
                    'name' => (string) ($option['name'] ?? ''),
                    'description' => (string) ($option['description'] ?? ''),
                    'env_variable' => (string) ($option['env_variable'] ?? ''),
                    'type' => (string) ($option['type'] ?? ''),
                    'upgradable' => (bool) ($option['upgradable'] ?? false),
                    'children' => $safeChildren,
                ];
            }

            $safeCheckoutFields = [];
            foreach (($catalog['checkout_fields'] ?? []) as $field) {
                if (!is_array($field)) {
                    continue;
                }
                $safeField = [];
                foreach (['name', 'label', 'type', 'description', 'required', 'default', 'validation', 'options'] as $key) {
                    if (array_key_exists($key, $field)) {
                        $safeField[$key] = $field[$key];
                    }
                }
                $safeCheckoutFields[] = $safeField;
            }

            $gameModes = is_array($catalog['game_mode_manager'] ?? null)
                ? $catalog['game_mode_manager']
                : [];
            $safeGameModes = [
                'cfg_options' => is_array($gameModes['cfg_options'] ?? null)
                    ? array_values($gameModes['cfg_options'])
                    : [],
                'map_group_options' => is_array($gameModes['map_group_options'] ?? null)
                    ? $gameModes['map_group_options']
                    : [],
            ];

            $product = is_array($catalog['product'] ?? null) ? $catalog['product'] : [];
            $safeData = [
                'product' => [
                    'name' => (string) ($product['name'] ?? 'CS2 Multimode Server'),
                    'description' => (string) ($product['description'] ?? ''),
                    'stock' => isset($product['stock']) ? (int) $product['stock'] : null,
                    'per_user_limit' => (int) ($product['per_user_limit'] ?? 0),
                ],
                'currency_code' => strtoupper((string) ($catalog['currency_code'] ?? '')),
                'currency_unit' => (string) ($catalog['currency_unit'] ?? 'rial'),
                'plans' => $safePlans,
                'config_options' => $safeOptions,
                'checkout_fields' => $safeCheckoutFields,
                'game_mode_manager' => $safeGameModes,
                'catalog_version' => (string) ($catalog['catalog_version'] ?? ''),
                'fetched_at' => gmdate('c'),
            ];

            $cacheData = $safeData;
            if ($cacheHandle !== false) {
                $cachePayload = json_encode(
                    ['expires_at' => time() + $cacheTtl, 'data' => $safeData],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                );
                rewind($cacheHandle);
                ftruncate($cacheHandle, 0);
                fwrite($cacheHandle, $cachePayload);
                fflush($cacheHandle);
            }
        }

        $respond(
            ['success' => true, 'data' => $cacheData],
            200,
            ['Cache-Control' => 'public, max-age=' . $cacheTtl]
        );
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $safeErrors = [
            'paymenter_unreachable',
            'paymenter_invalid_response',
            'cs2_checkout_not_configured',
            'cs2_checkout_manifest_unavailable',
            'cs2_catalog_unavailable',
            'cs2_plan_invalid',
            'cs2_product_invalid',
        ];
        $safeError = in_array($message, $safeErrors, true)
            ? $message
            : 'server_catalog_unavailable';

        error_log('[FragCity] Server catalog lookup failed; error=' . $safeError . '.');
        $status = in_array($safeError, ['cs2_checkout_not_configured', 'cs2_checkout_manifest_unavailable'], true)
            ? 503
            : 502;
        $respond(['success' => false, 'error' => $safeError], $status, ['Cache-Control' => 'no-store']);
    } finally {
        if ($cacheHandle !== false) {
            @flock($cacheHandle, LOCK_UN);
            fclose($cacheHandle);
        }
    }
}

if ($action === 'quote') {
    if ($method !== 'POST') {
        $respond(['success' => false, 'error' => 'method_not_allowed'], 405);
    }

    if (($_SESSION['user_logged_in'] ?? false) !== true) {
        $respond(['success' => false, 'error' => 'authentication_required'], 401);
    }

    $steamId64 = is_string($_SESSION['steamid'] ?? null)
        ? (string) $_SESSION['steamid']
        : '';
    if (!preg_match('/^[0-9]{17}$/D', $steamId64)) {
        $respond(['success' => false, 'error' => 'invalid_session'], 401);
    }

    $sessionCsrf = $_SESSION['auth_csrf_token'] ?? '';
    $submittedCsrf = (string) ($_SERVER['HTTP_X_FRAGCITY_CSRF'] ?? '');
    if (
        !is_string($sessionCsrf)
        || !preg_match('/^[a-f0-9]{64}$/D', $sessionCsrf)
        || !hash_equals($sessionCsrf, $submittedCsrf)
    ) {
        $respond(['success' => false, 'error' => 'csrf_failed'], 419);
    }

    $durationDays = filter_var($payload['duration_days'] ?? null, FILTER_VALIDATE_INT);
    $serverName = is_string($payload['server_name'] ?? null)
        ? trim($payload['server_name'])
        : '';
    $resourcesInput = is_array($payload['resources'] ?? null) ? $payload['resources'] : [];
    $eggInput = is_array($payload['egg'] ?? null) ? $payload['egg'] : [];

    $memoryMb = filter_var($resourcesInput['memory_mb'] ?? null, FILTER_VALIDATE_INT);
    $diskMb = filter_var($resourcesInput['disk_mb'] ?? null, FILTER_VALIDATE_INT);
    $cpuPercent = filter_var($resourcesInput['cpu_percent'] ?? null, FILTER_VALIDATE_INT);

    $allowedEggKeys = [
        'SRCDS_MAP',
        'SRCDS_MAXPLAYERS',
        'TICKRATE',
        'EXEC',
        'MAPGROUP',
        'COOLDOWN',
        'ENABLE_GUARD',
        'GUARD_BAN_MINUTES',
    ];
    if (array_diff(array_keys($eggInput), $allowedEggKeys) !== []) {
        $respond(['success' => false, 'error' => 'invalid_egg_variable'], 422);
    }

    $egg = [];
    foreach ($allowedEggKeys as $key) {
        if (array_key_exists($key, $eggInput)) {
            $egg[$key] = is_scalar($eggInput[$key]) ? (string) $eggInput[$key] : '';
        }
    }

    if (
        !in_array($durationDays, [1, 7, 15, 30], true)
        || !preg_match('/^[A-Za-z0-9 ]{1,64}$/D', $serverName)
        || $memoryMb === false || $memoryMb < 1 || $memoryMb > 1048576
        || $diskMb === false || $diskMb < 1 || $diskMb > 1048576
        || $cpuPercent === false || $cpuPercent < 1 || $cpuPercent > 6400
        || (string) ($egg['ENABLE_GUARD'] ?? '0') !== '0'
    ) {
        $respond(['success' => false, 'error' => 'validation_failed'], 422);
    }

    foreach (['SRCDS_MAP', 'SRCDS_MAXPLAYERS', 'TICKRATE', 'EXEC', 'MAPGROUP', 'COOLDOWN'] as $requiredKey) {
        if (!isset($egg[$requiredKey]) || $egg[$requiredKey] === '') {
            $respond(['success' => false, 'error' => 'validation_failed'], 422);
        }
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

        $accountStatement = $pdo->prepare(
            'SELECT paymenter_user_id, paymenter_sync_status
             FROM users
             WHERE steamid64 = ?
             LIMIT 1'
        );
        $accountStatement->execute([$steamId64]);
        $account = $accountStatement->fetch(PDO::FETCH_ASSOC);

        if (
            !is_array($account)
            || (int) ($account['paymenter_user_id'] ?? 0) < 1
            || (string) ($account['paymenter_sync_status'] ?? '') !== 'synced'
        ) {
            $respond(['success' => false, 'error' => 'paymenter_account_not_linked'], 409);
        }

        require_once dirname(__DIR__) . '/class/PaymenterApiClient.php';
        $quote = (new PaymenterApiClient())->quoteCs2ServerOrder([
            'steamid64' => $steamId64,
            'duration_days' => (int) $durationDays,
            'server_name' => $serverName,
            'resources' => [
                'memory_mb' => (int) $memoryMb,
                'disk_mb' => (int) $diskMb,
                'cpu_percent' => (int) $cpuPercent,
            ],
            // Feature limits are not yet configurable in the storefront.
            // Keep them pinned to the documented zero-cost defaults server-side.
            'feature_limits' => [
                'databases' => 0,
                'additional_allocations' => 0,
                'backups' => 0,
            ],
            'egg' => $egg,
        ]);

        $respond([
            'success' => true,
            'data' => [
                'currency_code' => $quote['currency_code'],
                'currency_unit' => 'rial',
                'amount_toman' => $quote['amount_toman'],
                'amount_rial' => $quote['amount_rial'],
                'recurring_subtotal_toman' => intdiv(max(0, $quote['recurring_subtotal_rial']), 10),
                'setup_fee_toman' => intdiv(max(0, $quote['setup_fee_total_rial']), 10),
                'tax_toman' => intdiv(max(0, $quote['tax_rial']), 10),
                'duration_days' => $quote['duration_days'],
                'price_lines' => array_map(
                    static function (array $line): array {
                        return [
                            'option' => (string) ($line['option'] ?? ''),
                            'value' => (string) ($line['value'] ?? ''),
                            'recurring_toman' => number_format(((float) ($line['recurring_price'] ?? 0)) / 10, 0, '.', ''),
                            'setup_fee_toman' => number_format(((float) ($line['setup_fee'] ?? 0)) / 10, 0, '.', ''),
                        ];
                    },
                    $quote['price_lines']
                ),
                'expires_in_seconds' => $quote['expires_in_seconds'],
            ],
        ], 200, ['Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0']);
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $safeErrors = [
            'fragcity_user_not_linked',
            'linked_paymenter_user_missing',
            'cs2_checkout_not_configured',
            'cs2_checkout_manifest_unavailable',
            'cs2_product_invalid',
            'cs2_product_out_of_stock',
            'cs2_product_user_limit',
            'cs2_plan_invalid',
            'cs2_plan_price_missing',
            'cs2_config_option_mapping_invalid',
            'cs2_config_option_price_missing',
            'cs2_quote_amount_mismatch',
            'egg_combination_invalid',
            'invalid_egg_variable',
            'egg_validation_failed',
            'paid_option_pricing_not_configured',
            'paymenter_unreachable',
            'paymenter_invalid_response',
            'bridge_not_configured',
            'source_ip_not_allowed',
            'invalid_signature_headers',
            'invalid_signature',
            'request_replayed',
        ];
        $safeError = in_array($message, $safeErrors, true)
            ? $message
            : 'cs2_quote_unavailable';

        error_log(
            '[FragCity] CS2 quote failed; steam_hash='
            . substr(hash('sha256', $steamId64), 0, 16)
            . '; error=' . $safeError . '.'
        );

        $status = match ($safeError) {
            'fragcity_user_not_linked', 'linked_paymenter_user_missing' => 409,
            'cs2_checkout_not_configured', 'cs2_checkout_manifest_unavailable' => 503,
            default => 502,
        };
        $respond(['success' => false, 'error' => $safeError], $status, ['Cache-Control' => 'no-store']);
    }
}

if ($action === 'checkout') {
    if ($method !== 'POST') {
        $respond(['success' => false, 'error' => 'method_not_allowed'], 405);
    }

    if (($_SESSION['user_logged_in'] ?? false) !== true) {
        $respond(['success' => false, 'error' => 'authentication_required'], 401);
    }

    $steamId64 = is_string($_SESSION['steamid'] ?? null) ? (string) $_SESSION['steamid'] : '';
    $sessionCsrf = $_SESSION['auth_csrf_token'] ?? '';
    $submittedCsrf = (string) ($_SERVER['HTTP_X_FRAGCITY_CSRF'] ?? '');
    if (
        !preg_match('/^[0-9]{17}$/D', $steamId64)
        || !is_string($sessionCsrf)
        || !preg_match('/^[a-f0-9]{64}$/D', $sessionCsrf)
        || !hash_equals($sessionCsrf, $submittedCsrf)
    ) {
        $respond(['success' => false, 'error' => 'authentication_or_csrf_failed'], 419);
    }

    $checkoutRef = is_string($payload['checkout_ref'] ?? null)
        ? strtolower(trim($payload['checkout_ref']))
        : '';
    $paymentMethod = is_string($payload['payment_method'] ?? null)
        ? strtolower(trim($payload['payment_method']))
        : '';
    $expectedAmountRial = filter_var($payload['expected_amount_rial'] ?? null, FILTER_VALIDATE_INT);

    $durationDays = filter_var($payload['duration_days'] ?? null, FILTER_VALIDATE_INT);
    $serverName = is_string($payload['server_name'] ?? null) ? trim($payload['server_name']) : '';
    $resourcesInput = is_array($payload['resources'] ?? null) ? $payload['resources'] : [];
    $eggInput = is_array($payload['egg'] ?? null) ? $payload['egg'] : [];
    $memoryMb = filter_var($resourcesInput['memory_mb'] ?? null, FILTER_VALIDATE_INT);
    $diskMb = filter_var($resourcesInput['disk_mb'] ?? null, FILTER_VALIDATE_INT);
    $cpuPercent = filter_var($resourcesInput['cpu_percent'] ?? null, FILTER_VALIDATE_INT);

    $allowedEggKeys = [
        'SRCDS_MAP',
        'SRCDS_MAXPLAYERS',
        'TICKRATE',
        'EXEC',
        'MAPGROUP',
        'COOLDOWN',
        'ENABLE_GUARD',
        'GUARD_BAN_MINUTES',
    ];
    if (
        !preg_match('/^[a-f0-9]{32}$/D', $checkoutRef)
        || !in_array($paymentMethod, ['wallet', 'zarinpal'], true)
        || $expectedAmountRial === false
        || $expectedAmountRial < 10
        || $expectedAmountRial % 10 !== 0
        || !in_array($durationDays, [1, 7, 15, 30], true)
        || !preg_match('/^[A-Za-z0-9 ]{1,64}$/D', $serverName)
        || $memoryMb === false || $memoryMb < 1 || $memoryMb > 1048576
        || $diskMb === false || $diskMb < 1 || $diskMb > 1048576
        || $cpuPercent === false || $cpuPercent < 1 || $cpuPercent > 6400
        || array_diff(array_keys($eggInput), $allowedEggKeys) !== []
        || (string) ($eggInput['ENABLE_GUARD'] ?? '0') !== '0'
    ) {
        $respond(['success' => false, 'error' => 'validation_failed'], 422);
    }

    $egg = [];
    foreach ($allowedEggKeys as $key) {
        if (array_key_exists($key, $eggInput)) {
            if (!is_scalar($eggInput[$key])) {
                $respond(['success' => false, 'error' => 'validation_failed'], 422);
            }
            $egg[$key] = (string) $eggInput[$key];
        }
    }
    foreach (['SRCDS_MAP', 'SRCDS_MAXPLAYERS', 'TICKRATE', 'EXEC', 'MAPGROUP', 'COOLDOWN'] as $requiredKey) {
        if (!isset($egg[$requiredKey]) || $egg[$requiredKey] === '') {
            $respond(['success' => false, 'error' => 'validation_failed'], 422);
        }
    }

    $orderInput = [
        'duration_days' => (int) $durationDays,
        'server_name' => $serverName,
        'resources' => [
            'memory_mb' => (int) $memoryMb,
            'disk_mb' => (int) $diskMb,
            'cpu_percent' => (int) $cpuPercent,
        ],
        'feature_limits' => [
            'databases' => 0,
            'additional_allocations' => 0,
            'backups' => 0,
        ],
        'egg' => $egg,
    ];

    try {
        $indexSettings = fragcity_settings('files.index', []);
        $databaseProfile = is_array($indexSettings)
            ? (string) ($indexSettings['database_profile'] ?? 'main')
            : 'main';
        $pdo = fragcity_create_pdo($databaseProfile);
        if (!$pdo instanceof PDO) {
            throw new RuntimeException('fragcity_account_unavailable');
        }

        $accountStatement = $pdo->prepare(
            'SELECT paymenter_user_id, paymenter_sync_status, username, email, phone
             FROM users WHERE steamid64 = ? LIMIT 1'
        );
        $accountStatement->execute([$steamId64]);
        $account = $accountStatement->fetch(PDO::FETCH_ASSOC);
        if (
            !is_array($account)
            || (int) ($account['paymenter_user_id'] ?? 0) < 1
            || (string) ($account['paymenter_sync_status'] ?? '') !== 'synced'
        ) {
            $respond(['success' => false, 'error' => 'paymenter_account_not_linked'], 409);
        }

        require_once dirname(__DIR__) . '/class/PaymenterApiClient.php';
        $paymenter = new PaymenterApiClient();

        // Reconcile an existing reference before requesting a fresh quote.
        // This preserves the original ZarinPal amount/Authority and lets an
        // interrupted wallet request replay the exact Paymenter order, even if
        // catalogue prices changed after the first attempt.
        $isExistingWalletRetry = false;
        $existingOrderStatement = $pdo->prepare(
            'SELECT * FROM fragcity_cs2_checkout_orders WHERE order_ref = ? LIMIT 1'
        );
        $existingOrderStatement->execute([$checkoutRef]);
        $existingOrder = $existingOrderStatement->fetch(PDO::FETCH_ASSOC);

        if (is_array($existingOrder)) {
            $savedPayload = json_decode((string) ($existingOrder['payload_json'] ?? ''), true);
            $selectionMatches = is_array($savedPayload)
                && (string) ($savedPayload['order_ref'] ?? '') === $checkoutRef
                && (string) ($savedPayload['steamid64'] ?? '') === $steamId64
                && (int) ($savedPayload['duration_days'] ?? 0) === (int) $durationDays
                && (string) ($savedPayload['server_name'] ?? '') === $serverName
                && ($savedPayload['resources'] ?? null) === $orderInput['resources']
                && ($savedPayload['feature_limits'] ?? null) === $orderInput['feature_limits']
                && ($savedPayload['egg'] ?? null) === $egg;

            if (
                !$selectionMatches
                || (string) ($existingOrder['steamid64'] ?? '') !== $steamId64
                || (string) ($existingOrder['payment_method'] ?? '') !== $paymentMethod
            ) {
                $respond(['success' => false, 'error' => 'checkout_reference_conflict'], 409, ['Cache-Control' => 'no-store']);
            }

            $existingStatus = (string) ($existingOrder['status'] ?? '');
            if ($existingStatus === 'accepted') {
                $respond([
                    'success' => true,
                    'status' => 'accepted',
                    'replayed' => true,
                    'data' => [
                        'order_id' => (int) $existingOrder['paymenter_order_id'],
                        'invoice_id' => (int) $existingOrder['paymenter_invoice_id'],
                        'service_id' => (int) $existingOrder['paymenter_service_id'],
                        'provisioning' => 'queued_by_paymenter',
                    ],
                ], 200, ['Cache-Control' => 'no-store']);
            }

            if ($paymentMethod === 'zarinpal' && $existingStatus === 'awaiting_payment') {
                $authority = (string) ($existingOrder['authority'] ?? '');
                $zarinpalSettings = fragcity_settings('integrations.zarinpal', []);
                $startPayBase = is_array($zarinpalSettings)
                    ? rtrim(trim((string) ($zarinpalSettings['startpay_base_url'] ?? '')), '/') . '/'
                    : '';
                $urlParts = parse_url($startPayBase);
                if (
                    $authority === ''
                    || !is_array($urlParts)
                    || strtolower((string) ($urlParts['scheme'] ?? '')) !== 'https'
                    || empty($urlParts['host'])
                ) {
                    $respond(['success' => false, 'error' => 'checkout_payment_url_unavailable'], 503, ['Cache-Control' => 'no-store']);
                }
                $respond([
                    'success' => true,
                    'status' => 'awaiting_payment',
                    'replayed' => true,
                    'payment_url' => $startPayBase . rawurlencode($authority),
                    'data' => [
                        'amount_toman' => (int) $existingOrder['amount_toman'],
                        'amount_rial' => (int) $existingOrder['amount_rial'],
                    ],
                ], 200, ['Cache-Control' => 'no-store']);
            }

            if ($existingStatus === 'payment_verified') {
                $respond(['success' => false, 'error' => 'payment_verified_provisioning_pending'], 409, ['Cache-Control' => 'no-store']);
            }
            if ($existingStatus === 'gateway_request_unknown' || $existingStatus === 'payment_requesting') {
                $respond(['success' => false, 'error' => 'payment_request_status_unknown'], 409, ['Cache-Control' => 'no-store']);
            }
            if (in_array($existingStatus, ['payment_failed', 'gateway_request_failed', 'cancelled'], true)) {
                $respond(['success' => false, 'error' => 'checkout_retry_with_new_reference'], 409, ['Cache-Control' => 'no-store']);
            }
            if ($existingStatus !== 'wallet_processing' || $paymentMethod !== 'wallet') {
                $respond(['success' => false, 'error' => 'checkout_not_retryable'], 409, ['Cache-Control' => 'no-store']);
            }

            $savedExpectedAmount = filter_var($savedPayload['expected_amount_rial'] ?? null, FILTER_VALIDATE_INT);
            $storedAmountRial = (int) ($existingOrder['amount_rial'] ?? 0);
            if (
                $savedExpectedAmount === false
                || $savedExpectedAmount < 10
                || $savedExpectedAmount !== $storedAmountRial
                || $storedAmountRial % 10 !== 0
                || intdiv($storedAmountRial, 10) !== (int) ($existingOrder['amount_toman'] ?? -1)
                || !hash_equals((string) ($existingOrder['payload_hash'] ?? ''), hash('sha256', (string) $existingOrder['payload_json']))
            ) {
                $respond(['success' => false, 'error' => 'checkout_record_invalid'], 409, ['Cache-Control' => 'no-store']);
            }

            $canonicalOrder = $savedPayload;
            $payloadJson = (string) $existingOrder['payload_json'];
            $payloadHash = (string) $existingOrder['payload_hash'];
            $amountToman = (int) $existingOrder['amount_toman'];
            $amountRial = $storedAmountRial;
            $currencyCode = (string) $existingOrder['currency_code'];
            $quote = [
                'amount_rial' => $amountRial,
                'amount_toman' => $amountToman,
                'currency_code' => $currencyCode,
                'price_lines' => [],
            ];
            $isExistingWalletRetry = true;
        }

        if (!$isExistingWalletRetry) {
            $quoteInput = $orderInput + ['steamid64' => $steamId64];
            $quote = $paymenter->quoteCs2ServerOrder($quoteInput);
            if ($quote['amount_rial'] !== $expectedAmountRial) {
            $respond([
                'success' => false,
                'error' => 'quote_changed',
                'data' => [
                    'amount_rial' => $quote['amount_rial'],
                    'amount_toman' => $quote['amount_toman'],
                    'currency_unit' => 'toman',
                    'duration_days' => $quote['duration_days'],
                    'price_lines' => $quote['price_lines'],
                ],
            ], 409, ['Cache-Control' => 'no-store']);
        }

            $canonicalOrder = [
                'order_ref' => $checkoutRef,
                'steamid64' => $steamId64,
                'duration_days' => (int) $durationDays,
                'server_name' => $serverName,
                'resources' => $orderInput['resources'],
                'feature_limits' => $orderInput['feature_limits'],
                'egg' => $egg,
                'expected_amount_rial' => $quote['amount_rial'],
            ];
            $payloadJson = json_encode(
                $canonicalOrder,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
            $payloadHash = hash('sha256', $payloadJson);
            $amountToman = (int) $quote['amount_toman'];
            $amountRial = (int) $quote['amount_rial'];
            $currencyCode = (string) $quote['currency_code'];
        }

        $pdo->beginTransaction();
        $lookup = $pdo->prepare(
            'SELECT * FROM fragcity_cs2_checkout_orders
             WHERE order_ref = ? LIMIT 1 FOR UPDATE'
        );
        $lookup->execute([$checkoutRef]);
        $existing = $lookup->fetch(PDO::FETCH_ASSOC);

        if (is_array($existing)) {
            if (
                !hash_equals((string) $existing['payload_hash'], $payloadHash)
                || (string) $existing['steamid64'] !== $steamId64
                || (string) $existing['payment_method'] !== $paymentMethod
                || (int) $existing['amount_rial'] !== $amountRial
            ) {
                $pdo->rollBack();
                $respond(['success' => false, 'error' => 'checkout_reference_conflict'], 409);
            }

            $existingStatus = (string) $existing['status'];
            if ($existingStatus === 'accepted') {
                $pdo->commit();
                $respond([
                    'success' => true,
                    'status' => 'accepted',
                    'replayed' => true,
                    'data' => [
                        'order_id' => (int) $existing['paymenter_order_id'],
                        'invoice_id' => (int) $existing['paymenter_invoice_id'],
                        'service_id' => (int) $existing['paymenter_service_id'],
                        'provisioning' => 'queued_by_paymenter',
                    ],
                ]);
            }
            if ($paymentMethod === 'zarinpal' && $existingStatus === 'awaiting_payment') {
                $authority = (string) ($existing['authority'] ?? '');
                $settings = fragcity_settings('integrations.zarinpal', []);
                $startPayBase = is_array($settings)
                    ? rtrim(trim((string) ($settings['startpay_base_url'] ?? '')), '/') . '/'
                    : '';
                $pdo->commit();
                if ($authority === '' || $startPayBase === '/') {
                    $respond(['success' => false, 'error' => 'checkout_payment_url_unavailable'], 503);
                }
                $respond([
                    'success' => true,
                    'status' => 'awaiting_payment',
                    'replayed' => true,
                    'payment_url' => $startPayBase . rawurlencode($authority),
                    'data' => ['amount_toman' => $amountToman, 'amount_rial' => $amountRial],
                ], 200, ['Cache-Control' => 'no-store']);
            }
            if ($paymentMethod === 'wallet' && $existingStatus === 'wallet_processing') {
                // Paymenter order_ref idempotency makes retrying the exact same
                // payload safe even if the previous response was lost.
                $pdo->commit();
            } else {
                $pdo->commit();
                $respond([
                    'success' => false,
                    'error' => match ($existingStatus) {
                        'payment_verified' => 'payment_verified_provisioning_pending',
                        'gateway_request_unknown' => 'payment_request_status_unknown',
                        'payment_requesting' => 'checkout_in_progress',
                        'cancelled' => 'checkout_cancelled_use_new_reference',
                        default => 'checkout_not_retryable',
                    },
                ], 409, ['Cache-Control' => 'no-store']);
            }
        } else {
            $initialStatus = $paymentMethod === 'wallet' ? 'wallet_processing' : 'payment_requesting';
            $insert = $pdo->prepare(
                'INSERT INTO fragcity_cs2_checkout_orders
                    (order_ref, steamid64, payload_hash, payload_json, payment_method,
                     amount_toman, amount_rial, currency_code, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insert->execute([
                $checkoutRef,
                $steamId64,
                $payloadHash,
                $payloadJson,
                $paymentMethod,
                $amountToman,
                $amountRial,
                $currencyCode,
                $initialStatus,
            ]);
            $pdo->commit();
        }

        if ($paymentMethod === 'wallet') {
            try {
                $result = $paymenter->createCs2ServerOrder([
                    ...$canonicalOrder,
                    'payment' => ['provider' => 'wallet'],
                ]);
                $save = $pdo->prepare(
                    "UPDATE fragcity_cs2_checkout_orders
                     SET status = 'accepted', paymenter_order_id = ?, paymenter_invoice_id = ?,
                         paymenter_service_id = ?, accepted_at = CURRENT_TIMESTAMP, last_error = NULL
                     WHERE order_ref = ? AND status = 'wallet_processing'"
                );
                $save->execute([
                    $result['order_id'], $result['invoice_id'], $result['service_id'], $checkoutRef,
                ]);
                if ($save->rowCount() !== 1) {
                    throw new RuntimeException('checkout_state_save_failed');
                }
                $respond([
                    'success' => true,
                    'status' => 'accepted',
                    'replayed' => (bool) $result['replayed'],
                    'data' => [
                        'order_id' => $result['order_id'],
                        'invoice_id' => $result['invoice_id'],
                        'service_id' => $result['service_id'],
                        'provisioning' => $result['provisioning'],
                        'amount_toman' => $amountToman,
                    ],
                ], 200, ['Cache-Control' => 'no-store']);
            } catch (Throwable $e) {
                $safeError = preg_match('/^[a-z0-9_]{1,96}$/D', $e->getMessage())
                    ? $e->getMessage()
                    : 'paymenter_order_unavailable';
                $saveError = $pdo->prepare(
                    "UPDATE fragcity_cs2_checkout_orders SET last_error = ?
                     WHERE order_ref = ? AND status = 'wallet_processing'"
                );
                $saveError->execute([$safeError, $checkoutRef]);
                error_log('[FragCity] CS2 wallet checkout failed; order=' . $checkoutRef . '; error=' . $safeError . '.');
                $respond([
                    'success' => false,
                    'error' => $safeError,
                    'retryable' => true,
                    'message' => 'نتیجه ثبت سفارش از Paymenter قطعی نشد. همان درخواست را دوباره ارسال کنید؛ برداشت تکراری انجام نمی‌شود.',
                ], 502, ['Cache-Control' => 'no-store']);
            }
        }

        $zarinpalSettings = fragcity_settings('integrations.zarinpal', []);
        $merchantId = is_array($zarinpalSettings) ? trim((string) ($zarinpalSettings['merchant_id'] ?? '')) : '';
        $requestUrl = is_array($zarinpalSettings) ? trim((string) ($zarinpalSettings['request_url'] ?? '')) : '';
        $startPayBase = is_array($zarinpalSettings)
            ? rtrim(trim((string) ($zarinpalSettings['startpay_base_url'] ?? '')), '/') . '/'
            : '';
        $siteBaseUrl = rtrim(trim((string) fragcity_settings('app.site_base_url', '')), '/');
        $validHttpsUrl = static function (string $url): bool {
            $parts = parse_url($url);
            return is_array($parts)
                && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
                && !empty($parts['host'])
                && !isset($parts['user'])
                && !isset($parts['pass'])
                && !isset($parts['query'])
                && !isset($parts['fragment']);
        };
        if (
            $merchantId === ''
            || !$validHttpsUrl($requestUrl)
            || !$validHttpsUrl($startPayBase)
            || !$validHttpsUrl($siteBaseUrl)
            || !function_exists('curl_init')
        ) {
            $save = $pdo->prepare(
                "UPDATE fragcity_cs2_checkout_orders SET status = 'gateway_request_failed',
                    last_error = 'zarinpal_not_configured'
                 WHERE order_ref = ? AND status = 'payment_requesting'"
            );
            $save->execute([$checkoutRef]);
            $respond(['success' => false, 'error' => 'zarinpal_not_configured'], 503);
        }

        $requestBody = json_encode([
            'merchant_id' => $merchantId,
            'amount' => $amountRial,
            'callback_url' => $siteBaseUrl . '/pages/zarinpal/zarinpal_cs2_callback.php',
            'description' => 'FragCity CS2 server ' . $checkoutRef,
            'metadata' => [
                'email' => trim((string) ($account['email'] ?? '')),
                'mobile' => trim((string) ($account['phone'] ?? '')),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $curl = curl_init($requestUrl);
        if ($curl === false) {
            $save = $pdo->prepare(
                "UPDATE fragcity_cs2_checkout_orders SET status = 'gateway_request_unknown',
                    last_error = 'zarinpal_curl_init_failed'
                 WHERE order_ref = ? AND status = 'payment_requesting'"
            );
            $save->execute([$checkoutRef]);
            $respond(['success' => false, 'error' => 'zarinpal_request_unknown'], 502);
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $requestBody,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        $gatewayBody = curl_exec($curl);
        $curlError = curl_errno($curl);
        $httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if (!is_string($gatewayBody) || $curlError !== 0 || $httpStatus < 200 || $httpStatus >= 300) {
            $save = $pdo->prepare(
                "UPDATE fragcity_cs2_checkout_orders SET status = 'gateway_request_unknown',
                    last_error = 'zarinpal_transport_unknown'
                 WHERE order_ref = ? AND status = 'payment_requesting'"
            );
            $save->execute([$checkoutRef]);
            $respond([
                'success' => false,
                'error' => 'zarinpal_request_unknown',
                'message' => 'وضعیت درخواست درگاه قطعی نیست؛ برای جلوگیری از پرداخت تکراری، درخواست جدید ساخته نشد.',
            ], 502, ['Cache-Control' => 'no-store']);
        }

        $gatewayResponse = json_decode($gatewayBody, true, 32);
        $gatewayCode = is_array($gatewayResponse)
            ? filter_var($gatewayResponse['data']['code'] ?? null, FILTER_VALIDATE_INT)
            : false;
        $authority = is_array($gatewayResponse) ? ($gatewayResponse['data']['authority'] ?? null) : null;
        if (
            $gatewayCode !== 100
            || !is_string($authority)
            || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $authority)
        ) {
            $save = $pdo->prepare(
                "UPDATE fragcity_cs2_checkout_orders SET status = 'gateway_request_failed',
                    last_error = 'zarinpal_request_rejected'
                 WHERE order_ref = ? AND status = 'payment_requesting'"
            );
            $save->execute([$checkoutRef]);
            $respond(['success' => false, 'error' => 'zarinpal_request_rejected'], 502);
        }

        $save = $pdo->prepare(
            "UPDATE fragcity_cs2_checkout_orders SET authority = ?, status = 'awaiting_payment',
                last_error = NULL
             WHERE order_ref = ? AND status = 'payment_requesting'"
        );
        $save->execute([$authority, $checkoutRef]);
        if ($save->rowCount() !== 1) {
            throw new RuntimeException('checkout_authority_not_saved');
        }

        $respond([
            'success' => true,
            'status' => 'awaiting_payment',
            'payment_url' => $startPayBase . rawurlencode($authority),
            'data' => ['amount_toman' => $amountToman, 'amount_rial' => $amountRial],
        ], 200, ['Cache-Control' => 'no-store']);
    } catch (Throwable $e) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = $e->getMessage();
        $safeError = preg_match('/^[a-z0-9_]{1,96}$/D', $message)
            ? $message
            : 'cs2_checkout_unavailable';
        error_log('[FragCity] CS2 checkout failed; steam_hash=' . substr(hash('sha256', $steamId64), 0, 16) . '; error=' . $safeError . '.');
        $respond(['success' => false, 'error' => $safeError], 502, ['Cache-Control' => 'no-store']);
    }
}

if ($action === 'wallet_balance') {
    if ($method !== 'POST') {
        $respond(['success' => false, 'error' => 'method_not_allowed'], 405);
    }

    if (($_SESSION['user_logged_in'] ?? false) !== true) {
        $respond(['success' => false, 'error' => 'authentication_required'], 401);
    }

    $steamId64 = is_string($_SESSION['steamid'] ?? null)
        ? (string) $_SESSION['steamid']
        : '';
    if (!preg_match('/^[0-9]{17}$/D', $steamId64)) {
        $respond(['success' => false, 'error' => 'invalid_session'], 401);
    }

    $sessionCsrf = $_SESSION['auth_csrf_token'] ?? '';
    $submittedCsrf = (string) ($_SERVER['HTTP_X_FRAGCITY_CSRF'] ?? '');
    if (
        !is_string($sessionCsrf)
        || !preg_match('/^[a-f0-9]{64}$/D', $sessionCsrf)
        || !hash_equals($sessionCsrf, $submittedCsrf)
    ) {
        $respond(['success' => false, 'error' => 'csrf_failed'], 419);
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

        $accountStatement = $pdo->prepare(
            'SELECT paymenter_user_id, paymenter_sync_status
             FROM users
             WHERE steamid64 = ?
             LIMIT 1'
        );
        $accountStatement->execute([$steamId64]);
        $account = $accountStatement->fetch(PDO::FETCH_ASSOC);

        if (
            !is_array($account)
            || (int) ($account['paymenter_user_id'] ?? 0) < 1
            || (string) ($account['paymenter_sync_status'] ?? '') !== 'synced'
        ) {
            $respond(['success' => false, 'error' => 'paymenter_account_not_linked'], 409);
        }

        require_once dirname(__DIR__) . '/class/PaymenterApiClient.php';
        $wallet = (new PaymenterApiClient())->getCustomerWalletBalance($steamId64);

        $respond([
            'success' => true,
            'data' => [
                'currency_code' => $wallet['currency_code'],
                'currency_unit' => $wallet['currency_unit'],
                'balance' => $wallet['balance'],
                'wallet_enabled' => $wallet['wallet_enabled'],
                'limits' => $wallet['limits'],
            ],
        ], 200, ['Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0']);
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $safeErrors = [
            'fragcity_user_not_linked',
            'linked_paymenter_user_missing',
            'wallet_balance_unavailable',
            'paymenter_unreachable',
            'paymenter_invalid_response',
            'bridge_not_configured',
            'source_ip_not_allowed',
            'invalid_signature_headers',
            'invalid_signature',
            'request_replayed',
        ];
        $safeError = in_array($message, $safeErrors, true)
            ? $message
            : 'wallet_balance_unavailable';

        error_log(
            '[FragCity] Wallet balance lookup failed; steam_hash='
            . substr(hash('sha256', $steamId64), 0, 16)
            . '; error=' . $safeError . '.'
        );

        $status = match ($safeError) {
            'fragcity_user_not_linked', 'linked_paymenter_user_missing' => 409,
            default => 502,
        };
        $respond(['success' => false, 'error' => $safeError], $status, ['Cache-Control' => 'no-store']);
    }
}

$respond(['success' => false, 'error' => 'invalid_action'], 400);

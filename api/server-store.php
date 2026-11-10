<?php
declare(strict_types=1);

/**
 * FragCity server storefront API.
 *
 * Public action:
 *   GET  /api/server-store.php?action=catalog
 *
 * Authenticated action:
 *   POST /api/server-store.php  {"action":"wallet_balance"}
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

<?php
declare(strict_types=1);

require_once __DIR__ . '/../settings/loader.php';

/**
 * Private HMAC client for the FragCityAPI add-on in Paymenter.
 *
 * Never expose this client or its shared secret to browser JavaScript.
 */
final class PaymenterApiClient
{
    private string $baseUrl;
    private string $sharedSecret;

    public function __construct(?string $baseUrl = null, ?string $sharedSecret = null)
    {
        $baseUrl = trim($baseUrl ?? (string) fragcity_settings('integrations.paymenter_backend.base_url', ''));
        $sharedSecret = trim($sharedSecret ?? (string) fragcity_settings('integrations.paymenter_backend.shared_secret', ''));

        $parts = parse_url($baseUrl);
        if (
            !is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/')
        ) {
            throw new RuntimeException('Paymenter API base URL must be an HTTPS origin without a path.');
        }

        if (
            !preg_match('/^[a-f0-9]{64,256}$/iD', $sharedSecret)
            || preg_match('/[\r\n\x00-\x1F\x7F]/', $sharedSecret)
        ) {
            throw new RuntimeException('Paymenter bridge shared secret is missing or invalid.');
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is required for Paymenter API transport.');
        }

        $this->baseUrl = rtrim($baseUrl, '/');
        $this->sharedSecret = $sharedSecret;
    }

    /**
     * Verify TLS, route availability and HMAC configuration without changing
     * Paymenter records.
     *
     * @return array{http_status:int,service:string,version:string}
     */
    public function testConnection(): array
    {
        $response = $this->post('/api/v1/fragcity/bridge/ping', []);

        return [
            'http_status' => 200,
            'service' => (string) ($response['service'] ?? ''),
            'version' => (string) ($response['version'] ?? ''),
        ];
    }

    /**
     * Read the configured CS2 product, plans, prices, native Config Options
     * and checkout field manifest from Paymenter's private FragCity bridge.
     *
     * @return array<string,mixed>
     */
    public function getCs2Catalog(): array
    {
        $response = $this->post('/api/v1/fragcity/bridge/catalog/cs2', []);
        $data = $response['data'] ?? null;

        if (
            !is_array($data)
            || !is_array($data['product'] ?? null)
            || !isset($data['product']['id'])
            || !is_array($data['plans'] ?? null)
            || !is_array($data['config_options'] ?? null)
            || !is_array($data['checkout_fields'] ?? null)
        ) {
            throw new RuntimeException('paymenter_invalid_response');
        }

        return $data;
    }

    /**
     * Return the linked customer's Paymenter wallet balance in the configured
     * CS2 currency. The Paymenter user ID is resolved only on the private bridge.
     *
     * @return array{
     *   currency_code:string,
     *   currency_unit:string,
     *   balance:string,
     *   wallet_enabled:bool,
     *   limits:array{minimum_deposit:string,maximum_deposit:string,maximum_credit:string}
     * }
     */
    /**
     * Request a read-only, authoritative CS2 checkout quote from Paymenter.
     * Selection values are sent; prices, tax and totals are resolved by Paymenter.
     *
     * @param array<string,mixed> $quote
     * @return array<string,mixed>
     */
    public function quoteCs2ServerOrder(array $quote): array
    {
        $steamId64 = trim((string) ($quote['steamid64'] ?? ''));
        $durationDays = filter_var($quote['duration_days'] ?? null, FILTER_VALIDATE_INT);
        $serverName = trim((string) ($quote['server_name'] ?? ''));
        $resources = $quote['resources'] ?? [];
        $featureLimits = $quote['feature_limits'] ?? [];
        $egg = $quote['egg'] ?? [];

        if (
            !preg_match('/^[0-9]{17}$/D', $steamId64)
            || !in_array($durationDays, [1, 7, 15, 30], true)
            || !preg_match('/^[A-Za-z0-9 ]{1,64}$/D', $serverName)
            || !is_array($resources)
            || !is_array($featureLimits)
            || !is_array($egg)
        ) {
            throw new RuntimeException('validation_failed');
        }

        foreach (['memory_mb', 'disk_mb', 'cpu_percent'] as $key) {
            if (filter_var($resources[$key] ?? null, FILTER_VALIDATE_INT) === false) {
                throw new RuntimeException('validation_failed');
            }
        }

        foreach (['databases', 'additional_allocations', 'backups'] as $key) {
            if (filter_var($featureLimits[$key] ?? 0, FILTER_VALIDATE_INT) === false) {
                throw new RuntimeException('validation_failed');
            }
        }

        $payload = [
            'steamid64' => $steamId64,
            'duration_days' => (int) $durationDays,
            'server_name' => $serverName,
            'resources' => [
                'memory_mb' => (int) $resources['memory_mb'],
                'disk_mb' => (int) $resources['disk_mb'],
                'cpu_percent' => (int) $resources['cpu_percent'],
            ],
            'feature_limits' => [
                'databases' => (int) ($featureLimits['databases'] ?? 0),
                'additional_allocations' => (int) ($featureLimits['additional_allocations'] ?? 0),
                'backups' => (int) ($featureLimits['backups'] ?? 0),
            ],
            'egg' => $egg,
        ];

        $response = $this->post('/api/v1/fragcity/bridge/quotes/cs2', $payload);
        $data = $response['data'] ?? null;
        $amountRial = is_array($data)
            ? filter_var($data['amount_rial'] ?? null, FILTER_VALIDATE_INT)
            : false;
        $amountToman = is_array($data)
            ? filter_var($data['amount_toman'] ?? null, FILTER_VALIDATE_INT)
            : false;

        if (
            !is_array($data)
            || $amountRial === false
            || $amountToman === false
            || $amountRial < 10
            || $amountRial % 10 !== 0
            || $amountToman !== intdiv($amountRial, 10)
            || strtoupper((string) ($data['currency_code'] ?? '')) === ''
            || ($data['currency_unit'] ?? null) !== 'rial'
            || !is_array($data['price_lines'] ?? null)
        ) {
            throw new RuntimeException('paymenter_invalid_response');
        }

        return [
            'currency_code' => strtoupper((string) $data['currency_code']),
            'currency_unit' => 'rial',
            'amount_rial' => $amountRial,
            'amount_toman' => $amountToman,
            'recurring_subtotal_rial' => (int) ($data['recurring_subtotal_rial'] ?? 0),
            'setup_fee_total_rial' => (int) ($data['setup_fee_total_rial'] ?? 0),
            'tax_rial' => (int) ($data['tax_rial'] ?? 0),
            'duration_days' => (int) $data['duration_days'],
            'price_lines' => $data['price_lines'],
            'expires_in_seconds' => max(1, (int) ($data['expires_in_seconds'] ?? 60)),
        ];
    }

    public function getCustomerWalletBalance(string $steamId64): array
    {
        if (!preg_match('/^[0-9]{17}$/D', $steamId64)) {
            throw new RuntimeException('invalid_steamid');
        }

        $response = $this->post(
            '/api/v1/fragcity/bridge/wallet/balance',
            ['steamid64' => $steamId64]
        );
        $data = $response['data'] ?? null;

        if (
            !is_array($data)
            || !is_string($data['currency_code'] ?? null)
            || !is_string($data['currency_unit'] ?? null)
            || !is_numeric($data['balance'] ?? null)
            || (float) $data['balance'] < 0
            || !is_bool($data['wallet_enabled'] ?? null)
            || !is_array($data['limits'] ?? null)
            || !is_numeric($data['limits']['minimum_deposit'] ?? null)
            || !is_numeric($data['limits']['maximum_deposit'] ?? null)
            || !is_numeric($data['limits']['maximum_credit'] ?? null)
        ) {
            throw new RuntimeException('paymenter_invalid_response');
        }

        return [
            'currency_code' => strtoupper($data['currency_code']),
            'currency_unit' => $data['currency_unit'],
            'balance' => number_format((float) $data['balance'], 2, '.', ''),
            'wallet_enabled' => $data['wallet_enabled'],
            'limits' => [
                'minimum_deposit' => number_format((float) $data['limits']['minimum_deposit'], 2, '.', ''),
                'maximum_deposit' => number_format((float) $data['limits']['maximum_deposit'], 2, '.', ''),
                'maximum_credit' => number_format((float) $data['limits']['maximum_credit'], 2, '.', ''),
            ],
        ];
    }

    /**
     * Create or synchronize the Paymenter account associated with a SteamID64.
     *
     * @param array{
     *   steamid64:string,
     *   username:string,
     *   email:string,
     *   phone:string,
     *   first_name:string,
     *   last_name:string,
     *   password:string
     * } $user
     * @return array{user_id:int,created:bool,email:string}
     */
    public function syncUser(array $user): array
    {
        $response = $this->post('/api/v1/fragcity/bridge/users/sync', $user);
        $data = $response['data'] ?? null;

        if (
            !is_array($data)
            || !isset($data['user_id'])
            || !is_numeric($data['user_id'])
            || (int) $data['user_id'] < 1
        ) {
            throw new RuntimeException('Paymenter bridge returned an invalid user synchronization response.');
        }

        return [
            'user_id' => (int) $data['user_id'],
            'created' => (bool) ($data['created'] ?? false),
            'email' => (string) ($data['email'] ?? ''),
        ];
    }

    /**
     * Reserve the customer's Paymenter wallet capacity before creating a
     * ZarinPal payment request. Call this before redirecting the customer to
     * the gateway, then settle the same topup_ref after server-side Verify.
     *
     * @param array<string,mixed> $topup
     * @return array{topup_ref:string,status:string,invoice_id:?int,expires_at:?string,replayed:bool,currency_code:string,currency_unit:string}
     */
    public function prepareCustomerWalletTopup(array $topup): array
    {
        $topupRef = strtolower(trim((string) ($topup['topup_ref'] ?? '')));
        $steamId64 = trim((string) ($topup['steamid64'] ?? ''));
        $amountToman = filter_var($topup['amount_toman'] ?? null, FILTER_VALIDATE_INT);
        $amountRial = filter_var($topup['amount_rial'] ?? null, FILTER_VALIDATE_INT);

        if (
            !preg_match('/^[a-f0-9]{32}$/D', $topupRef)
            || !preg_match('/^[0-9]{17}$/D', $steamId64)
            || $amountToman === false
            || $amountRial === false
            || $amountToman < 1
            || $amountToman > intdiv(PHP_INT_MAX, 10)
            || $amountRial !== $amountToman * 10
        ) {
            throw new RuntimeException('validation_failed');
        }

        $response = $this->post('/api/v1/fragcity/bridge/wallet/topups/prepare', [
            'topup_ref' => $topupRef,
            'steamid64' => $steamId64,
            'amount_toman' => $amountToman,
            'amount_rial' => $amountRial,
        ]);

        $data = $response['data'] ?? null;
        $status = is_array($data) ? (string) ($data['status'] ?? '') : '';
        $invoiceIdRaw = is_array($data) ? ($data['invoice_id'] ?? null) : null;
        $invoiceId = $invoiceIdRaw === null
            ? null
            : filter_var($invoiceIdRaw, FILTER_VALIDATE_INT);
        $expiresAt = is_array($data) && is_string($data['expires_at'] ?? null)
            ? $data['expires_at']
            : null;

        if (
            !is_array($data)
            || ($data['topup_ref'] ?? null) !== $topupRef
            || !in_array($status, ['prepared', 'accepted'], true)
            || !is_string($data['currency_code'] ?? null)
            || strtoupper($data['currency_code']) === ''
            || ($data['currency_unit'] ?? null) !== 'rial'
            || ($status === 'prepared' && ($invoiceId !== null || $expiresAt === null))
            || ($status === 'accepted' && ($invoiceId === false || $invoiceId === null || $invoiceId < 1))
        ) {
            throw new RuntimeException('paymenter_invalid_response');
        }

        return [
            'topup_ref' => $topupRef,
            'status' => $status,
            'invoice_id' => $invoiceId === false ? null : $invoiceId,
            'expires_at' => $expiresAt,
            'replayed' => (bool) ($data['replayed'] ?? false),
            'currency_code' => strtoupper($data['currency_code']),
            'currency_unit' => 'rial',
        ];
    }

    /**
     * Apply a verified ZarinPal wallet deposit to the linked Paymenter account.
     * This method must only be called by the authenticated website backend
     * after the payment provider's server-side Verify API has succeeded.
     *
     * @param array<string,mixed> $topup
     * @return array{topup_ref:string,invoice_id:int,status:string,replayed:bool,currency_code:string,currency_unit:string,wallet_balance:string}
     */
    public function createCustomerWalletTopup(array $topup): array
    {
        $topupRef = strtolower(trim((string) ($topup['topup_ref'] ?? '')));
        $steamId64 = trim((string) ($topup['steamid64'] ?? ''));
        $payment = $topup['payment'] ?? [];

        if (
            !preg_match('/^[a-f0-9]{32}$/D', $topupRef)
            || !preg_match('/^[0-9]{17}$/D', $steamId64)
            || !is_array($payment)
            || ($payment['provider'] ?? null) !== 'zarinpal'
        ) {
            throw new RuntimeException('validation_failed');
        }

        $amountToman = filter_var($payment['amount_toman'] ?? null, FILTER_VALIDATE_INT);
        $amountRial = filter_var($payment['amount_rial'] ?? null, FILTER_VALIDATE_INT);
        $authority = trim((string) ($payment['authority'] ?? ''));
        $refId = trim((string) ($payment['ref_id'] ?? ''));

        if (
            $amountToman === false
            || $amountRial === false
            || $amountToman < 1
            || $amountToman > intdiv(PHP_INT_MAX, 10)
            || $amountRial !== $amountToman * 10
            || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $authority)
            || !preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $refId)
        ) {
            throw new RuntimeException('validation_failed');
        }

        $response = $this->post('/api/v1/fragcity/bridge/wallet/topups/zarinpal', [
            'topup_ref' => $topupRef,
            'steamid64' => $steamId64,
            'payment' => [
                'provider' => 'zarinpal',
                'authority' => $authority,
                'ref_id' => $refId,
                'amount_toman' => $amountToman,
                'amount_rial' => $amountRial,
            ],
        ]);

        $data = $response['data'] ?? null;
        if (
            !is_array($data)
            || ($data['topup_ref'] ?? null) !== $topupRef
            || !isset($data['invoice_id'])
            || !is_numeric($data['invoice_id'])
            || (int) $data['invoice_id'] < 1
            || ($data['status'] ?? null) !== 'accepted'
            || !is_numeric($data['wallet_balance'] ?? null)
            || (float) $data['wallet_balance'] < 0
        ) {
            throw new RuntimeException('paymenter_invalid_response');
        }

        return [
            'topup_ref' => $topupRef,
            'invoice_id' => (int) $data['invoice_id'],
            'status' => 'accepted',
            'replayed' => (bool) ($data['replayed'] ?? false),
            'currency_code' => strtoupper((string) ($data['currency_code'] ?? '')),
            'currency_unit' => (string) ($data['currency_unit'] ?? ''),
            'wallet_balance' => number_format((float) $data['wallet_balance'], 2, '.', ''),
        ];
    }

    /**
     * Submit a CS2 server order using either a verified ZarinPal receipt or
     * the linked customer's Paymenter Wallet balance.
     *
     * This method is private server-side transport only. Never call it from
     * browser JavaScript, and never pass an unverified browser callback here.
     *
     * @param array<string,mixed> $order
     * @return array{order_id:int,invoice_id:int,service_id:int,status:string,replayed:bool,provisioning:string,currency_code:string,currency_unit:string}
     */
    public function createCs2ServerOrder(array $order): array
    {
        $orderRef = strtolower(trim((string) ($order['order_ref'] ?? '')));
        $steamId64 = trim((string) ($order['steamid64'] ?? ''));
        $payment = $order['payment'] ?? [];
        $resources = $order['resources'] ?? [];
        $featureLimits = $order['feature_limits'] ?? [];
        $egg = $order['egg'] ?? [];

        if (
            !preg_match('/^[a-f0-9]{32}$/D', $orderRef)
            || !preg_match('/^[0-9]{17}$/D', $steamId64)
            || !is_array($payment)
            || !in_array(($payment['provider'] ?? null), ['zarinpal', 'wallet'], true)
            || !is_array($resources)
            || !is_array($featureLimits)
            || !is_array($egg)
        ) {
            throw new RuntimeException('validation_failed');
        }

        $paymentProvider = (string) $payment['provider'];
        $isZarinpal = $paymentProvider === 'zarinpal';
        $amountToman = $isZarinpal
            ? filter_var($payment['amount_toman'] ?? null, FILTER_VALIDATE_INT)
            : 0;
        $amountRial = $isZarinpal
            ? filter_var($payment['amount_rial'] ?? null, FILTER_VALIDATE_INT)
            : 0;
        $expectedAmountRial = array_key_exists('expected_amount_rial', $order)
            ? filter_var($order['expected_amount_rial'], FILTER_VALIDATE_INT)
            : null;
        $durationDays = filter_var($order['duration_days'] ?? null, FILTER_VALIDATE_INT);
        $authority = $isZarinpal ? trim((string) ($payment['authority'] ?? '')) : '';
        $refId = $isZarinpal ? trim((string) ($payment['ref_id'] ?? '')) : '';
        $serverName = trim((string) ($order['server_name'] ?? ''));

        if (
            ($isZarinpal && (
                $amountToman === false
                || $amountRial === false
                || $amountToman < 1
                || $amountToman > intdiv(PHP_INT_MAX, 10)
                || $amountRial !== $amountToman * 10
                || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $authority)
                || !preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $refId)
            ))
            || ($expectedAmountRial !== null && ($expectedAmountRial === false || $expectedAmountRial < 10 || $expectedAmountRial % 10 !== 0))
            || !in_array($durationDays, [1, 7, 15, 30], true)
            || !preg_match('/^[A-Za-z0-9 ]{1,64}$/D', $serverName)
        ) {
            throw new RuntimeException('validation_failed');
        }

        $memoryMb = filter_var($resources['memory_mb'] ?? null, FILTER_VALIDATE_INT);
        $diskMb = filter_var($resources['disk_mb'] ?? null, FILTER_VALIDATE_INT);
        $cpuPercent = filter_var($resources['cpu_percent'] ?? null, FILTER_VALIDATE_INT);
        $databases = filter_var($featureLimits['databases'] ?? 0, FILTER_VALIDATE_INT);
        $additionalAllocations = filter_var($featureLimits['additional_allocations'] ?? 0, FILTER_VALIDATE_INT);
        $backups = filter_var($featureLimits['backups'] ?? 0, FILTER_VALIDATE_INT);
        if (
            $memoryMb === false
            || $memoryMb < 1
            || $memoryMb > 1048576
            || $diskMb === false
            || $diskMb < 1
            || $diskMb > 1048576
            || $cpuPercent === false
            || $cpuPercent < 1
            || $cpuPercent > 6400
            || !in_array($databases, [0, 1, 2, 3, 5, 10], true)
            || !in_array($additionalAllocations, [0, 1, 2, 3, 5], true)
            || !in_array($backups, [0, 1, 2, 3, 5, 10], true)
        ) {
            throw new RuntimeException('validation_failed');
        }

        $paymentPayload = ['provider' => $paymentProvider];
        if ($isZarinpal) {
            $paymentPayload += [
                'authority' => $authority,
                'ref_id' => $refId,
                'amount_toman' => $amountToman,
                'amount_rial' => $amountRial,
            ];
        }

        $payload = [
            'order_ref' => $orderRef,
            'steamid64' => $steamId64,
            'payment' => $paymentPayload,
            ...($expectedAmountRial !== null ? ['expected_amount_rial' => (int) $expectedAmountRial] : []),
            'duration_days' => $durationDays,
            'server_name' => $serverName,
            'resources' => [
                'memory_mb' => $memoryMb,
                'disk_mb' => $diskMb,
                'cpu_percent' => $cpuPercent,
            ],
            'feature_limits' => [
                'databases' => $databases,
                'additional_allocations' => $additionalAllocations,
                'backups' => $backups,
            ],
            'egg' => $egg,
        ];

        $response = $this->post('/api/v1/fragcity/bridge/orders/cs2', $payload);
        $data = $response['data'] ?? null;

        if (
            !is_array($data)
            || !isset($data['order_id'], $data['invoice_id'], $data['service_id'])
            || !is_numeric($data['order_id'])
            || !is_numeric($data['invoice_id'])
            || !is_numeric($data['service_id'])
            || (int) $data['order_id'] < 1
            || (int) $data['invoice_id'] < 1
            || (int) $data['service_id'] < 1
            || ($data['status'] ?? null) !== 'accepted'
        ) {
            throw new RuntimeException('paymenter_invalid_response');
        }

        return [
            'order_id' => (int) $data['order_id'],
            'invoice_id' => (int) $data['invoice_id'],
            'service_id' => (int) $data['service_id'],
            'status' => 'accepted',
            'replayed' => (bool) ($data['replayed'] ?? false),
            'provisioning' => (string) ($data['provisioning'] ?? 'queued_by_paymenter'),
            'currency_code' => (string) ($data['currency_code'] ?? ''),
            'currency_unit' => (string) ($data['currency_unit'] ?? ''),
        ];
    }

    /**
     * Retrieve CS2 services owned by the authenticated FragCity Steam account.
     *
     * @return list<array<string,mixed>>
     */
    public function getCustomerServers(string $steamId64): array
    {
        if (!preg_match('/^[0-9]{17}$/D', $steamId64)) {
            throw new RuntimeException('invalid_steamid');
        }

        $response = $this->post(
            '/api/v1/fragcity/bridge/servers/list',
            ['steamid64' => $steamId64]
        );
        $servers = $response['data']['servers'] ?? null;

        if (!is_array($servers)) {
            throw new RuntimeException('paymenter_invalid_response');
        }

        return array_values(array_filter($servers, 'is_array'));
    }

    /**
     * Set a customer-selected Pterodactyl password for one owned service.
     * The password is transported only over the signed HTTPS bridge and is
     * never persisted or returned by this client.
     *
     * @return array{password_updated:bool}
     */
    public function setCustomerPanelPassword(
        string $steamId64,
        int $serviceId,
        string $password
    ): array {
        if (!preg_match('/^[0-9]{17}$/D', $steamId64) || $serviceId < 1) {
            throw new RuntimeException('validation_failed');
        }

        $response = $this->post(
            '/api/v1/fragcity/bridge/servers/password',
            [
                'steamid64' => $steamId64,
                'service_id' => $serviceId,
                'password' => $password,
            ]
        );

        if (($response['data']['password_updated'] ?? false) !== true) {
            throw new RuntimeException('paymenter_invalid_response');
        }

        return ['password_updated' => true];
    }

    /**
     * Update the Pterodactyl username for one service owned by this customer.
     * The bridge verifies service ownership and keeps the panel identity in sync.
     *
     * @return array{username_updated:bool,username:string}
     */
    public function setCustomerPanelUsername(
        string $steamId64,
        int $serviceId,
        string $username
    ): array {
        $username = strtolower(trim($username));
        if (
            !preg_match('/^[0-9]{17}$/D', $steamId64)
            || $serviceId < 1
            || !preg_match('/^[a-z0-9][a-z0-9_-]{1,48}[a-z0-9]$/D', $username)
        ) {
            throw new RuntimeException('validation_failed');
        }

        $response = $this->post(
            '/api/v1/fragcity/bridge/servers/username',
            [
                'steamid64' => $steamId64,
                'service_id' => $serviceId,
                'username' => $username,
            ]
        );

        if (($response['data']['username_updated'] ?? false) !== true) {
            throw new RuntimeException('paymenter_invalid_response');
        }

        return [
            'username_updated' => true,
            'username' => (string) ($response['data']['username'] ?? $username),
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function post(string $path, array $payload): array
    {
        $body = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));
        $message = $timestamp . "\n" . $nonce . "\n" . $body;
        $signature = hash_hmac('sha256', $message, $this->sharedSecret);

        $handle = curl_init($this->baseUrl . $path);
        if ($handle === false) {
            throw new RuntimeException('Could not initialize the Paymenter bridge request.');
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'X-FragCity-Timestamp: ' . $timestamp,
                'X-FragCity-Nonce: ' . $nonce,
                'X-FragCity-Signature: ' . $signature,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        ]);

        $rawResponse = curl_exec($handle);
        $curlError = curl_errno($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if ($rawResponse === false || $curlError !== 0) {
            error_log('[FragCity] Paymenter bridge transport failed; cURL code=' . $curlError . '.');
            throw new RuntimeException('paymenter_unreachable');
        }

        $decoded = json_decode((string) $rawResponse, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            error_log('[FragCity] Paymenter bridge returned invalid JSON; HTTP ' . $status . '.');
            throw new RuntimeException('paymenter_invalid_response');
        }

        if ($status < 200 || $status >= 300 || ($decoded['success'] ?? false) !== true) {
            $error = is_string($decoded['error'] ?? null)
                ? $decoded['error']
                : 'paymenter_request_failed';

            $safeErrors = [
                'bridge_not_configured',
                'source_ip_not_allowed',
                'invalid_signature_headers',
                'invalid_signature',
                'request_replayed',
                'invalid_json_body',
                'validation_failed',
                'username_required',
                'username_already_in_use',
                'first_name_required',
                'last_name_required',
                'linked_paymenter_user_missing',
                'email_already_linked_to_another_paymenter_user',
                'paymenter_user_already_linked_to_another_steam_account',
                'email_already_in_use',
                'account_sync_failed',
                'invalid_steamid',
                'customer_servers_unavailable',
                'service_not_found',
                'panel_password_update_failed',
                'panel_password_rate_limited',
                'panel_username_invalid',
                'panel_username_already_in_use',
                'panel_username_update_failed',
                'panel_username_rate_limited',
                'cs2_checkout_not_configured',
                'cs2_checkout_manifest_unavailable',
                'cs2_catalog_unavailable',
                'cs2_plan_price_missing',
                'cs2_quote_amount_mismatch',
                'wallet_insufficient_credit',
                'wallet_not_enabled',
                'wallet_balance_unavailable',
                'wallet_topup_not_configured',
                'wallet_topup_amount_invalid',
                'wallet_topup_reservation_missing',
                'wallet_topup_reservation_expired',
                'wallet_topup_reservation_failed',
                'wallet_topup_pending_invoice_exists',
                'wallet_maximum_credit_exceeded',
                'wallet_topup_reference_conflict',
                'wallet_topup_processing_incomplete',
                'wallet_topup_creation_failed',
                'cs2_product_invalid',
                'cs2_product_out_of_stock',
                'cs2_product_user_limit',
                'cs2_plan_invalid',
                'cs2_config_option_mapping_invalid',
                'cs2_config_option_price_missing',
                'fragcity_user_not_linked',
                'paid_option_pricing_not_configured',
                'invalid_order_payload',
                'invalid_egg_variable',
                'egg_validation_failed',
                'egg_combination_invalid',
                'order_reference_conflict',
                'zarinpal_payment_already_used',
                'order_processing_incomplete',
            ];

            if (!in_array($error, $safeErrors, true)) {
                $error = 'paymenter_request_failed';
            }

            error_log('[FragCity] Paymenter bridge rejected a request; HTTP ' . $status . ', error=' . $error . '.');
            throw new RuntimeException($error);
        }

        return $decoded;
    }
}

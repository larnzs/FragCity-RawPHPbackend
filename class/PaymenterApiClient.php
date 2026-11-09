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
     * Submit a CS2 server order after the website has verified its ZarinPal payment.
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
        $egg = $order['egg'] ?? [];

        if (
            !preg_match('/^[a-f0-9]{32}$/D', $orderRef)
            || !preg_match('/^[0-9]{17}$/D', $steamId64)
            || !is_array($payment)
            || ($payment['provider'] ?? null) !== 'zarinpal'
            || !is_array($resources)
            || !is_array($egg)
        ) {
            throw new RuntimeException('validation_failed');
        }

        $amountToman = filter_var($payment['amount_toman'] ?? null, FILTER_VALIDATE_INT);
        $amountRial = filter_var($payment['amount_rial'] ?? null, FILTER_VALIDATE_INT);
        $durationDays = filter_var($order['duration_days'] ?? null, FILTER_VALIDATE_INT);
        $authority = trim((string) ($payment['authority'] ?? ''));
        $refId = trim((string) ($payment['ref_id'] ?? ''));
        $serverName = trim((string) ($order['server_name'] ?? ''));

        if (
            $amountToman === false
            || $amountRial === false
            || $amountToman < 1
            || $amountToman > intdiv(PHP_INT_MAX, 10)
            || $amountRial !== $amountToman * 10
            || !in_array($durationDays, [1, 7, 15, 30], true)
            || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $authority)
            || !preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $refId)
            || !preg_match('/^[A-Za-z0-9 ]{1,64}$/D', $serverName)
        ) {
            throw new RuntimeException('validation_failed');
        }

        $memoryMb = filter_var($resources['memory_mb'] ?? null, FILTER_VALIDATE_INT);
        $diskMb = filter_var($resources['disk_mb'] ?? null, FILTER_VALIDATE_INT);
        $cpuPercent = filter_var($resources['cpu_percent'] ?? null, FILTER_VALIDATE_INT);
        if (
            !in_array($memoryMb, [1024, 1280, 1536], true)
            || $diskMb === false
            || $diskMb < 5120
            || $diskMb > 10240
            || (($diskMb - 5120) % 1024) !== 0
            || !in_array($cpuPercent, [100, 200, 300, 400], true)
        ) {
            throw new RuntimeException('validation_failed');
        }

        $payload = [
            'order_ref' => $orderRef,
            'steamid64' => $steamId64,
            'payment' => [
                'provider' => 'zarinpal',
                'authority' => $authority,
                'ref_id' => $refId,
                'amount_toman' => $amountToman,
                'amount_rial' => $amountRial,
            ],
            'duration_days' => $durationDays,
            'server_name' => $serverName,
            'resources' => [
                'memory_mb' => $memoryMb,
                'disk_mb' => $diskMb,
                'cpu_percent' => $cpuPercent,
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

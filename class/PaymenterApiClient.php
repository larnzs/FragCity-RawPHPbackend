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
     * Connect a customer's own Pterodactyl Client API key. Paymenter validates
     * server ownership and stores the key encrypted at rest.
     *
     * @return array{client_api_connected:bool}
     */
    public function connectCustomerClientApi(
        string $steamId64,
        int $serviceId,
        string $apiToken
    ): array {
        $apiToken = trim($apiToken);
        if (
            !preg_match('/^[0-9]{17}$/D', $steamId64)
            || $serviceId < 1
            || strlen($apiToken) < 20
            || strlen($apiToken) > 255
            || preg_match('/[\r\n\x00-\x20\x7F]/', $apiToken)
        ) {
            throw new RuntimeException('validation_failed');
        }

        $response = $this->post(
            '/api/v1/fragcity/bridge/servers/client-api/connect',
            [
                'steamid64' => $steamId64,
                'service_id' => $serviceId,
                'api_token' => $apiToken,
            ]
        );

        if (($response['data']['client_api_connected'] ?? false) !== true) {
            throw new RuntimeException('paymenter_invalid_response');
        }

        return ['client_api_connected' => true];
    }

    /**
     * Remove the stored encrypted Client API credential for this customer.
     *
     * @return array{client_api_disconnected:bool}
     */
    public function disconnectCustomerClientApi(string $steamId64): array
    {
        if (!preg_match('/^[0-9]{17}$/D', $steamId64)) {
            throw new RuntimeException('invalid_steamid');
        }

        $response = $this->post(
            '/api/v1/fragcity/bridge/servers/client-api/disconnect',
            ['steamid64' => $steamId64]
        );

        if (($response['data']['client_api_disconnected'] ?? false) !== true) {
            throw new RuntimeException('paymenter_invalid_response');
        }

        return ['client_api_disconnected' => true];
    }

    /**
     * Request a short-lived WebSocket credential for one owned CS2 service.
     *
     * @return array{socket:string,token:string,expires_in:int,identifier:string}
     */
    public function getCustomerConsoleCredentials(string $steamId64, int $serviceId): array
    {
        if (!preg_match('/^[0-9]{17}$/D', $steamId64) || $serviceId < 1) {
            throw new RuntimeException('validation_failed');
        }

        $response = $this->post(
            '/api/v1/fragcity/bridge/servers/console',
            ['steamid64' => $steamId64, 'service_id' => $serviceId]
        );
        $data = $response['data'] ?? null;

        if (
            !is_array($data)
            || !is_string($data['socket'] ?? null)
            || !str_starts_with(strtolower($data['socket']), 'wss://')
            || !is_string($data['token'] ?? null)
            || $data['token'] === ''
        ) {
            throw new RuntimeException('paymenter_invalid_response');
        }

        return [
            'socket' => $data['socket'],
            'token' => $data['token'],
            'expires_in' => max(60, min(900, (int) ($data['expires_in'] ?? 600))),
            'identifier' => (string) ($data['identifier'] ?? ''),
        ];
    }

    /**
     * Run one allow-listed file-manager action on an owned customer service.
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function customerFileAction(string $steamId64, int $serviceId, array $input): array
    {
        $allowedActions = [
            'list',
            'read',
            'write',
            'create_folder',
            'rename',
            'delete',
            'download',
            'upload_url',
        ];
        $fileAction = strtolower(trim((string) ($input['file_action'] ?? '')));

        if (
            !preg_match('/^[0-9]{17}$/D', $steamId64)
            || $serviceId < 1
            || !in_array($fileAction, $allowedActions, true)
        ) {
            throw new RuntimeException('validation_failed');
        }

        $payload = array_merge($input, [
            'steamid64' => $steamId64,
            'service_id' => $serviceId,
            'file_action' => $fileAction,
        ]);

        $timeout = $fileAction === 'write' ? 45 : 20;
        $response = $this->post(
            '/api/v1/fragcity/bridge/servers/files',
            $payload,
            $timeout
        );
        $data = $response['data'] ?? null;

        if (!is_array($data)) {
            throw new RuntimeException('paymenter_invalid_response');
        }

        return $data;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function post(string $path, array $payload, int $timeoutSeconds = 20): array
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
            CURLOPT_TIMEOUT => max(5, min(60, $timeoutSeconds)),
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
                'service_not_active',
                'panel_password_update_failed',
                'panel_password_rate_limited',
                'panel_username_invalid',
                'panel_username_already_in_use',
                'panel_username_update_failed',
                'panel_username_rate_limited',
                'client_api_key_invalid',
                'client_api_key_must_belong_to_server_owner',
                'client_api_server_verification_failed',
                'client_api_invalid_response',
                'client_api_connection_failed',
                'client_api_connect_rate_limited',
                'client_api_not_connected',
                'client_api_storage_not_ready',
                'client_api_permission_denied',
                'client_api_file_too_large_to_edit',
                'client_api_file_not_editable',
                'client_api_invalid_file_content',
                'client_api_invalid_file_name',
                'client_api_invalid_file_list',
                'client_api_invalid_file_path',
                'client_api_invalid_file_action',
                'customer_console_unavailable',
                'customer_file_action_failed',
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

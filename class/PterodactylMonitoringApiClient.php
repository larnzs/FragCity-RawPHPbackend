<?php
declare(strict_types=1);

require_once __DIR__ . '/../settings/loader.php';

/** Signed website-side client for the Linux Pterodactyl Monitoring API. */
final class PterodactylMonitoringApiClient
{
    private string $baseUrl;
    private string $secret;

    public function __construct()
    {
        $baseUrl = trim((string)fragcity_settings('integrations.pterodactyl_monitoring_api.base_url', ''));
        $secret = (string)fragcity_settings('integrations.pterodactyl_monitoring_api.shared_secret', '');
        $parts = parse_url($baseUrl);

        if (!is_array($parts)
            || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/')) {
            throw new RuntimeException('Pterodactyl Monitoring API URL must be an HTTPS origin.');
        }
        if (!preg_match('/^[a-f0-9]{64,128}$/D', $secret)) {
            throw new RuntimeException('Pterodactyl Monitoring API shared secret is not configured.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is required for Pterodactyl Monitoring API transport.');
        }

        $this->baseUrl = rtrim($baseUrl, '/');
        $this->secret = $secret;
    }

    /** @return array<string, mixed> */
    public function getSnapshot(): array
    {
        $payload = ['action' => 'get_snapshot'];
        $rawBody = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        $timestamp = (string)time();
        $nonce = bin2hex(random_bytes(32));
        $path = '/v1/pterodactyl-monitoring';
        $canonical = $timestamp . "\n" . $nonce . "\nPOST\n" . $path . "\n" . hash('sha256', $rawBody);
        $signature = hash_hmac('sha256', $canonical, $this->secret);

        $handle = curl_init($this->baseUrl . $path);
        if ($handle === false) {
            throw new RuntimeException('Could not initialize Pterodactyl Monitoring API request.');
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $rawBody,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'X-FragCity-Timestamp: ' . $timestamp,
                'X-FragCity-Nonce: ' . $nonce,
                'X-FragCity-Signature: ' . $signature,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        ]);

        $response = curl_exec($handle);
        $curlError = curl_errno($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if ($response === false || $curlError !== 0) {
            error_log('[FragCity] Pterodactyl Monitoring transport failed; cURL code=' . $curlError);
            throw new RuntimeException('Pterodactyl Monitoring API is currently unavailable.');
        }

        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            error_log('[FragCity] Pterodactyl Monitoring returned invalid JSON; HTTP=' . $status);
            throw new RuntimeException('Pterodactyl Monitoring API returned an invalid response.');
        }
        if ($status < 200 || $status >= 300 || ($decoded['success'] ?? false) !== true) {
            $code = is_string($decoded['error'] ?? null) ? $decoded['error'] : 'request_failed';
            error_log('[FragCity] Pterodactyl Monitoring request failed; HTTP=' . $status . '; code=' . $code);
            throw new RuntimeException('Pterodactyl Monitoring API request failed.');
        }

        $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
        if (!is_array($data['nodes'] ?? null) || !is_array($data['servers'] ?? null)) {
            throw new RuntimeException('Pterodactyl Monitoring API snapshot is incomplete.');
        }
        return $data;
    }
}

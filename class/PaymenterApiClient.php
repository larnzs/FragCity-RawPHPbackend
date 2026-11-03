<?php
declare(strict_types=1);

require_once __DIR__ . '/../settings/loader.php';

/**
 * Server-side client for the Paymenter Admin API.
 *
 * This client must never be instantiated from browser-facing JavaScript.
 * The API key belongs only in the private FragCity runtime settings file.
 */
final class PaymenterApiClient
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct(?string $baseUrl = null, ?string $apiKey = null)
    {
        $baseUrl = trim($baseUrl ?? (string) fragcity_settings('integrations.paymenter_backend.base_url', ''));
        $apiKey = trim($apiKey ?? (string) fragcity_settings('integrations.paymenter_backend.api_key', ''));

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

        if ($apiKey === '' || strlen($apiKey) > 1024 || preg_match('/[\\r\\n\\x00-\\x1F\\x7F]/', $apiKey)) {
            throw new RuntimeException('Paymenter Admin API key is missing or invalid.');
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is required for Paymenter API transport.');
        }

        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
    }

    /**
     * Read one product page to verify TLS, API authentication and products.view.
     *
     * @return array{http_status:int, visible_products:int}
     */
    public function testConnection(): array
    {
        $url = $this->baseUrl . '/api/v1/admin/products?per_page=1';
        $handle = curl_init($url);

        if ($handle === false) {
            throw new RuntimeException('Could not initialize the Paymenter API request.');
        }

        curl_setopt_array($handle, [
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        ]);

        $response = curl_exec($handle);
        $curlError = curl_errno($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if ($response === false || $curlError !== 0) {
            error_log('[FragCity] Paymenter API transport failed; cURL code=' . $curlError);
            throw new RuntimeException('Paymenter API is unreachable; check DNS, TLS and outbound HTTPS.');
        }

        if ($status === 401) {
            throw new RuntimeException('Paymenter rejected the API key (HTTP 401). Check that the key is enabled and valid.');
        }

        if ($status === 403) {
            throw new RuntimeException('Paymenter denied this API key (HTTP 403). Check key type, IP restrictions and products.view permission.');
        }

        if ($status < 200 || $status >= 300) {
            error_log('[FragCity] Paymenter API returned HTTP ' . $status . ' during read-only preflight.');
            throw new RuntimeException('Paymenter API returned HTTP ' . $status . '. Check the base URL and server logs.');
        }

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE || !is_array($decoded['data'] ?? null)) {
            error_log('[FragCity] Paymenter API returned an unexpected response during read-only preflight.');
            throw new RuntimeException('Paymenter API response was not valid product-list JSON.');
        }

        return [
            'http_status' => $status,
            'visible_products' => count($decoded['data']),
        ];
    }
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/../settings/loader.php';

/** Signed website client for the on-demand Pterodactyl/A2S public server snapshot. */
final class PterodactylStandbyApiClient
{
    private string $baseUrl;
    private string $secret;

    public function __construct()
    {
        $baseUrl = trim((string)fragcity_settings('integrations.pterodactyl_standby_api.base_url', ''));
        $secret = (string)fragcity_settings('integrations.pterodactyl_standby_api.shared_secret', '');
        $parts = parse_url($baseUrl);

        if (!is_array($parts)
            || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/')) {
            throw new RuntimeException('Pterodactyl Standby API URL must be an HTTPS origin.');
        }

        if (!preg_match('/^[a-f0-9]{64,128}$/D', $secret)) {
            throw new RuntimeException('Pterodactyl Standby API shared secret is not configured.');
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is required for Pterodactyl Standby API transport.');
        }

        $this->baseUrl = rtrim($baseUrl, '/');
        $this->secret = $secret;
    }

    public function getSnapshot(): array
    {
        $payload = ['action' => 'list_servers'];
        $rawBody = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        $timestamp = (string)time();
        $nonce = bin2hex(random_bytes(32));
        $path = '/v1/pterodactyl-standby';
        $canonical = $timestamp . "\n" . $nonce . "\nPOST\n" . $path . "\n" . hash('sha256', $rawBody);
        $signature = hash_hmac('sha256', $canonical, $this->secret);

        $handle = curl_init($this->baseUrl . $path);
        if ($handle === false) {
            throw new RuntimeException('Could not initialize Pterodactyl Standby API request.');
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
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        ]);

        $response = curl_exec($handle);
        $curlError = curl_errno($handle);
        $curlErrorText = curl_error($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $contentType = (string)(curl_getinfo($handle, CURLINFO_CONTENT_TYPE) ?: '');
        $handle = null;

        if ($response === false || $curlError !== 0) {
            error_log('[FragCity] Pterodactyl Standby API transport failed; cURL code=' . $curlError);
            if (PHP_SAPI === 'cli') {
                throw new RuntimeException(
                    'Pterodactyl Standby API transport failed (cURL error ' . $curlError . ': ' . $curlErrorText . ').'
                );
            }
            throw new RuntimeException('Pterodactyl Standby API is currently unavailable.');
        }

        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            // Keep diagnostics useful without logging an untrusted response body.
            // A reverse-proxy HTML 404/502 page is the most common reason this
            // integration receives non-JSON despite a successful TCP/TLS request.
            $trimmedResponse = ltrim((string)$response, "\\xEF\\xBB\\xBF \\t\\r\\n");
            $firstByte = $trimmedResponse !== '' ? $trimmedResponse[0] : '';
            $bodyFormat = $firstByte === '<'
                ? 'html-or-xml'
                : ($firstByte === '' ? 'empty' : 'non-json-text');
            $safeContentType = preg_replace('/[^A-Za-z0-9+._\\/-]/', '', $contentType) ?? '';
            error_log(
                '[FragCity] Pterodactyl Standby API returned invalid JSON; HTTP=' . $status
                . '; content_type=' . substr($safeContentType, 0, 80)
                . '; body_format=' . $bodyFormat
                . '; json_error=' . json_last_error_msg()
            );
            if (PHP_SAPI === 'cli') {
                throw new RuntimeException(
                    'Pterodactyl Standby API returned an invalid response (HTTP ' . $status
                    . '; content-type=' . ($safeContentType !== '' ? $safeContentType : 'unknown')
                    . '; body-format=' . $bodyFormat
                    . '; JSON error=' . json_last_error_msg() . ').'
                );
            }
            throw new RuntimeException('Pterodactyl Standby API returned an invalid response.');
        }

        if ($status < 200 || $status >= 300 || ($decoded['success'] ?? false) !== true) {
            $code = is_string($decoded['error'] ?? null) ? $decoded['error'] : 'request_failed';
            error_log('[FragCity] Pterodactyl Standby API request failed; HTTP=' . $status . '; code=' . $code);
            if (PHP_SAPI === 'cli') {
                throw new RuntimeException('Pterodactyl Standby API request failed (HTTP ' . $status . '; code=' . $code . ').');
            }
            throw new RuntimeException('Pterodactyl Standby API request failed.');
        }

        return is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
    }
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/../settings/loader.php';

/**
 * Server-to-server client. Never call this from browser JavaScript.
 * The shared secret is read from ignored runtime settings, never from Git.
 */
final class WeaponPaintsApiClient
{
    private string $baseUrl;
    private string $secret;

    public function __construct()
    {
        $baseUrl = trim((string)fragcity_settings('integrations.weaponpaints_api.base_url', ''));
        $secret = (string)fragcity_settings('integrations.weaponpaints_api.shared_secret', '');

        $parts = parse_url($baseUrl);
        if (!is_array($parts)
            || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/')) {
            throw new RuntimeException('WeaponPaints API must use an HTTPS origin URL.');
        }

        if (strlen($secret) < 64) {
            throw new RuntimeException('WeaponPaints API shared secret is not configured.');
        }

        $this->baseUrl = rtrim($baseUrl, '/');
        $this->secret = $secret;
    }

    public function request(array $payload): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is required for the WeaponPaints API.');
        }

        try {
            $rawBody = json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            throw new RuntimeException('Could not encode WeaponPaints API request.', 0, $e);
        }

        $timestamp = (string)time();
        $nonce = bin2hex(random_bytes(32));
        $path = '/v1/weaponpaints';
        $canonical = $timestamp . "\n" . $nonce . "\nPOST\n" . $path . "\n" . hash('sha256', $rawBody);
        $signature = hash_hmac('sha256', $canonical, $this->secret);

        $curl = curl_init($this->baseUrl . $path);
        if ($curl === false) {
            throw new RuntimeException('Could not initialize WeaponPaints API request.');
        }

        curl_setopt_array($curl, [
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
            CURLOPT_TIMEOUT => 12,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($curl);
        $curlError = curl_errno($curl);
        $httpStatus = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($response === false || $curlError !== 0) {
            error_log('[FragCity] WeaponPaints API transport failed (cURL error ' . $curlError . ').');
            throw new RuntimeException('WeaponPaints API is currently unavailable.');
        }

        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            error_log('[FragCity] WeaponPaints API returned invalid JSON (HTTP ' . $httpStatus . ').');
            throw new RuntimeException('WeaponPaints API returned an invalid response.');
        }

        if ($httpStatus < 200 || $httpStatus >= 300 || ($decoded['success'] ?? false) !== true) {
            $code = is_string($decoded['error'] ?? null) ? $decoded['error'] : 'request_failed';
            error_log('[FragCity] WeaponPaints API request failed (HTTP ' . $httpStatus . ', ' . $code . ').');
            throw new RuntimeException('WeaponPaints API request failed.');
        }

        return is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
    }
}

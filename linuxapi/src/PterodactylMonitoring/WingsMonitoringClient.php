<?php
declare(strict_types=1);

/**
 * Read-only Wings API client.
 *
 * The Wings bearer token is obtained server-side from the Pterodactyl node
 * configuration endpoint and is never returned in monitoring snapshots.
 */
final class FragCityWingsMonitoringClient
{
    private string $baseUrl;
    private string $token;

    public function __construct(string $baseUrl, string $token)
    {
        $baseUrl = rtrim(trim($baseUrl), '/');
        $token = trim($token);
        $parts = parse_url($baseUrl);

        if (!is_array($parts)
            || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['https', 'http'], true)
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/')) {
            throw new RuntimeException('Invalid Wings API URL.');
        }
        if (strtolower((string)$parts['scheme']) === 'http' && !$this->isPrivateHost((string)$parts['host'])) {
            throw new RuntimeException('Unencrypted Wings API is allowed only on a loopback or private network address.');
        }
        if ($token === '' || strlen($token) > 512) {
            throw new RuntimeException('Wings authentication token is missing.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is required for Wings monitoring.');
        }

        $this->baseUrl = $baseUrl;
        $this->token = $token;
    }

    /** @return array<string, mixed> */
    public function getSystemInformation(): array
    {
        $payload = $this->get('/api/system?v=2');
        if (!is_array($payload['system'] ?? null) || !is_array($payload['docker'] ?? null)) {
            throw new RuntimeException('Wings returned an invalid system information payload.');
        }
        return $payload;
    }

    /** @return array<int, array<string, mixed>> */
    public function getServers(): array
    {
        $payload = $this->get('/api/servers');
        if (!array_is_list($payload)) {
            throw new RuntimeException('Wings returned an invalid server collection.');
        }
        return $payload;
    }

    private function get(string $path): array
    {
        $handle = curl_init($this->baseUrl . $path);
        if ($handle === false) {
            throw new RuntimeException('Could not initialize Wings API request.');
        }

        $scheme = strtolower((string)parse_url($this->baseUrl, PHP_URL_SCHEME));
        $protocol = $scheme === 'https' ? CURLPROTO_HTTPS : CURLPROTO_HTTP;
        curl_setopt_array($handle, [
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $this->token,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => $protocol,
            CURLOPT_REDIR_PROTOCOLS => $protocol,
        ]);

        $response = curl_exec($handle);
        $curlError = curl_errno($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if ($response === false || $curlError !== 0) {
            throw new RuntimeException('Wings API transport failed; cURL code=' . $curlError);
        }
        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Wings API returned invalid JSON; HTTP=' . $status);
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('Wings API request failed; HTTP=' . $status);
        }
        return $decoded;
    }

    private function isPrivateHost(string $host): bool
    {
        $host = trim($host, '[]');
        if (strtolower($host) === 'localhost') {
            return true;
        }

        $ip = filter_var($host, FILTER_VALIDATE_IP);
        if ($ip === false) {
            return false;
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }
}

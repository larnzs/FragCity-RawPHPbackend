<?php
declare(strict_types=1);

/**
 * CLI-only, read-only smoke test for the deployed FragCity Linux API.
 *
 * Usage:
 *   php linuxapi/tools/smoke-test.php https://api.fragcity.ir
 *   php linuxapi/tools/smoke-test.php https://api.fragcity.ir 76561198000000000
 *
 * The second argument is optional and triggers a read-only get_selection call.
 * Runtime secrets are read from /etc/fragcity-api/config.php.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (!function_exists('curl_init')) {
    fwrite(STDERR, "ERROR: PHP cURL extension is required.\n");
    exit(2);
}

$baseUrl = trim((string)($argv[1] ?? ''));
$parts = parse_url($baseUrl);
if (!is_array($parts)
    || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
    || empty($parts['host'])
    || isset($parts['user'])
    || isset($parts['pass'])
    || isset($parts['query'])
    || isset($parts['fragment'])
    || (isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/')) {
    fwrite(STDERR, "Usage: php smoke-test.php https://api.fragcity.ir [TEST_STEAMID]\n");
    exit(2);
}

$configPath = getenv('FRAGCITY_API_CONFIG') ?: '/etc/fragcity-api/config.php';
if (!is_file($configPath) || !is_readable($configPath)) {
    fwrite(STDERR, "ERROR: Runtime config not found/readable: " . $configPath . "\n");
    exit(2);
}

try {
    $config = require $configPath;
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: Could not load runtime config.\n");
    exit(2);
}
$secret = is_array($config) ? (string)($config['shared_secret'] ?? '') : '';
if (!preg_match('/^[a-f0-9]{64,128}$/D', $secret)) {
    fwrite(STDERR, "ERROR: shared_secret must be 64-128 lowercase hex characters.\n");
    exit(2);
}

function smokeRequest(string $baseUrl, string $secret, array $payload): bool
{
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $timestamp = (string)time();
    $nonce = bin2hex(random_bytes(32));
    $path = '/v1/weaponpaints';
    $canonical = $timestamp . "\n" . $nonce . "\nPOST\n" . $path . "\n" . hash('sha256', $body);
    $signature = hash_hmac('sha256', $canonical, $secret);

    $handle = curl_init(rtrim($baseUrl, '/') . $path);
    if ($handle === false) {
        fwrite(STDERR, "ERROR: Could not initialize cURL.\n");
        return false;
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
        CURLOPT_TIMEOUT => 15,
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

    // PHP 8.5 deprecates curl_close(); releasing the CurlHandle reference closes it.
    $handle = null;

    if ($response === false || $curlError !== 0) {
        $detail = $curlErrorText !== '' ? $curlErrorText : 'no cURL detail available';
        fwrite(STDERR, "ERROR: Request transport failed (cURL code " . $curlError . ": " . $detail . ").\n");
        return false;
    }

    $json = json_decode((string)$response, true);
    if (!is_array($json) || json_last_error() !== JSON_ERROR_NONE) {
        fwrite(STDERR, "ERROR: API returned invalid JSON; HTTP " . $status . ".\n");
        return false;
    }

    echo "HTTP " . $status . ": " . json_encode($json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    return $status >= 200 && $status < 300 && ($json['success'] ?? false) === true;
}

$baseUrl = rtrim($baseUrl, '/');
$ok = smokeRequest($baseUrl, $secret, ['action' => 'ping_db']);
if (!$ok) {
    fwrite(STDERR, "FAIL: signed ping_db failed. Check TLS, HMAC forwarding, DB config, and nonce table grants.\n");
    exit(1);
}
echo "PASS: signed ping_db.\n";

$steamid = (string)($argv[2] ?? '');
if ($steamid !== '') {
    if (!preg_match('/^[0-9]{1,20}$/D', $steamid)) {
        fwrite(STDERR, "ERROR: TEST_STEAMID must contain 1-20 digits.\n");
        exit(2);
    }
    $ok = smokeRequest($baseUrl, $secret, ['action' => 'get_selection', 'steamid' => $steamid]);
    if (!$ok) {
        fwrite(STDERR, "FAIL: read-only get_selection failed. Check plugin table names/columns and SELECT grants.\n");
        exit(1);
    }
    echo "PASS: read-only get_selection.\n";
}

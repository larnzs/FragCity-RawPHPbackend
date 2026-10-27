<?php
declare(strict_types=1);

/**
 * Static architecture/security regression checks for the Agent API bridge.
 * This is not a substitute for deployed integration tests or schema verification.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__, 2);
$read = static function (string $relative) use ($root): string {
    $path = $root . DIRECTORY_SEPARATOR . $relative;
    if (!is_file($path) || !is_readable($path)) {
        fwrite(STDERR, "FAIL: required source file missing: {$relative}\n");
        exit(2);
    }
    $contents = file_get_contents($path);
    if (!is_string($contents)) {
        fwrite(STDERR, "FAIL: unable to read source file: {$relative}\n");
        exit(2);
    }
    return $contents;
};

$index = $read('linuxapi/public/index.php');
$config = $read('linuxapi/config.example.php');
$bridge = $read('api/internal_agent_bridge.php');
$schema = $read('linuxapi/site-bridge-schema.sql');
$preflight = $read('tools/site-bridge-preflight.php');

$checks = [
    'Agent routes are allowlisted' =>
        str_contains($index, '$allowedRoutes = [')
        && str_contains($index, 'if (!in_array($route, $allowedRoutes, true))'),
    'Bridge URL is pinned to HTTPS and one known site endpoint' =>
        str_contains($index, "!== 'https'")
        && str_contains($index, "!== 'fragcity.ir'")
        && str_contains($index, "!== '/api/internal_agent_bridge.php'"),
    'Upstream TLS is verified and redirects are disabled' =>
        str_contains($index, 'CURLOPT_SSL_VERIFYPEER => true')
        && str_contains($index, 'CURLOPT_SSL_VERIFYHOST => 2')
        && str_contains($index, 'CURLOPT_FOLLOWLOCATION => false'),
    'Site bridge verifies HMAC in constant time' =>
        str_contains($bridge, 'hash_equals(hash_hmac(\'sha256\', $canonical, $secret), $signature)'),
    'Site bridge has persistent replay protection' =>
        str_contains($bridge, 'INSERT INTO fragcity_api_bridge_nonces')
        && str_contains($schema, 'CREATE TABLE IF NOT EXISTS fragcity_api_bridge_nonces'),
    'Agent token must be unexpired and belong to an InsMaster account' =>
        str_contains($bridge, 't.expires_at > NOW()')
        && str_contains($bridge, 'u.insmaster = 1'),
    'Metrics require an existing server assigned to this node' =>
        str_contains($bridge, 'SELECT server_name, server_ip, port, node_name FROM pending_servers')
        && str_contains($bridge, '$assignedNode !== $nodeName'),
    'RCON command polling matches node, IP, and port' =>
        str_contains($bridge, 'c.server_ip = rs.server_ip')
        && str_contains($bridge, 'WHERE rs.node_name = ? AND c.status =')
        && str_contains($bridge, "c.status = \\'pending\\'"),
    'RCON result updates match node, IP, port, and pending state' =>
        str_contains($bridge, 'UPDATE rcon_command_logs c')
        && str_contains($bridge, 'rs.server_ip = c.server_ip')
        && str_contains($bridge, "WHERE c.id = ? AND c.status = \'pending\'"),
    'RCON queue rejects arbitrary unregistered destinations and control characters' =>
        str_contains($bridge, 'SELECT server_id FROM registered_servers WHERE server_ip = ? AND port = ? LIMIT 1')
        && str_contains($bridge, "preg_match('/[\\x00-\\x1F\\x7F]/', \$command)"),
    'Bulk sync uses a fixed plugin-table map, not the website-services DB' =>
        str_contains($bridge, '$tableMap = [')
        && str_contains($bridge, 'kyonxcbt_ranks_plugin')
        && !str_contains($bridge, "'kyonxcbt_fragcity_services' =>"),
    'Linux API DB config stays local to GLOBAL_weaponpaints' =>
        str_contains($config, "'host' => '127.0.0.1'")
        && str_contains($config, "'name' => 'GLOBAL_weaponpaints'")
        && !str_contains($config, "'name' => 'kyonxcbt_fragcity_services'"),
    'Legacy static skin-sync secret is absent from active API source' =>
        !str_contains($index, 'secretagent67')
        && !str_contains($bridge, 'secretagent67'),
    'Shared-host schema preflight is CLI-only and read-only' =>
        str_contains($preflight, "PHP_SAPI !== 'cli'")
        && str_contains($preflight, 'INFORMATION_SCHEMA.COLUMNS')
        && str_contains($preflight, 'INFORMATION_SCHEMA.STATISTICS')
        && !preg_match('/\\b(?:INSERT\\s+INTO|UPDATE\\s+[A-Za-z_][A-Za-z0-9_]*\\s+SET|DELETE\\s+FROM|CREATE\\s+TABLE|ALTER\\s+TABLE|DROP\\s+TABLE|TRUNCATE\\s+TABLE)\\b/i', $preflight),
];

$failed = 0;
foreach ($checks as $name => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . PHP_EOL;
    if (!$passed) {
        $failed++;
    }
}

if ($failed > 0) {
    fwrite(STDERR, "{$failed} architecture check(s) failed.\n");
    exit(1);
}

echo 'All ' . count($checks) . ' architecture checks passed.' . PHP_EOL;

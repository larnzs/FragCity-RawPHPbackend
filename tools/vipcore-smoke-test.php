<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This smoke test is CLI-only.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/class/FragCityLinuxApiClient.php';

try {
    $data = (new FragCityLinuxApiClient())->request([
        'action' => 'ping_db',
    ], '/v1/vipcore');

    if (($data['database'] ?? null) !== 'ok'
        || ($data['table'] ?? null) !== 'vip_users'
        || !is_numeric($data['server_id'] ?? null)) {
        throw new RuntimeException('VIPCore API returned an invalid database health payload.');
    }

    echo "PASS: Signed VIPCore API request and GLOBAL_vip.vip_users access succeeded.\n";
    echo 'ServerId: ' . (int)$data['server_id'] . PHP_EOL;
    echo 'Rows: ' . (int)($data['row_count'] ?? 0) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

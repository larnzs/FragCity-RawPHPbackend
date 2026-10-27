<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once dirname(__DIR__) . '/class/FragCityLinuxApiClient.php';

try {
    $data = (new FragCityLinuxApiClient())->request(['action' => 'ping_db']);
    if (($data['database'] ?? null) !== 'ok') {
        throw new RuntimeException('API did not confirm database connectivity.');
    }
    echo "PASS: Signed API request succeeded and the WeaponPaints database is reachable.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

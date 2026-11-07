<?php
declare(strict_types=1);

/**
 * Regression test for both legacy dotted JSON keys and nested JSON paths.
 * Usage: php tools/settings-loader-test.php
 */

$testSettings = [
    'app' => [
        'timezone' => 'UTC',
    ],
    'files' => [
        'pages.vip' => [
            'purchase_catalog' => [
                'checkout_enabled' => true,
                'currency' => 'تومان',
            ],
        ],
        'pages.zarinpal.vip_request' => [
            'database_profile' => 'main',
        ],
        'nested' => [
            'branch' => [
                'value' => 'nested-ok',
            ],
        ],
    ],
    'integrations' => [
        'zarinpal' => [
            'merchant_id' => 'TEST-MERCHANT',
        ],
    ],
];

$tempPath = tempnam(sys_get_temp_dir(), 'fragcity-settings-test-');
if ($tempPath === false) {
    fwrite(STDERR, "FAIL: could not create temporary settings file.\n");
    exit(1);
}

try {
    $json = json_encode($testSettings, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (file_put_contents($tempPath, $json) === false) {
        throw new RuntimeException('Could not write temporary settings file.');
    }

    putenv('FRAGCITY_SETTINGS_FILE=' . $tempPath);
    require_once __DIR__ . '/../settings/loader.php';

    $checks = [
        'legacy dotted VIP key resolves checkout_enabled' =>
            fragcity_settings('files.pages.vip.purchase_catalog.checkout_enabled', false) === true,
        'legacy dotted ZarinPal request key resolves database profile' =>
            fragcity_settings('files.pages.zarinpal.vip_request.database_profile') === 'main',
        'ordinary nested JSON paths still resolve' =>
            fragcity_settings('files.nested.branch.value') === 'nested-ok',
        'ordinary integration paths still resolve' =>
            fragcity_settings('integrations.zarinpal.merchant_id') === 'TEST-MERCHANT',
        'missing settings preserve the supplied default' =>
            fragcity_settings('files.not_configured.value', 'fallback') === 'fallback',
    ];

    $failed = false;
    foreach ($checks as $label => $passed) {
        echo ($passed ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
        $failed = $failed || !$passed;
    }

    if ($failed) {
        exit(1);
    }

    echo "All settings loader tests passed." . PHP_EOL;
} finally {
    putenv('FRAGCITY_SETTINGS_FILE');
    if (is_file($tempPath)) {
        unlink($tempPath);
    }
}

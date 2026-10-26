<?php
/**
 * Copy this file to /etc/fragcity-api/config.php on the Linux host.
 * Never commit real credentials or this runtime config.
 */
return [
    // Generate with: openssl rand -hex 32
    'shared_secret' => 'REPLACE_WITH_A_RANDOM_64_CHARACTER_HEX_SECRET',

    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        // Replace with the actual Linux database name after verifying its schema.
        'name' => 'kyonxcbt_weaponpaints_plugin',
        // Dedicated least-privilege account; do not use root.
        'username' => 'fragcity_weaponpaints_api',
        'password' => 'REPLACE_WITH_A_UNIQUE_DATABASE_PASSWORD',
        'charset' => 'utf8mb4',
    ],
];

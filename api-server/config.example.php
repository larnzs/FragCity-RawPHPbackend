<?php
/**
 * Copy to /etc/fragcity-api/config.php on the Debian host.
 * Never commit the real file or its credentials.
 */
return [
    // Create with: openssl rand -hex 32
    'shared_secret' => 'REPLACE_WITH_A_RANDOM_64_CHARACTER_HEX_SECRET',

    'database' => [
        // Must be reachable from the Debian host PHP-FPM process.
        // If MariaDB is containerized, verify whether the correct host is
        // 127.0.0.1 or the local Docker bridge/gateway address.
        'host' => '127.0.0.1',
        'port' => 3306,
        // Verify the exact database name against the live MariaDB instance.
        'name' => 'kyonxcbt_weaponpaints_plugin',
        // Dedicated least-privilege account; never use MariaDB root here.
        'username' => 'fragcity_weaponpaints_api',
        'password' => 'REPLACE_WITH_A_UNIQUE_DATABASE_PASSWORD',
        'charset' => 'utf8mb4',
    ],
];

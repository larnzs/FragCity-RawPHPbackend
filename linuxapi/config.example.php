<?php
/**
 * Copy to /etc/fragcity-api/config.php on the Linux node.
 * Never commit the runtime config.
 *
 * This service currently exposes only the WeaponPaints API.
 * Website account, wallet, support, and payment data stay in the website DB;
 * the Linux API must not connect to that database.
 */
return [
    // Generate on Linux with: openssl rand -hex 32
    'shared_secret' => 'REPLACE_WITH_A_RANDOM_64_CHARACTER_HEX_SECRET',

    'database' => [
        // Verify the host listener with ss -lntp before reusing on another node.
        // This FragCity node has MariaDB listening on 127.0.0.1:3306.
        'host' => '127.0.0.1',
        'port' => 3306,
        // Shared WeaponPaints database used by the CS2 containers.
        'name' => 'GLOBAL_weaponpaints',
        'username' => 'fragcity_weaponpaints_api',
        'password' => 'REPLACE_WITH_A_UNIQUE_DATABASE_PASSWORD',
        'charset' => 'utf8mb4',
    ],
];

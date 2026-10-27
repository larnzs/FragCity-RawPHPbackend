<?php
/**
 * Copy to /etc/fragcity-api/config.php on the Linux node.
 * Never commit the runtime config.
 */
return [
    // Generate on Linux with: openssl rand -hex 32
    'shared_secret' => 'REPLACE_WITH_A_RANDOM_64_CHARACTER_HEX_SECRET',

    'database' => [
        // Verify how PHP-FPM reaches MariaDB before filling this in.
        // If MariaDB runs in Docker, 127.0.0.1 may not reach the container.
        'host' => '127.0.0.1',
        'port' => 3306,
        // Current FragCity Linux node uses the shared container database.
        // Verify this again before reusing the guide on a different node.
        'name' => 'GLOBAL_weaponpaints',
        'username' => 'fragcity_weaponpaints_api',
        'password' => 'REPLACE_WITH_A_UNIQUE_DATABASE_PASSWORD',
        'charset' => 'utf8mb4',
    ],
];

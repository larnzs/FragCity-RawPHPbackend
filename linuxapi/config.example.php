<?php
/**
 * Copy to /etc/fragcity-api/config.php on the Linux node.
 * Never commit the runtime config.
 */
return [
    // Generate on Linux with: openssl rand -hex 32
    'shared_secret' => 'REPLACE_WITH_A_RANDOM_64_CHARACTER_HEX_SECRET',

    // This bridge relays Agent requests to PHP running on the shared website host.
    // It does NOT connect Linux to kyonxcbt_fragcity_services or any hosted MySQL port.
    // Generate a DIFFERENT secret from shared_secret and set it in the website's private settings.json.
    'website_bridge' => [
        'url' => 'https://fragcity.ir/api/internal_agent_bridge.php',
        'shared_secret' => 'REPLACE_WITH_A_DIFFERENT_RANDOM_64_CHARACTER_HEX_SECRET',
    ],

    'database' => [
        // Verify the host listener with ss -lntp before reusing on another node.
        // This FragCity node has MariaDB listening on 127.0.0.1:3306.
        'host' => '127.0.0.1',
        'port' => 3306,
        // The shared WeaponPaints database used by the CS2 containers and website.
        // Verify this again before reusing the guide on a different node.
        'name' => 'GLOBAL_weaponpaints',
        'username' => 'fragcity_weaponpaints_api',
        'password' => 'REPLACE_WITH_A_UNIQUE_DATABASE_PASSWORD',
        'charset' => 'utf8mb4',
    ],
];

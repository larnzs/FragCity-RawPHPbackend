<?php
/**
 * Copy to /etc/fragcity-api/config.php on the Linux node.
 * Never commit the runtime config.
 *
 * This service exposes the WeaponPaints API and a cached public Server Browser.
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

    // Pterodactyl Standby credentials are independent from the WeaponPaints HMAC key.
    // Keep the Pterodactyl Application API key and this shared secret on Linux only.
    'pterodactyl_standby' => [
        'enabled' => false,
        'shared_secret' => 'REPLACE_WITH_A_SEPARATE_64_CHARACTER_HEX_SECRET',
        'cache_file' => '/var/cache/fragcity-api/pterodactyl-standby.json',
        'cache_max_age_seconds' => 180,
        'public_connect_host' => '85.185.40.85',
        'query_host' => '192.168.2.3',
        // Optional overrides keyed by numeric Pterodactyl node ID:
        // 'node_public_connect_hosts' => [1 => '85.185.40.85'],
        // 'node_query_hosts' => [1 => '192.168.2.3'],
        'query_timeout_ms' => 1500,
        'max_servers' => 40,
        'region' => '',
        'pterodactyl' => [
            'base_url' => 'https://server1.fragcity.ir',
            'application_api_key' => 'REPLACE_WITH_READ_ONLY_APPLICATION_API_KEY',
            'node_id' => 0,
            'allowed_egg_ids' => [],
            'allowed_docker_images' => [
                'ghcr.io/k4ryuu/cs2-egg-go',
            ],
        ],
    ],
];

<?php
declare(strict_types=1);

/**
 * FragCity Linux API private runtime configuration example.
 *
 * Copy this file to /etc/fragcity-api/config.php only for a fresh install.
 * For an existing installation, merge individual keys carefully; never replace
 * a live config without preserving its database credentials and HMAC secrets.
 *
 * Keep the runtime file outside the public document root and out of Git.
 * PHP-FPM (www-data) needs read access but must not be able to write it.
 *
 * This API must never connect to the FragCity website database or receive
 * the website database credentials.
 *
 * IMPORTANT:
 * Every REPLACE_WITH_* value below is a placeholder, not a working credential.
 * Fill these values locally on the server. Never commit the completed runtime
 * config and never paste real passwords, tokens or HMAC keys into Git.
 */


/*
|--------------------------------------------------------------------------
| Shared credentials
|--------------------------------------------------------------------------
|
| These variables keep repeated credentials consistent across the integrations.
| Use the existing values from the private runtime config when migrating.
|
*/

// Existing MariaDB account shared by WeaponPaints, VIPCore and Ranks.
$pluginsDbPassword = 'REPLACE_WITH_EXISTING_PLUGINS_DATABASE_PASSWORD';

// Shared by WeaponPaints, SimpleAdmin, VIPCore and Ranks.
// Must match integrations.weaponpaints_api.shared_secret on the website.
$rootSharedSecret = 'REPLACE_WITH_WEAPONPAINTS_64_HEX_SECRET';

// Independent HMAC key for Pterodactyl Monitoring.
$monitoringSharedSecret = 'REPLACE_WITH_MONITORING_64_HEX_SECRET';

// Independent HMAC key for Pterodactyl Standby / Server Browser.
$standbySharedSecret = 'REPLACE_WITH_STANDBY_64_HEX_SECRET';

// Read-only Pterodactyl Application API token for Monitoring.
// Monitoring is disabled by default; configure and enable only after validation.
$monitoringApplicationApiKey = 'REPLACE_WITH_READ_ONLY_MONITORING_APPLICATION_API_TOKEN';

// Read-only Pterodactyl Application API token for Standby.
// It requires permission to read Servers and Allocations.
$standbyApplicationApiKey = 'REPLACE_WITH_READ_ONLY_STANDBY_APPLICATION_API_TOKEN';


return [

    /*
    |--------------------------------------------------------------------------
    | Shared API authentication
    |--------------------------------------------------------------------------
    |
    | Used by:
    | - POST /v1/weaponpaints
    | - POST /v1/simpleadmin
    | - POST /v1/vipcore
    | - POST /v1/ranks
    |
    | Must match:
    | settings/settings.json
    | integrations.weaponpaints_api.shared_secret
    |
    */

    'shared_secret' => $rootSharedSecret,


    /*
    |--------------------------------------------------------------------------
    | WeaponPaints database and API nonce store
    |--------------------------------------------------------------------------
    |
    | Existing MariaDB account: plugins
    | Database: GLOBAL_weaponpaints
    |
    | The same account is reused for the SimpleAdmin, VIPCore and Ranks
    | databases. Its MariaDB grants must cover the required schemas/tables.
    |
    */

    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'GLOBAL_weaponpaints',
        'username' => 'plugins',
        'password' => $pluginsDbPassword,
        'charset' => 'utf8mb4',
    ],


    /*
    |--------------------------------------------------------------------------
    | VIPCore API
    |--------------------------------------------------------------------------
    |
    | Endpoint: POST /v1/vipcore
    | Database: GLOBAL_vip
    | Table: vip_users
    |
    | Uses the existing plugins MariaDB account.
    | Required operations: SELECT, INSERT and UPDATE on vip_users.
    | Also requires SELECT and INSERT on fragcity_vip_free_trial_claims
    | after the one-time trial migration has been applied.
    |
    */

    'vipcore' => [

        'enabled' => true,

        'database' => [
            'host' => '127.0.0.1',
            'port' => 3306,
            'name' => 'GLOBAL_vip',
            'username' => 'plugins',
            'password' => $pluginsDbPassword,
            'charset' => 'utf8mb4',
        ],

        // Must match ServerId in the VIPCore vip_core.json configuration.
        'server_id' => 0,

        // Temporary no-payment test grants remain disabled by default.
        'test_mode' => [
            'enabled' => false,
            'duration_seconds' => 3600,

            // Keep empty to disallow every test grant.
            // If test grants are deliberately enabled, add exact 17-digit
            // SteamID64 values for explicitly authorized test accounts only.
            'allowed_steamids' => [],
        ],

    ],


    /*
    |--------------------------------------------------------------------------
    | Ranks API
    |--------------------------------------------------------------------------
    |
    | Endpoint: POST /v1/ranks
    | Database: GLOBAL_ranks
    | Table: lvl_base
    |
    | API operations are read-only and use the existing plugins account.
    | Required privilege: SELECT on GLOBAL_ranks.lvl_base.
    |
    */

    'ranks' => [

        'enabled' => true,

        'database' => [
            'host' => '127.0.0.1',
            'port' => 3306,
            'name' => 'GLOBAL_ranks',
            'username' => 'plugins',
            'password' => $pluginsDbPassword,
            'charset' => 'utf8mb4',
        ],

    ],


    /*
    |--------------------------------------------------------------------------
    | SimpleAdmin API
    |--------------------------------------------------------------------------
    |
    | Endpoint: POST /v1/simpleadmin
    | Database: GLOBAL_simpleadmin
    |
    | Reuses the root database connection above; only the schema name changes.
    | The API exposes allowlisted actions and selected fields, not arbitrary SQL.
    |
    */

    'simpleadmin' => [

        'enabled' => true,

        'database' => [
            'name' => 'GLOBAL_simpleadmin',
        ],

    ],


    /*
    |--------------------------------------------------------------------------
    | Pterodactyl Monitoring API
    |--------------------------------------------------------------------------
    |
    | Endpoint: POST /v1/pterodactyl-monitoring
    |
    | Monitoring is disabled by default. Configure the independent HMAC key,
    | read-only Application API token, authorized Node IDs and any Wings
    | overrides before enabling it.
    |
    */

    'pterodactyl_monitoring' => [

        'enabled' => false,

        'shared_secret' => $monitoringSharedSecret,

        'max_servers' => 100,

        // Set only when this API process runs on the monitored Wings node.
        // Leave 0 when the API host is not itself the monitored node.
        'local_node_id' => 0,

        // Optional host disk sampling path. Empty uses the Wings system.data path.
        'host_metrics_disk_path' => '',

        'pterodactyl' => [

            'base_url' => 'https://panel.fragcity.ir',

            // Read-only Application API token. Never expose it to the website
            // frontend or browser. Configure the required read permissions.
            'application_api_key' => $monitoringApplicationApiKey,

            // Explicit numeric Node IDs authorized for Monitoring.
            // Empty means no Nodes are authorized.
            'allowed_node_ids' => [],

            // Optional Egg ID allowlist.
            'allowed_egg_ids' => [],

            'allowed_docker_images' => [
                'ghcr.io/k4ryuu/cs2-egg-go',
            ],

        ],

        // Optional Wings URL overrides keyed by numeric Node ID.
        // Prefer HTTPS with a valid certificate. Plain HTTP is permitted
        // only for loopback or private IP addresses.
        'wings_base_urls' => [],

    ],


    /*
    |--------------------------------------------------------------------------
    | Pterodactyl Standby / Public CS2 Server Browser API
    |--------------------------------------------------------------------------
    |
    | Endpoint: POST /v1/pterodactyl-standby
    |
    | Discovers eligible servers through the Pterodactyl Application API,
    | resolves their default game allocations and queries those exact UDP
    | ports with A2S_INFO. Player details are queried on demand with A2S_PLAYER.
    |
    | No arbitrary port-range scanning or Cron job is used by the active browser.
    | The current discovery implementation is intentionally single-Node.
    |
    */

    'pterodactyl_standby' => [

        'enabled' => true,

        'shared_secret' => $standbySharedSecret,

        // Ensure PHP-FPM can write to the cache directory.
        // Example:
        // install -d -o www-data -g www-data -m 0750 /var/cache/fragcity-api
        'cache_file' => '/var/cache/fragcity-api/pterodactyl-standby.json',

        'cache_ttl_seconds' => 10,

        'cache_max_stale_seconds' => 180,

        // Public address shown in steam://connect links.
        'public_connect_host' => 'REPLACE_WITH_PUBLIC_GAME_HOST',

        // Private/internal address reachable by this Linux API host over UDP A2S.
        'query_host' => 'REPLACE_WITH_PRIVATE_GAME_QUERY_HOST',

        'query_timeout_ms' => 1500,

        'max_servers' => 40,

        'region' => '',

        'pterodactyl' => [

            'base_url' => 'https://panel.fragcity.ir',

            // Read-only Application API token. It needs Servers: Read and
            // Allocations: Read so the API can resolve each primary allocation.
            // Never expose this token to the website frontend or browser.
            'application_api_key' => $standbyApplicationApiKey,

            // REQUIRED: replace with the real numeric Node ID from Admin -> Nodes.
            'node_id' => 0,

            // Add exact Egg IDs where possible.
            // Empty is permitted only when the Docker image allowlist identifies
            // the eligible CS2 servers.
            'allowed_egg_ids' => [],

            'allowed_docker_images' => [
                'ghcr.io/k4ryuu/cs2-egg-go',
            ],

        ],

    ],

];

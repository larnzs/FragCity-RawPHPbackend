<?php
declare(strict_types=1);

/**
 * FragCity Linux API private runtime configuration example.
 *
 * Copy this file to /etc/fragcity-api/config.php only during a fresh install.
 * For an existing installation, merge individual keys carefully; do not replace
 * the live config because it contains the real database password and HMAC secrets.
 *
 * PHP-FPM (www-data) needs READ access to the private file but must not be able
 * to write it. Never place this file below the public document root or commit it.
 *
 * This Linux API owns the WeaponPaints service connection and the server-side
 * Pterodactyl integrations. It must never connect to the FragCity website DB
 * (kyonxcbt_fragcity_services) or receive the website's database credentials.
 */
return [
    /*
     * WeaponPaints endpoint authentication:
     * POST /v1/weaponpaints
     *
     * Generate a separate 64-character lowercase hex key with:
     *   openssl rand -hex 32
     * Put the matching value in the website's integrations.weaponpaints_api.shared_secret.
     * This key is NOT the Standby or Monitoring key.
     */
    'shared_secret' => 'REPLACE_WITH_WEAPONPAINTS_64_HEX_SECRET',

    /*
     * Linux-local database used by CS2 WeaponPaints containers.
     * It also stores fragcity_api_nonces for replay protection on signed API calls.
     * Use the limited fragcity_weaponpaints_api account, not MariaDB root.
     * Keep TCP/3306 private; the API should use the local listener.
     */
    'database' => [
        'host' => '127.0.0.1', // MariaDB loopback listener on the API host.
        'port' => 3306,        // Confirm with: sudo ss -lntp | grep 3306
        'name' => 'GLOBAL_weaponpaints',
        'username' => 'fragcity_weaponpaints_api',
        'password' => 'REPLACE_WITH_THE_EXISTING_UNIQUE_DATABASE_PASSWORD',
        'charset' => 'utf8mb4',
    ],

    /*
     * Pterodactyl Monitoring endpoint:
     * POST /v1/pterodactyl-monitoring
     *
     * Returns allowlisted Node and CS2-container resource metrics. Monitoring is
     * independent from the public Server Browser. Leave enabled=false until every
     * node ID, Application API permission and Wings connection has been verified.
     */
    'pterodactyl_monitoring' => [
        'enabled' => false, // Set true only after configuring the fields below.
        // Generate independently with: openssl rand -hex 32
        // Copy the same value to integrations.pterodactyl_monitoring_api.shared_secret on the website.
        'shared_secret' => 'REPLACE_WITH_MONITORING_64_HEX_SECRET',
        'max_servers' => 100, // Hard-bounded by the service (1–250).
        // Set to the real numeric Node ID ONLY if this API process runs on that same Wings host.
        // Leave 0 when the API host is not the monitored node; remote host CPU/disk values are not fabricated.
        'local_node_id' => 0,
        // Optional path override for host disk sampling. Empty uses the system.data path returned by Wings.
        'host_metrics_disk_path' => '',
        'pterodactyl' => [
            'base_url' => 'https://server1.fragcity.ir', // Panel origin only; no /api path.
            // Read-only Application API token. It must be able to read Node records/config and server records.
            // Never copy this token to the website settings or browser.
            'application_api_key' => 'REPLACE_WITH_READ_ONLY_APPLICATION_API_TOKEN',
            // Explicit numeric Node IDs allowed for Monitoring. Example: [1].
            // Empty means no nodes are authorized; do not use guessed IDs.
            'allowed_node_ids' => [],
            // Optional Egg ID allowlist. Combine with Docker image allowlist as needed.
            'allowed_egg_ids' => [],
            'allowed_docker_images' => [
                'ghcr.io/k4ryuu/cs2-egg-go',
            ],
        ],
        // Optional Wings URL overrides keyed by numeric Node ID.
        // Prefer HTTPS with a valid certificate. Plain HTTP is permitted only for loopback/private IPs.
        // Example when API and Wings are on the same host: [1 => 'http://127.0.0.1:8080'].
        'wings_base_urls' => [],
    ],

    /*
     * Public CS2 Server Browser endpoint:
     * POST /v1/pterodactyl-standby
     *
     * The Linux API discovers servers through Pterodactyl Application API, selects
     * their DEFAULT game Allocations and queries those exact UDP ports with A2S_INFO.
     * No arbitrary port-range scan and no Cron job are used by the active browser.
     * The current discovery implementation is intentionally single-Node.
     */
    'pterodactyl_standby' => [
        'enabled' => false, // Set true after completing the settings and manual tests.
        // Generate independently with: openssl rand -hex 32
        // Copy the same value to integrations.pterodactyl_standby_api.shared_secret on the website.
        'shared_secret' => 'REPLACE_WITH_STANDBY_64_HEX_SECRET',
        // Absolute local path. Ensure PHP-FPM can write to its directory:
        // sudo install -d -o www-data -g www-data -m 0750 /var/cache/fragcity-api
        'cache_file' => '/var/cache/fragcity-api/pterodactyl-standby.json',
        'cache_ttl_seconds' => 10,       // Refresh on the first website request after this TTL (3–120 seconds).
        'cache_max_stale_seconds' => 180, // Serve last good snapshot during temporary upstream failure (max 3600).
        // Public address and game port shown in steam://connect links; must be reachable by players.
        'public_connect_host' => '85.185.40.85',
        // Private/internal IP reachable by THIS Linux API host over UDP A2S.
        // Do not put the public API hostname here unless UDP query really routes there.
        'query_host' => '192.168.2.3',
        // Optional per-Node overrides, keyed by numeric Pterodactyl Node ID:
        // 'node_public_connect_hosts' => [1 => '85.185.40.85'],
        // 'node_query_hosts' => [1 => '192.168.2.3'],
        'query_timeout_ms' => 1500, // Per-server A2S timeout.
        'max_servers' => 40,        // Upper-bounded by discovery (1–100).
        'region' => '',             // Optional display metadata; does not affect discovery.
        'pterodactyl' => [
            'base_url' => 'https://server1.fragcity.ir', // Pterodactyl Panel origin only.
            // Read-only Application API token. It needs Servers: Read and Allocations: Read
            // so the API can resolve each server's primary Allocation ID and port.
            // It may be shared with Monitoring if permissions cover both; HMAC secrets stay separate.
            'application_api_key' => 'REPLACE_WITH_READ_ONLY_APPLICATION_API_TOKEN',
            'node_id' => 0, // REQUIRED: replace with the real numeric Node ID from Admin → Nodes.
            // Add the exact Egg IDs used by CS2 servers where possible. Empty is allowed only when
            // the Docker image allowlist below identifies the eligible CS2 servers.
            'allowed_egg_ids' => [],
            'allowed_docker_images' => [
                'ghcr.io/k4ryuu/cs2-egg-go',
            ],
        ],
    ],
];

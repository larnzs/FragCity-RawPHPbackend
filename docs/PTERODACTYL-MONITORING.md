# FragCity — Pterodactyl Monitoring

## Purpose

The CS2 Admin Panel has an admin-only monitoring page that requests a fresh snapshot on demand. No Cron job, cache refresher, or browser-side Pterodactyl token is used.

- Panel page: `panels/cs2/monitoring.php`
- Browser polling endpoint: `GET /api/admin-pterodactyl-monitoring.php`
- Website signed client: `class/PterodactylMonitoringApiClient.php`
- Website repository: `class/PterodactylMonitoringRepository.php`
- Linux API route: `POST /v1/pterodactyl-monitoring`
- Linux monitoring service: `linuxapi/src/PterodactylMonitoring/PterodactylMonitoringService.php`

The browser polls the website endpoint every 15 seconds while the monitoring page is open. The website checks the current session and requires `role=admin`, then sends a signed request to the Linux API. The Linux API fetches Pterodactyl data and returns a sanitized snapshot. The browser never receives the Pterodactyl Application API key or Wings token.

## Where the metrics come from

Pterodactyl 1.x Wings exposes authenticated endpoints on the node's Daemon API port (default **8080**):

- `GET /api/system?v=2`: Wings version, system architecture, CPU thread count, total physical RAM, OS/kernel and Docker/container counts.
- `GET /api/servers`: each loaded server's state and current container utilization, including CPU, memory, disk, network counters and uptime.

The node's configured memory/disk capacity and allocations are read from the Pterodactyl Application API node record. Per-server resource values are read from Wings. The CS2 resource totals are the sum of allowlisted containers. If `local_node_id` is configured for the node on which the FragCity Linux API process runs, an additional host-level sampler reads actual CPU utilization from `/proc/stat`, RAM used/available from `/proc/meminfo`, and disk used/free from the configured Wings data path. These host metrics are shown separately from CS2 container totals. Remote nodes do not receive invented host CPU/disk readings: their cards use Wings system info, per-container resources, and Pterodactyl's configured memory/disk capacity/allocation values. The server disk figures are per-container usage; the node disk capacity is a configured limit unless local host metrics are enabled.

The default Wings daemon port is 8080. SFTP is a separate port, usually 2022; it is not the metrics endpoint.

## Linux runtime configuration

Back up `/etc/fragcity-api/config.php` first, then merge the following block into its returned array. Do not replace the WeaponPaints configuration, its database account, or its HMAC key.

~~~php
'pterodactyl_monitoring' => [
    'enabled' => true,
    'shared_secret' => 'A_SEPARATE_64_CHARACTER_HEX_SECRET',
    'max_servers' => 100,
    // Set only when this Linux API process runs on the same machine as that Wings node.
    'local_node_id' => 1, // Replace 1 with the actual numeric Pterodactyl node ID.
    // Optional override; otherwise uses the node configuration's system.data path.
    'host_metrics_disk_path' => '',
    'pterodactyl' => [
        'base_url' => 'https://server1.fragcity.ir',
        'application_api_key' => 'READ_ONLY_APPLICATION_API_KEY',
        'allowed_node_ids' => [1], // Replace 1 with the actual numeric Pterodactyl node ID.
        'allowed_egg_ids' => [],
        'allowed_docker_images' => [
            'ghcr.io/k4ryuu/cs2-egg-go',
        ],
    ],
    'wings_base_urls' => [],
],
~~~

Use an Application API token with read-only permissions for Node records, Node configuration, and Server records (the corresponding node read/configuration and server read scopes). The node-configuration request returns the node's Wings authentication token; the Linux API uses it internally for Wings requests and never places it in the monitoring response. Do not paste either token into chat, Git, or browser JavaScript.

The `allowed_node_ids` list is deliberately explicit. Find the actual numeric node ID in the Pterodactyl Admin → Nodes page; do not guess it. The Egg ID allowlist can be used instead of, or alongside, the Docker image allowlist. Leave unrelated nodes and Eggs out.

### Wings TLS and internal URLs

When the node configuration has Wings SSL enabled, the service derives the HTTPS Wings URL from the Pterodactyl node FQDN and Daemon Port, and verifies the TLS certificate.

If Wings SSL is disabled, do not send the Wings token to a public HTTP hostname. Configure a loopback/private override instead. Example for an API process running on the same Linux node as Wings:

~~~php
'wings_base_urls' => [
    1 => 'http://127.0.0.1:8080', // Replace 1 with the actual numeric Pterodactyl node ID.
],
~~~

The client rejects unencrypted HTTP URLs unless their host is a loopback or private-network IP. For a different node, use HTTPS with a valid certificate or a private network address reachable only from the Linux API host.

The Linux API host must be able to reach the Pterodactyl Panel Application API and each configured Wings endpoint. The Wings Daemon Port must be reachable from the Linux API host, but it does not need to be exposed to the public website or browser for this server-side integration.

The `local_node_id` must be the actual numeric node ID from Pterodactyl Admin → Nodes and must identify the machine running `api.fragcity.ir` PHP. Leave it at `0` (or omit the host sampler) if the Linux API runs on a different machine. The default disk sampler path is the `system.data` path returned by the Wings node configuration; set `host_metrics_disk_path` only when you intentionally want to measure another mounted filesystem.


## Website runtime settings

Merge the website integration fragment from `settings/pterodactyl-monitoring.integration.example.json` into the private `settings/settings.json` on the website:

~~~json
{
  "integrations": {
    "pterodactyl_monitoring_api": {
      "base_url": "https://api.fragcity.ir",
      "shared_secret": "THE_SAME_SECRET_AS_LINUX_CONFIG"
    }
  }
}
~~~

The website's `shared_secret` must exactly match `pterodactyl_monitoring.shared_secret` on Linux. Generate a separate random secret for this integration; do not reuse the WeaponPaints or Standby secret.

## Deploy the Linux API route

The Nginx example includes the fixed route `/v1/pterodactyl-monitoring`. Merge the matching `location` block into the live API server block, then validate and reload Nginx:

~~~bash
sudo nginx -t
sudo systemctl reload nginx
~~~

Deploy the latest `linuxapi/` source to `/var/www/fragcity-api` using the existing deployment procedure. The route uses the existing nonce-replay table in `GLOBAL_weaponpaints`; it does not connect to the website database and does not add tables.

## Checks

From the website repository root:

~~~powershell
php -l class/PterodactylMonitoringApiClient.php
php -l class/PterodactylMonitoringRepository.php
php -l api/admin-pterodactyl-monitoring.php
php -l panels/cs2/monitoring.php
php -l linuxapi/src/PterodactylMonitoring/PterodactylMonitoringPanelClient.php
php -l linuxapi/src/PterodactylMonitoring/WingsMonitoringClient.php
php -l linuxapi/src/PterodactylMonitoring/PterodactylMonitoringService.php
php -l linuxapi/src/PterodactylMonitoring/PterodactylHostMetrics.php
php -l linuxapi/public/index.php
php linuxapi/tools/architecture-check.php
~~~

Then sign into FragCity with an account whose stored role is exactly `admin` and open `panels/cs2/index.php?page=monitoring`. The page should display node health, Wings/system details, and resource counters for allowlisted CS2 servers. It refreshes every 15 seconds while open.

Common errors:

- `monitoring_temporarily_unavailable`: Linux API runtime block is disabled/missing, HMAC secret mismatch, or upstream Pterodactyl/Wings request failed.
- `node_monitoring_unavailable`: the configured node could not provide a usable Wings snapshot; check node configuration permissions, FQDN/Daemon Port, TLS and firewall.
- Node loads but no CS2 server rows appear: check `allowed_node_ids`, `allowed_egg_ids` and `allowed_docker_images`.
- HTTP 401/403 from Wings: the Application API token may not be allowed to read node configuration, or the node's Wings token/config is out of sync.

Monitoring is read-only. It does not create, start, stop, restart, delete, or reconfigure any Pterodactyl server.

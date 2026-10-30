# FragCity Public Server Browser — Pterodactyl + A2S

This is the active source for the public CS2 Server Browser. It discovers CS2 servers through the Pterodactyl Application API, queries only their configured default Allocations through A2S_INFO, and returns a sanitized snapshot to the website. **It does not scan a port range and does not require Cron.**

## Request flow

1. pages/serverlist.php calls PterodactylStandbyRepository.
2. class/PterodactylStandbyApiClient.php signs a POST /v1/pterodactyl-standby request to the Linux API with the dedicated pterodactyl_standby.shared_secret.
3. The Linux API validates HMAC and nonce replay, then invokes PterodactylStandbySnapshotCache.
4. On the first request after the 10-second cache TTL, PterodactylStandbyDiscovery calls the read-only Pterodactyl Application API.
5. The Application API client restricts discovery to one configured Node and an explicit CS2 Egg ID and/or Docker image allowlist. It reads each CS2 server's default Allocation (the configured game port). It never accepts a host or port supplied by the website visitor.
6. FragCityA2SInfoQuery::queryBatch() sends concurrent UDP A2S_INFO requests to those Allocation ports at the configured internal query_host. It handles A2S challenge responses and verifies AppID 730.
7. The Linux API returns sanitized name, map, players, capacity, VAC, password and Query RTT fields. The public page displays the public connect host/port, not the private query host.

The request cache is refreshed only when a website request arrives after the TTL. It uses an atomic JSON write and a file lock so simultaneous visitors do not trigger duplicate refreshes. If upstream Pterodactyl/A2S is temporarily unavailable, the last good snapshot can be served as stale for up to 180 seconds. No scheduled job is needed.

## Components

| Responsibility | File |
|---|---|
| Website signed API client | class/PterodactylStandbyApiClient.php |
| Website repository | class/PterodactylStandbyRepository.php |
| Active public page | pages/serverlist.php |
| Linux API route | POST /v1/pterodactyl-standby |
| Read-only Pterodactyl Application API | linuxapi/src/PterodactylStandby/PterodactylApplicationClient.php |
| A2S_INFO parser + concurrent UDP batch | linuxapi/src/PterodactylStandby/A2SInfoQuery.php |
| Pterodactyl/A2S discovery | linuxapi/src/PterodactylStandby/PterodactylStandbyDiscovery.php |
| On-demand short cache | linuxapi/src/PterodactylStandby/PterodactylStandbySnapshotCache.php |
| Optional manual cache warm-up | linuxapi/bin/pterodactyl-standby-refresh.php |

The old range scanner (public_server_scan_targets, public_server_scan_ports, tools/refresh-public-server-browser.php) is no longer used by the public page. Its files and tables are intentionally retained for rollback/reference; do not schedule its Cron job and do not drop the tables as part of this change.

## Linux runtime configuration

Merge this block into /etc/fragcity-api/config.php. Preserve the existing WeaponPaints database configuration and other integration blocks. Do not copy the example file over the live config.

~~~php
'pterodactyl_standby' => [
    'enabled' => true,
    'shared_secret' => 'A_SEPARATE_64_CHARACTER_HEX_SECRET',

    // On-demand response cache.
    'cache_file' => '/var/cache/fragcity-api/pterodactyl-standby.json',
    'cache_ttl_seconds' => 10,
    'cache_max_stale_seconds' => 180,

    // Public address players use in steam://connect.
    'public_connect_host' => '85.185.40.85',

    // Internal IP reachable from the Linux API host for UDP A2S queries.
    'query_host' => '192.168.2.3',

    'query_timeout_ms' => 1500,
    'max_servers' => 40,
    'region' => '',

    'pterodactyl' => [
        'base_url' => 'https://server1.fragcity.ir',
        'application_api_key' => 'READ_ONLY_APPLICATION_API_KEY',
        'node_id' => 1, // Replace 1 with the actual numeric node ID.
        'allowed_egg_ids' => [],
        'allowed_docker_images' => [
            'ghcr.io/k4ryuu/cs2-egg-go',
        ],
    ],
],
~~~

Use a Pterodactyl Application API key with read-only access to server records and Allocation relationships for the selected Node. Configure the exact numeric Node ID from Pterodactyl Admin → Nodes. Prefer an Egg ID allowlist where possible; the Docker image allowlist is also supported. The Application API key remains only in /etc/fragcity-api/config.php.

For the Server Browser token, grant **Servers: Read** and **Allocations: Read** in the Application API key permissions. The Application API server record exposes the primary allocation ID as `attributes.allocation`; the included Allocation records expose their own `attributes.id`. Discovery matches these IDs. Do not rely on the Client API's `is_default` field when reading Application API Allocation records. If the Allocations relationship is absent, discovery now fails with a specific diagnostic instead of silently returning an empty list.

For more than one Node, the current Standby discovery is intentionally single-node. Add additional Node support only after the first configured Node is working end-to-end; do not add guessed IPs or port ranges.

### Cache directory permissions

The Linux API PHP-FPM user must be able to create and update the cache directory. On the current Debian deployment, if PHP-FPM runs as www-data:

~~~bash
sudo install -d -o www-data -g www-data -m 0750 /var/cache/fragcity-api
~~~

No systemd timer or Cron Job is required. The optional CLI file linuxapi/bin/pterodactyl-standby-refresh.php can be run manually to warm the cache, but it should not be scheduled.

## Website runtime settings

Merge settings/pterodactyl-standby.integration.example.json into the private website settings/settings.json:

~~~json
{
  "integrations": {
    "pterodactyl_standby_api": {
      "base_url": "https://api.fragcity.ir",
      "shared_secret": "THE_SAME_SECRET_AS_LINUX"
    }
  }
}
~~~

The website shared_secret must exactly match Linux pterodactyl_standby.shared_secret. Generate a separate random secret; do not reuse WeaponPaints or Pterodactyl Monitoring secrets. The Pterodactyl Application API key must never be placed in website settings or browser JavaScript.

## Deployment

1. Pull the website repository on Laragon and run the PHP checks below.
2. Deploy the updated linuxapi/ source to /var/www/fragcity-api with the existing Linux API deployment procedure.
3. Merge the pterodactyl_standby block into /etc/fragcity-api/config.php.
4. Ensure the cache directory exists and is writable by the PHP-FPM user.
5. Nginx already has a fixed location = /v1/pterodactyl-standby route in linuxapi/nginx/fragcity-api-https.conf.example; no extra route is required if it is present in the live vhost.
6. No database migration is needed for this Pterodactyl Server Browser path. The old scanner tables remain untouched and are no longer read by pages/serverlist.php.

## Validation

From the repository root in Laragon:

~~~powershell
php -l linuxapi/src/PterodactylStandby/A2SInfoQuery.php
php -l linuxapi/src/PterodactylStandby/PterodactylApplicationClient.php
php -l linuxapi/src/PterodactylStandby/PterodactylStandbyDiscovery.php
php -l linuxapi/src/PterodactylStandby/PterodactylStandbySnapshotCache.php
php -l linuxapi/public/index.php
php -l class/PterodactylStandbyApiClient.php
php -l class/PterodactylStandbyRepository.php
php -l pages/serverlist.php
php linuxapi/tests/pterodactyl-primary-allocation-test.php
php linuxapi/tools/architecture-check.php
~~~

After deployment, run the explicit allocation A2S preflight on the Linux API host:

~~~bash
php linuxapi/bin/pterodactyl-standby-test-a2s.php 27085 29000 29001
~~~

Then open index.php?page=servers_vps (or the existing Server Browser route). The first request after cache expiry fetches the Pterodactyl server list and A2S data; requests within the following 10 seconds reuse that snapshot. Refreshing the page after 10 seconds triggers a new on-demand discovery.

If the website CLI reports an invalid/non-JSON response, first test the exact Nginx route without credentials. A plain GET is deliberately rejected by the PHP API with JSON HTTP 405 when the route reaches the front controller:

~~~bash
curl -i https://api.fragcity.ir/v1/pterodactyl-standby
~~~

Expected route-reached response: `HTTP 405` with `Content-Type: application/json` and `{"success":false,"error":"method_not_allowed"}`. An HTML/plain 404 usually means the live TLS vhost is missing the exact `location = /v1/pterodactyl-standby` block or the request reached a different vhost. An HTML 502 indicates a PHP-FPM/upstream problem. Inspect the active config with `sudo nginx -T`, confirm the location exists in the `api.fragcity.ir` 443 server block, then run `sudo nginx -t && sudo systemctl reload nginx` after a correction.

Expected initial servers for the configured Node are the three known CS2 Allocations on 27085, 29000 and 29001; each must return AppID 730 to appear online. Offline, suspended, uninstalled, invalid or non-CS2 allocations are not shown as online.

## Troubleshooting

- pterodactyl_standby_disabled: set enabled to true in the Linux runtime config.
- pterodactyl_standby_unavailable: inspect the Linux API PHP error log. The response deliberately does not expose Pterodactyl credentials or upstream URLs.
- No servers returned: verify node_id, the Docker image/Egg allowlist, the server's primary Allocation, and that the Application API key has both Servers: Read and Allocations: Read permissions.
- All discovered servers offline: verify query_host is reachable from the Linux API host over UDP, and that each default Allocation's game port is the A2S query port.
- Names/maps load but Connect does not work: verify public_connect_host and router/NAT forwarding for the public game ports.
- Cache write error: create /var/cache/fragcity-api and grant write access to the PHP-FPM account.
- Website reports transport timeout: check that the website can reach https://api.fragcity.ir, and keep the 25-second website API timeout in place.

## Scope of this step

This step solves Pterodactyl-backed CS2 server discovery and A2S_INFO for the public server list. It does not yet implement A2S_PLAYER, per-player detail, RCON, game-server lifecycle controls, or Pterodactyl resource monitoring. Those remain separate follow-on work.

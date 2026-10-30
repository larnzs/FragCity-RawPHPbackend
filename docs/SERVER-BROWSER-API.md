# FragCity Server Browser API

## Architecture

The website consumes only the signed HTTPS endpoint:

- Website-side client: `class/FragCityServerBrowserApiClient.php`
- Website-side repository: `class/ServerBrowserRepository.php`
- Linux API endpoint: `POST /v1/server-browser`
- Discovery worker: `linuxapi/bin/refresh-server-browser.php`
- Cached snapshot: `/var/cache/fragcity-api/server-browser.json`

The refresh worker uses the Pterodactyl 1.x Application API to enumerate servers and their assigned allocations. It restricts discovery to the configured node and configured CS2 Egg IDs or Docker image allowlist. Only the default allocation of each matching server is queried. A2S_INFO is sent over UDP to the configured internal query address; it is never sent by the website visitor's browser.

The public connect address is deliberately configured separately from the internal query address. For the current node, the known values are:

- Internal A2S host: `192.168.2.3`
- Public connect host: `85.185.40.85`
- Query/game port: the server's default Pterodactyl allocation port

No arbitrary port range is scanned. A successful A2S response must identify AppID 730 before the server is shown as online.

## Website runtime settings

Merge this fragment into the existing private `settings/settings.json`; do not replace the entire settings file:

~~~json
{
  "integrations": {
    "server_browser_api": {
      "base_url": "https://api.fragcity.ir",
      "shared_secret": "THE_SAME_SERVER_BROWSER_SECRET_CONFIGURED_ON_LINUX"
    }
  }
}
~~~

Keep the value private. It is used by PHP on the website host and must never be rendered into HTML or JavaScript. The local Laragon site can use the same HTTPS API origin, so local development and the production website consume the same server snapshot.

## Linux runtime configuration

Merge the following block into the existing `/etc/fragcity-api/config.php` return array. Do not replace the existing `shared_secret` or `database` sections used by WeaponPaints.

~~~php
'server_browser' => [
    'enabled' => true,
    'shared_secret' => 'A_SEPARATE_64_CHARACTER_HEX_SECRET',
    'cache_file' => '/var/cache/fragcity-api/server-browser.json',
    'cache_max_age_seconds' => 180,
    'public_connect_host' => '85.185.40.85',
    'query_host' => '192.168.2.3',
    'query_timeout_ms' => 1500,
    'max_servers' => 40,
    'region' => '',
    'pterodactyl' => [
        'base_url' => 'https://server1.fragcity.ir',
        'application_api_key' => 'READ_ONLY_APPLICATION_API_KEY',
        'node_id' => 1,
        'allowed_egg_ids' => [],
        'allowed_docker_images' => [
            'ghcr.io/k4ryuu/cs2-egg-go',
        ],
    ],
],
~~~

Important configuration notes:

- Generate a new independent HMAC secret with `openssl rand -hex 32`. The website value and Linux value must match. Do not reuse the WeaponPaints key.
- Create a Pterodactyl Application API key with read-only access to the server list and its allocations (the applicable `servers.read` and `allocations.read` permissions). Do not grant server create, update, delete, power, or user-management permissions. The key is stored only on Linux.
- Set `node_id` to the numeric ID of the node hosting these CS2 containers. Find it in the Panel under Admin → Nodes; the numeric ID is shown in the node detail URL. The sample value is a placeholder.
- The list request asks only for the `allocations` relationship. The Egg ID and node ID are read from the server's own attributes; no write permission is required.
- The Docker image allowlist is based on the configured CS2 Egg image. If the Application API reports a different image string, add the verified image or the exact Egg ID to the allowlist.
- For additional nodes, configure `node_query_hosts` as a map from numeric node ID to the IP bound by Docker for that node. Keep public connect hosts separate if nodes use different public addresses.
- The endpoint's HMAC key is separate from WeaponPaints. The current Linux API still uses its existing nonce table for request replay protection.

## Active Nginx vhost

The tracked Nginx examples include the new exact route, but the active server config is not changed by a Git pull. Before testing the API, add this location inside the existing TLS server block in /etc/nginx/sites-available/fragcity-api.conf:

~~~nginx
location = /v1/server-browser {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root/index.php;
    fastcgi_param SCRIPT_NAME /index.php;
    fastcgi_param HTTP_X_FRAGCITY_TIMESTAMP $http_x_fragcity_timestamp;
    fastcgi_param HTTP_X_FRAGCITY_NONCE $http_x_fragcity_nonce;
    fastcgi_param HTTP_X_FRAGCITY_SIGNATURE $http_x_fragcity_signature;
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
}
~~~

Then validate and reload:

~~~bash
sudo nginx -t
sudo systemctl reload nginx
~~~

Do not replace the entire active API vhost, and do not modify the Pterodactyl Panel vhost.

## Cache directory and worker

The worker and PHP-FPM both run as `www-data` on the current setup. Create the cache directory:

~~~bash
sudo install -d -o www-data -g www-data -m 0750 /var/cache/fragcity-api
~~~

After deploying the repository's updated `linuxapi/` directory to `/var/www/fragcity-api/`, install the systemd examples:

~~~bash
sudo install -o root -g root -m 0644 \
  /var/www/fragcity-api/systemd/fragcity-server-browser-refresh.service.example \
  /etc/systemd/system/fragcity-server-browser-refresh.service

sudo install -o root -g root -m 0644 \
  /var/www/fragcity-api/systemd/fragcity-server-browser-refresh.timer.example \
  /etc/systemd/system/fragcity-server-browser-refresh.timer

sudo systemctl daemon-reload
sudo systemctl enable --now fragcity-server-browser-refresh.timer
~~~

Run one manual refresh and inspect its output before enabling the timer:

~~~bash
sudo -u www-data php /var/www/fragcity-api/bin/refresh-server-browser.php
sudo systemctl status fragcity-server-browser-refresh.timer --no-pager
sudo journalctl -u fragcity-server-browser-refresh.service -n 30 --no-pager
sudo ls -l /var/cache/fragcity-api/server-browser.json
~~~

The timer refreshes the snapshot every 30 seconds. A refresh lock prevents overlapping workers. A temporary file plus atomic rename preserves the previous valid snapshot if a refresh fails.

## Validation

Before configuring the Pterodactyl token, test the PHP A2S implementation itself against the three known internal ports. After deploying the new source files, run:

~~~bash
sudo -u www-data php /var/www/fragcity-api/bin/test-a2s.php 27085 29000 29001
~~~

The test reads only `server_browser.query_host` from the Linux runtime config and queries only the explicitly supplied ports (maximum ten). It does not need the Pterodactyl Application API key and does not scan a range. Each response must identify AppID 730.

Then:

1. Run the discovery worker manually; expected output starts with `PASS: refreshed N CS2 allocation(s)`.
2. Confirm the JSON cache contains the discovered server list, and that each online server has `app_id=730` in the query result before it is published as online.
3. Use the website's signed client with a read-only smoke test to call `list_servers`.
4. Open the Server Browser locally in Laragon. It should display the same cached snapshot as the production website.
5. Check the website PHP error log if the API call fails. Check the systemd journal if discovery fails.

The website endpoint only reads the cache. It does not call Pterodactyl, open UDP sockets, or trigger a refresh per visitor.

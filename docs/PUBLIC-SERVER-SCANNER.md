# Public CS2 Server Browser — Database Scanner

## Active architecture

The public Server Browser is intentionally independent from Pterodactyl and from the Linux WeaponPaints API.

1. `public_server_scan_targets` stores an approved IPv4 address and inclusive port range.
2. `public_server_scan_ports` stores one scan state per port, including the last A2S result and the next retry time.
3. `class/PublicServerBrowserA2SScanner.php` handles the Source A2S_INFO UDP protocol and response parsing.
4. `class/PublicServerBrowserScanRepository.php` owns scanner-table SQL, inventory creation, retry scheduling and persistence.
5. `class/PublicServerBrowserScanStoreInterface.php` defines the storage contract; `PublicServerBrowserScanRepository.php` is its MySQL/MariaDB implementation.
6. `class/PublicServerBrowserScanWorker.php` orchestrates bounded batches without depending on the website UI or bootstrap.
7. `tools/refresh-public-server-browser.php` is a thin CLI entrypoint. It loads the environment settings, creates PDO and runs the worker from Cron.
8. `class/PublicServerBrowserRepository.php` reads only recent AppID 730 results for the public page.
9. The page does not query UDP, Pterodactyl, or a separate API when visitors load it.

The currently seeded target is:

| IPv4 host | Port start | Port end | Total |
|---|---:|---:|---:|
| `85.185.40.85` | 27085 | 29100 | 2,016 |

Only this explicitly configured host/range is scanned. To add another host, add another row to `public_server_scan_targets`; the worker validates the IPv4 address and limits each configured range to at most 4,096 ports.

## Database migration

Back up the website database first. Run this migration **once** against `kyonxcbt_fragcity_services` (or the production site's equivalent database):

- **phpMyAdmin:** select the website database → Import → choose `database/migrations/20261030_create_public_server_browser_tables.sql` → Go.
- **MySQL CLI:** from the repository root, run:

~~~bash
mysql -u DB_USER -p kyonxcbt_fragcity_services < database/migrations/20261030_create_public_server_browser_tables.sql
~~~

The migration creates:

- `public_server_scan_targets`: target host, inclusive port range, region, enable switch and retry/query intervals.
- `public_server_scan_ports`: per-port state, A2S metadata, last query time, next query time and last successful discovery time.

It also inserts the initial target `85.185.40.85:27085-29100`. Re-running the migration does not replace the existing target's enable switch or retry settings.

The migration intentionally avoids SQL `CHECK` constraints so it can be imported through a wider range of MariaDB/phpMyAdmin versions. The worker validates IPv4 targets, port ranges and scan limits in PHP before opening UDP sockets; timeout/retry settings are also clamped in the worker.

The first scheduled worker run creates the 2,016 per-port state rows. No manual IP/port row insertion is needed.

### Database permissions

The web/Cron database account needs SELECT, INSERT and UPDATE on the two new tables. It does not need access to WeaponPaints or the Pterodactyl database.

For the Laragon local account, the tracked `tools/laragon-site-db-user.sql` now includes grants for both scanner tables. Run the migration first, then execute only the two new GRANT statements at the end of that file with a local MariaDB administrator. Do not re-run its CREATE/ALTER USER statements unless you intentionally want to reset the local account password.

On cPanel/shared hosting, the database user usually already has privileges over its assigned database. If not, grant SELECT, INSERT and UPDATE on just these two tables through the hosting control panel or ask the provider.

## Query and retry policy

Defaults in the migration:

- A2S timeout: 1,500 ms per port.
- Maximum batch size: 32 concurrent UDP requests.
- Maximum scheduled work: 256 ports per Cron invocation.
- Online CS2 server: refresh after 90 seconds.
- Timeout, invalid response or non-CS2 application: retry after 3,600 seconds (one hour).

For the full range of 2,016 ports, the worker processes at most 256 per invocation. With a one-minute Cron schedule, the initial pass takes approximately eight invocations. A slower schedule (for example every five minutes) takes longer to discover the first complete range, but does not change the retry policy.

These are bounded batches; the worker does not open 2,016 sockets at once. A MySQL advisory lock prevents overlapping Cron processes. Each response must parse as a valid Source A2S_INFO packet and report AppID 730 before it is shown as a CS2 server.

Previously discovered servers remain visible with an Offline status for up to 10 minutes after their last query. After that, they are hidden until a fresh result arrives. This prevents a stopped Cron Job from leaving stale servers visible indefinitely, while the per-port retry schedule still waits one hour before retrying an unresponsive port.

## Portable runtime and host-specific configuration

The scanner is split into reusable PHP classes and does not require Laragon, Debian, Pterodactyl, or a specific hosting company. The same CLI worker can run on cPanel/shared hosting, a VPS, or another Linux/Windows PHP CLI environment.

Each runner needs all of the following:

- PHP CLI (PHP 8.x recommended for the FragCity application).
- PDO with the `pdo_mysql` driver and credentials for the website database.
- PHP UDP stream support: `stream_socket_client`, `stream_select` and `hrtime`.
- Outbound UDP access from the runner to every explicitly configured target IP/port.
- A Cron/scheduler that can execute the PHP CLI command.
- A copy of the required FragCity files and a private runtime settings JSON file.

The runner can be on a different machine from the website and game server. In that case, its host must be allowed to connect to the website's MySQL/MariaDB server. Restrict remote database access to the runner's fixed IP where possible; do not expose MySQL to the public internet. If the database provider only permits connections from the website account itself, run the Cron on that same hosting account.

The runtime settings file is selected through the existing `FRAGCITY_SETTINGS_FILE` environment variable. The runner also supports these optional per-host overrides:

| Environment variable | Default | Purpose |
|---|---:|---|
| `FRAGCITY_SERVER_BROWSER_DB_PROFILE` | `files.index.database_profile`, or `main` | Select a database profile from the runtime settings JSON |
| `FRAGCITY_SERVER_BROWSER_MAX_PER_RUN` | `256` | Maximum ports processed per Cron invocation (1–4096) |
| `FRAGCITY_SERVER_BROWSER_BATCH_SIZE` | `32` | Concurrent A2S queries per batch (1–128) |
| `FRAGCITY_SERVER_BROWSER_TIME_LIMIT` | `30` | PHP execution limit in seconds; use `0` to disable the PHP-level limit (0–300) |

Example environment for a low-resource shared host:

~~~bash
export FRAGCITY_SETTINGS_FILE="/home/ACCOUNT/private/fragcity-settings.json"
export FRAGCITY_SERVER_BROWSER_DB_PROFILE="main"
export FRAGCITY_SERVER_BROWSER_MAX_PER_RUN="128"
export FRAGCITY_SERVER_BROWSER_BATCH_SIZE="32"
export FRAGCITY_SERVER_BROWSER_TIME_LIMIT="25"

php /home/ACCOUNT/fragcity/tools/refresh-public-server-browser.php
~~~

Keep the settings JSON outside the public web root when possible. Do not commit it or place database credentials in the Cron command itself. Environment overrides are for operational tuning only; approved target IPs and port ranges remain in `public_server_scan_targets`.

**Important network limitation:** no code can force a hosting provider to permit outbound UDP. Run the UDP preflight from the exact PHP CLI environment and host that will execute Cron. A passing test from Laragon or a VPS does not prove that a different shared host can send UDP.

## PHP syntax and architecture checks

From the repository root, run:

~~~powershell
php -l class/PublicServerBrowserA2SScanner.php
php -l class/PublicServerBrowserScanRepository.php
php -l class/PublicServerBrowserScanWorker.php
php -l class/PublicServerBrowserRepository.php
php -l tools/refresh-public-server-browser.php
php -l tools/test-public-server-browser-udp.php
php linuxapi/tools/architecture-check.php
~~~

These are static/syntax checks. They do not prove the shared host can send UDP; the next section performs that separate network preflight.

## First test — run on the shared hosting account

The PHP runtime that runs the Cron Job must permit outbound UDP streams and `stream_select`. A local Laragon test does not prove the shared host permits UDP.

After running the migration and deploying the latest repository, execute this from the **same host and PHP CLI runtime that will run Cron** (for example, the cPanel Terminal), not from a different machine:

~~~bash
php tools/test-public-server-browser-udp.php 85.185.40.85 27085 29000 29001
~~~

The test is CLI-only and checks only those three explicit ports. It verifies that the PHP runtime can send UDP, receive A2S responses, handle an A2S challenge if returned, and validate AppID 730.

Expected output contains three lines with `PASS`, server name, map, player count and Query RTT. If it returns a timeout for all three ports, do not enable the range Cron yet.

### What a failed UDP preflight means

- **All three ports time out:** either outbound UDP is blocked by the shared host, public UDP forwarding to `192.168.2.3` is missing, or the public route is not reaching Docker.
- **Some pass and some time out:** verify the router's UDP forwarding and Docker allocation for each failed port.
- **A non-CS2 response:** the port is reachable, but the application is not CS2 (AppID 730); it will not be listed publicly.
- **PHP says `stream_socket_client` or `stream_select` is unavailable:** the hosting PHP policy/extensions do not support this approach. The active scanner cannot run there without provider changes or a different worker host.

The three earlier successful tests were run from Debian against the internal Docker address `192.168.2.3`. This new preflight is specifically required because the scanner will run from the shared host against the public address `85.185.40.85`. For those public queries, the MikroTik must forward the required inbound UDP ports to `192.168.2.3`, preserving each destination port. The existing internal test alone does not confirm that external path.

## Shared-hosting Cron Job

Only enable Cron after the three-port UDP preflight passes.

Recommended schedule: **once per minute**, if the hosting provider allows it. Add a cPanel Cron Job similar to this (replace the absolute paths with the account's real paths):

~~~cron
* * * * * /usr/bin/php -q /home/ACCOUNT/public_html/tools/refresh-public-server-browser.php >> /home/ACCOUNT/logs/public-server-browser.log 2>&1
~~~

Use the PHP CLI path/version recommended by the hosting provider. The Cron Job must run as the same website account that can read `settings/settings.json` and connect to the configured main database.

If the provider enforces a five-minute minimum, use:

~~~cron
*/5 * * * * /usr/bin/php -q /home/ACCOUNT/public_html/tools/refresh-public-server-browser.php >> /home/ACCOUNT/logs/public-server-browser.log 2>&1
~~~

The first complete discovery then takes roughly eight Cron runs (about eight minutes at a one-minute schedule, or about 40 minutes at five minutes). After the first pass, only due ports are queried.

## Manual first run and verification

Run one manual pass before relying on Cron:

~~~bash
php tools/refresh-public-server-browser.php
~~~

Expected summary resembles:

~~~text
PASS: scanned 256 port(s); online=3, offline=..., non_cs2=..., errors=...; elapsed=...s
~~~

The exact counts vary as the range is scanned. Query progress is stored in the database, so the next Cron invocation resumes with due ports.

Read-only database checks:

~~~sql
SELECT id, host, port_start, port_end, enabled
FROM public_server_scan_targets;

SELECT status, COUNT(*) AS total
FROM public_server_scan_ports
GROUP BY status;

SELECT t.host, p.port, p.server_name, p.map_name,
       p.players, p.max_players, p.query_rtt_ms, p.last_queried_at
FROM public_server_scan_ports AS p
JOIN public_server_scan_targets AS t ON t.id = p.target_id
WHERE p.status = 'online' AND p.app_id = 730
ORDER BY t.host, p.port;
~~~

Only after the migration, the UDP preflight and one manual run succeed should you leave the Cron Job enabled.

## Add or change a range

Add another approved address/range:

~~~sql
INSERT INTO public_server_scan_targets
    (host, port_start, port_end, region, enabled)
VALUES
    ('ANOTHER_IPV4_ADDRESS', 27015, 27100, 'Region name', 1);
~~~

The worker automatically creates per-port rows for a new or extended range. To stop scanning a target, set `enabled = 0`; its rows remain stored but are no longer queried or displayed.

If you change the target range, the worker inserts missing rows. Old port rows outside the current configured range remain in the database but are ignored by both the worker and public page.

## Pterodactyl Standby

The previous Pterodactyl Application API integration has been preserved separately under the Pterodactyl Standby names and is not part of the active scanner. See `docs/PTERODACTYL-STANDBY-API.md`. No Pterodactyl API key or Linux API secret is required for the active public Server Browser.

# FragCity Linux API — Deployment Notes

> Current architecture: WeaponPaints, the on-demand Pterodactyl/A2S Server Browser, and Pterodactyl/Wings Monitoring are served by the Linux API. The website keeps its own database on shared hosting (or a local Laragon copy). The retired Windows-Agent API returns HTTP 410.

## Active and retired endpoints

| Method | Path | Purpose | Authentication |
|---|---|---|---|
| GET | `/health` | Public service health only | None |
| POST | `/v1/weaponpaints` | WeaponPaints read/write operations | Root `shared_secret` |
| POST | `/v1/pterodactyl-standby` | Public CS2 Server Browser snapshot | `pterodactyl_standby.shared_secret` |
| POST | `/v1/pterodactyl-monitoring` | Node and CS2-container resource snapshot | `pterodactyl_monitoring.shared_secret` |
| any | `/v1/agent/*` | Retired Windows-Agent compatibility route | Returns HTTP 410 |

All POST endpoints use timestamped HMAC-SHA256 signatures and nonce replay protection. Each endpoint has an independent HMAC secret. The Linux API records request nonces in `GLOBAL_weaponpaints.fragcity_api_nonces`.

## Configuration files

- Tracked example: `linuxapi/config.example.php`.
- Live private Linux config: `/etc/fragcity-api/config.php`.
- Override path for CLI/manual diagnostics: environment variable `FRAGCITY_API_CONFIG`.
- Website-side complete template: `settings/settings.example.json`.
- Website-side fragments: `settings/weaponpaints-api.integration.example.json`, `settings/pterodactyl-standby.integration.example.json` and `settings/pterodactyl-monitoring.integration.example.json`.

For a first installation, copy the PHP example to the private path and replace every placeholder. For an existing installation, merge only the new keys; do not overwrite the current database password or root WeaponPaints HMAC secret.

Generate each HMAC key separately on Linux:

```bash
openssl rand -hex 32
```

The 64-character output for the WeaponPaints root key matches website `integrations.weaponpaints_api.shared_secret`. Generate a second key for Standby and a third for Monitoring. The corresponding website settings must match each endpoint's Linux block exactly.

The website `base_url` values must be the HTTPS origin `https://api.fragcity.ir` only. Do not append an endpoint path.

## Data boundaries and feature behavior

### WeaponPaints

The Linux API connects to `GLOBAL_weaponpaints` via `127.0.0.1:3306`. It must never connect to `kyonxcbt_fragcity_services` or receive the website database credentials. The website's `databases.main` profile is independent.

The website uses `class/FragCityLinuxApiClient.php` and `class/WeaponPaintsRepository.php`. Keep `files.database.weaponpaints_plugin.transport` set to `api`; do not create a direct website-side WeaponPaints DB profile.

### Public CS2 Server Browser

The active public page `pages/serverlist.php` uses `PterodactylStandbyRepository`. The website sends a signed request to Linux API; Linux reads the allowlisted CS2 server inventory through the read-only Pterodactyl Application API, selects each server's default Allocation, then performs A2S_INFO over UDP to the configured internal `query_host`.

Only explicit Allocation ports are queried; visitor-controlled hosts/ports and broad port-range scans are not accepted. The active flow is on-demand with a 10-second snapshot cache and does not need Cron. The current discovery implementation is intentionally single-Node. The legacy database scanner and its tables remain for rollback/reference only; do not schedule its worker as the active source.

The website receives only sanitized fields. Keep the Pterodactyl Application API token on Linux; it must never be placed in website settings or browser JavaScript.

For the Server Browser integration, the Application API key needs **Servers: Read** and **Allocations: Read**. The primary game port is selected by matching the server record's `allocation` ID with the included Allocation record's `id`; the Application API response must include the `allocations` relationship.

### Pterodactyl Monitoring

The website retains an admin-only authenticated endpoint for read-only monitoring. The legacy CS2 dashboard UI and the `panels/` directory have been removed; no browser monitoring page is shipped. The endpoint can still sign a request to Linux API, which reads Pterodactyl Node/server data and Wings resource counters and returns a sanitized snapshot. Application API and Wings tokens remain on Linux. The backend route is on-demand and does not require Cron.

## Private Linux configuration and permissions

The PHP runtime config must remain outside the document root:

```text
/etc/fragcity-api/config.php
```

The file should be owned by root and readable by the PHP-FPM group, but not writable by the web process. Keep the API's MariaDB account least-privileged and MariaDB port 3306 private. Do not use the MariaDB root account.

For the current Debian service, PHP-FPM runs as `www-data`. The Standby snapshot cache directory can be prepared with:

```bash
sudo install -d -o www-data -g www-data -m 0750 /var/cache/fragcity-api
```

The API requires access to the configured database for nonce replay protection. The existing `fragcity_api_nonces` table and grants must be preserved.

## Nginx configuration

- `linuxapi/nginx/fragcity-api.conf.example` is the HTTP bootstrap example.
- `linuxapi/nginx/fragcity-api-https.conf.example` is the HTTPS/TLS example.

Both examples route the fixed API paths to `public/index.php` and reject other paths/PHP files. Verify the PHP-FPM socket matches the installed Debian version. Do not replace or edit the Pterodactyl Panel vhost as part of the API deployment.

After modifying the live API vhost:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

## Validation

From the website repository root on Laragon:

```powershell
php -l class/FragCityLinuxApiClient.php
php -l class/WeaponPaintsRepository.php
php -l class/PterodactylStandbyApiClient.php
php -l class/PterodactylStandbyRepository.php
php -l class/PterodactylMonitoringApiClient.php
php -l class/PterodactylMonitoringRepository.php
php -l linuxapi/public/index.php
php -l linuxapi/config.example.php
php linuxapi/tests/pterodactyl-primary-allocation-test.php
php linuxapi/tools/architecture-check.php
```

On the Linux API host, test the explicit A2S Allocation ports configured for the current Node:

```bash
php bin/pterodactyl-standby-test-a2s.php 27085 29000 29001
```

This manual test verifies direct A2S connectivity from the Linux host; it does not by itself verify the shared-host website-to-Linux HTTPS/HMAC path. Test the signed routes separately using the existing smoke-test tooling and the website CLI repository tests.

Repository changes are not automatically deployed to Debian or cPanel. Back up private runtime configuration before merging new keys, deploy API source through the existing deployment procedure, and validate each environment independently. Never paste API tokens, HMAC secrets, database passwords or Wings tokens into chat or Git.

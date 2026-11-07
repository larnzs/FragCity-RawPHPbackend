# FragCity Linux API — Deployment Notes

> Current architecture: WeaponPaints, SimpleAdmin, VIPCore entitlements, the read-only Ranks leaderboard, the on-demand Pterodactyl/A2S Server Browser, and Pterodactyl/Wings Monitoring are served by the Linux API. The website keeps its own database on shared hosting (or a local Laragon copy). The retired Windows-Agent API returns HTTP 410.

## Active and retired endpoints

| Method | Path | Purpose | Authentication |
|---|---|---|---|
| GET | `/health` | Public service health only | None |
| POST | `/v1/weaponpaints` | WeaponPaints read/write operations | Root `shared_secret` |
| POST | `/v1/simpleadmin` | Read-only SimpleAdmin lookups and Ban List data | Root `shared_secret` |
| POST | `/v1/vipcore` | VIPCore status, paid grants and authenticated site workflows including membership revocation | Same root `shared_secret` |
| POST | `/v1/ranks` | Read-only Ranks leaderboard and player statistics | Same root `shared_secret` |
| POST | `/v1/pterodactyl-standby` | Public CS2 Server Browser snapshot | `pterodactyl_standby.shared_secret` |
| POST | `/v1/pterodactyl-monitoring` | Node and CS2-container resource snapshot | `pterodactyl_monitoring.shared_secret` |
| any | `/v1/agent/*` | Retired Windows-Agent compatibility route | Returns HTTP 410 |

All POST endpoints use timestamped HMAC-SHA256 signatures and nonce replay protection. WeaponPaints, SimpleAdmin, VIPCore and Ranks intentionally share the existing root `shared_secret`; Pterodactyl Standby and Monitoring retain their independent secrets. The Linux API records request nonces in `GLOBAL_weaponpaints.fragcity_api_nonces`.

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

### VIPCore entitlements

The Linux API reads and writes the existing VIPCore-owned table `GLOBAL_vip.vip_users`; it does not create or alter the plugin schema. The table is keyed by `(account_id, sid)`, stores the uppercase VIP group and an absolute Unix `expires` timestamp (0 means permanent). The website sends SteamID64; Linux converts it to Steam Account ID and supplies the configured `ServerId`. That value must match `ServerId` in the live VIPCore `vip_core.json` (the repository currently documents `0`; verify before enabling).

The current FragCity deployment reuses the existing MariaDB account `plugins`@ `%` for WeaponPaints, SimpleAdmin, VIPCore and Ranks. Do not create `fragcity_vip_api`, generate another database password, or change the existing account as part of this integration. The private Linux config uses the same `$pluginsDbPassword` variable for the WeaponPaints, VIPCore and Ranks connections.

The VIPCore service reads and writes `GLOBAL_vip.vip_users`; customer revocation additionally executes a scoped `DELETE` for the authenticated Steam account and inserts an append-only row in `fragcity_vip_membership_revocations`. It does not need DDL or arbitrary SQL at runtime. However, the current shared `plugins` account has broader schema-level permissions (including `WITH GRANT OPTION`) than this endpoint requires. This is an explicit compatibility choice for the existing installation, not a least-privilege design. Keep the account private to the host and do not expose its password. A future move to separate restricted service accounts should be handled as a coordinated credential migration, not by changing grants during this deployment.

Inspect the existing account without changing it:

```sql
SHOW GRANTS FOR 'plugins'@'%';
SHOW CREATE TABLE GLOBAL_vip.vip_users;
```

Merge the `vipcore` block from `linuxapi/config.example.php` into the existing `/etc/fragcity-api/config.php`; do not replace that live file or its existing secrets. Set `enabled => true`, reuse the existing `plugins` credentials, and keep `server_id => 0` only while it matches the running VIPCore configuration. Leave `test_mode.enabled => false` for connection checks. If a designated test grant is later approved, temporarily enable test mode and allowlist only that exact test SteamID64. The endpoint rejects tiers 4-6 and refuses to overwrite an active or permanent membership. Test grants do not read prices, debit the wallet, or call ZarinPal.

The fixed API actions include `ping_db`, `get_status`, `get_free_trial_status`, `claim_free_silver_trial`, `grant_paid_membership`, `revoke_membership` and the restricted `grant_test_vip`. Customer revocation is requested only from an authenticated FragCity session, through the website's signed server-to-server client; the browser never supplies a SteamID64 or database key. The API deletes only the current `(account_id, sid)` membership row, verifies the deletion, records the former tier/expiry/order reference in `fragcity_vip_membership_revocations`, and leaves payment ledgers intact. The website marks the matching order as `revoked` so it is no longer offered as a pending activation retry. The website's VIP page reads membership status from VIPCore rather than legacy `users.vip_level/vip_expire` fields.

### Paid VIP checkout database prerequisites

The paid VIP storefront remains fail-closed until the Linux API confirms that the paid-order ledger and the existing VIPCore membership table are readable and writable by the configured `vipcore.database` account.

Apply these migrations in order on the existing `GLOBAL_vip` database:

1. `linuxapi/migrations/20261107_vip_paid_orders.sql`
2. `linuxapi/migrations/20261107_vip_paid_order_tier_prices.sql`
3. `linuxapi/migrations/20261107_vip_paid_order_permissions.sql`

The third migration grants only `SELECT` and `INSERT` on the paid-order ledger to the already-existing `plugins`@`%` account used by this deployment.\n\nFor customer-initiated membership revocation, also apply these two additional migrations on `GLOBAL_vip` after the paid-order migrations:\n\n4. `linuxapi/migrations/20261107_vip_membership_revocations.sql`\n5. `linuxapi/migrations/20261107_vip_membership_revocation_permissions.sql`\n\nThe revocation permissions migration grants `DELETE` only on `vip_users` and `SELECT, INSERT` on the separate revocation audit table. The API verifies that exactly one current membership row was removed before it reports success. Never delete rows from `fragcity_vip_paid_orders` or the one-time free-trial claim ledger. Do not create another MariaDB user. If the actual MariaDB account host has changed from the documented `%`, stop and adjust the grant to the exact existing account shown by `SELECT User, Host FROM mysql.user WHERE User = 'plugins';`.

After deploying the latest `linuxapi/src/VIPCore/VipCoreService.php` to the live API source (normally `/var/www/fragcity-api/src/VIPCore/VipCoreService.php`), run the CLI preflight:

```bash
sudo php /var/www/fragcity-api/tools/vipcore-paid-ledger-preflight.php
```

The preflight uses the same private `vipcore` connection as the API and checks the exact schema and SELECT/INSERT/UPDATE capabilities required by checkout. Its INSERT/UPDATE probes use `WHERE 1 = 0` and do not modify rows. Continue only when every check reports `PASS` and the final result says the database prerequisites are ready. If any check fails, its SQLSTATE and MariaDB driver code identify whether a migration/column or privilege is missing. Do not bypass the website's readiness check.

### Ranks leaderboard

The website reads the existing `GLOBAL_ranks.lvl_base` table only through the signed `POST /v1/ranks` route. The table schema is owned by the game plugin: do not create, alter, truncate or seed it as part of this integration. The API exposes three fixed actions: `ping_db`, `get_leaderboard` and `get_player`. The leaderboard action performs allowlisted sorting, literal search, filtered aggregates, Top 3 and bounded pagination on Linux; it does not accept SQL fragments or arbitrary column names. The player action accepts SteamID64 and converts it to the Steam2 value stored in `lvl_base.steam`.

The Ranks service executes read-only `SELECT` queries against `GLOBAL_ranks.lvl_base`. In the current FragCity deployment it reuses the same existing `plugins`@ `%` MariaDB account as WeaponPaints and VIPCore. Do not create `fragcity_ranks_api` or add another database password.

The existing shared account has broader grants across its plugin schemas than the Ranks endpoint needs, and includes `WITH GRANT OPTION`. This is a known security trade-off of reusing the current plugin account; it is not least-privilege isolation. No GRANT or REVOKE is required for the already verified installation. Inspect the existing permissions with:

```sql
SHOW GRANTS FOR 'plugins'@'%';
```

Merge the `ranks` block from `linuxapi/config.example.php` into `/etc/fragcity-api/config.php`, set `enabled => true`, and reuse the existing `plugins` password. Never replace the whole live config or rotate its root HMAC secret. The route uses the existing WeaponPaints root HMAC key; the website's `integrations.weaponpaints_api.shared_secret` remains the single matching secret.

After deploying the new API files and fixed Nginx route, run `nginx -t`, reload Nginx, then run `php tools/ranks-smoke-test.php` from the website repository root. The smoke test checks HMAC authentication, database access, and a first leaderboard page. The site leaderboard and profile pages use `class/RanksRepository.php`; they no longer require a direct website-side connection to `GLOBAL_ranks`.

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

The file should be owned by root and readable by the PHP-FPM group, but not writable by the web process. Keep MariaDB port 3306 private and never use the MariaDB root account from PHP. The current FragCity host intentionally reuses the existing `plugins` account, whose grants are broader than the minimum permissions required by each endpoint; treat it as a high-impact private credential.

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
php -l class/VipCoreRepository.php
php -l api/vip-test-purchase.php
php -l linuxapi/src/VIPCore/VipCoreService.php
php -l linuxapi/tests/vipcore-service-test.php
php -l class/PterodactylStandbyApiClient.php
php -l class/PterodactylStandbyRepository.php
php -l class/PterodactylMonitoringApiClient.php
php -l class/PterodactylMonitoringRepository.php
php -l linuxapi/public/index.php
php -l linuxapi/config.example.php
php linuxapi/tests/pterodactyl-primary-allocation-test.php
php linuxapi/tests/vipcore-service-test.php
php linuxapi/tools/architecture-check.php
```

On the Linux API host, test the explicit A2S Allocation ports configured for the current Node:

```bash
php bin/pterodactyl-standby-test-a2s.php 27085 29000 29001
```

This manual test verifies direct A2S connectivity from the Linux host; it does not by itself verify the shared-host website-to-Linux HTTPS/HMAC path. Test the signed routes separately using the existing smoke-test tooling and the website CLI repository tests.

Repository changes are not automatically deployed to Debian or cPanel. Back up private runtime configuration before merging new keys, deploy API source through the existing deployment procedure, and validate each environment independently. Never paste API tokens, HMAC secrets, database passwords or Wings tokens into chat or Git.

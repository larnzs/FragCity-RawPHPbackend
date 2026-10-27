# FragCity Linux API — Deployment Notes

> Architecture baseline (2026-10-27): this service currently exposes the WeaponPaints API only. The old Windows-Agent bridge is retired and must not be re-enabled as part of a routine deployment.

## Active endpoint

- Public health: `GET /health`
- WeaponPaints: `POST /v1/weaponpaints`
- Retired Windows-Agent compatibility routes: `/v1/agent/*` returns HTTP 410.

The Linux application connects to `GLOBAL_weaponpaints` via `127.0.0.1:3306`. It does not connect to `kyonxcbt_fragcity_services`. The website’s main database remains on the website host (or a local Laragon copy during development).

## Website client configuration

The website uses `class/FragCityLinuxApiClient.php` and `class/WeaponPaintsRepository.php`. In the private website `settings/settings.json`, configure:

- `integrations.weaponpaints_api.base_url`: `https://api.fragcity.ir`
- `integrations.weaponpaints_api.shared_secret`: the existing API shared secret, stored privately only.
- `files.database.weaponpaints_plugin.transport`: `api`

Do not configure `databases.weaponpaints`, `kyonxcbt_weaponpaints_plugin`, or any Linux MariaDB credentials in the website settings. Keep the actual secret out of Git and browser JavaScript.

**Important:** local PHP may still be calling the real API. During save tests, use a dedicated test SteamID/account only; the API targets the real `GLOBAL_weaponpaints` data store.

## Server API security and deployment

- Keep MariaDB port 3306 private.
- Keep `/etc/fragcity-api/config.php` readable by PHP-FPM and not writable by the web process.
- Keep TLS certificate verification enabled.
- Preserve the existing Pterodactyl Nginx vhost when changing the API vhost.
- The old `website_bridge` config block and `linuxapi/site-bridge-schema.sql` are obsolete and have been removed. Do not recreate bridge nonce/login-attempt tables or restore `api/internal_agent_bridge.php` as an active DB connector.

## Validation

From the repository root in the local PHP CLI:

```powershell
php -l linuxapi/public/index.php
php -l class/FragCityLinuxApiClient.php
php -l class/WeaponPaintsRepository.php
php linuxapi/tools/architecture-check.php
php tools/site-bridge-preflight.php
```

The schema preflight checks the ten supplied website tables through the local `main` DB profile. It does not connect to WeaponPaints or alter any data.

Repository changes are not automatically deployed to Debian or cPanel. After deployment, independently run the existing signed WeaponPaints smoke test from the Debian host and controlled test-player read/write tests before treating API writes as production-verified.

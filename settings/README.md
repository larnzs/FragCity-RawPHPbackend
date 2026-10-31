# FragCity runtime configuration

This directory contains the website settings loader, safe example files, and web-server protection snippets.

## Which file is actually loaded?

- `settings/settings.json` is the private runtime file used by Laragon and, by default, by the website on the host.
- `settings/settings.example.json` is the complete tracked website template. It contains placeholders only and is safe to commit.
- `FRAGCITY_SETTINGS_FILE` may point the PHP process to a different private JSON file. When it is set, that exact file is loaded.
- `settings/*.integration.example.json` files are small, optional fragments. They are NOT loaded automatically. Merge the relevant `integrations` object into the private `settings/settings.json`.

The loader intentionally does not fall back to the example file. If the runtime file is missing, unreadable or invalid JSON, configuration stays fail-closed. Do not copy the example over an existing local or Production settings file.

Create the file only when it does not already exist:

```powershell
Copy-Item .\settings\settings.example.json .\settings\settings.json
```

The main example uses valid JSON. JSON has no comment syntax, so it carries adjacent `_comment` metadata properties as inline documentation. The settings loader ignores these unknown metadata keys. Keep comments as JSON strings; do not add JavaScript-style `//` or `/* */` comments to the runtime JSON.

## Website settings structure

The complete template documents these sections:

| Section | Purpose |
|---|---|
| `app` | Environment name, timezone, canonical site URL and debug switch |
| `databases.main` | FragCity website database connection for the current environment |
| `databases.plugin_sync` | Optional isolated plugin-sync schema |
| `databases.legacy_services` | Compatibility profile for older site pages |
| `databases.ranks_plugin` | Optional Ranks plugin schema |
| `databases.simpleadmin_plugin` | Optional SimpleAdmin plugin schema |
| `integrations.steam` | Steam Web API and OpenID-related site settings |
| `integrations.zarinpal` | Payment gateway URLs and merchant identity |
| `integrations.weaponpaints_api` | Signed Linux WeaponPaints API client |
| `integrations.pterodactyl_standby_api` | Signed Server Browser API client |
| `integrations.pterodactyl_monitoring_api` | Signed admin Monitoring API client |
| `files` | Per-page, class and plugin behavior/settings |

Every database profile referenced by `files.*.database_profile` must exist under `databases`. A profile with an empty schema name is intentionally unconfigured. Do not configure a website-side `databases.weaponpaints` connection: the site must never connect directly to the Linux WeaponPaints database.

## Website-to-Linux API credentials

There are three independent signed API clients. Keep their secrets different:

| Website setting | Matching Linux setting | Endpoint |
|---|---|---|
| `integrations.weaponpaints_api.shared_secret` | top-level `shared_secret` | `POST /v1/weaponpaints` |
| `integrations.pterodactyl_standby_api.shared_secret` | `pterodactyl_standby.shared_secret` | `POST /v1/pterodactyl-standby` |
| `integrations.pterodactyl_monitoring_api.shared_secret` | `pterodactyl_monitoring.shared_secret` | `POST /v1/pterodactyl-monitoring` |

For all three clients, `base_url` is the HTTPS origin only, for example `https://api.fragcity.ir`. Do not append `/v1`, an endpoint path, a query string or a trailing application subdirectory; the PHP client appends the fixed endpoint itself.

Each shared secret must be 64 lowercase hexadecimal characters or longer (up to 128), and should be generated independently on Linux with:

```bash
openssl rand -hex 32
```

Do not paste secrets into chat, Git, issue trackers or browser JavaScript. The website stores only the matching HMAC secret for each integration. It must never store the Pterodactyl Application API token or a Wings token.

### WeaponPaints

Use `settings/weaponpaints-api.integration.example.json` as a small reference fragment, or use the complete template. The website's `files.database.weaponpaints_plugin.transport` must remain `api`. Its secret matches the root `shared_secret` in `/etc/fragcity-api/config.php`. The Linux API's database connection remains on `GLOBAL_weaponpaints`; this is separate from `databases.main`.

### Public Server Browser / Standby

Use `settings/pterodactyl-standby.integration.example.json`. Its secret matches Linux `pterodactyl_standby.shared_secret`. The website receives a sanitized server snapshot; the Linux API owns the read-only Pterodactyl Application API token and sends A2S_INFO to the selected default Allocations. The live implementation is single-Node for now and refreshes its 10-second cache on demand; no Cron job is needed.

### Pterodactyl Monitoring

Use `settings/pterodactyl-monitoring.integration.example.json`. Its secret matches Linux `pterodactyl_monitoring.shared_secret`. This signed admin-only endpoint reads Pterodactyl/Wings resource data. The legacy CS2 web panel and its dashboard UI have been removed; the backend route remains available for future authorized tooling. It is separate from the public Server Browser and WeaponPaints.

## Linux API private configuration

The matching Linux-side template is `linuxapi/config.example.php`. It documents:

- root WeaponPaints `shared_secret` and the Linux-local MariaDB connection;
- an independent `pterodactyl_standby` block, including cache, internal A2S query host, public connect host, Node ID, allowlists and read-only Application API token;
- an independent `pterodactyl_monitoring` block, including Node allowlist, host metrics options, Wings URL overrides and the read-only Application API token.

The example is copied to `/etc/fragcity-api/config.php` only for a fresh install. On an existing server, merge the required keys into the live config; never overwrite its real database password or current HMAC secrets. Keep the file outside the public document root, readable by PHP-FPM and not writable by the web process.

The same Pterodactyl Application API token may be reused by Standby and Monitoring if its read-only scopes cover both features. Its value stays only on Linux. The HMAC secrets must still be unique per endpoint.

## Web-server protection

- Apache deployments should retain `settings/.htaccess` to block direct access to private settings.
- Nginx does not read `.htaccess`; include `settings/nginx-protect.conf` inside the active website `server {}` block, or store the runtime settings outside the document root and set `FRAGCITY_SETTINGS_FILE`.
- The API Nginx templates live under `linuxapi/nginx/`. The HTTP file is a bootstrap example; the HTTPS file is the TLS example. Both should route only the fixed API paths to `public/index.php`.

See `docs/CONFIGURATION.md`, `docs/PTERODACTYL-STANDBY-API.md`, `docs/PTERODACTYL-MONITORING.md` and `docs/LINUXAPI-DEPLOYMENT.md` for deployment and validation steps.

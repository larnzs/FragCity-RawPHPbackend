# Runtime settings

FragCity loads runtime configuration only from `settings/settings.json` by default.
It may be overridden with the `FRAGCITY_SETTINGS_FILE` environment variable.

`settings/settings.example.json` is documentation/reference only. The runtime never
falls back to it. If the runtime settings file is missing, unreadable, or invalid,
database access stays disabled and an error is written to the PHP error log.

## Laragon / local setup

Extract the provided local configuration bundle at the repository root. It creates the ignored `settings/settings.json` and `local-config/laragon-site-db-user.sql`; the latter contains the generated local DB password and must remain local. The tracked `tools/laragon-site-db-user.sql` is only a placeholder template.

1. Put the environment-specific JSON at `settings/settings.json`.
2. Set `databases.main` to a least-privileged local account for
   `kyonxcbt_fragcity_services`.
3. Set `integrations.weaponpaints_api.base_url` to
   `https://api.fragcity.ir`.
4. Set `integrations.weaponpaints_api.shared_secret` to the exact secret
   configured in `/etc/fragcity-api/config.php` on the Linux host.
5. Set `files.database.weaponpaints_plugin.transport` to `api`.

Do not configure a website-side connection to `GLOBAL_weaponpaints`; the website
uses the signed HTTPS API. Do not expose the shared secret in browser JavaScript.

`settings/settings.json` is intentionally ignored by Git. A private repository is
not a secure secret store: credentials accidentally committed remain in Git history
and may be copied to clones, logs, backups, or automation systems.


## Public CS2 Server Browser

The active public Server Browser obtains the CS2 server inventory through the signed Pterodactyl Standby API. Merge `settings/pterodactyl-standby.integration.example.json` into the private `settings/settings.json`:

~~~json
{
  "integrations": {
    "pterodactyl_standby_api": {
      "base_url": "https://api.fragcity.ir",
      "shared_secret": "MATCH_THE_LINUX_PTERODACTYL_STANDBY_SECRET"
    }
  }
}
~~~

The Pterodactyl Application API key stays in `/etc/fragcity-api/config.php` on Linux and must never be placed in website settings or browser JavaScript. The Linux API reads the configured Pterodactyl Node and CS2 Egg/Docker image allowlist, selects each server's default Allocation, and performs concurrent A2S_INFO queries.

The API refreshes its short cache (10 seconds by default) when a website request arrives. No Cron Job or manual port-range scan is needed. The legacy database scanner files and tables are retained for rollback only; do not schedule `tools/refresh-public-server-browser.php`.

See `docs/PTERODACTYL-STANDBY-API.md` for runtime config, cache permissions, deployment and validation. The admin resource dashboard is a separate integration documented in `docs/PTERODACTYL-MONITORING.md`.

## Pterodactyl Monitoring

The admin-only CS2 panel page `panels/cs2/monitoring.php` obtains live node/server resource information through the signed Linux API route `/v1/pterodactyl-monitoring`. Configure `integrations.pterodactyl_monitoring_api.base_url` and its separate `shared_secret` in the private website `settings/settings.json`; the matching Linux-side Pterodactyl Application API key, node allowlist, and Wings settings stay in `/etc/fragcity-api/config.php`.

Use `settings/pterodactyl-monitoring.integration.example.json` as the website-side fragment. Never put the Pterodactyl Application API key or Wings token in website settings or browser JavaScript. The browser is authorized by the FragCity admin session only; it never receives Pterodactyl credentials.

See `docs/PTERODACTYL-MONITORING.md` for node allowlists, Wings TLS/private URL requirements, deployment, and validation.

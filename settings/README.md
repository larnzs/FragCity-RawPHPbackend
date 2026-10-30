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

The active public Server Browser uses the main website database. It does not require a new API URL, HMAC secret or Pterodactyl token in `settings/settings.json`.

- Scanner targets and per-port results are stored in `public_server_scan_targets` and `public_server_scan_ports`.
- The scheduled CLI job is `tools/refresh-public-server-browser.php`.
- The public page reads results through `class/PublicServerBrowserRepository.php`.
- The Cron Job and PHP website use the existing `files.index.database_profile` / `databases.main` connection. A separate runner can select its profile with `FRAGCITY_SERVER_BROWSER_DB_PROFILE`; its runtime JSON path can be supplied with `FRAGCITY_SETTINGS_FILE`.
- The A2S parser, storage interface, PDO scanner-table repository and batch worker are separate classes. Per-host workload settings can be overridden with `FRAGCITY_SERVER_BROWSER_MAX_PER_RUN`, `FRAGCITY_SERVER_BROWSER_BATCH_SIZE` and `FRAGCITY_SERVER_BROWSER_TIME_LIMIT`.

See `docs/PUBLIC-SERVER-SCANNER.md` for the database migration, runtime requirements, environment overrides, UDP preflight and portable Cron setup. Any runner still needs outbound UDP permission and database reachability; hosting-provider firewall restrictions cannot be bypassed by the PHP module.

The separate Pterodactyl API experiment is preserved under `Pterodactyl Standby`; it is not required to display public servers.

## Pterodactyl Monitoring

The admin-only CS2 panel page `panels/cs2/monitoring.php` obtains live node/server resource information through the signed Linux API route `/v1/pterodactyl-monitoring`. Configure `integrations.pterodactyl_monitoring_api.base_url` and its separate `shared_secret` in the private website `settings/settings.json`; the matching Linux-side Pterodactyl Application API key, node allowlist, and Wings settings stay in `/etc/fragcity-api/config.php`.

Use `settings/pterodactyl-monitoring.integration.example.json` as the website-side fragment. Never put the Pterodactyl Application API key or Wings token in website settings or browser JavaScript. The browser is authorized by the FragCity admin session only; it never receives Pterodactyl credentials.

See `docs/PTERODACTYL-MONITORING.md` for node allowlists, Wings TLS/private URL requirements, deployment, and validation.

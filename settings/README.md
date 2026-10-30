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
- The Cron Job and PHP website use the existing `files.index.database_profile` / `databases.main` connection.

See `docs/PUBLIC-SERVER-SCANNER.md` for the database migration, UDP preflight and shared-hosting Cron setup.

The separate Pterodactyl API experiment is preserved under `Pterodactyl Standby`; it is not required to display public servers.
# FragCity — Configuration Guide

## Purpose

Environment-specific values are grouped in JSON: one block per consuming module under `files`, shared reusable database profiles under `databases`, and external-service settings under `integrations`.

## Files

- `settings/loader.php`: shared JSON loader, dotted-key lookup, timezone setup, database profile inheritance, and PDO factory.
- `settings/settings.example.json`: tracked example with blank DB credentials and payment merchant ID.
- `settings/settings.json`: runtime settings for one environment; ignored by Git.
- `settings/.htaccess`: protects settings under Apache.
- `settings/nginx-protect.conf`: snippet to copy into the Laragon Nginx server block. Nginx ignores .htaccess; block `/settings/` or put runtime JSON outside DocumentRoot and set `FRAGCITY_SETTINGS_FILE`.

## Database profiles

- `main`: site, wallet, server records, Agent and panel pages.
- `weaponpaints`: WeaponPaints plugin DB.
- `plugin_sync`: database selected for mapped plugin bulk synchronization.
- `ranks_plugin` and `simpleadmin_plugin`: databases used by the ranks and ban-list adapters.
- `legacy_services`: explicitly separate profile for legacy password-reset pages.

Profiles can inherit host, port, username and password from a base profile and override their database name. Empty profile fields return null; the application never falls back to credentials embedded in PHP.

## Module blocks migrated

The settings example defines blocks for the main index, customer/weaponpaint DB wrappers, Agent endpoints, plugin sync, Steam login routes, Zarinpal wallet/server request and callback routes, server purchase/list pages, VIP and password recovery, legacy test index, and CS2 panel configuration/settings/overview/players/servers.

The JSON example also holds the CS2 cfg allowlist, plugin table-to-database map, site URL, timezone and Zarinpal merchant/API/payment-gateway endpoints. Translation dictionaries and business logic remain in PHP; runtime settings and data lists belong in JSON.

## Local setup

1. Copy `settings/settings.example.json` to `settings/settings.json`.
2. Set a local-only DB profile and fill it using credentials for a dedicated development database. Do not use Production credentials locally.
3. Set `app.site_base_url` to the local virtual host (the example uses `http://fragcity.test`).
4. Disable real payments locally and leave the local Zarinpal merchant ID blank.
5. Before creating a secrets-bearing `settings.json` under the web root, apply `settings/nginx-protect.conf` to Nginx or keep the runtime file outside DocumentRoot with `FRAGCITY_SETTINGS_FILE`.

## PHP version and timezone

The repository uses PHP 8.x features. Select PHP 8.4 in Laragon. The loader applies `app.timezone` early (UTC by default). Parse errors occur before loader execution, so a PHP-version mismatch must be fixed separately.

## Deployment

Prepare a separate runtime `settings.json` per environment before deployment. Set the production site URL, production-specific DB profiles and gateway configuration on the server, protect the file from HTTP access, back up, deploy, run smoke tests, and retain rollback. Never commit Production credentials.

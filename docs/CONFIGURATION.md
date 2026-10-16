# FragCity — Configuration Guide

## Purpose

Environment-specific values are grouped in JSON: per-module blocks under `files` and reusable DB profiles under `databases`.

## Files

- `settings/loader.php`: shared JSON loader, dotted-key lookup, timezone setup, database profile inheritance, and PDO factory.
- `settings/settings.example.json`: tracked example with blank database credentials.
- `settings/settings.json`: runtime config for one environment; ignored by Git.
- `settings/.htaccess`: Apache protection. Nginx ignores .htaccess; deny `/settings/` in the Nginx server block or put runtime JSON outside DocumentRoot and set `FRAGCITY_SETTINGS_FILE`.

## Database profiles

`main` serves the website/API DB; `weaponpaints` inherits common connection fields and overrides the schema; `plugin_sync` is used for plugin database synchronization. Empty profile fields return null and never fall back to embedded credentials.

## Migrated blocks

`files.index`, `files.class.config`, `files.class.customer_db`, `files.agent.db`, `files.agent.agent_login`, `files.agent.registered_servers_api`, `files.agent.sync_bulk`, `files.steam.steam_login`, four `files.pages.zarinpal.*` blocks and `files.panels.cs2.*` blocks.

The full example includes CS2 cfg names and the existing plugin table-to-database map. Translation dictionaries and business logic remain in PHP; runtime configuration and data lists live in JSON.

## PHP version and timezone

The code uses PHP 8.x features. Select PHP 8.4 in Laragon. The loader sets `app.timezone` early (UTC by default). Parse errors occur before loader execution, so PHP-version incompatibility must be fixed separately.

## Deployment

Prepare separate runtime settings before deployment. Ensure Nginx/Apache denies direct HTTP access to private settings, back up, deploy, smoke-test, and retain rollback. Never commit Production credentials.

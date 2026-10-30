# Pterodactyl Standby API

This integration is preserved for a later Pterodactyl-backed server inventory. This is not the active public Server Browser path.

## Components

- Website client: `class/PterodactylStandbyApiClient.php`
- Website repository: `class/PterodactylStandbyRepository.php`
- Linux API route: `POST /v1/pterodactyl-standby`
- Pterodactyl Application client: `linuxapi/src/PterodactylStandby/PterodactylApplicationClient.php`
- Discovery class: `linuxapi/src/PterodactylStandby/PterodactylStandbyDiscovery.php`
- Optional snapshot worker: `linuxapi/bin/pterodactyl-standby-refresh.php`

The Application API client is read-only. It restricts discovery to one configured Pterodactyl Node and the configured CS2 Egg ID / Docker image allowlist, then uses each server's default allocation. The A2S implementation is kept separately under `linuxapi/src/PterodactylStandby/A2SInfoQuery.php`.

## Runtime settings

The Linux-only config namespace is `pterodactyl_standby`. The optional website integration fragment is `settings/pterodactyl-standby.integration.example.json`.

Keep the Pterodactyl Application API key only in `/etc/fragcity-api/config.php`. Use a distinct HMAC secret for this optional route; do not reuse the WeaponPaints secret.

## Status

This legacy snapshot route remains separate from the active admin Monitoring API described in `docs/PTERODACTYL-MONITORING.md`. It still uses its optional cache-refresh worker and is not required by the monitoring page.

# Pterodactyl Standby API

This integration is preserved for a later Pterodactyl-backed server inventory. It is **not** the active public Server Browser path.

## Components

- Website client: \`class/PterodactylStandbyApiClient.php\`
- Website repository: \`class/PterodactylStandbyRepository.php\`
- Linux API route: \`POST /v1/pterodactyl-standby\`
- Pterodactyl Application client: \`linuxapi/src/PterodactylStandby/PterodactylApplicationClient.php\`
- Discovery class: \`linuxapi/src/PterodactylStandby/PterodactylStandbyDiscovery.php\`
- Optional snapshot worker: \`linuxapi/bin/pterodactyl-standby-refresh.php\`

The Application API client is read-only. It restricts discovery to one configured Pterodactyl Node and the configured CS2 Egg ID / Docker image allowlist, then uses each server's default allocation. The A2S implementation is kept separately under \`linuxapi/src/ServerBrowser/A2SInfoQuery.php\`.

## Runtime settings

The Linux-only config namespace is \`pterodactyl_standby\`. The optional website integration fragment is \`settings/pterodactyl-standby.integration.example.json\`.

Keep the Pterodactyl Application API key only in \`/etc/fragcity-api/config.php\`. Use a distinct HMAC secret for this optional route; do not reuse the WeaponPaints secret.

## Status

The active Server Browser currently uses the website database and a shared-hosting CLI Cron Job. Do not enable this standby worker or point the public page at this API unless the Pterodactyl-based inventory is deliberately reactivated.

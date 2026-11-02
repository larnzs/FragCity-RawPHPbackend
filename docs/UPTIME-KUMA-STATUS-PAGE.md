# FragCity Public Status Page — Uptime Kuma

FragCity's public \`index.php?page=status\` page reads a published Uptime Kuma Status Page through its public JSON endpoints. The Uptime Kuma management UI, credentials and private monitor settings are never sent to the browser.

## Architecture

- Uptime Kuma runs as a separate Docker container on the Debian node.
- Nginx publishes Kuma on \`https://status.fragcity.ir\`; Docker itself binds only to \`127.0.0.1:3001\`.
- Uptime Kuma owns monitor scheduling, heartbeat history, incident announcements and notification delivery.
- The website's \`UptimeKumaStatusRepository\` reads the published page, latest heartbeat data and public incident history. The website API returns only a small allowlisted response to the browser.
- No SQL connection to Uptime Kuma's SQLite database is used.

The public integration endpoints are:
- \`GET /api/status-page/{slug}\`
- \`GET /api/status-page/heartbeat/{slug}\`
- \`GET /api/status-page/{slug}/incident-history\`

These endpoints require the Status Page to be published. The current Uptime Kuma 2.5.x implementation caches page configuration and incident history for five minutes and heartbeat data for one minute. The FragCity page refreshes its own sanitized endpoint every 60 seconds.

## Deploy Uptime Kuma on the Debian node

Create a DNS A record for \`status.fragcity.ir\` pointing at the public address of the Debian/Nginx host. Then on that host:

~~~bash
sudo install -d -m 0750 /opt/fragcity-uptime-kuma
cd /opt/fragcity-uptime-kuma
~~~

Copy \`deploy/uptime-kuma/compose.yaml\` from this repository to \`/opt/fragcity-uptime-kuma/compose.yaml\`, then start it:

~~~bash
sudo docker compose up -d
sudo docker compose ps
~~~

The Compose template pins Uptime Kuma 2.5.9 and binds its web port to loopback only. Persistent application data lives in \`/opt/fragcity-uptime-kuma/data\`; keep this directory in the regular backup plan.

Install \`deploy/uptime-kuma/nginx-status.fragcity.ir.conf.example\` as an Nginx configuration file. It proxies HTTP and WebSocket traffic to \`127.0.0.1:3001\`. Obtain the certificate before enabling the TLS server block:

~~~bash
sudo certbot certonly --webroot -w /var/www/letsencrypt -d status.fragcity.ir
sudo nginx -t
sudo systemctl reload nginx
~~~

Do not open TCP 3001 on the public firewall. Only Nginx should be reachable from the internet. Create the first Kuma administrator account immediately, enable 2FA, and keep the management UI protected.

## Create the monitors

Create a small set of checks first. Suggested initial monitors:

| Monitor name | Monitor type | Target |
|---|---|---|
| FragCity Website | HTTP(s) | \`https://fragcity.ir\` |
| FragCity API | HTTP(s) | \`https://api.fragcity.ir/health\` |
| FragCity CS2 Server | Steam Game Server | The public host and A2S query/game port of the currently published server |

Use a 60-second interval at first. For the API health endpoint, require HTTP 200; optionally validate the expected JSON keyword \`"ok":true\`. For the game server, use the Steam Game Server monitor type, not a generic HTTP request.

If the website or API has a different canonical public hostname, use the real production URL. Do not point public monitors at a private Docker IP or an internal-only hostname.

## Publish the Status Page

1. In Uptime Kuma, open **Status Pages** and create a page with slug \`fragcity\`.
2. Set its title to \`FragCity — وضعیت سرویس‌ها\`.
3. Create public groups such as \`سایت و API\` and \`سرورهای بازی\`.
4. Add the monitors above to the appropriate groups.
5. Mark both groups as **Public** and publish the Status Page.
6. Keep monitor URLs, private tags and internal notes out of public fields. Only the public monitor names and heartbeat states are copied to FragCity.
7. Configure a notification provider in Kuma (for example email or Discord) so an outage can alert an operator; the public page itself is not an alerting system.

The status page slug must match \`fragcity\`. If you choose another slug, update the website runtime setting.

## Website runtime settings

Merge this object into the private \`settings/settings.json\` file on the website host. Do not replace the complete runtime file:

~~~json
{
  "integrations": {
    "uptime_kuma": {
      "base_url": "https://status.fragcity.ir",
      "status_page_slug": "fragcity"
    }
  }
}
~~~

The base URL must be an HTTPS origin only (no path, query string or credentials). The public Status Page slug must be published. No API key or Kuma admin password is required or accepted.

## What the FragCity page displays

- Overall state: operational, partial degradation, down, maintenance or unknown.
- Count of operational monitors and total public monitors.
- Average of the available per-monitor **24-hour** uptime values.
- Active public incidents and active planned maintenance.
- Per-monitor latest state, last response time, last check time and recent heartbeat strip.
- Up to six recent incident entries.

The public heartbeat endpoint currently returns at most 100 recent heartbeat records per monitor and a 24-hour uptime value. It does **not** provide a 90-day numeric uptime series through this public endpoint. The FragCity page therefore labels its uptime as 24 hours and the small bars as recent checks; it does not invent a 90-day chart. Longer-period SLA reporting can be added later through a dedicated data source or verified Kuma badge.

## Important monitoring limitation

Uptime Kuma is running on the same Debian host as the CS2 server/API in this initial setup. It can check the website, API and game-server endpoints, but it cannot send a reliable real-time alert about a total failure of its own host or power/network path. For independent outage alerts, add an external monitor or a second Kuma instance on a separate host later.

The public website API is read-only, same-origin constrained and sanitizes the monitor data. It never exposes Kuma's management URL, credentials, monitor target URLs, private group data or raw upstream error messages.

## Validation

From the FragCity repository root:

~~~powershell
php -l class/UptimeKumaStatusRepository.php
php -l api/public-status.php
php -l pages/status.php
php linuxapi/tools/architecture-check.php
~~~

After merging runtime settings and publishing the Kuma Status Page, open \`/index.php?page=status\`. If the integration is not configured or Kuma cannot be reached, the page shows a clear unavailable state instead of fabricated green indicators.

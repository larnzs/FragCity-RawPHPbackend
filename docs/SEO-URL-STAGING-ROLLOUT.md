# FragCity SEO URL and Staging Rollout

This runbook covers the canonical URL changes in `.htaccess`, the page metadata, the public sitemap, and the staging verification sequence.

## Important source-of-truth check

Do not deploy this repository to the live domain until the production document root has been confirmed to use this exact codebase. The GitHub repository description currently identifies this repository as an older, unfinished website version. The public site could therefore be running from a different deployment source.

## What changed in this repository

- Clean routes such as `/serverguide/`, `/servers/`, `/vip/`, and `/support/` now map to the existing PHP router.
- Legacy `?page=...` URLs with no functional parameters redirect to their canonical paths.
- All clean page routes use a trailing slash.
- The apex HTTPS hostname is canonical; the staging hostname is preserved.
- Public pages receive page-specific title, description, canonical, and Open Graph metadata.
- Non-production hosts and non-public pages receive `noindex,nofollow,noarchive` plus an `X-Robots-Tag` header.
- The coming-soon server purchase page and launcher are not included in the public sitemap.
- Unknown routes return a real HTTP 404 rather than silently rendering the homepage.
- `robots.txt` references `/sitemap.xml`.

## Full backup before staging

A complete backup must include more than the Git repository:

1. The entire website document root, including hidden files such as `.htaccess`, static assets, and uploaded media.
2. The production database export.
3. The private runtime configuration (for example, `settings/settings.json`) and any separately stored integration configuration.
4. DNS, cPanel domain/subdomain configuration, cron jobs, and any scheduled tasks required by the site.

Preferred approach: create a full account backup from the hosting control panel, then separately verify that the relevant MySQL database is included. Store the archive outside the public document root and download an encrypted copy to secure storage.

For a shell-based backup, use the hosting provider's approved backup process and a protected MySQL client option file. Do not put database passwords directly in shell history or command-line arguments. Do not commit, email, or upload a database dump or private settings file to GitHub.

## Safe staging setup

- Create a separate test subdomain and a separate document root.
- Deploy the candidate code to that document root; do not point the test subdomain at the production document root.
- Use a separate staging database. Prefer synthetic or anonymized records rather than exposing production users' personal information on a test host.
- Protect the staging hostname with hosting-level password protection or an IP allowlist.
- Keep the staging site's `settings/settings.json` separate from production.
- Disable live Zarinpal payments, outgoing customer emails, production Steam account synchronization, and Paymenter/Pterodactyl provisioning callbacks on staging.
- Keep staging on HTTPS. The application detects non-production hostnames and marks their HTML pages `noindex`.

## Staging smoke tests

Run these after the files and staging configuration are in place. Replace `STAGING_HOST` with the actual test hostname.

```bash
curl -sSI "https://STAGING_HOST/serverguide/" | head
curl -sSI "https://STAGING_HOST/index.php?page=serverguide" | head
curl -sSI "https://STAGING_HOST/serverguide" | head
curl -sSI "https://STAGING_HOST/not-a-real-page/" | head
curl -sSI "https://STAGING_HOST/robots.txt" | head
curl -sSI "https://STAGING_HOST/sitemap.xml" | head
curl -sSI "https://STAGING_HOST/serverguide/" | grep -i -E "HTTP/|X-Robots-Tag"
```

Expected results:

| Request | Expected result |
|---|---|
| `/serverguide/` | HTTP 200 |
| `/index.php?page=serverguide` | HTTP 301 to `/serverguide/` |
| `/serverguide` | HTTP 301 to `/serverguide/` |
| Unknown path | HTTP 404 |
| Staging HTML response | `X-Robots-Tag: noindex, nofollow, noarchive` |
| `/robots.txt` and `/sitemap.xml` | HTTP 200 |

Also test the login modal, registration completion, support tabs/ticket links, VIP page selection, the mobile navigation, and the server list. Confirm there is no redirect loop, Apache 500 response, missing asset, or PHP warning.

## Production rollout

Only after staging passes:

1. Take a fresh production backup immediately before deployment.
2. Confirm the production document root is mapped to the intended repository and branch.
3. Deploy the reviewed commit and verify the clean routes, old-URL redirects, canonical tags, sitemap, and 404 behavior on the live hostname.
4. Submit `https://fragcity.ir/sitemap.xml` through the site's search-console property.
5. Keep the pre-deployment backup until the live checks are complete and the rollback window has passed.

## Execution status

This repository change does not itself create a hosting backup, clone the database, configure a test subdomain, or deploy to the live server. Those actions require access to the hosting control panel or server environment. No production or staging data has been copied by this change.

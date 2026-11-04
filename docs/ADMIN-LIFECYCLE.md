# FragCity Admin Lifecycle Dashboard & API

## Scope

The administrator interface lives in the Raw PHP FragCity website at \`index.php?page=lifecycle\`. It is only rendered for an authenticated session whose role is \`admin\`. The dashboard uses the existing lifecycle registry and audit-event table; no Paymenter or Pterodactyl Core files are changed.

The browser posts actions to \`/api/admin-lifecycle.php\`. That endpoint checks the administrator session and \`X-FragCity-CSRF\`, then calls the Paymenter bridge over HTTPS with the existing HMAC signature. The HMAC secret and Pterodactyl Application API credentials are never included in browser markup or JavaScript.

## Paymenter Bridge routes

All routes are POST and require the existing HMAC-SHA256 timestamp, nonce and signature headers:

- \`/api/v1/fragcity/bridge/admin/lifecycle/list\` — paginated mappings, state counts, search by Service ID, Pterodactyl IDs, identifier, UUID or customer name/email.
- \`/api/v1/fragcity/bridge/admin/lifecycle/detail\` — identity, Paymenter service, external IDs and last 50 lifecycle events.
- \`/api/v1/fragcity/bridge/admin/lifecycle/reconcile\` — inspect one service through the existing FragCityCS2 summary method. This is read/observe only. It does not create or delete a Pterodactyl resource.
- \`/api/v1/fragcity/bridge/admin/lifecycle/authorize-recreation\` — records an audited, reasoned approval for one mapping in \`missing_external\`. It does not dispatch a job; the administrator must separately retry that exact Paymenter CreateJob after reviewing backup/data-loss implications.

The dashboard does not expose remote server deletion. A service missing from Paymenter is marked \`paymenter_missing\` for review only; there is no automatic cleanup of a Pterodactyl resource.

## Deployment

1. Deploy/upgrade the Paymenter FragCityAPI extension first. Its version is 1.7.0 and its upgrade hook runs the existing migrations; this dashboard adds no database tables.
2. Deploy the website main branch second.
3. Run \`composer dump-autoload\` and \`php artisan optimize:clear\` on Paymenter, then verify the four route names under \`route:list\`.
4. Log in to FragCity with an administrator account and open \`index.php?page=lifecycle\`.
5. Verify list filtering, detail/history, read-only reconcile and the missing-server authorization flow in a disposable test service before using it for production recovery.

## Trust boundary

The signed Paymenter API trusts the FragCity website backend as the administrator authorization boundary. The website checks the session role and CSRF before forwarding an action. Actor SteamID64/username values sent to Paymenter are audit metadata, not an independent role grant. Keep the Paymenter shared secret in runtime settings only.

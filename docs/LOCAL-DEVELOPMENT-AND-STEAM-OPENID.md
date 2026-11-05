# Local Development, Steam OpenID and Profile API Diagnostics

This guide is for the Laragon/local Windows copy of FragCity. Keep its private `settings/settings.json` separate from Production.

## 1. The local hostname must resolve on Windows

The sample local origin is `http://fragcity.test`. The `.test` suffix is intentionally local and is not a public DNS domain. Steam OpenID redirects the browser back to the exact callback URL; it does not tunnel the callback into Laragon. Windows must therefore resolve the hostname to the local machine.

Open Notepad as Administrator and edit:

```text
C:\Windows\System32\drivers\etc\hosts
```

Add this line if Laragon is listening on the local machine:

```text
127.0.0.1 fragcity.test
```

Then run in an elevated Command Prompt:

```bat
ipconfig /flushdns
ping -4 fragcity.test
```

The hostname should resolve to `127.0.0.1`. Also ensure Laragon has a matching virtual host for `fragcity.test` and that its Apache/Nginx service is running.

For local HTTP, set the private runtime values consistently:

- `app.site_base_url`: `http://fragcity.test`
- `integrations.steam.domain_name`: `fragcity.test`

For local HTTPS, use `https://fragcity.test` and install/trust a local certificate whose hostname is exactly `fragcity.test`. Do not mix an HTTP `site_base_url` with an HTTPS local site URL or vice versa.

Production must use its own private values:

- `app.site_base_url`: `https://fragcity.ir`
- `integrations.steam.domain_name`: `fragcity.ir`

Never deploy `fragcity.test` as the Production callback host.

## 2. Steam OpenID callback construction

The active login and registration pages link to the root-absolute route `/steam/steam_login.php`, so opening the login modal from `/profile/` or another clean route cannot turn it into a nested URL.

The OpenID trust root and callback origin are taken from the environment's configured `app.site_base_url`. If `integrations.steam.domain_name` is non-empty, it must match that URL's hostname. This keeps scheme, hostname and optional port consistent and avoids trusting an arbitrary `Host` header.

A correct PHP callback URL does not make a local `.test` hostname resolvable: the Windows hosts file and Laragon virtual host are still required.

## 3. Diagnose FACEIT and Ranks from the same PHP runtime as the website

Do not send the private `settings/settings.json`, FACEIT API key or Linux API HMAC secret to anyone. Run the diagnostics from the FragCity repository root in Laragon Terminal:

```bat
php tools/profile-integrations-smoke-test.php 76561198214760293
```

Replace the SteamID64 with the account whose Profile page is being checked. The script reports only whether required settings are present, the configured API hostname, the Ranks health/player lookup result, and the FACEIT status/Level/ELO. It never prints secret values.

Interpretation:

- `FACEIT API key configured: FAIL`: add a real server-side key to `integrations.faceit_api.api_key` in the private runtime settings file. Do not put it in JavaScript or any tracked example.
- `FACEIT ... status=api_unavailable`: inspect the PHP error log for the FACEIT HTTP/cURL diagnostic. HTTP 401/403 indicates rejected credentials; 429 indicates rate limiting; transport errors indicate the PHP host cannot reach the Data API.
- `Ranks API authentication and DB ping: FAIL`: check that `integrations.weaponpaints_api.base_url` is `https://api.fragcity.ir`, that the HMAC secret matches the root `shared_secret` in the Linux API's private `/etc/fragcity-api/config.php`, and that the local PHP runtime can reach that HTTPS host.
- `Ranks player lookup: API is reachable, but no row matched`: the signed API and database connection worked; that SteamID64 has no matching row in `GLOBAL_ranks.lvl_base`.
- `FACEIT status=unranked` or `no_cs2_profile`: the FACEIT request succeeded, but the account has no CS2 rank data.

The website reads Ranks through the signed `POST /v1/ranks` integration. FACEIT is queried by server-side PHP using `integrations.faceit_api.api_key`; it does not use the Linux Ranks endpoint.

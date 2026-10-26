# FragCity WeaponPaints API — Linux deployment

This package is a separate PHP-FPM application. **Do not place it inside**
\`/var/www/pterodactyl\`, the Panel's \`public\` directory, a Pterodactyl server
container, or the shared-hosting website's document root.

## Intended layout on Debian

- App files: \`/var/www/fragcity-api/\`
- Nginx document root: \`/var/www/fragcity-api/public/\`
- Runtime secret + database config: \`/etc/fragcity-api/config.php\`
- Nginx vhost: \`/etc/nginx/sites-available/fragcity-api.conf\`
- Public hostname: \`api.fragcity.ir\`

Only \`api-server/public\` is the document root. All configuration and source
outside \`public\` should not be directly served by Nginx.

## Deployment outline

1. Create a DNS A record for \`api.fragcity.ir\` pointing at the Linux host's
   public IP. Do not proceed with TLS issuance until DNS resolves correctly.
2. Copy the contents of \`api-server/\` to \`/var/www/fragcity-api/\`.
3. Create \`/etc/fragcity-api/config.php\` from \`api-server/config.example.php\`.
   Use the *actual* Linux WeaponPaints database name and a dedicated DB account.
   Generate the HMAC secret on Linux using \`openssl rand -hex 32\`. Do not paste
   that secret into Git, this repository, browser JavaScript, or chat messages.
4. Create the \`fragcity_api_nonces\` table in the target plugin database using
   \`api-server/schema.sql\`. Verify that the six \`wp_player_*\` tables exist
   before granting access. The API user needs access only to those tables and
   the nonce table; do not use MariaDB root from the API process.
5. Protect runtime config:
   \`\`\`bash
   sudo install -d -o root -g www-data -m 0750 /etc/fragcity-api
   sudo chown root:www-data /etc/fragcity-api/config.php
   sudo chmod 0640 /etc/fragcity-api/config.php
   \`\`\`
   The config's parent directory must be readable/traversable by the PHP-FPM
   worker group but not writable by it.
6. Install \`api-server/nginx/fragcity-api.conf.example\` as
   \`/etc/nginx/sites-available/fragcity-api.conf\`, check the PHP-FPM socket
   exists (this example uses PHP 8.3), enable the site, run \`nginx -t\`, then
   reload Nginx. Do not edit the existing Pterodactyl vhost for this.
7. After HTTP is served successfully and DNS resolves, obtain a certificate for
   \`api.fragcity.ir\` using the server's existing Certbot/ACME setup. The website
   must call the API using HTTPS only.
8. Test \`GET https://api.fragcity.ir/health\`. Expected JSON reports the service
   name and version. Then test \`ping_db\` with a correctly signed server-to-server
   request from a secure local test script. A health response alone does not
   prove database access or table compatibility.

## Request authentication contract

The API accepts signed JSON only at \`POST /v1/weaponpaints\`. Headers:

- \`X-FragCity-Timestamp\`: Unix timestamp in seconds, within ±300 seconds.
- \`X-FragCity-Nonce\`: fresh 32–128 character lowercase hex nonce.
- \`X-FragCity-Signature\`: lowercase hex HMAC-SHA256 signature.
- \`Content-Type: application/json\`.

Signature input is the UTF-8 string:

\`\`\`text
timestamp + "\\n" + nonce + "\\n" + uppercase_method + "\\n" + path + "\\n" + hex_sha256(raw_body)
\`\`\`

Sign it with the shared secret and HMAC-SHA256. The nonce is stored in the database
to reject replay. Requests are server-to-server only; never expose the secret to
the browser.

Supported actions: \`ping_db\`, \`get_selection\`, \`save_glove\`, \`save_agent\`,
\`save_music\`, \`save_pin\`, \`save_knife\`, \`save_skin\`. The API deliberately
does not accept raw SQL, caller-supplied table names, or browser-originated writes.

## Important current integration status

This package creates the Linux-side API service but **does not yet switch**
\`pages/weaponpaints.php\` away from its current database adapter. Do not consider
the shared-hosting integration finished or point the live page at this API until
the website-side signed client is implemented and the API's requests are tested
against the real Linux schema. The current API's expected table/column shape is
based on the existing FragCity page and still needs verification against the live
database before production use.

The API intentionally has no CORS allowlist because browser JavaScript should not
call it. The shared-hosting PHP backend will call it over HTTPS with the secret
stored only in server-side runtime configuration.

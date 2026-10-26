# FragCity WeaponPaints API — Linux deployment

This is a standalone PHP-FPM application. **Do not place it inside**
\`/var/www/pterodactyl\`, the Panel's public directory, a game-server container,
or the shared-hosting website's document root.

## Paths on Debian

- Application: \`/var/www/fragcity-api/\`
- Nginx document root: \`/var/www/fragcity-api/public/\`
- Runtime secret + DB config: \`/etc/fragcity-api/config.php\`
- Nginx vhost: \`/etc/nginx/sites-available/fragcity-api.conf\`
- Hostname: \`api.fragcity.ir\`

Only the \`public/\` directory is web-accessible. The PHP source outside it and
the runtime config must never be served directly by Nginx.

## Deployment steps

1. Add DNS A record \`api.fragcity.ir\` pointing at the Linux node's public IP.
   Do not request a certificate until DNS resolves to this node.
2. Copy the contents of repository folder \`api-server/\` to
   \`/var/www/fragcity-api/\`. Preserve the \`public/\` directory structure.
3. Confirm the PHP-FPM socket:
   \`\`\`bash
   ls -l /run/php/
   php -m | grep -E 'PDO|pdo_mysql'
   \`\`\`
   The provided Nginx example assumes \`/run/php/php8.3-fpm.sock\`; adjust only
   if the actual installed socket differs.
4. Identify how the Linux host reaches the existing MariaDB instance. If MariaDB
   is containerized, inspect \`docker ps\` and its port bindings. The API must
   connect over a loopback/local bridge path; do not expose port 3306 to the
   public Internet.
5. Generate one random secret on Linux:
   \`\`\`bash
   openssl rand -hex 32
   \`\`\`
   Store it only in \`/etc/fragcity-api/config.php\` and the shared host's
   ignored \`settings/settings.json\`. Never place the real secret in Git,
   browser JavaScript, a public issue, or a chat message.
6. Create a dedicated MariaDB account for this API; do not use root. Verify the
   plugin database name and the six expected \`wp_player_*\` tables first. Create
   \`fragcity_api_nonces\` using \`api-server/schema.sql\`. Grant only SELECT,
   INSERT, UPDATE on the required plugin tables and INSERT, DELETE on the nonce
   table. Restrict the account's host to the actual local connection source.
7. Copy \`api-server/config.example.php\` to \`/etc/fragcity-api/config.php\`.
   Fill in the verified DB address/name/account and generated secret, then set
   permissions:
   \`\`\`bash
   sudo install -d -o root -g www-data -m 0750 /etc/fragcity-api
   sudo chown root:www-data /etc/fragcity-api/config.php
   sudo chmod 0640 /etc/fragcity-api/config.php
   \`\`\`
   Ensure the config parent remains traversable by the PHP-FPM worker group but
   is not writable by the web worker.
8. Install \`api-server/nginx/fragcity-api.conf.example\` as
   \`/etc/nginx/sites-available/fragcity-api.conf\`, enable the site, and verify:
   \`\`\`bash
   sudo nginx -t
   sudo systemctl reload nginx
   \`\`\`
   Keep the existing Pterodactyl vhost intact.
9. After HTTP and DNS work, obtain a certificate for \`api.fragcity.ir\` using
   the existing Certbot/ACME procedure. From then on, call the API only over
   HTTPS. Test \`GET https://api.fragcity.ir/health\`; this checks PHP routing but
   does not prove database connectivity.
10. Test the signed \`ping_db\` action using the site's PHP client or a private
    test script. Then test authenticated reads and writes against a test account
    before enabling the API transport for the live page.

## Signed request contract

The API accepts requests only at \`POST /v1/weaponpaints\`:

- \`Content-Type: application/json\`
- \`X-FragCity-Timestamp\`: Unix time, within ±300 seconds.
- \`X-FragCity-Nonce\`: fresh 64-character lowercase hex value.
- \`X-FragCity-Signature\`: HMAC-SHA256, hex encoded.

The exact HMAC message is:

\`\`\`text
timestamp + "\\n" + nonce + "\\n" + uppercase_method + "\\n" + path + "\\n" + hex_sha256(raw_body)
\`\`\`

The API validates the signature with \`hash_equals\`, rejects stale timestamps,
and records nonce hashes to prevent replay. It accepts fixed actions only;
arbitrary SQL/table names are never accepted.

Supported actions: \`ping_db\`, \`get_selection\`, \`save_glove\`, \`save_agent\`,
\`save_music\`, \`save_pin\`, \`save_knife\`, \`save_skin\`.

## Shared-host website settings

The code adds a signed PHP client and a typed repository. Local Laragon remains
on \`transport: local\`; the shared host switches to \`transport: api\` only after
the Linux API passes the smoke tests. Merge this fragment into the existing
ignored \`settings/settings.json\`; do not replace the whole settings file:

\`\`\`json
{
  "integrations": {
    "weaponpaints_api": {
      "base_url": "https://api.fragcity.ir",
      "shared_secret": "SAME_RANDOM_64_CHARACTER_HEX_SECRET_AS_LINUX"
    }
  },
  "files": {
    "database.weaponpaints_plugin": {
      "database_profile": "weaponpaints",
      "transport": "api"
    }
  }
}
\`\`\`

The setting key \`database.weaponpaints_plugin\` is deliberately a literal key
under \`files\`. The shared-host PHP runtime must have cURL enabled. The secret
is read only by PHP and is never sent to the browser.

## Current limitations

The implementation is based on the SQL and serialized values currently used by
\`pages/weaponpaints.php\`. I cannot verify compatibility with the live Linux
schema from the repository alone. Before production, inspect the actual table
columns, unique indexes used by \`ON DUPLICATE KEY UPDATE\`, the source address
MariaDB sees for the API connection, and the installed PHP-FPM socket. Do not
switch production to API mode until those checks and the smoke tests pass.

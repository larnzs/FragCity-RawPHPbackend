# FragCity Linux API — deployment guide

The source folder is `linuxapi/`. It is a standalone PHP-FPM application. Do
**not** place it inside `/var/www/pterodactyl`, the Panel's public directory,
a game-server container, or the shared-hosting website document root.

## Target layout on Debian

- Application files: `/var/www/fragcity-api/`
- Nginx document root: `/var/www/fragcity-api/public/`
- Runtime secret + DB configuration: `/etc/fragcity-api/config.php`
- Nginx vhost: `/etc/nginx/sites-available/fragcity-api.conf`
- Public hostname: `api.fragcity.ir`

Only `public/index.php` is routed to PHP-FPM. Runtime credentials live outside
the webroot and are not in Git.

## Install

1. Create DNS A record `api.fragcity.ir` to the Linux node public IP.
2. From the repository root on the Linux node, copy the API package and keep
   the app files root-owned/read-only to the web worker:
   ```bash
   sudo install -d -o root -g root -m 0755 /var/www/fragcity-api
   sudo cp -a linuxapi/. /var/www/fragcity-api/
   sudo chown -R root:root /var/www/fragcity-api
   sudo find /var/www/fragcity-api -type d -exec chmod 0755 {} \;
   sudo find /var/www/fragcity-api -type f -exec chmod 0644 {} \;
   ```
   If transferring with WinSCP instead, upload the *contents* of `linuxapi/`
   into `/var/www/fragcity-api/`, preserving `public/index.php`, then apply
   the ownership and permissions above.
3. Confirm PHP-FPM socket and required modules:
   ```bash
   ls -l /run/php/
   php -m | grep -E 'PDO|pdo_mysql|curl|mbstring'
   ```
   The sample vhost assumes `/run/php/php8.3-fpm.sock`; update it only if your
   actual socket name differs. Check modules for the PHP-FPM version too.

4. Confirm how host PHP-FPM reaches the existing MariaDB. Because it may be
   containerized, `127.0.0.1` is only correct if MariaDB is actually listening
   on the host loopback. Inspect Docker port bindings and the existing local/
   bridge listener first. Do not expose TCP 3306 to the public Internet.
5. Create a random secret on Linux:
   ```bash
   openssl rand -hex 32
   ```
   Save it privately. Never put the real secret in the repository or browser.
6. Verify the actual plugin database name and the columns/unique indexes in
   these tables: `wp_player_skins`, `wp_player_gloves`, `wp_player_knife`,
   `wp_player_agents`, `wp_player_music`, `wp_player_pins`. Create
   `fragcity_api_nonces` by running `linuxapi/schema.sql` in that database.
7. Create a separate MariaDB account `fragcity_weaponpaints_api` and grant
   only the privileges listed in `linuxapi/schema.sql`. The account should be
   restricted to the actual local connection source; never use root.
8. Copy the example config outside the webroot and edit it:
   ```bash
   sudo install -d -o root -g www-data -m 0750 /etc/fragcity-api
   sudo install -o root -g www-data -m 0640 \
     /var/www/fragcity-api/config.example.php /etc/fragcity-api/config.php
   sudo nano /etc/fragcity-api/config.php
   ```
   Set the actual DB connection and generated secret. The PHP-FPM worker must
   be able to read this file but must not be able to write it.
9. Install and enable only the new vhost:
   ```bash
   sudo cp /var/www/fragcity-api/nginx/fragcity-api.conf.example \
     /etc/nginx/sites-available/fragcity-api.conf
   sudo ln -s /etc/nginx/sites-available/fragcity-api.conf \
     /etc/nginx/sites-enabled/fragcity-api.conf
   sudo nginx -t
   sudo systemctl reload nginx
   ```
   If the symlink already exists, do not create a duplicate. Leave the existing
   Pterodactyl Panel server block unchanged.
10. Bootstrap HTTP, verify `http://api.fragcity.ir/health`, then obtain the
    certificate through the existing Certbot/ACME setup (for example
    `sudo certbot certonly --nginx -d api.fragcity.ir`). After the certificate
    exists, replace the bootstrap vhost with
    `/var/www/fragcity-api/nginx/fragcity-api-https.conf.example`, then run
    `sudo nginx -t` and reload Nginx. Do not switch the shared-host client to
    API mode before HTTPS works.

11. Smoke test `GET https://api.fragcity.ir/health`, then run the signed CLI
    check from the repository root on Linux:
    `php linuxapi/tools/smoke-test.php https://api.fragcity.ir`
    Add a test account's SteamID as a second argument for a read-only selection
    test. This checks signature/header forwarding, nonce persistence, DB access,
    and (when supplied) the table SELECT grants.

12. Test read/write actions with a test account and verify the data in MariaDB
    before switching the live shared-host runtime config to API transport.

## Request security

Only `POST /v1/weaponpaints` accepts data. Every request must include JSON and
these headers:

- `X-FragCity-Timestamp`: Unix timestamp within ±300 seconds.
- `X-FragCity-Nonce`: fresh 64-character random hex nonce.
- `X-FragCity-Signature`: HMAC-SHA256 hex digest.

The signed message is:
```text
timestamp + "\n" + nonce + "\n" + uppercase_method + "\n" + path + "\n" + hex_sha256(raw_body)
```

The API uses `hash_equals`, validates timestamps and saves nonce hashes in a
unique-key table to reject replays. It has fixed actions only; no arbitrary SQL
or caller-supplied table names are accepted. Request bodies are size-limited.

Actions: `ping_db`, `get_selection`, `save_glove`, `save_agent`,
`save_music`, `save_pin`, `save_knife`, `save_skin`.

## Shared-host settings

The website PHP client uses the shared secret server-side only and requires PHP
cURL. In the shared host's ignored `settings/settings.json`, merge these keys
without replacing the rest of your settings:

```json
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
```

The `database.weaponpaints_plugin` key is a literal object key inside
`files`. Local Laragon remains `transport: local` in the tracked example.
The actual shared-host JSON should never be committed.

## Validation status and production caveats

The API code is shaped around SQL currently used by the existing FragCity page.
Actual MariaDB schema details, duplicate/unique indexes, Docker listener path,
PHP-FPM socket, DNS, TLS, and live requests must still be verified on the Debian
node. Those facts cannot be confirmed from the Git repository alone. Do not
declare production live until both the PHP lint workflow and the server smoke
tests succeed.

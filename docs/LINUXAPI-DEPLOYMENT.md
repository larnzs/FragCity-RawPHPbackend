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
2. Copy the contents of repo folder `linuxapi/` to
   `/var/www/fragcity-api/`, preserving the `public/` directory.
3. Confirm PHP-FPM socket and required modules:
   ```bash
   ls -l /run/php/
   php -m | grep -E 'PDO|pdo_mysql|curl|mbstring'
   ```
   The sample vhost assumes `/run/php/php8.3-fpm.sock`; update it only if your
   actual socket name differs.
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
8. Copy `linuxapi/config.example.php` to
   `/etc/fragcity-api/config.php`. Enter the verified DB host/name/account and
   the same generated shared secret. Protect it:
   ```bash
   sudo install -d -o root -g www-data -m 0750 /etc/fragcity-api
   sudo chown root:www-data /etc/fragcity-api/config.php
   sudo chmod 0640 /etc/fragcity-api/config.php
   ```
   Ensure the PHP-FPM worker can read the file but cannot write it.
9. Copy `linuxapi/nginx/fragcity-api.conf.example` to
   `/etc/nginx/sites-available/fragcity-api.conf`. Enable only this new vhost,
   leave Pterodactyl's existing site alone, and run:
   ```bash
   sudo nginx -t
   sudo systemctl reload nginx
   ```
10. Bootstrap HTTP then obtain the certificate through the existing Certbot/
    ACME setup. After the certificate exists, replace the bootstrap vhost with
    `linuxapi/nginx/fragcity-api-https.conf.example` (adjust the PHP-FPM socket
    if required), then run `sudo nginx -t` and reload Nginx. Do not switch the
    shared-host client to API mode before HTTPS works.
11. Smoke test `GET https://api.fragcity.ir/health`. Then call the signed
    `ping_db` action using the website's PHP client. Health alone does not test
    the DB.
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
timestamp + "\\n" + nonce + "\\n" + uppercase_method + "\\n" + path + "\\n" + hex_sha256(raw_body)
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

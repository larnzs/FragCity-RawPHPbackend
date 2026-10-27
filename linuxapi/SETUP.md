# FragCity Linux API — setup and recovery runbook

This is the repeatable setup guide for the files in this directory. Follow it on the Debian API host when deploying FragCity on a new node or rebuilding the service.

## FragCity database target

For the current FragCity installation, the website API must use **GLOBAL_weaponpaints**. The CS2 plugin containers share that database so player selections follow the player across servers.

- API database: GLOBAL_weaponpaints
- Do not use GLOBAL_simpleadmin for WeaponPaints.
- Do not use s2_weaponpaints for the website API; it is container-scoped.
- Host database address verified on the current Debian node: 127.0.0.1:3306.
- Application files: /var/www/fragcity-api/
- Public document root: /var/www/fragcity-api/public/
- Private runtime config: /etc/fragcity-api/config.php
- PHP-FPM must read the private config but must not be able to write it.
- Never expose MariaDB TCP/3306 to the public Internet, use MariaDB root from the API, or commit runtime secrets.

The API is a separate service. Do not place it in the Pterodactyl Panel directory, inside a game-server container, or in the shared-hosting website document root.


## Current installation record — verified 2026-10-27

This section records the state of the existing `server1` install so a future operator can continue from the correct step rather than rerunning first-install commands.

### Completed and verified

- API package was copied to `/var/www/fragcity-api/`. The package contains `public/index.php`, the Nginx examples, `schema.sql`, `config.example.php`, and `tools/smoke-test.php`.
- PHP CLI is 8.3.35. The `curl`, `mbstring`, and `pdo_mysql` extensions are present.
- Syntax checks passed for the API entry point, config example, smoke-test script, and private runtime config.
- PHP-FPM socket exists at `/run/php/php8.3-fpm.sock`.
- `/etc/fragcity-api/config.php` exists; `www-data` can read it and cannot write it. Do not replace it with the example or generate a new secret during continuation.
- MariaDB listens on `127.0.0.1:3306` and also on `172.18.0.1:3306`. The API is configured to use the verified host loopback `127.0.0.1:3306`.
- The correct application database is `GLOBAL_weaponpaints`, shared by the CS2 plugin containers. `GLOBAL_simpleadmin` is not the API target, and `s2_weaponpaints` is not the website API target.
- The existing six plugin tables and their primary keys were inspected. No plugin table was altered.
- `GLOBAL_weaponpaints.fragcity_api_nonces` was created and its definition verified.
- Account `fragcity_weaponpaints_api`@ `127.0.0.1` exists. The granted permissions were inspected. It has table-specific permissions only; it is not a MariaDB root account.
- A permission check executed as `www-data` passed for database `GLOBAL_weaponpaints`, the required plugin-table SELECT checks, and Nonce INSERT/DELETE within a transaction that was rolled back.
- `nginx -t` passes for the current Nginx configuration.

### Not completed yet

- At the last check, `getent ahostsv4 api.fragcity.ir` returned no address.
- `/etc/letsencrypt/live/api.fragcity.ir/fullchain.pem` did not exist.
- `nginx -T` showed no vhost for `api.fragcity.ir`.
- The HTTPS health check, signed `ping_db`, signed `get_selection`, and any test-player write tests have **not** been completed.
- The shared-host website must remain on `transport: local` until the signed API smoke tests pass.

### Where to resume

Do not repeat the database account creation/config-generation block under “Fresh-install account and private config”; the account and config already exist on this node. Use the “Repair the current account if it already exists” instructions only if a permission issue reappears. The next deployment step is to configure/verify the DNS A record, install the separate API Nginx vhost, bootstrap HTTP, issue TLS, and then run the signed smoke test. Keep the existing Pterodactyl Panel vhost untouched.


## 1. Preflight checks

Run these read-only checks before creating users or changing config:

~~~bash
docker ps --format 'table {{.Names}}\t{{.Ports}}'
sudo ss -lntp | grep ':3306' || true

sudo mariadb -NBe "
SELECT SCHEMA_NAME
FROM information_schema.SCHEMATA
WHERE SCHEMA_NAME IN ('GLOBAL_weaponpaints','GLOBAL_simpleadmin','s2_weaponpaints');
"

sudo mariadb -NBe "
SELECT table_name
FROM information_schema.tables
WHERE table_schema = 'GLOBAL_weaponpaints'
  AND table_name IN (
    'wp_player_skins','wp_player_gloves','wp_player_knife',
    'wp_player_agents','wp_player_music','wp_player_pins'
  )
ORDER BY table_name;
"
~~~

Confirm the six plugin tables exist in GLOBAL_weaponpaints and inspect their columns/primary keys before relying on the API SQL. The expected primary keys are:

- wp_player_skins: steamid, weapon_team, weapon_defindex
- wp_player_gloves: steamid, weapon_team
- wp_player_knife: steamid, weapon_team
- wp_player_agents: steamid
- wp_player_music: steamid, weapon_team
- wp_player_pins: steamid, weapon_team

The API uses the same INSERT ... ON DUPLICATE KEY UPDATE pattern as the existing WeaponPaints repository. Do not alter the plugin's table structure merely to make the API fit.

## 2. Copy the application files

From the repository root on the Debian host, copy the contents of linuxapi into /var/www/fragcity-api. Inspect an existing destination before updating it; do not overwrite unknown files blindly.

~~~bash
sudo install -d -o root -g root -m 0755 /var/www/fragcity-api
sudo cp -a linuxapi/. /var/www/fragcity-api/
sudo chown -R root:root /var/www/fragcity-api
sudo find /var/www/fragcity-api -type d -exec chmod 0755 {} \;
sudo find /var/www/fragcity-api -type f -exec chmod 0644 {} \;

ls -l /run/php/
php -m | grep -Ei '^(curl|mbstring|pdo_mysql)$'
php -l /var/www/fragcity-api/public/index.php
php -l /var/www/fragcity-api/config.example.php
php -l /var/www/fragcity-api/tools/smoke-test.php
~~~

The sample Nginx vhost assumes the PHP-FPM socket /run/php/php8.3-fpm.sock. Verify the socket exists before enabling that vhost.

## 3. Create the anti-replay table

This is the only new table required by the API; it stores hashes of request nonces, not player data. Execute it against the verified global WeaponPaints database:

~~~bash
sudo mariadb GLOBAL_weaponpaints < /var/www/fragcity-api/schema.sql
sudo mariadb -t -e "SHOW CREATE TABLE GLOBAL_weaponpaints.fragcity_api_nonces;"
~~~

The schema uses CREATE TABLE IF NOT EXISTS. It does not recreate or replace the six existing plugin tables.

## 4. Fresh-install account and private config

Use this section only on a fresh install where both the private config and the MariaDB account do not already exist. If either exists, inspect it and use the recovery section below instead of overwriting it.

**Do not paste this whole installation block directly at an interactive SSH prompt.** A guard intentionally uses `exit 1` when the config/user already exists; pasted directly into the interactive shell, that can end the shell session. Save the script section to a root-owned file (for example `/root/setup-fragcity-api.sh`) and run it as a child process with `bash /root/setup-fragcity-api.sh`. This way its `exit` only stops the script, not your SSH session. Review the generated file before running it. The script generates a unique database password and HMAC secret, keeps temporary SQL/config files restricted, and does not print either secret.

~~~bash
set -euo pipefail

CONFIG="/etc/fragcity-api/config.php"

if [ -e "$CONFIG" ]; then
    echo "STOP: API config already exists; inspect it before proceeding."
    exit 1
fi

EXISTING_USER="$(mariadb --batch --skip-column-names -e "SELECT COUNT(*) FROM mysql.user WHERE User='fragcity_weaponpaints_api';")"
if [ "$EXISTING_USER" != "0" ]; then
    echo "STOP: an account with this username already exists; use recovery steps."
    exit 1
fi

TABLE_COUNT="$(mariadb --batch --skip-column-names -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='GLOBAL_weaponpaints' AND table_name IN ('wp_player_skins','wp_player_gloves','wp_player_knife','wp_player_agents','wp_player_music','wp_player_pins','fragcity_api_nonces');")"
if [ "$TABLE_COUNT" != "7" ]; then
    echo "STOP: expected six plugin tables and fragcity_api_nonces in GLOBAL_weaponpaints."
    exit 1
fi

install -d -o root -g www-data -m 0750 /etc/fragcity-api

DB_PASS="$(openssl rand -hex 32)"
API_SECRET="$(openssl rand -hex 32)"
SQL_FILE="$(mktemp /run/fragcity-api-grants.XXXXXX)"
CONFIG_TMP="$(mktemp /etc/fragcity-api/config.php.tmp.XXXXXX)"

cleanup() {
    if [ -n "$SQL_FILE" ]; then rm -f -- "$SQL_FILE"; fi
    if [ -n "$CONFIG_TMP" ]; then rm -f -- "$CONFIG_TMP"; fi
}
trap cleanup EXIT

chmod 0600 "$SQL_FILE"

cat > "$SQL_FILE" <<SQL
CREATE USER 'fragcity_weaponpaints_api'@'127.0.0.1' IDENTIFIED BY '$DB_PASS';

GRANT SELECT, INSERT, UPDATE ON GLOBAL_weaponpaints.wp_player_skins TO 'fragcity_weaponpaints_api'@'127.0.0.1';
GRANT INSERT, UPDATE ON GLOBAL_weaponpaints.wp_player_gloves TO 'fragcity_weaponpaints_api'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON GLOBAL_weaponpaints.wp_player_knife TO 'fragcity_weaponpaints_api'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON GLOBAL_weaponpaints.wp_player_agents TO 'fragcity_weaponpaints_api'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON GLOBAL_weaponpaints.wp_player_music TO 'fragcity_weaponpaints_api'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON GLOBAL_weaponpaints.wp_player_pins TO 'fragcity_weaponpaints_api'@'127.0.0.1';

GRANT SELECT, INSERT, DELETE ON GLOBAL_weaponpaints.fragcity_api_nonces TO 'fragcity_weaponpaints_api'@'127.0.0.1';
SQL

mariadb < "$SQL_FILE"
rm -f -- "$SQL_FILE"
SQL_FILE=""

cat > "$CONFIG_TMP" <<PHP
<?php
declare(strict_types=1);

return [
    'shared_secret' => '$API_SECRET',
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'GLOBAL_weaponpaints',
        'username' => 'fragcity_weaponpaints_api',
        'password' => '$DB_PASS',
        'charset' => 'utf8mb4',
    ],
];
PHP

chown root:www-data "$CONFIG_TMP"
chmod 0640 "$CONFIG_TMP"
mv "$CONFIG_TMP" "$CONFIG"
CONFIG_TMP=""

unset DB_PASS API_SECRET
echo "API account and private config created; secrets were not printed."
~~~

The nonce table grant includes SELECT intentionally. The API cleanup query checks created_at, and the validation below deletes by nonce_hash; MariaDB requires SELECT permission for columns used by these WHERE conditions in addition to DELETE.

## 5. Repair the current account if it already exists

The current installation created the account and config before this guide was added. Its first permission test failed when deleting a test nonce because SELECT was not granted on fragcity_api_nonces. Do not recreate the user or overwrite the existing config. As root, apply the missing table-level SELECT privilege while retaining the required insert/delete privileges:

~~~sql
GRANT SELECT, INSERT, DELETE
ON GLOBAL_weaponpaints.fragcity_api_nonces
TO 'fragcity_weaponpaints_api'@'127.0.0.1';

SHOW GRANTS FOR 'fragcity_weaponpaints_api'@'127.0.0.1';
~~~

Run that SQL in MariaDB (for example, via sudo mariadb). If MariaDB reports that this exact account/host does not exist, inspect mysql.user and SHOW GRANTS first; do not create an additional host entry by guesswork.

## 6. Test permissions as the PHP-FPM user

Use this here-document form instead of a long php -r command; it is less error-prone when pasting into a terminal. It reads only plugin data. The nonce test inserts and deletes a temporary nonce inside a transaction and then rolls the transaction back.

~~~bash
sudo -u www-data php <<'PHP'
<?php
declare(strict_types=1);

$c = require '/etc/fragcity-api/config.php';
$d = $c['database'];

$pdo = new PDO(
    'mysql:host='.$d['host'].';port='.$d['port'].';dbname='.$d['name'].';charset='.$d['charset'],
    $d['username'],
    $d['password'],
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

foreach ([
    'wp_player_skins',
    'wp_player_knife',
    'wp_player_agents',
    'wp_player_music',
    'wp_player_pins',
] as $table) {
    $pdo->query('SELECT 1 FROM '.$table.' LIMIT 0');
}

$pdo->beginTransaction();
try {
    $nonce = hash('sha256', random_bytes(32));
    $insert = $pdo->prepare('INSERT INTO fragcity_api_nonces (nonce_hash, created_at) VALUES (:nonce, UTC_TIMESTAMP())');
    $insert->execute([':nonce' => $nonce]);

    $delete = $pdo->prepare('DELETE FROM fragcity_api_nonces WHERE nonce_hash = :nonce');
    $delete->execute([':nonce' => $nonce]);

    $pdo->rollBack();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

echo 'Database: '.$pdo->query('SELECT DATABASE()')->fetchColumn().PHP_EOL;
echo "Required SELECT permissions: OK".PHP_EOL;
echo "Nonce INSERT/DELETE permissions: OK".PHP_EOL;
PHP
~~~

Expected result: database is GLOBAL_weaponpaints and both permission checks say OK. If a test fails, stop and inspect the exact MariaDB error before changing grants. Do not send passwords or the shared secret into chat.

## 7. Configure Nginx and HTTPS

1. Create an A record for api.fragcity.ir pointing to this node's public IP. Confirm the PHP-FPM socket and verify ports 80/443 are already allowed by the intended firewall policy. Do not add a public rule for port 3306.
2. Copy the bootstrap HTTP vhost and enable only this new site. Keep the existing Pterodactyl Panel vhost untouched:

~~~bash
sudo cp /var/www/fragcity-api/nginx/fragcity-api.conf.example /etc/nginx/sites-available/fragcity-api.conf
if [ ! -e /etc/nginx/sites-enabled/fragcity-api.conf ]; then
    sudo ln -s /etc/nginx/sites-available/fragcity-api.conf /etc/nginx/sites-enabled/fragcity-api.conf
fi
sudo nginx -t
sudo systemctl reload nginx
curl -i http://api.fragcity.ir/health
~~~

3. Only after DNS and HTTP are correct, obtain the certificate using the node's established Certbot/ACME process. Example:

~~~bash
sudo certbot certonly --nginx -d api.fragcity.ir
~~~

4. After the certificate exists, switch the vhost to the TLS example and test before reloading:

~~~bash
sudo cp /var/www/fragcity-api/nginx/fragcity-api-https.conf.example /etc/nginx/sites-available/fragcity-api.conf
sudo nginx -t
sudo systemctl reload nginx
curl -fsS https://api.fragcity.ir/health
~~~

The health response is public and does not prove database connectivity. Do not switch the website to API transport until the signed smoke test passes.

## 8. Signed API smoke tests

The script reads /etc/fragcity-api/config.php and tests HMAC verification, Nginx header forwarding, nonce insertion, and the database ping.

~~~bash
php /var/www/fragcity-api/tools/smoke-test.php https://api.fragcity.ir
~~~

To test read-only selection queries, pass a test SteamID as the second argument. Keep the identifier in your own terminal; do not post it in public logs or chat.

~~~bash
php /var/www/fragcity-api/tools/smoke-test.php https://api.fragcity.ir TEST_STEAMID
~~~

Write operations should only be tested with a test player/account and should be verified against GLOBAL_weaponpaints. The smoke-test script itself does not perform player-data writes.

## 9. Switch the shared-host website only after verification

In the shared-host runtime settings file (the ignored settings/settings.json), merge these keys without replacing unrelated settings:

~~~json
{
  "integrations": {
    "weaponpaints_api": {
      "base_url": "https://api.fragcity.ir",
      "shared_secret": "SAME_RANDOM_SECRET_AS_ON_LINUX"
    }
  },
  "files": {
    "database.weaponpaints_plugin": {
      "database_profile": "weaponpaints",
      "transport": "api"
    }
  }
}
~~~

The integrations key holds the same generated secret as the Linux config. Keep it server-side only. Never commit the runtime settings file. Local Laragon must keep transport set to local.

## Troubleshooting

- HTTP 503 service_not_configured: check that /etc/fragcity-api/config.php exists, is PHP syntax-valid, contains a 64-character lowercase hex shared_secret, and is readable by www-data but not writable by it.
- SQLSTATE 42000 / error 1143 on `fragcity_api_nonces`: the first version of the grant omitted `SELECT`. MariaDB also requires SELECT permission for a column referenced by a DELETE statement's WHERE clause. Fix the existing account with:
  ```sql
  GRANT SELECT, INSERT, DELETE
  ON GLOBAL_weaponpaints.fragcity_api_nonces
  TO 'fragcity_weaponpaints_api'@'127.0.0.1';
  ```
  Then inspect `SHOW GRANTS FOR 'fragcity_weaponpaints_api'@'127.0.0.1';` and rerun the permission check as `www-data`. Do not recreate the account or overwrite its config just to fix this grant.
- Long pasted blocks appear to have lost/interleaved text in the terminal during the first attempt. If output contains fragments from two lines mixed together, do not assume the entire block ran cleanly: rerun one command block at a time, preferably by saving scripts and executing them with `bash /path/to/script.sh`. For PHP, prefer a saved `.php` file or the documented here-document and check that the full expected success output appears. Never paste actual passwords or the shared secret into chat.
- Database connection failure: confirm the DB name is GLOBAL_weaponpaints, verify the actual MariaDB listener with ss -lntp, and test as www-data. Do not guess Docker addressing.
- Signed request rejected: ensure time is synchronized, all three X-FragCity headers are forwarded by Nginx, and the client and server use the exact same secret.
- TLS/redirect failure: verify DNS, certificate paths, and nginx -t before reload.

Do not declare the service production-ready until the database permission test, HTTPS health check, signed ping_db smoke test, and read-only get_selection test all pass.

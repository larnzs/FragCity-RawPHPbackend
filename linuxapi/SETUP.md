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

### DNS and network behavior recorded on 2026-10-27

- The authoritative nameservers `ns835.mihanwebhost.com` and `ns836.mihanwebhost.com` both returned `api.fragcity.ir A 85.185.40.85`.
- Public recursive resolvers `1.1.1.1` and `8.8.8.8` also returned `85.185.40.85`, TTL 3600 seconds.
- The Debian host's `/etc/resolv.conf` is managed by NetworkManager and lists `5.200.200.200` first and `8.8.8.8` second.
- The operator confirmed that `5.200.200.200` is the intentionally configured Telecom/ISP resolver used with the existing DNS-tunnel setup. Do **not** replace it with public DNS or edit `/etc/resolv.conf` just because this resolver returns a different answer for one API hostname. Preserve the existing ISP/DNS-tunnel behavior unless the operator explicitly chooses to change it.
- On this date, `dig @5.200.200.200 api.fragcity.ir A` and the default `dig api.fragcity.ir A` returned `NXDOMAIN`, while direct queries to both authoritative nameservers and public resolvers returned the A record. Therefore `getent ahostsv4 api.fragcity.ir` and an ordinary `curl http://api.fragcity.ir/health` failed on the Debian host even though DNS authority is correct. Record this as a resolver-path discrepancy; do not assume the authoritative DNS record is missing.
- For a **diagnostic direct-origin test only**, bypass the local resolver for this one curl invocation while retaining the HTTP Host header:
  ```bash
  curl -i --connect-timeout 5 --max-time 10 \
    --resolve api.fragcity.ir:80:85.185.40.85 \
    http://api.fragcity.ir/health
  ```
  This deliberately bypasses the configured resolver/tunnel for this request. It can test direct reachability of the public origin; it does **not** test the configured DNS-tunnel path. For a TLS test after a certificate exists, the equivalent is `--resolve api.fragcity.ir:443:85.185.40.85`, which preserves hostname/SNI verification.
- If the default resolver is required to route the API through the ISP DNS tunnel, diagnose that route separately rather than globally changing the DNS settings. Keep the selected resolver order as-is until the operator explicitly requests a change.

### Current status and next step

- The separate bootstrap HTTP vhost now exists at `/etc/nginx/sites-available/fragcity-api.conf` and is enabled by `/etc/nginx/sites-enabled/fragcity-api.conf`.
- `nginx -t` passed and reloading Nginx succeeded.
- Local virtual-host test `curl -H 'Host: api.fragcity.ir' http://127.0.0.1/health` returned HTTP 200 with the expected `fragcity-linuxapi` health JSON.
- A normal curl by hostname did not resolve through the configured default resolver, as recorded above. Public/authoritative DNS is correct; the public HTTP route still needs to be checked separately.
- A direct-origin HTTP test then succeeded: `curl --resolve api.fragcity.ir:80:85.185.40.85 http://api.fragcity.ir/health` returned HTTP 200 with the expected API health JSON. This confirms the selected public-IP route answered from the Debian host while bypassing only local DNS lookup for that request. It does **not** replace a true off-host reachability test, but it is the recorded successful direct-origin check.
- Certbot 4.0.0 is installed; the `nginx`, `standalone`, and `webroot` plugins are available.
- On 2026-10-27, `sudo certbot certonly --nginx -d api.fragcity.ir` completed successfully. The certificate is saved at `/etc/letsencrypt/live/api.fragcity.ir/fullchain.pem` and the private key at `/etc/letsencrypt/live/api.fragcity.ir/privkey.pem`. Certbot reported expiry on 2027-01-25 and said automatic renewal was scheduled.
- Before issuing the certificate, the original HTTP vhost was backed up to `/etc/nginx/sites-available/fragcity-api.conf.pre-letsencrypt`.
- Certbot did not ask for an email during this issuance. The existing node already had certificates and likely reused its existing Certbot account/contact configuration. Fresh installations may still ask for email and terms acceptance.
- On 2026-10-27, the TLS vhost was installed from `/var/www/fragcity-api/nginx/fragcity-api-https.conf.example` into `/etc/nginx/sites-available/fragcity-api.conf`; the enabled symlink remained `/etc/nginx/sites-enabled/fragcity-api.conf`.
- `nginx -t` passed and `systemctl reload nginx` succeeded with the TLS vhost active.
- HTTPS origin test succeeded: `curl --resolve api.fragcity.ir:443:85.185.40.85 https://api.fragcity.ir/health` returned `HTTP/2 200` and the expected API JSON. This test was run from the Debian host while pinning the hostname to the public IP; it verifies the TLS listener/certificate and health route for that path, but does not by itself prove reachability from every off-host network.
- Nginx emitted two warnings during config validation: (1) `listen ... http2` is deprecated in the API vhost on lines 22-23; (2) protocol options were redefined for `[::]:443`, with a reference to `/etc/nginx/sites-enabled/pterodactyl.conf:52`. They were warnings, not fatal errors; the config test and reload succeeded. Do not edit the Panel vhost just to silence the warning without reviewing the shared 443 listener definitions. A future cleanup can update the API vhost to the Nginx-version-supported HTTP/2 syntax and then re-test.
- The signed `ping_db` smoke test passed on `server1` on 2026-10-27 after applying the temporary `/etc/hosts` mapping for the test. Response: HTTP 200, `success: true`, `data.database: ok`, followed by `PASS: signed ping_db`. This verifies the signed request path (including HMAC/header forwarding), nonce insertion, API-to-MariaDB connection, and `GLOBAL_weaponpaints` selection for this request.
- Signed read-only `get_selection` and controlled test-player write/read-back tests remain unverified. Continue with the read-only selection test in Section 8. Because the successful commands were pasted directly into an interactive shell, its `EXIT` trap may remain installed and the marked `/etc/hosts` line may still exist. Check the marker and use the subshell-wrapped Section 8 command so cleanup runs when that test finishes; do not change NetworkManager or ISP DNS settings.
- The shared-host website must remain on `transport: local` until the signed API smoke tests pass.

Do not repeat the database account creation/config-generation block under “Fresh-install account and private config”; the account and config already exist on this node. The direct-origin HTTP test, Let's Encrypt issuance, TLS vhost install, Nginx reload, HTTPS health test, and signed `ping_db` smoke test have completed successfully. The next step is the read-only player-selection test. Do not switch the shared-host website to API transport until those tests and controlled test-player writes are verified. Do not touch the existing Pterodactyl Panel vhost or change DNS resolver settings globally without explicit operator approval.



### Successful HTTP origin check — 2026-10-27

The following command succeeded on `server1` and returned HTTP 200 with the expected FragCity API health response:

~~~bash
curl -i --connect-timeout 5 --max-time 10 \
  --resolve api.fragcity.ir:80:85.185.40.85 \
  http://api.fragcity.ir/health
~~~

Expected response body:

~~~json
{"ok":true,"service":"fragcity-linuxapi","version":1}
~~~

This diagnostic resolves the name explicitly to the public origin only for this request; it does not change DNS configuration or verify that every off-host network can reach port 80.

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

1. Create an A record for api.fragcity.ir pointing to this node's public IP. Confirm it from the domain's authoritative nameservers, not only from the Debian host's default resolver. Some nodes intentionally use an ISP DNS resolver for a DNS-tunnel/network path; do not replace or reorder that resolver just because a separate query returns NXDOMAIN. See “DNS and network behavior recorded on 2026-10-27” above. Confirm the PHP-FPM socket and verify ports 80/443 are already allowed by the intended firewall policy. Do not add a public rule for port 3306.
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

3. Only after the authoritative DNS check and HTTP-origin test succeed, obtain the certificate using the node's established Certbot/ACME process:

~~~bash
sudo certbot certonly --nginx -d api.fragcity.ir
~~~

On the current node, Certbot 4.0.0 completed this command successfully on 2026-10-27. It saved `fullchain.pem` and `privkey.pem` under `/etc/letsencrypt/live/api.fragcity.ir/`, reported expiry on 2027-01-25, and reported that a scheduled automatic renewal task was configured. The command did not ask for an email on this run; the node already had Certbot setup for other certificates, and likely reused its existing ACME account/contact configuration. Do not assume fresh nodes will skip those prompts.

4. After the certificate exists, switch the vhost to the TLS example and test before reloading:

~~~bash
sudo cp /var/www/fragcity-api/nginx/fragcity-api-https.conf.example /etc/nginx/sites-available/fragcity-api.conf
sudo nginx -t
sudo systemctl reload nginx
curl -fsS https://api.fragcity.ir/health
~~~

**Verified on this node (2026-10-27):** `nginx -t` succeeded and the reload completed. The HTTPS request, using a direct-origin override because the default resolver follows the ISP DNS-tunnel path, returned:

~~~text
HTTP/2 200
{"ok":true,"service":"fragcity-linuxapi","version":1}
~~~

The exact test used was:

~~~bash
curl -i --connect-timeout 5 --max-time 10 \
  --resolve api.fragcity.ir:443:85.185.40.85 \
  https://api.fragcity.ir/health
~~~

Nginx warned that `listen 443 ssl http2` is deprecated in the installed version, and warned that protocol options are redefined for the IPv6 port-443 listener with a reference to the existing Pterodactyl vhost. These warnings did not prevent config validation or reload. Do not make an unreviewed change to the Pterodactyl vhost; if cleaning up the HTTP/2 syntax, use the syntax supported by the installed Nginx version and re-run `nginx -t`.

The health response is public and does not prove database connectivity. Do not switch the website to API transport until the signed smoke test passes.

After the HTTPS vhost is active, verify the certificate and scheduled renewal before declaring TLS setup complete:

~~~bash
sudo certbot certificates
systemctl list-timers --all | grep -Ei 'certbot|letsencrypt' || true
sudo certbot renew --dry-run
~~~

The dry run checks the renewal path; it does not replace the live certificate. Do not delete or reissue a valid certificate just because Certbot did not ask for an email.

## 8. Signed API smoke tests

The script reads /etc/fragcity-api/config.php and tests HMAC verification, Nginx header forwarding, nonce insertion, and the database ping.

~~~bash
php /var/www/fragcity-api/tools/smoke-test.php https://api.fragcity.ir
~~~

### If smoke test returns cURL code 6

On `server1`, `ERROR: Request transport failed (cURL code 6)` means cURL could not resolve `api.fragcity.ir`. This occurs before the request reaches TLS/HMAC/API/database verification; it is not evidence that HMAC or database configuration is wrong. On the current host, the configured ISP resolver participates in the existing DNS-tunnel setup and returns NXDOMAIN for this API hostname, while authoritative/public resolvers return `85.185.40.85`. Do not alter `/etc/resolv.conf`, NetworkManager, or resolver order to work around this single-name failure.

Run this temporary local mapping only for the signed smoke test. The command below is deliberately wrapped in a subshell so the marked line is removed when the test ends even if pasted directly into an interactive root shell. It reuses an existing line only if it carries the exact temporary marker; an unrelated pre-existing entry makes it stop:

~~~bash
# Clear the EXIT trap that may have been left in the current interactive shell by the previous pasted block.
trap - EXIT

# Use a subshell so the cleanup trap runs when this test finishes.
(
set -eu
if grep -Eq '(^|[[:space:]])api\.fragcity\.ir([[:space:]]|$)' /etc/hosts; then
    if grep -q 'FRAGCITY_API_SMOKE_TEMP' /etc/hosts; then
        echo "Using existing temporary API host mapping; it will be removed at exit."
    else
        echo "STOP: api.fragcity.ir already exists in /etc/hosts without the temporary marker; no changes made."
        grep -n 'api\.fragcity\.ir' /etc/hosts
        exit 1
    fi
else
    printf '%s\n' '85.185.40.85 api.fragcity.ir # FRAGCITY_API_SMOKE_TEMP' >> /etc/hosts
fi
trap 'sed -i "/FRAGCITY_API_SMOKE_TEMP/d" /etc/hosts' EXIT
getent ahostsv4 api.fragcity.ir
php /var/www/fragcity-api/tools/smoke-test.php https://api.fragcity.ir
)
~~~

This pins name resolution locally only for the test; it does not change DNS settings and does not validate the ISP DNS-tunnel route. The previously successful `curl --resolve ...:443:85.185.40.85` remains the separate direct-origin TLS/health check.

### Successful signed `ping_db` test — 2026-10-27

The following run passed using the temporary host mapping above. Since that first block was pasted directly into an interactive root shell, the `EXIT` trap may still be installed in that shell and the temporary line may persist until it exits. Use the subshell-wrapped command above for subsequent tests, which guarantees cleanup at the end of each test block:

~~~text
HTTP 200: {
    "success": true,
    "data": {
        "database": "ok"
    }
}
PASS: signed ping_db.
~~~

This verifies that the request reached the API, its HMAC was accepted, the nonce could be stored, and the API established a DB connection to `GLOBAL_weaponpaints`. It does not yet verify the read-only selection queries or any player-data writes.

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
- `ERROR: Request transport failed (cURL code 6)`: local name resolution failed before TLS/HMAC/DB checks. Use the temporary Section 8 host mapping to test this single hostname without changing the intentional ISP DNS-tunnel resolver settings. Do not treat this error as an HMAC rejection.
- Signed request rejected: ensure time is synchronized, all three X-FragCity headers are forwarded by Nginx, and the client and server use the exact same secret.
- TLS/redirect failure: verify DNS, certificate paths, and nginx -t before reload.

The database permission test, HTTPS health check, and signed `ping_db` smoke test are verified on this node. Do not declare the service production-ready until the read-only `get_selection` test and controlled test-player write/read-back tests also pass.

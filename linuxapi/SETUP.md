# FragCity Linux API — setup and recovery runbook

This is the repeatable setup guide for the files in this directory. Follow it on the Debian API host when deploying FragCity on a new node or rebuilding the service.

## Active data paths and retired Agent bridge

- **WeaponPaints:** the Linux API connects to the Linux-local MariaDB database `GLOBAL_weaponpaints` through `127.0.0.1:3306`.
- **Ranks:** the signed `/v1/ranks` route reads the plugin-owned table `GLOBAL_ranks.lvl_base`. The website leaderboard and profile do not connect directly to this schema.
- **VIPCore:** the signed `/v1/vipcore` route reads the existing `GLOBAL_vip.vip_users` table and exposes a restricted, test-only grant action. The grant action is admin-only, requires an explicit Linux SteamID64 allowlist, and does not implement payment.
- **Shared MariaDB identity (current FragCity deployment):** WeaponPaints, VIPCore and Ranks reuse the existing `plugins`@ `%` account and one password variable in the private runtime config. Do not create `fragcity_vip_api` or `fragcity_ranks_api` users for this deployment. The account's current grants are broader than the individual endpoints require and include `WITH GRANT OPTION`; this is a known compatibility trade-off, not a least-privilege boundary. Keep the credential private and do not broaden or alter grants during routine deployment.
- **Website data:** the main website database `kyonxcbt_fragcity_services` stays on shared hosting; Laragon uses a local copy. The confirmed website schema consists of `donations`, `password_resets`, `tickets`, `ticket_replies`, `users`, `user_wallets`, `vip_codes`, `vip_usage`, `wallet_transactions`, and `withdraw_requests`.
- The Linux API must never open a MySQL connection to `kyonxcbt_fragcity_services`, receive its credentials, or expose MariaDB port 3306.
- **Windows Agent API:** retired. `/v1/agent/*` returns HTTP 410; `api/internal_agent_bridge.php` is a fail-closed retired endpoint. Do not configure `website_bridge`, set `integrations.linuxapi_site_bridge`, create bridge-specific tables, or execute the former `site-bridge-schema.sql`.
- The old Agent/instance-maker tables (including `agent_tokens`, `agent_nodes`, `pending_servers`, `registered_servers`, `server_ports`, `server_configs`, and related queue/runtime tables) are not in the active website schema and must not be re-created for this migration.
- Read-only Pterodactyl integrations now support the public Server Browser (Application API + A2S) and admin Monitoring (Application API + Wings). Customer server provisioning, lifecycle controls, purchase/renewal and wallet billing remain disabled until a separate write-capable management workflow and a clear order/status source-of-truth contract are implemented.
- The C# `WebAPIClient` repository is reference-only for understanding old contracts; no C# code is changed by this work.

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
- Historical note (2026-10-27): the initial setup used `fragcity_weaponpaints_api`@ `127.0.0.1` with table-specific grants. This account arrangement was superseded on 2026-11-05 for the current FragCity deployment: the live private config now uses the existing `plugins`@ `%` account for WeaponPaints, VIPCore and Ranks.
- A permission check executed as `www-data` passed for database `GLOBAL_weaponpaints`, the required plugin-table SELECT checks, and Nonce INSERT/DELETE within a transaction that was rolled back.
- The signed `ping_db` smoke test passed with HTTP 200 and `data.database: ok`.
- The signed read-only `get_selection` smoke test passed with HTTP 200 and populated data for `skins`, `knife`, `agents`, `music`, and `pins`; the test player's SteamID and actual selected items are intentionally not recorded here.
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
- The signed `ping_db` smoke test passed on `server1` on 2026-10-27 after locally mapping `api.fragcity.ir` for that test. Response: HTTP 200, `success: true`, `data.database: ok`, followed by `PASS: signed ping_db`.
- The signed read-only `get_selection` smoke test passed on 2026-10-27 with HTTP 200 and `PASS: read-only get_selection`. The response included existing rows under `skins`, `knife`, `agents`, `music`, and `pins`; the player identifier and selected-item values are not recorded in this guide. The current query does not return glove selections.
- Both signed tests verify that the tested requests reached the API, passed HMAC validation and nonce handling, and used the intended `GLOBAL_weaponpaints` connection. The `get_selection` result also verifies the SELECT queries it actually executes; it does not test any writes.
- Controlled test-player write/read-back tests remain unverified. The website WeaponPaints repository is API-only (`transport: api`). Complete controlled test-player write/read-back tests before treating saves as production-verified. Do not change NetworkManager or ISP DNS settings to work around the local resolver discrepancy.

Do not repeat the database account creation/config-generation block under “Fresh-install account and private config”; the account and config already exist on this node. The direct-origin HTTP test, Let's Encrypt issuance, TLS vhost install, Nginx reload, HTTPS health test, signed `ping_db`, and read-only `get_selection` tests have completed successfully. The next step is controlled test-player write/read-back verification. Do not switch the shared-host website to API transport until this and the remaining checks are verified. Do not touch the existing Pterodactyl Panel vhost or change DNS resolver settings globally without explicit operator approval.



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

## 2. Stage the schema migration only

From the repository root on the Debian host, stage the SQL file first. **Do not replace `public/index.php` yet**: the new API reads the inventory table, so the database migration and API account grant must succeed before the runtime code is deployed.

~~~bash
sudo install -d -o root -g root -m 0755 /var/www/fragcity-api
sudo install -o root -g root -m 0644 linuxapi/schema.sql /var/www/fragcity-api/schema.sql
~~~

After Section 3 and either Section 4 (fresh install) or Section 5 (existing install) are complete, deploy the runtime files in Section 6. The sample Nginx vhost assumes the PHP-FPM socket `/run/php/php8.3-fpm.sock`.

## 3. Create the API-owned tables

The schema creates two service-owned tables in the verified global WeaponPaints database: `fragcity_api_nonces` prevents replayed signed requests, and `wp_player_skin_inventory` stores one customization per player, team, weapon and paint. The existing `wp_player_skins` table remains unchanged because the CS2 plugin uses it as the active loadout row.

Run this migration **before deploying the API code that reads the new inventory table**:

~~~bash
sudo mariadb GLOBAL_weaponpaints < /var/www/fragcity-api/schema.sql
sudo mariadb -t -e "SHOW CREATE TABLE GLOBAL_weaponpaints.fragcity_api_nonces;"
sudo mariadb -t -e "SHOW CREATE TABLE GLOBAL_weaponpaints.wp_player_skin_inventory;"
~~~

The schema uses CREATE TABLE IF NOT EXISTS. It does not recreate or replace any of the six existing plugin tables.

## 4. Existing plugins account and private config

The current FragCity deployment already has the MariaDB account `plugins`@ `%`. Reuse this account for the Linux API connections to `GLOBAL_weaponpaints`, `GLOBAL_vip` and `GLOBAL_ranks`; the SimpleAdmin block also inherits the root database credentials and changes only the schema name.

Do not create new `fragcity_weaponpaints_api`, `fragcity_vip_api` or `fragcity_ranks_api` accounts for the current deployment. Do not reset the existing account password. The private runtime config uses one `$pluginsDbPassword` variable for all three explicit database connections.

Before configuring a new server, verify that the expected account and schemas exist:

~~~bash
sudo mariadb -e "SELECT User, Host FROM mysql.user WHERE User='plugins';"
sudo mariadb -e "SHOW GRANTS FOR 'plugins'@'%';"
sudo mariadb -e "SHOW CREATE TABLE GLOBAL_weaponpaints.fragcity_api_nonces\\G"
sudo mariadb -e "SHOW CREATE TABLE GLOBAL_vip.vip_users\\G"
sudo mariadb -e "SHOW CREATE TABLE GLOBAL_ranks.lvl_base\\G"
~~~

The account currently has broad schema-level grants across plugin databases, including `WITH GRANT OPTION`. This exceeds the minimum operations required by these API routes. It is an explicit reuse decision for the existing installation, not least-privilege isolation. Do not attempt to reduce these grants during this deployment because other plugin integrations may rely on them. A future move to restricted per-service accounts needs a separate planned credential migration and verification of every consumer.

For a fresh installation, create the private runtime config from the tracked example only when the private file does not already exist:

~~~bash
if [ -e /etc/fragcity-api/config.php ]; then
    echo "STOP: private API config already exists; merge changes instead of replacing it."
    exit 1
fi

sudo install -d -o root -g www-data -m 0750 /etc/fragcity-api
sudo install -o root -g www-data -m 0640 linuxapi/config.example.php /etc/fragcity-api/config.php
sudo nano /etc/fragcity-api/config.php
~~~

Replace every placeholder locally with the existing database password, the matching HMAC secrets, and the real Pterodactyl token/host values required by enabled integrations. Never commit this completed runtime file or paste its secrets into chat.

For an existing installation, merge only the missing keys from `linuxapi/config.example.php`; do not replace the live file. Preserve the existing root HMAC key, Standby HMAC key, Application API token and database password.

The private config should be owned by `root:www-data` and mode `0640`:

~~~bash
sudo chown root:www-data /etc/fragcity-api/config.php
sudo chmod 0640 /etc/fragcity-api/config.php
php -l /etc/fragcity-api/config.php
sudo -u www-data test -r /etc/fragcity-api/config.php
~~~

## 5. Verify shared-account access without changing grants

Inspect the current account's grants; do not run `CREATE USER`, `ALTER USER`, `GRANT` or `REVOKE` as part of this reuse path:

~~~sql
SHOW GRANTS FOR 'plugins'@'%';
~~~

The route adapters keep their own fixed schemas, actions and SQL:
- WeaponPaints uses its allowlisted read/write operations and nonce replay table in `GLOBAL_weaponpaints`.
- VIPCore reads membership state and its restricted test grant writes only to `GLOBAL_vip.vip_users`.
- Ranks performs read-only queries against `GLOBAL_ranks.lvl_base`.
- SimpleAdmin reads allowlisted records from `GLOBAL_simpleadmin`.

The current MariaDB account has wider privileges than the SQL operations above require. Application-layer action allowlists do not turn a broad MariaDB grant into least-privilege isolation; treat the shared password as a high-impact secret and keep it server-side.

## 6. Deploy the updated API runtime files

Only run this after the schema migration has completed and the API account has `SELECT, INSERT, UPDATE, DELETE` privileges on both `wp_player_skin_inventory` and `wp_player_skins` (Section 4 grants these on a fresh install; Section 5 grants them to the existing account). Run it from the repository root on the Debian host.

~~~bash
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

The private runtime configuration remains at `/etc/fragcity-api/config.php`; these copy commands do not create or replace that file.

## 7. Test permissions as the PHP-FPM user

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
    'wp_player_skin_inventory',
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

## 8. Configure Nginx and HTTPS

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

## 9. Signed API smoke tests

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

The following run passed using the temporary host mapping above. The next command uses a subshell and a marked `EXIT` cleanup, so the temporary mapping is scoped to the test block. The guide does not ask you to alter the existing ISP resolver configuration:

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

### Successful read-only `get_selection` test — 2026-10-27

The same signed smoke-test script was run with a test SteamID as the second argument. It returned HTTP 200, `success: true`, populated result arrays, and `PASS: read-only get_selection`. The response contained records for:

- `skins` — weapon skin selections, including team-specific rows and sticker/keychain fields.
- `knife` — the saved knife selection.
- `agents` — T/CT agent selections.
- `music` — team-specific music selections.
- `pins` — team-specific pin selections.

This verifies only the SELECT statements actually implemented in `get_selection`. The current endpoint does **not** query `wp_player_gloves`, so do not claim glove reads have been tested. The output included real player data; keep SteamIDs and selected-item details out of generic installation documentation and public logs.

### Reusable API pattern for current and future plugin integrations

WeaponPaints remains the reference transport implementation. Ranks and VIPCore are now implemented as domain-specific signed routes: `POST /v1/ranks` is read-only for `GLOBAL_ranks.lvl_base`; `POST /v1/vipcore` reads `GLOBAL_vip.vip_users` and exposes a temporary no-payment test grant. SimpleAdmin also has its own restricted read-only route. These are not generic SQL proxies.

Reuse the security/transport pattern where appropriate:

1. HTTPS only, with certificate validation on clients; keep MariaDB port 3306 private rather than exposing it to plugin nodes or the Internet.
2. Signed requests using HMAC-SHA256 over a canonical representation of the timestamp, nonce, HTTP method, path, and SHA-256 hash of the exact request body. Validate timestamps within the allowed window, verify signatures with a constant-time comparison, and reject replayed nonces.
3. Strict request validation, prepared SQL statements, explicit action/route allowlists, bounded payload sizes, and generic external errors with useful but secret-free server logs. Never accept a caller-provided table name, column name, or SQL fragment.
4. Domain-specific routes are active: `/v1/ranks`, `/v1/vipcore`, and `/v1/simpleadmin`. Share the root HMAC transport where intended, but keep each route's action allowlist, validation, schema and database connection separate. Do not turn `/v1/weaponpaints` into an unrestricted all-plugin SQL endpoint.
5. Least-privilege database permissions. Keep `fragcity_weaponpaints_api` limited to its existing WeaponPaints/nonce grants. Ranks uses `fragcity_ranks_api` with `SELECT` on `GLOBAL_ranks.lvl_base` only. VIPCore uses `fragcity_vip_api` with `SELECT`, `INSERT`, and `UPDATE` on `GLOBAL_vip.vip_users` only. Do not reuse game-plugin or WeaponPaints credentials.
6. Test in stages: signed `ping_db`; read-only queries against known test data; controlled writes for a test player/account; then read-after-write comparison against the expected database rows. Record the request contract, expected database mutations, rollback/recovery approach, and test results before enabling the website integration.

Ranks and VIPCore schemas have been inspected for this integration. Do not alter either plugin-owned table. Confirm VIPCore's live `ServerId` before enabling the route. Leave `test_mode.enabled` false for initial connection checks; before a one-hour grant test, add only the trusted administrator's exact SteamID64 to `test_mode.allowed_steamids`, then temporarily enable test mode. The website endpoint also requires the authenticated site role `admin`. Keep the allowlist empty when no grant test is being performed.

Ranks is read-only. VIPCore test grants must be checked against `GLOBAL_vip.vip_users` and only with a designated test Steam account; no wallet deduction or ZarinPal payment is performed. The signed smoke tests only ping/read data and do not grant VIP.

## 10. Switch the shared-host website only after verification

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
- `ERROR: Request transport failed (cURL code 6)`: local name resolution failed before TLS/HMAC/DB checks. Use the temporary Section 9 host mapping to test this single hostname without changing the intentional ISP DNS-tunnel resolver settings. Do not treat this error as an HMAC rejection.
- Signed request rejected: ensure time is synchronized, all three X-FragCity headers are forwarded by Nginx, and the client and server use the exact same secret.
- TLS/redirect failure: verify DNS, certificate paths, and nginx -t before reload.

### تفسیر خروجی smoke test محلی Laragon

این بخش برای اجرای دستور زیر در ریشهٔ ریپوی وب‌سایت روی ویندوز است:

```powershell
php tools\weaponpaints-smoke-test.php
```

| خروجی | معنی و اقدام بعدی |
|---|---|
| `Linux API shared secret is not configured.` | فایل `settings/settings.json` بارگذاری شده، اما `integrations.weaponpaints_api.shared_secret` با قالب مورد انتظار سازگار نیست. کلید باید با مقدار `shared_secret` در `/etc/fragcity-api/config.php` یکسان و ۶۴ تا ۱۲۸ کاراکتر هگزادسیمال کوچک باشد؛ دستور `openssl rand -hex 32` یک کلید معتبر ۶۴ کاراکتری می‌سازد. این خطا پیش از درخواست شبکه رخ می‌دهد. |
| `Linux API request failed (HTTP ...; code=...)` | درخواست پاسخی از API گرفته، ولی status HTTP یا فیلد `success` نشان می‌دهد درخواست پذیرفته نشده است. `HTTP 503; code=service_not_configured` معمولاً به کانفیگ اجرایی API یا قالب secret روی لینوکس مربوط است؛ `HTTP 401; code=unauthorized` معمولاً به عدم تطابق secret، زمان سیستم، یا هدرهای امضا مربوط می‌شود. جزئیات بیشتری را در لاگ API/Nginx بررسی کن. |
| `Linux API is currently unavailable (cURL error N: ...).` | درخواست در لایهٔ انتقال HTTPS شکست خورده است. کد و پیام cURL در خروجی CLI نشان داده می‌شود؛ از آن برای تفکیک DNS، اتصال TCP، timeout یا خطای TLS استفاده کن. |
| `PASS: Signed API request succeeded and the WeaponPaints database is reachable.` | درخواست HMAC امضاشده موفق شده و API پاسخ تأیید دسترسی دیتابیس WeaponPaints را داده است. این تست به‌تنهایی ذخیرهٔ واقعی یک اسکین را آزمایش نمی‌کند. |

کدهای رایج cURL: `6` یعنی نام میزبان resolve نشده؛ `7` یعنی اتصال به میزبان/پورت برقرار نشده؛ `28` یعنی timeout؛ `35` معمولاً خطای handshake مربوط به TLS است؛ و `60` معمولاً اعتبارسنجی گواهی TLS را رد می‌کند. این‌ها راهنمای تشخیص اولیه‌اند؛ پیام کامل cURL را نیز بخوان.

برای جداکردن مشکل DNS/TLS از امضای API، این درخواست GET عمومی را در PowerShell اجرا کن:

```powershell
nslookup api.fragcity.ir
curl.exe -i --connect-timeout 5 --max-time 10 https://api.fragcity.ir/health
```

انتظار می‌رود `/health` یک پاسخ HTTP موفق با `"service":"fragcity-linuxapi"` برگرداند. این فقط دسترسی HTTPS و مسیر health را آزمایش می‌کند و نه امضای HMAC یا اتصال دیتابیس.

**نکتهٔ PHP 8.5:** پیام `Deprecated: Function curl_close() is deprecated` به‌خودی‌خود دلیل شکست اتصال نیست؛ شکست واقعی در نسخهٔ کلاینت به‌صورت خطای cURL ثبت می‌شود. فراخوانی `curl_close()` از کلاینت وب و smoke test لینوکس حذف شده و تست CLI اکنون باید کد و پیام واقعی cURL را نشان دهد. پس از `git pull`، تست را دوباره اجرا کن. اگر خطای انتقال باقی ماند، متن کامل خطای جدید را بفرست، اما هیچ secret، رمز دیتابیس، یا هدر امضا را ارسال نکن.

The database permission test, HTTPS health check, signed `ping_db`, and read-only `get_selection` tests are verified on this node. Do not declare the service production-ready until controlled test-player write/read-back tests and the remaining deployment checks pass.

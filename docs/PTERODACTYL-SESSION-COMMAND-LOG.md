# FragCity — Pterodactyl Session Command Log

> **Purpose:** Chronological record of the substantive commands, the reason each was used, observed outcomes, mistakes and corrections from the Panel/Wings setup conversation. This is a learning aid and audit trail, not a script to run from top to bottom.
>
> **Current scope:** Debian 13 VM, Pterodactyl Panel in the local LAN. This is not Production. Secrets are intentionally omitted. Never paste real passwords, APP_KEYs, tokens, or the complete contents of `.env` into this document.
>
> **Related guides:** [Quick Setup in README](../README.md), [Panel LAN Setup](PTERODACTYL-LAN-SETUP.md), [Wings & Docker Install](WINGS-INSTALL.md), [Debian PoC history](DEBIAN-POC.md), [Current Status](CHANGELOG-STATUS.md).

## How to use this log

- **Executed / observed** means the user supplied a terminal output showing the command or result.
- **Suggested / not yet executed** means the command appeared in instructions but there is no evidence it was run.
- Historical commands are preserved to explain what happened. Prefer the current canonical procedure in the linked guide when repeating a task.
- Do not rerun database creation, `APP_KEY` generation, migrations, or administrator creation on the current installation without a specific reason.

## 1. Identify the Panel directory and verify PHP extensions

### Command used

~~~bash
set -eu

PANEL_DIR='/var/www/pterodactyl'

echo "=== CHECK REQUIRED PHP EXTENSIONS ==="
for module in bcmath curl dom gd intl json mbstring mysqli openssl PDO pdo_mysql posix tokenizer xml zip; do
    if php8.3 -m | grep -Eixq "$module"; then
        printf 'OK: %s\n' "$module"
    else
        printf 'MISSING: %s\n' "$module" >&2
        exit 1
    fi
done

echo
echo "=== CHECK WEB SERVER ACCOUNT ==="
id www-data

echo
echo "=== CHECK PANEL FILES ==="
for required_file in artisan .env.example composer.json public/index.php; do
    if ! sudo test -f "$PANEL_DIR/$required_file"; then
        echo "ERROR: missing $PANEL_DIR/$required_file" >&2
        exit 1
    fi
    echo "OK: $required_file"
done

if sudo test -e "$PANEL_DIR/.env"; then
    echo "STOP: .env already exists; this command will not overwrite it." >&2
    exit 1
fi

echo
echo "=== SET PANEL OWNERSHIP ==="
sudo chown -R www-data:www-data "$PANEL_DIR"

echo
echo "=== SET STORAGE / CACHE PERMISSIONS ==="
sudo chmod -R 755 "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"

echo
echo "=== VERIFY OWNERSHIP AND PERMISSIONS ==="
sudo stat -c '%U:%G mode=%a %n' \
    "$PANEL_DIR" \
    "$PANEL_DIR/artisan" \
    "$PANEL_DIR/storage" \
    "$PANEL_DIR/bootstrap/cache"

echo
echo "=== DISK AFTER PERMISSION SETUP ==="
df -h /
~~~

### What this does

- `set -eu` stops the shell on an unhandled command failure or use of an unset variable.
- `php8.3 -m` lists extensions loaded by PHP CLI; `grep -Eixq` checks each requested module name.
- `id www-data` confirms the Linux service account used by the web app.
- The required-file loop confirms that this directory looks like a Panel source tree before permissions are changed.
- The `.env` check prevents accidentally overwriting an existing configuration.
- `chown -R` changes owner and group recursively. Recursive ownership should be deliberate; do not apply it to unrelated system directories.
- This first command used `chmod -R 755` on storage/cache. A later write test showed this was not the final desired state for all existing files. The later, more precise directory/file permission procedure in section 6 is the corrective record and the preferred procedure.
- `df -h /` reports root filesystem capacity.

### Observed outcome

The earlier terminal checks subsequently showed PHP 8.3.35, Composer 2.10.3, and the required Panel files. No secret values are stored here.

## 2. Check Composer, the Panel path and existing `.env`

### Command used

~~~bash
set -eu

cd /var/www/pterodactyl

echo "=== PHP 8.3 ==="
php8.3 -v

echo
echo "=== COMPOSER ==="
composer --version

echo
echo "=== COMPOSER PATH ==="
command -v composer

echo
echo "=== PANEL DIRECTORY ==="
sudo stat -c '%U:%G mode=%a %n' \
    /var/www/pterodactyl \
    /var/www/pterodactyl/composer.json
~~~

### Observed outcome

- PHP CLI: `8.3.35`
- Composer: `2.10.3`
- Composer path: `/usr/local/bin/composer`
- Panel directory and composer file were owned by `www-data:www-data` at that check.
- Composer printed a root-package version notice. It was a notice, not the final installation failure.

## 3. Create `.env`, install dependencies and generate `APP_KEY`

### Command used

~~~bash
set -eu

PANEL_DIR='/var/www/pterodactyl'
cd "$PANEL_DIR"

if sudo test -e "$PANEL_DIR/.env"; then
    echo "STOP: .env already exists. Nothing was overwritten."
    exit 1
fi

sudo cp .env.example .env

COMPOSER_BIN="$(command -v composer)"
sudo env COMPOSER_ALLOW_SUPERUSER=1 \
    php8.3 "$COMPOSER_BIN" install --no-dev --optimize-autoloader

sudo php8.3 artisan key:generate --force

if sudo grep -Eq '^APP_KEY=base64:.+' .env; then
    echo "OK: APP_KEY generated"
else
    echo "ERROR: APP_KEY was not generated correctly" >&2
    exit 1
fi

sudo php8.3 "$COMPOSER_BIN" check-platform-reqs --no-dev

sudo php8.3 artisan list | grep -E 'p:environment|p:user:make' || true
~~~

### Explanation

- `.env.example` is a template; `.env` contains real environment settings and must stay private.
- Composer reads the lock file and installs PHP dependencies. `--no-dev` skips development-only packages; `--optimize-autoloader` optimizes class loading.
- `COMPOSER_ALLOW_SUPERUSER=1` was used because this historical command ran Composer with sudo. For routine application operations, prefer the service account where supported and follow the official instructions for the exact Panel release.
- `key:generate` creates the Laravel application encryption key. Do not casually run it again on an initialized installation.
- `check-platform-reqs --no-dev` checks whether installed PHP/runtime extensions satisfy the installed package requirements.

### Observed outcome

Composer reported a timeout while fetching some Packagist metadata, but continued installing packages. The install finished, package discovery completed, `APP_KEY` was generated, and platform requirements were reported successful. This is the observed result for that attempt; it is not a guarantee that every future Composer run will work offline.

## 4. Check MariaDB, Redis and PHP-FPM

### Command used

~~~bash
set -eu

if sudo systemctl is-active --quiet mariadb; then
    echo "MariaDB: ACTIVE"
else
    echo "MariaDB: NOT ACTIVE"
fi

sudo mariadb -Nse 'SELECT VERSION();'

if sudo systemctl is-active --quiet redis-server; then
    echo "Redis: ACTIVE"
else
    echo "Redis: NOT ACTIVE"
fi

if command -v redis-cli >/dev/null 2>&1; then
    redis-cli ping
else
    echo "redis-cli is not installed"
fi

systemctl list-units --type=service --all 'php8.3-fpm.service' --no-legend
~~~

### Observed outcome

- MariaDB: active, version `11.8.6-MariaDB-0+deb13u1`.
- Redis: active; the test returned `PONG` at this point.
- PHP-FPM: `php8.3-fpm.service` active/running.

**Redis warning:** the later Panel environment wizard left Redis Password blank. If Redis is now configured with a password, verify both the Redis service and Panel config are aligned. Entering a password in Panel alone does not enable authentication in Redis.

## 5. Inspect the MariaDB database and account

### Read-only existence check

~~~bash
set -eu

sudo mariadb -Nse "
SELECT SCHEMA_NAME
FROM INFORMATION_SCHEMA.SCHEMATA
WHERE SCHEMA_NAME = 'panel';
"

sudo mariadb -Nse "
SELECT CONCAT(User, '@', Host)
FROM mysql.user
WHERE User = 'pterodactyl';
"
~~~

### What happened

Both checks initially returned empty output: neither the `panel` database nor the `pterodactyl` MariaDB account existed at that point.

An accidental attempt used the host name as the database name:

~~~sql
CREATE DATABASE fc-node-01
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
~~~

MariaDB returned SQL syntax error at the hyphen. This command failed and did not create the intended database. The intended database name is `panel`, not the machine hostname.

The user was then created with a literal placeholder password in the interactive session, so that password had to be replaced with a unique real password afterwards. The actual password is intentionally not recorded.

### Correct SQL procedure for a fresh database

First enter MariaDB:

~~~bash
sudo env MYSQL_HISTFILE=/dev/null mariadb
~~~

Then use the following SQL, replacing the password placeholder with a strong unique value **locally, before execution**:

~~~sql
CREATE DATABASE panel
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER 'pterodactyl'@'127.0.0.1'
    IDENTIFIED BY 'REPLACE_WITH_A_UNIQUE_PASSWORD';

GRANT ALL PRIVILEGES ON panel.*
    TO 'pterodactyl'@'127.0.0.1';

SHOW DATABASES LIKE 'panel';
SHOW GRANTS FOR 'pterodactyl'@'127.0.0.1';
EXIT;
~~~

If the database/user already exists, do not rerun `CREATE` blindly. For the account created with a placeholder password, the password-change operation is:

~~~sql
ALTER USER 'pterodactyl'@'127.0.0.1'
    IDENTIFIED BY 'REPLACE_WITH_YOUR_NEW_UNIQUE_PASSWORD';

GRANT ALL PRIVILEGES ON panel.*
    TO 'pterodactyl'@'127.0.0.1';

SHOW GRANTS FOR 'pterodactyl'@'127.0.0.1';
~~~

A MariaDB administrative account can often be accessed locally via `sudo mariadb` on Debian using socket-based authentication. To inspect the actual root authentication plugin:

~~~bash
sudo mariadb -e "SELECT User, Host, plugin FROM mysql.user WHERE User = 'root';"
~~~

Do not assume the database root password is the same as the Linux password, and do not use MariaDB root as the Panel's database user. The Panel uses the restricted `pterodactyl` account.

### Observed outcome

The user later confirmed that the database setup was corrected and working. The Panel wizard used:

- DB host: `127.0.0.1`
- DB port: `3306`
- Database: `panel`
- DB username: `pterodactyl`
- Password: entered interactively and intentionally omitted from documentation.

## 6. Fix Laravel cache write permission

### Error observed

The command

~~~bash
sudo -u www-data php8.3 artisan p:environment:setup
~~~

failed with:

~~~text
file_put_contents(/var/www/pterodactyl/storage/framework/cache/data/...):
Failed to open stream: Permission denied
~~~

This meant that the service account `www-data` could not write to a Laravel cache file. It did not indicate a DB password error.

### Corrective command actually executed

~~~bash
set -eu
cd /var/www/pterodactyl

sudo chown -R www-data:www-data storage bootstrap/cache

sudo find storage bootstrap/cache \
    -type d -exec chmod 750 {} +

sudo find storage bootstrap/cache \
    -type f -exec chmod 640 {} +

sudo -u www-data sh -c \
  'touch storage/framework/cache/data/.fragcity-permission-test &&
   rm storage/framework/cache/data/.fragcity-permission-test &&
   echo "OK: www-data can write to Laravel cache"'
~~~

### Observed outcome

The test returned:

~~~text
OK: www-data can write to Laravel cache
~~~

After that, `p:environment:setup` reached the interactive prompt and completed on the subsequent attempt.

**Important lesson:** `chown` sets ownership; `chmod` sets permissions. Directories need execute/search permission for traversal. The scoped procedure above is preferred to broad `chmod -R 777` or indiscriminately changing ownership of all system directories.

## 7. Configure Panel environment and database through Artisan

### Commands executed

~~~bash
cd /var/www/pterodactyl
sudo -u www-data php8.3 artisan p:environment:setup
sudo -u www-data php8.3 artisan p:environment:database
~~~

The first run of `p:environment:setup` was interrupted with Ctrl+C at the Egg Author Email prompt. It was run again and completed. A later cache permission error was corrected as documented in section 6.

### Values selected for LAN test

| Prompt | Value selected |
|---|---|
| Egg Author Email | a real email was entered; not repeated in this guide |
| Application URL | `http://192.168.1.5` |
| Timezone | `Asia/Tehran` |
| Cache Driver | `redis` |
| Session Driver | `redis` |
| Queue Driver | `redis` |
| UI settings editor | `yes` |
| Anonymous telemetry | `no` |
| Redis Host | `127.0.0.1` |
| Redis Password | blank in the wizard |
| Redis Port | `6379` |
| Database Host | `127.0.0.1` |
| Database Port | `3306` |
| Database Name | `panel` |
| Database Username | `pterodactyl` |
| Database Password | entered interactively; not recorded |

### Read-only configuration summary command

~~~bash
cd /var/www/pterodactyl

sudo grep -E \
'^(APP_ENV|APP_URL|APP_DEBUG|CACHE_DRIVER|CACHE_STORE|SESSION_DRIVER|QUEUE_CONNECTION|REDIS_HOST|DB_CONNECTION|DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME)=' \
.env || true
~~~

The summary check earlier showed `APP_URL=http://panel.example.com` before the successful wizard run. The later successful wizard selected `http://192.168.1.5`. Do not treat the old output as the current URL. Never print all of `.env` because it contains secrets.



### Troubleshooting: compare Artisan as the admin and service account

After the setup wizard returned unexpectedly, these read-only checks were run:

~~~bash
set -eu
cd /var/www/pterodactyl

sudo php8.3 artisan --version
sudo php8.3 artisan list | grep -E 'p:environment:(setup|database|mail)' || true

sudo -u www-data php8.3 artisan --version
sudo -u www-data php8.3 artisan list | grep -E 'p:environment:(setup|database|mail)' || true

sudo stat -c '%U:%G mode=%a %n' .env

if sudo -u www-data test -r .env; then
    echo "OK: www-data can read .env"
else
    echo "ERROR: www-data cannot read .env"
fi

if sudo -u www-data test -w .env; then
    echo "OK: www-data can write .env"
else
    echo "ERROR: www-data cannot write .env"
fi
~~~

**Observed outcome:** both identities reported Laravel Framework 12.64.0 and the expected `p:environment:setup`, `p:environment:database`, and `p:environment:mail` commands. The `.env` file was `www-data:www-data mode=640`; `www-data` could read and write it. The earlier wizard failure was a cache path permission failure, not a missing Artisan command.

The safe configuration-summary command is in the previous section. Never use `cat .env` in output that will be copied into chat or documentation.

## 8. Run migrations and seed, then create the administrator

### Commands actually executed

~~~bash
sudo -u www-data php8.3 artisan migrate --seed --force
sudo -u www-data php8.3 artisan p:user:make
~~~

### Observed outcomes

- The migration table was created.
- The stored MySQL schema loaded successfully.
- The listed migration completed.
- `NestSeeder` and `EggSeeder` finished successfully.
- The seed output included a Source Engine Egg named `Counter-Strike: Global Offensive`.
- The administrator creation command confirmed `Admin: Yes`. Personal email/name and password are intentionally omitted.

Do not rerun these commands just because they appear in the guide. They already succeeded in the current Panel install.

## 9. Query which Counter-Strike Eggs exist

### Query actually executed

~~~bash
sudo mariadb panel -e "
SELECT id, name, nest_id
FROM eggs
WHERE LOWER(name) LIKE '%counter%strike%'
   OR LOWER(name) LIKE '%cs2%'
ORDER BY name;
"
~~~

### Observed result

Only the following row was returned:

~~~text
id: 11
name: Counter-Strike: Global Offensive
nest_id: 2
~~~

The query did **not** find an Egg explicitly named Counter-Strike 2/CS2. This does not mean Pterodactyl cannot manage CS2; it means the dedicated CS2 Egg has not yet been imported/verified. Adding the Egg, selecting the image, and testing disk use belong to the later CS2 integration chapter.

## 10. NGINX checks and the applied LAN site

### Diagnostic commands used

~~~bash
cd /var/www/pterodactyl

command -v nginx || true
sudo systemctl is-active nginx
sudo ls -la /etc/nginx/sites-available/
sudo ls -la /etc/nginx/sites-enabled/
sudo test -S /run/php/php8.3-fpm.sock && echo "PHP-FPM socket exists"
sudo ss -ltnp | grep -E ':(80|443)\\b' || true
sudo test -f public/index.php && echo "Panel entry file exists"
~~~

The user initially observed `command -v nginx` reporting that no command was found while the `nginx` systemd service was active and listening on TCP/80. That can occur if the executable is not in the user's PATH. Check the binary by its full path instead:

~~~bash
sudo /usr/sbin/nginx -v
~~~

### NGINX site configuration provided and applied for LAN

The following block was used as the Panel server configuration:

~~~nginx
server {
    listen 80;
    listen [::]:80;
    server_name 192.168.1.5;

    root /var/www/pterodactyl/public;
    index index.php;

    access_log /var/log/nginx/pterodactyl.app-access.log;
    error_log /var/log/nginx/pterodactyl.app-error.log error;

    client_max_body_size 100m;
    client_body_timeout 120s;
    sendfile off;

    add_header X-Content-Type-Options nosniff;
    add_header X-Robots-Tag none;
    add_header Content-Security-Policy "frame-ancestors 'self'";
    add_header X-Frame-Options DENY;
    add_header Referrer-Policy same-origin;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ^~ /assets/ {
        location ~ /\. {
            deny all;
        }

        try_files $uri =404;
    }

    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;

        fastcgi_param PHP_VALUE "upload_max_filesize = 100M \n post_max_size = 100M";
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param HTTP_PROXY "";

        fastcgi_intercept_errors off;
        fastcgi_buffer_size 16k;
        fastcgi_buffers 4 16k;
        fastcgi_connect_timeout 300;
        fastcgi_send_timeout 300;
        fastcgi_read_timeout 300;
    }

    location ~ /\.ht {
        deny all;
    }
}
~~~

The config path is `/etc/nginx/sites-available/pterodactyl.conf`; the enabled link points to it from `/etc/nginx/sites-enabled/pterodactyl.conf`. The default site symlink was disabled so the Panel site could serve the LAN address.

### How the site was enabled

The config file was written only after checking that a file with that name did not already exist. These commands enabled the site and disabled the default site symlink while preserving the original file in `sites-available`:

~~~bash
sudo ln -s \
    /etc/nginx/sites-available/pterodactyl.conf \
    /etc/nginx/sites-enabled/pterodactyl.conf

if sudo test -L /etc/nginx/sites-enabled/default; then
    sudo rm /etc/nginx/sites-enabled/default
    echo "Default site symlink disabled; source file is preserved."
else
    echo "No default-site symlink found."
fi
~~~

Removing this symlink only disables the default site's enabled link; it does not delete `/etc/nginx/sites-available/default`. Do not remove an existing custom config without inspecting it.

### Syntax check, reload and local HTTP test

~~~bash
sudo /usr/sbin/nginx -t
sudo systemctl reload nginx
sudo systemctl is-active nginx

curl -sS -o /dev/null \
    -w 'HTTP status: %{http_code}\n' \
    -H 'Host: 192.168.1.5' \
    http://127.0.0.1/
~~~

### Observed outcome

The user confirmed that the site worked and they successfully logged in to Panel from the Windows browser at `http://192.168.1.5`. This is a LAN/HTTP success, **not** a public HTTPS setup.

## 11. HeidiSQL, SSH, SFTP and MariaDB root

### SSH connectivity test (successful)

From Windows PowerShell:

~~~powershell
ssh fragcityadmin@192.168.1.5
~~~

The user successfully authenticated and got a shell on `fc-node-01`. During first connection, OpenSSH displayed the host-key fingerprint and asked for trust confirmation. The fingerprint observed in the conversation was:

~~~text
SHA256:+qNf6tmHQbmqIM5q5zdYMPdGZYdp+08FG/SZ1koHk5g
~~~

Always verify the host key through a trusted path if it changes unexpectedly; do not blindly accept an unexpected new fingerprint.

### HeidiSQL via a separate PowerShell SSH tunnel

This command was suggested to create a local tunnel:

~~~powershell
ssh -v -N -L 3307:127.0.0.1:3306 fragcityadmin@192.168.1.5
~~~

Keep that PowerShell window open. If the tunnel connects, configure HeidiSQL as **MariaDB/MySQL TCP/IP** with host `127.0.0.1`, port `3307`, user `pterodactyl` and the DB password; the SSH tunnel forwards local port 3307 through SSH to MariaDB on the Debian loopback at port 3306.

The internal HeidiSQL SSH Tunnel mode was also discussed. However, an actual successful HeidiSQL database login was **not confirmed in the logs available for this guide**; an SSH login is confirmed, which does not by itself prove that HeidiSQL's database handshake succeeded.

### WinSCP / SFTP

WinSCP SFTP was recommended because it reuses SSH and does not need a separate insecure FTP daemon. LAN settings: SFTP, host `192.168.1.5`, port `22`, Linux account `fragcityadmin`. File permissions still apply; do not change system paths to mode 777 just to edit them.

### Inspect MariaDB root authentication

~~~bash
sudo mariadb -e "SELECT User, Host, plugin FROM mysql.user WHERE User = 'root';"
~~~

On Debian, local `sudo mariadb` often works through socket-based authentication. The actual plugin should be inspected rather than assuming what the root password is. Linux `root`, MariaDB `root` and MariaDB `pterodactyl` are different identities. Avoid exposing MariaDB to LAN/WAN or using database root from HeidiSQL for routine Panel work.

## 12. Disk investigation before Wings/Docker

### Commands executed

~~~bash
set -u

df -hT /
df -ih /
sudo du -xhd1 / 2>/dev/null | sort -h
sudo du -xhd1 /var /usr /home /opt /tmp 2>/dev/null | sort -h
sudo du -sh /var/cache/apt/archives 2>/dev/null || true
sudo journalctl --disk-usage
sudo du -sh /var/log 2>/dev/null || true
sudo du -sh /var/lib/docker /var/lib/containerd 2>/dev/null || true
~~~

### Observed output

- Root filesystem: 112 GiB total, 80 GiB used, 27 GiB free, 75%.
- `/home`: about 72 GiB; `/home/steam`: about 71 GiB.
- `/usr`: about 5 GiB; `/var`: about 2 GiB.
- APT archive cache: about 197 MiB.
- systemd journal: about 52.7 MiB; `/var/log`: about 71 MiB.
- No `/var/lib/docker`/containerd size was reported at that time.

The user confirmed that `/home/steam` contains the previously installed CS2 files and KUS project files. The path must be preserved. A CS2 Egg may download another game copy and exceed the free space; don't create a Panel-managed CS2 instance until the dedicated storage/volume integration chapter proves the safe approach.

## 13. Docker preflight — executed; Docker installation not yet executed

### Check command used

~~~bash
set -eu
. /etc/os-release
printf 'OS: %s (%s)\n' "$PRETTY_NAME" "$VERSION_CODENAME"
printf 'Architecture: %s\n' "$(dpkg --print-architecture)"

conflict_found=0
for package in docker.io docker-compose docker-doc docker-buildx podman-docker containerd runc; do
    status="$(dpkg-query -W -f='${db:Status-Status}' "$package" 2>/dev/null || true)"
    if [ "$status" = installed ]; then
        printf 'INSTALLED: %s\n' "$package"
        conflict_found=1
    fi
done

if [ "$conflict_found" -eq 0 ]; then
    echo "OK: no listed legacy/conflicting packages found."
else
    echo "STOP: review the listed packages before installation."
fi

if sudo test -e /etc/apt/sources.list.d/docker.sources; then
    sudo cat /etc/apt/sources.list.d/docker.sources
else
    echo "Official Docker repository file not found yet."
fi
~~~

### Observed outcome

- OS: Debian GNU/Linux 13 (Trixie)
- Architecture: `amd64`
- No listed legacy/conflicting packages found.
- Official Docker repository file did not exist yet.
- Docker CLI and Docker service were not installed/active.
- Most recent resources: four vCPU, around 5.6 GiB RAM, 27 GiB root space free.

**Status at the time this earlier entry was written (historical):** Docker repository setup and Engine verification had not yet been run. The later section [Docker verification — 18 October 2026](#docker-verification--18-october-2026-executed--observed) supersedes that state: Docker 29.9.1 is now installed and `hello-world` succeeded. Wings remains pending.

## 14. Current status and next action

### Completed

- [x] Debian Node preflight and LAN SSH.
- [x] PHP/Composer and Panel dependencies.
- [x] MariaDB/Redis checks and Panel DB/account.
- [x] Environment wizard, migrations/seeds and admin account.
- [x] Cache permission correction.
- [x] NGINX configuration and successful login from Windows in LAN.
- [x] Checked the database for CS-related Eggs; only CS:GO was found.

### Pending

- [ ] Record the exact installed Panel release/commit before selecting Wings.
- [ ] Install Docker from official Debian repository.
- [ ] Verify Docker Engine and `hello-world`.
- [ ] Create the Panel Node, install Wings and configure its service.
- [ ] Confirm Wings is Online.
- [ ] Add/verify a dedicated CS2 Egg.
- [ ] Design and test reuse of the existing `/home/steam` files in the later KUS integration chapter.
- [ ] Separately document public DNS/TLS/firewall/router/VMware port forwarding; none of these is part of the current LAN setup.

**Never confuse these outcomes:** opening Panel means the web application works; a green/online Wings means the node agent is connected; a successful CS2 server lifecycle means the game server actually starts/stops/restarts from Panel. The overall PoC is not complete until all required stages are tested.


## Docker verification — 18 October 2026 (executed / observed)

### Command/result

The user ran a read/verify block in Debian `fc-node-01`:

~~~bash
sudo docker --version
sudo systemctl is-active docker
sudo docker info --format 'Server={{.ServerVersion}} Containers={{.Containers}} Images={{.Images}}'
sudo docker run --rm hello-world
df -h /
sudo du -sh /var/lib/docker 2>/dev/null || true
~~~

Observed output:
- Docker version 29.9.1, build `dfc520d`.
- systemd service state: `ACTIVE`.
- Engine information: Server `29.9.1`, Containers `0`, Images `0` at the time of the info query (before the test image was pulled).
- Docker Hub pull for `hello-world:latest` completed successfully; the amd64 test container ran and printed `Hello from Docker!`.
- Root filesystem after the test: 112 GiB total, 80 GiB used, 27 GiB available; `du -sh /var/lib/docker` printed 232 KiB in that sampling.
- In the immediately preceding diagnostic, official Docker GPG and Debian Trixie/InRelease endpoints each returned HTTP 200, and dpkg-query reported the five Docker packages installed.

### Interpretation

- **Docker Engine is installed and its basic container workflow was verified.** The old HTTP 403 was not reproduced by the endpoint test.
- This does not prove Wings is installed, connected to Panel, or able to run CS2.
- Do not rerun the Docker installation block. Next action is to read the exact Panel version before choosing a compatible Wings release.

### Next read-only command

~~~bash
cd /var/www/pterodactyl
sudo -u www-data php8.3 artisan p:info
~~~

This Artisan command displays Panel version and configuration details. Review the output and redact any sensitive value before sharing; never record database credentials, APP_KEY, tokens, or the complete `.env` contents.

Official version references:
- https://docs.pterodactyl.io/v2 (2.0 documentation is marked pre-release)
- https://docs.pterodactyl.io/v2/wings/installing (states Wings 1.13.2/1.13.3 were tested with Panel 2.0)
- https://docs.pterodactyl.io/v1/wings/installing (separate 1.x instructions)

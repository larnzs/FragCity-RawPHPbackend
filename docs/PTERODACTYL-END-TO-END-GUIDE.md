# راهنمای یکپارچهٔ FragCity — نصب Panel، بررسی‌ها و ادامهٔ Wings

> **هدف این فایل:** همهٔ مراحل معنادار این گفتگو را به ترتیب اجرا، با بلوک‌های کد قابل پیدا کردن و وضعیت واقعی هر مرحله نگه دارد. اول توضیحات معماری را بخوان؛ سپس مرحله‌ها را به ترتیب دنبال کن.
>
> **وضعیت واقعی:** Pterodactyl Panel در LAN نصب شده و ورود Windows موفق بوده است. Docker Engine نسخهٔ 29.9.1 نصب شده، سرویس فعال است و آزمون `hello-world` موفق بوده است. Wings هنوز نصب نشده؛ قدم بعدی تأیید نسخهٔ دقیق Panel و انتخاب نسخهٔ سازگار Wings است.
>
> راهنمای سریع مخزن در [README](../README.md)، این راهنمای یکپارچه در ادامه، ثبت فرمان‌ها و خطاهای گفتگو در [Session Command Log](PTERODACTYL-SESSION-COMMAND-LOG.md)، راهنمای تفصیلی Panel در [PTERODACTYL-LAN-SETUP](PTERODACTYL-LAN-SETUP.md)، و فصل Wings در [WINGS-INSTALL](WINGS-INSTALL.md) قرار دارند.

## توضیحات کامل قبل از اجرا

### اجزای سیستم

- **Debian 13:** سیستم‌عامل نود آزمایشی FragCity است و داخل VMware اجرا می‌شود.
- **Pterodactyl Panel:** وب‌اپلیکیشن مدیریتی که از Windows در آدرس فعلی http://192.168.1.5 باز می‌شود.
- **NGINX:** وب‌سرور؛ درخواست‌های HTTP را دریافت می‌کند و درخواست‌های PHP را به PHP-FPM می‌فرستد.
- **PHP-FPM:** برنامهٔ Laravel پنل را اجرا می‌کند.
- **MariaDB:** دیتابیس اختصاصی Panel است؛ دیتابیس وب‌سایت FragCity نیست و حساب مدیریتی root آن نباید در پیکربندی Panel استفاده شود.
- **Redis:** در تنظیمات فعلی برای cache، session و queue انتخاب شده است.
- **Wings:** عامل نود است؛ درخواست‌های مدیریت سرور را از Panel می‌گیرد و Docker را کنترل می‌کند.
- **Docker Engine:** runtime ساخت و اجرای containerها است؛ خودش Panel، Wings یا CS2 نیست.
- **Container:** محیطی جداشده است که فرایند واقعی سرور بازی در آن اجرا می‌شود.
- **Egg:** الگوی نصب و اجرای نوع بازی است؛ SQL فعلی فقط Egg با نام Counter-Strike: Global Offensive را نشان داد، نه Egg اختصاصی CS2.
- **Allocation:** IP و پورت‌هایی است که به یک سرور بازی اختصاص می‌دهیم.

یک Panel می‌تواند چند Node را مدیریت کند و یک Node می‌تواند چند سرور بازی را میزبانی کند. برای هر سرور بازی روی یک VM الزاماً Node جدا نیاز نیست. DNS به‌تنهایی پورت را تعیین نمی‌کند.

### فصل‌بندی پروژه

| فصل | موضوع | وضعیت |
|---|---|---|
| ۰ | دانلود Debian، ساخت VM در VMware و نصب سیستم‌عامل | بعداً مستند مستقل می‌شود |
| ۱ | راه‌اندازی اولیهٔ Debian، SSH، شبکه، به‌روزرسانی سیستم و آماده‌سازی فایل‌ها | بعداً مستند مستقل می‌شود |
| ۲ | نصب Panel در LAN | تکمیل شده؛ login موفق |
| ۳ | Docker، Wings و اتصال Node | در حال انجام |
| ۴ | ادغام cs2-kus-addons و فایل‌های فعلی بازی با Egg/Container/Volume | هنوز شروع نشده |
| ۵ | دامنه، HTTPS، NGINX عمومی، firewall، روتر/VMware و دسترسی بیرونی | آینده؛ فعلاً انجام نشده |

### هشدارهای غیرقابل چشم‌پوشی

- محیط فعلی **LAN-only** است؛ دامنهٔ واقعی، HTTPS عمومی، Port Forwarding جدید و دسترسی عمومی تنظیم نشده‌اند.
- در آخرین بررسی، ریشهٔ دیسک 112 GiB، حدود 80 GiB مصرف و حدود 27 GiB فضای آزاد داشت.
- حدود 71 GiB در /home/steam شامل فایل‌های موجود CS2 و cs2-kus-addons است. **این مسیر را حذف، جابه‌جا، overwrite یا بدون طراحی mount نکن.** تا پایان فصل ۴، سرور CS2 جدید از Egg نساز؛ ممکن است دانلود بازی را تکرار کند و فضای دیسک را پر کند.
- هرگز رمز، APP_KEY، token یا محتوای کامل .env را در GitHub یا چت قرار نده.
- روی نصب فعلی فرمان‌های دارای برچسب «انجام‌شده» را بی‌دلیل تکرار نکن؛ مخصوصاً ساخت دیتابیس، تولید APP_KEY، migration و ساخت مدیر.
- یک ناسازگاری مستنداتی دربارهٔ نسخهٔ دقیق Panel باقی است: خروجی واقعی Artisan، Laravel Framework 12.64.0 را نشان داد و دستور p:environment:addons:run-hooks هم موجود بود؛ در سند تاریخی به archive v1.15.1 اشاره شده، اما این شواهد به‌طور قطعی یک نسخه را ثابت نمی‌کنند. پیش از انتخاب Wings باید نسخهٔ واقعی Panel با p:info و اسناد رسمی تطبیق داده شود.

## مراحل اجرا

### مرحله ۱ — بررسی Debian، شبکه و منابع

**وضعیت: انجام شده.**

~~~bash
set -u

echo "=== OS ==="
cat /etc/os-release | grep -E '^(PRETTY_NAME|VERSION_ID)='

echo
echo "=== VIRTUALIZATION / CPU ==="
systemd-detect-virt || true
uname -m
uname -r
lscpu | grep -E '^(Architecture|CPU\(s\)|Model name|Thread|Core|Socket)'

echo
echo "=== MEMORY / DISK ==="
free -h
df -hT /
df -ih /

echo
echo "=== NETWORK ==="
hostname -I
ip -br address
ip route
~~~

**نتیجهٔ واقعی:** Debian GNU/Linux 13 (Trixie)، VMware، x86_64/amd64، kernel 6.12.113+deb13-amd64، چهار vCPU، RAM حدود 5.6 GiB، IP برابر 192.168.1.5. DHCP Reservation تنظیم شده است.

### مرحله ۲ — نصب dependencyهای پایهٔ Panel

**وضعیت: انجام شده؛ روی ماشین فعلی دوباره نصب نکن مگر نیاز مشخصی باشد.**

~~~bash
sudo apt install -y \
  php8.3 php8.3-cli php8.3-common php8.3-gd php8.3-mysql \
  php8.3-mbstring php8.3-bcmath php8.3-xml php8.3-curl \
  php8.3-zip php8.3-fpm php8.3-intl php8.3-opcache \
  mariadb-server redis-server nginx \
  git curl tar unzip
~~~

این بسته‌ها runtime وب و دیتابیس را آماده می‌کنند: PHP برای کد پنل، PHP-FPM برای ارتباط NGINX با PHP، MariaDB برای داده‌های Panel، Redis برای queue/cache/session و ابزارهای curl/tar/unzip/git برای دریافت و مدیریت فایل‌ها.

### مرحله ۳ — بررسی افزونه‌های PHP و فایل‌های پنل

**وضعیت: بررسی انجام شده.**

~~~bash
set -eu

PANEL_DIR='/var/www/pterodactyl'

for module in bcmath curl dom gd intl json mbstring mysqli openssl PDO pdo_mysql posix tokenizer xml zip; do
    if php8.3 -m | grep -Eixq "$module"; then
        printf 'OK: %s\n' "$module"
    else
        printf 'MISSING: %s\n' "$module" >&2
        exit 1
    fi
done

echo
id www-data

for required_file in artisan .env.example composer.json public/index.php; do
    if ! sudo test -f "$PANEL_DIR/$required_file"; then
        echo "ERROR: missing $PANEL_DIR/$required_file" >&2
        exit 1
    fi
    echo "OK: $required_file"
done

echo
echo "=== SET PANEL OWNERSHIP ==="
sudo chown -R www-data:www-data "$PANEL_DIR"

echo
echo "=== SET RUNTIME DIRECTORY / FILE MODES ==="
sudo find "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache" -type d -exec chmod 750 {} +
sudo find "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache" -type f -exec chmod 640 {} +

sudo stat -c '%U:%G mode=%a %n' \
    "$PANEL_DIR" "$PANEL_DIR/artisan" \
    "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"

df -h /
~~~

### مرحله ۴ — Composer dependencies، .env و APP_KEY

**وضعیت: انجام شده. این بلوک را روی نصب فعلی دوباره اجرا نکن.**

~~~bash
set -eu
cd /var/www/pterodactyl

echo "=== PHP / COMPOSER ==="
php8.3 -v
composer --version
command -v composer

echo
echo "=== PROTECT EXISTING ENV ==="
if sudo test -e /var/www/pterodactyl/.env; then
    echo "STOP: .env already exists; do not overwrite it."
    exit 1
fi

sudo cp .env.example .env
sudo chown www-data:www-data .env
sudo chmod 640 .env

COMPOSER_BIN="$(command -v composer)"
sudo env COMPOSER_ALLOW_SUPERUSER=1 \
    php8.3 "$COMPOSER_BIN" install --no-dev --optimize-autoloader

sudo php8.3 artisan key:generate --force

if sudo grep -Eq '^APP_KEY=base64:.+' .env; then
    echo "OK: APP_KEY exists"
else
    echo "ERROR: APP_KEY missing" >&2
    exit 1
fi

sudo env COMPOSER_ALLOW_SUPERUSER=1 \
    php8.3 "$COMPOSER_BIN" check-platform-reqs --no-dev
~~~

Composer طبق composer.lock وابستگی‌های PHP را نصب می‌کند. APP_KEY کلید رمزنگاری برنامه است؛ یک بار برای نصب تازه ساخته می‌شود. آن را دوباره تولید نکن و مقدارش را در مستندات ننویس.

**نتیجهٔ واقعی:** Composer 2.10.3 نصب بود؛ dependencyها نصب شدند؛ Composer در ابتدا timeout از Packagist گزارش کرد اما نصب را ادامه داد؛ package discovery تکمیل شد و check-platform-reqs موفق بود.

### مرحله ۵ — بررسی MariaDB، Redis و PHP-FPM

**وضعیت: انجام شده.**

~~~bash
set -eu

echo "=== MARIADB ==="
sudo systemctl is-active mariadb
sudo mariadb -Nse 'SELECT VERSION();'

echo
echo "=== REDIS ==="
sudo systemctl is-active redis-server
redis-cli ping

echo
echo "=== PHP-FPM ==="
systemctl list-units --type=service --all 'php8.3-fpm.service' --no-legend

echo
echo "=== LISTENERS ==="
sudo ss -ltnup | grep -E ':(22|80|443|2022|3306|6379|8080)\b' || true
~~~

**نتیجهٔ واقعی:** MariaDB 11.8.6 active، Redis active و قبلاً PONG، PHP-FPM 8.3 active. MariaDB فقط روی 127.0.0.1:3306 و Redis روی loopback گوش می‌دادند؛ این وضعیت داخلی را حفظ کن.

### مرحله ۶ — بررسی و ساخت دیتابیس اختصاصی Panel

**وضعیت: دیتابیس نهایی درست شده و Panel از آن استفاده می‌کند. روی نصب فعلی CREATE DATABASE/CREATE USER را دوباره اجرا نکن.**

ابتدا بررسی فقط‌خواندنی:

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

برای نصب تازه، ورود محلی:

~~~bash
sudo env MYSQL_HISTFILE=/dev/null mariadb
~~~

داخل MariaDB، فقط اگر دیتابیس و user از قبل وجود ندارند، SQL زیر را اجرا کن. رمز جایگزین را قبل از اجرا به یک رمز قوی و منحصربه‌فرد تغییر بده:

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

در جلسه، یک بار تلاش با نام دیتابیس fc-node-01 به‌دلیل خط تیره خطای SQL داد؛ نام صحیح panel است. کاربر pterodactyl هم ابتدا با متن placeholder ساخته شد و باید رمز واقعی برایش تعیین می‌شد.

اگر user وجود دارد، برای تغییر رمز از ALTER USER استفاده کن، نه CREATE USER:

~~~sql
ALTER USER 'pterodactyl'@'127.0.0.1'
    IDENTIFIED BY 'REPLACE_WITH_YOUR_NEW_UNIQUE_PASSWORD';

GRANT ALL PRIVILEGES ON panel.*
    TO 'pterodactyl'@'127.0.0.1';

SHOW GRANTS FOR 'pterodactyl'@'127.0.0.1';
~~~

### مرحله ۷ — تفاوت root لینوکس و root دیتابیس

**وضعیت: بررسی آموزشی مطرح شد؛ برای دیدن plugin واقعی اجرا کن.**

~~~bash
sudo mariadb -e "SELECT User, Host, plugin FROM mysql.user WHERE User = 'root';"
~~~

Debian معمولاً اجازه می‌دهد مدیر سیستم با sudo mariadb وارد MariaDB شود، مثلاً از طریق unix_socket. این به معنی یکسان‌بودن رمز حساب Linux و MariaDB نیست. حساب Linux، حساب MariaDB root و حساب pterodactyl را جدا در نظر بگیر. Panel از pterodactyl استفاده می‌کند، نه root.

### مرحله ۸ — اصلاح خطای نوشتن Cache

**وضعیت: انجام شد و آزمون موفق بود.**

خطایی که واقعی رخ داد:

~~~text
file_put_contents(.../storage/framework/cache/data/...):
Failed to open stream: Permission denied
~~~

فرمان اصلاح‌شده:

~~~bash
set -eu
cd /var/www/pterodactyl

sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 750 {} +
sudo find storage bootstrap/cache -type f -exec chmod 640 {} +

sudo -u www-data sh -c \
  'touch storage/framework/cache/data/.fragcity-permission-test &&
   rm storage/framework/cache/data/.fragcity-permission-test &&
   echo "OK: www-data can write to Laravel cache"'
~~~

**نتیجهٔ واقعی:** OK: www-data can write to Laravel cache.

chown مالکیت را تغییر می‌دهد؛ chmod سطح دسترسی را. 750 برای پوشه‌ها و 640 برای فایل‌ها استفاده شد. از 777 استفاده نکن.

### مرحله ۹ — تنظیم محیط Panel

**وضعیت: تکمیل شده. این wizard را روی نصب موجود دوباره اجرا نکن مگر قصد تغییر تنظیم مشخصی داشته باشی.**

~~~bash
cd /var/www/pterodactyl
sudo -u www-data php8.3 artisan p:environment:setup
~~~

مقادیر واردشده در جلسه:

| سؤال | پاسخ |
|---|---|
| Egg Author Email | ایمیل معتبر در ترمینال وارد شد؛ در Git تکرار نمی‌شود |
| Application URL | http://192.168.1.5 |
| Timezone | Asia/Tehran |
| Cache | redis |
| Session | redis |
| Queue | redis |
| UI settings editor | yes |
| Anonymous telemetry | no |
| Redis Host | 127.0.0.1 |
| Redis Password | خالی |
| Redis Port | 6379 |

اجرای اول با Ctrl+C لغو شد؛ سپس خطای Cache رخ داد؛ پس از اصلاح مرحلهٔ ۸، wizard کامل شد.

بررسی تنظیمات بدون نمایش secret:

~~~bash
cd /var/www/pterodactyl
sudo grep -E \
'^(APP_ENV|APP_URL|APP_DEBUG|CACHE_DRIVER|CACHE_STORE|SESSION_DRIVER|QUEUE_CONNECTION|REDIS_HOST|DB_CONNECTION|DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME)=' \
.env || true
~~~

### مرحله ۱۰ — تنظیم اتصال دیتابیس از طریق wizard

**وضعیت: تکمیل شده.**

~~~bash
cd /var/www/pterodactyl
sudo -u www-data php8.3 artisan p:environment:database
~~~

مقادیر واقعی: host برابر 127.0.0.1، port برابر 3306، نام panel، user برابر pterodactyl و رمز همان رمز واقعی دیتابیس که تعاملی وارد شد. رمز در این سند ذخیره نمی‌شود.

### مرحله ۱۱ — ساخت جدول‌ها و حساب مدیر

**وضعیت: هر دو فرمان موفق شدند. دوباره اجرا نکن.**

~~~bash
cd /var/www/pterodactyl
sudo -u www-data php8.3 artisan migrate --seed --force
~~~

نتیجه: جدول migration ساخته شد، schema بارگذاری شد، migration اجرا شد و NestSeeder/EggSeeder با موفقیت پایان یافتند.

~~~bash
cd /var/www/pterodactyl
sudo -u www-data php8.3 artisan p:user:make
~~~

حساب مدیر ساخته شد و خروجی Admin: Yes را نشان داد. اطلاعات شخصی و رمز در این سند نگهداری نمی‌شوند.

### مرحله ۱۲ — بررسی Eggهای Counter-Strike

**وضعیت: فقط CS:GO در دیتابیس دیده شد.**

~~~bash
sudo mariadb panel -e "
SELECT id, name, nest_id
FROM eggs
WHERE LOWER(name) LIKE '%counter%strike%'
   OR LOWER(name) LIKE '%cs2%'
ORDER BY name;
"
~~~

نتیجهٔ واقعی: ID برابر 11، نام Counter-Strike: Global Offensive، Nest ID برابر 2. Egg اختصاصی CS2 هنوز اضافه/تأیید نشده است.

### مرحله ۱۳ — بررسی و تنظیم NGINX

**وضعیت: انجام شده؛ ورود از Windows موفق است.**

بررسی اولیه:

~~~bash
set -eu
cd /var/www/pterodactyl

if command -v nginx >/dev/null 2>&1; then
    nginx -v 2>&1
else
    echo "nginx not found in user's PATH; check the full binary path"
fi

sudo systemctl is-active nginx
sudo ls -la /etc/nginx/sites-available/
sudo ls -la /etc/nginx/sites-enabled/
sudo test -S /run/php/php8.3-fpm.sock && echo "PHP-FPM socket exists"
sudo ss -ltnp | grep -E ':(80|443)\b' || true
sudo test -f public/index.php && echo "Panel entry file exists"
sudo /usr/sbin/nginx -v
~~~

در خروجی اول nginx در PATH کاربر پیدا نشد، اما service active و پورت 80 در حال listen بود؛ مسیر کامل /usr/sbin/nginx نسخهٔ 1.26.3 را تأیید کرد.

پیکربندی LAN ثبت‌شده در /etc/nginx/sites-available/pterodactyl.conf:

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

فعال‌سازی و تست:

~~~bash
set -eu

if sudo test -e /etc/nginx/sites-enabled/pterodactyl.conf || sudo test -L /etc/nginx/sites-enabled/pterodactyl.conf; then
    echo "Panel site link already exists; inspect it rather than recreating."
else
    sudo ln -s /etc/nginx/sites-available/pterodactyl.conf /etc/nginx/sites-enabled/pterodactyl.conf
fi

if sudo test -L /etc/nginx/sites-enabled/default; then
    sudo rm /etc/nginx/sites-enabled/default
    echo "Default site symlink disabled; original config retained."
fi

sudo /usr/sbin/nginx -t
sudo systemctl reload nginx
sudo systemctl is-active nginx
curl -sS -o /dev/null -w 'HTTP status: %{http_code}\n' -H 'Host: 192.168.1.5' http://127.0.0.1/
~~~

نتیجهٔ واقعی: کاربر تأیید کرد پنل در Windows باز شد و login موفق بود. این فقط LAN/HTTP است و HTTPS یا دسترسی عمومی نیست.

### مرحله ۱۴ — اتصال Windows به Debian و Browse فایل‌ها

SSH واقعی از PowerShell موفق بود:

~~~powershell
ssh fragcityadmin@192.168.1.5
~~~

WinSCP با SFTP برای Browse فایل‌ها پیشنهاد شد:
- Protocol: SFTP
- Host: 192.168.1.5
- Port: 22
- User: fragcityadmin
- Password: رمز حساب Linux

برای HeidiSQL، روش SSH tunnel پیشنهاد شد؛ **ورود نهایی HeidiSQL در خروجی گفتگو تأیید نشده بود**:

~~~powershell
ssh -v -N -L 3307:127.0.0.1:3306 fragcityadmin@192.168.1.5
~~~

در حالی که پنجره PowerShell باز است، HeidiSQL از نوع MariaDB/MySQL TCP/IP با host 127.0.0.1، port 3307، user pterodactyl و رمز دیتابیس وصل می‌شود. این تونل پورت محلی ویندوز را به MariaDB در loopback Debian می‌رساند؛ نیازی به بازکردن پورت 3306 در روتر نیست. در HeidiSQL داخلی SSH Tunnel نیز host/port دیتابیس را 127.0.0.1:3306 بگذار و بخش SSH را جدا تنظیم کن.

### مرحله ۱۵ — بررسی فضای دیسک

**وضعیت: انجام شده.**

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

نتیجهٔ ثبت‌شده: root 112 GiB، حدود 80 GiB مصرف و 27 GiB آزاد؛ /home/steam حدود 71 GiB. APT cache حدود 197 MiB، journal حدود 52.7 MiB و /var/log حدود 71 MiB بودند.

### مرحله ۱۶ — Docker preflight

**وضعیت: انجام شده؛ Docker CLI/service نصب نبود و مخزن رسمی Docker وجود نداشت.**

~~~bash
set -eu

. /etc/os-release
printf 'OS: %s (%s)\n' "$PRETTY_NAME" "$VERSION_CODENAME"
printf 'Architecture: %s\n' "$(dpkg --print-architecture)"

for package in docker.io docker-compose docker-doc docker-buildx podman-docker containerd runc; do
    status="$(dpkg-query -W -f='${db:Status-Status}' "$package" 2>/dev/null || true)"
    if [ "$status" = installed ]; then
        printf 'INSTALLED: %s\n' "$package"
    fi
done

if sudo test -f /etc/apt/sources.list.d/docker.sources; then
    sudo cat /etc/apt/sources.list.d/docker.sources
else
    echo "Official Docker repository file not found yet."
fi

df -h /
~~~

نتیجه: Debian 13/Trixie، معماری amd64؛ هیچ‌یک از بسته‌های متعارض بررسی‌شده نصب نبودند و docker.sources وجود نداشت.

### مرحله ۱۷ — نصب Docker Engine

**وضعیت: مخزن/بسته‌ها نصب شده‌اند؛ سرویس و Engine هنوز تأیید نشده‌اند.**

Docker در این معماری محیط‌های container را اجرا می‌کند. این مرحله در ۱۸ اکتبر ۲۰۲۶ با [راهنمای رسمی Docker برای Debian](https://docs.docker.com/engine/install/debian/) تطبیق داده شد: Debian 13/Trixie و معماری amd64 پشتیبانی می‌شوند و روش مخزن APT رسمی انتخاب شده است.

**هشدار شبکه:** Docker هنگام انتشار پورت containerها می‌تواند با قواعد firewall/NAT کار کند و در برخی پیکربندی‌ها پورت‌های published از قواعد UFW/firewalld عبور کنند. در این مرحله هیچ پورت بازی یا Port Forwarding عمومی باز نمی‌کنیم؛ رفتار firewall و زنجیرهٔ `DOCKER-USER` در فصل شبکه بررسی می‌شود.

~~~bash
set -eu

sudo apt update
sudo apt install -y ca-certificates curl
sudo install -m 0755 -d /etc/apt/keyrings

if ! sudo test -s /etc/apt/keyrings/docker.asc; then
    sudo curl -fsSL https://download.docker.com/linux/debian/gpg -o /etc/apt/keyrings/docker.asc
fi
sudo chmod a+r /etc/apt/keyrings/docker.asc

if sudo test -e /etc/apt/sources.list.d/docker.sources; then
    echo "STOP: docker.sources already exists; inspect it before proceeding."
    sudo cat /etc/apt/sources.list.d/docker.sources
    exit 1
fi

. /etc/os-release
sudo tee /etc/apt/sources.list.d/docker.sources >/dev/null <<EOF
Types: deb
URIs: https://download.docker.com/linux/debian
Suites: $VERSION_CODENAME
Components: stable
Architectures: $(dpkg --print-architecture)
Signed-By: /etc/apt/keyrings/docker.asc
EOF

sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo systemctl enable --now docker
~~~

خلاصهٔ فرمان‌ها: apt update فهرست بسته‌ها را به‌روزرسانی می‌کند؛ docker.asc کلید عمومی برای اعتبارسنجی امضای repository است؛ docker.sources محل و suite بسته‌ها و کلید امضا را مشخص می‌کند؛ Docker Engine/CLI/containerd سپس نصب می‌شوند؛ systemctl سرویس را آغاز و برای بوت بعدی فعال می‌کند.

اگر docker.sources از قبل وجود داشت، به‌جای overwrite کردن بررسی‌اش کن. فعلاً پورت عمومی یا Port Forwarding جدید باز نکن و کاربر fragcityadmin را به گروه docker اضافه نکن؛ از sudo docker استفاده کن.

### عیب‌یابی مرحله ۱۷ — خطای HTTP 403 هنگام دریافت Docker

**تاریخچه و نتیجهٔ بازآزمایی:** در تلاش قبلی HTTP 403 گزارش شد. در تست بعدی هر دو URL رسمی (`/linux/debian/gpg` و `dists/trixie/InRelease`) مقدار HTTP=200 دادند و dpkg-query تمام پنج بستهٔ Docker را installed گزارش کرد؛ بنابراین 403 در تست اخیر تکرار نشد. این نتیجه فقط دریافت مخزن و نصب بسته‌ها را نشان می‌دهد و سلامت daemon را ثابت نمی‌کند. VMDK را برای حل 403 از نو نساز.

**پیش از عیب‌یابی:** در VMware از VM یک snapshot/backup امن بگیر؛ برای backup مستقل و سازگارتر، Debian را به‌صورت عادی خاموش کن و سپس پوشهٔ VM را کپی کن. Snapshot جای backup مستقل را نمی‌گیرد. هیچ فایلی را از `/home/steam` پاک یا جابه‌جا نکن و کلید TLS را غیرفعال نکن.

این بلوک فقط اتصال و وضعیت فایل‌های تنظیمات را بررسی می‌کند؛ چیزی نصب، حذف یا تغییر نمی‌دهد:

~~~bash
set -u

echo "=== TIME ==="
date -u

echo
echo "=== DOCKER OFFICIAL ENDPOINTS (GET, NOT HEAD) ==="
for url in \
    "https://download.docker.com/linux/debian/gpg" \
    "https://download.docker.com/linux/debian/dists/trixie/InRelease"
do
    echo
    printf 'URL: %s\n' "$url"
    curl -sS -L --connect-timeout 10 --max-time 30 \
        -o /dev/null \
        -w 'HTTP=%{http_code} remote_ip=%{remote_ip} total_seconds=%{time_total}\n' \
        "$url"
    result=$?
    if [ "$result" -ne 0 ]; then
        printf 'curl_exit_code=%s\n' "$result"
    fi
done

echo
echo "=== DOCKER KEY FILE ==="
if sudo test -e /etc/apt/keyrings/docker.asc; then
    sudo stat -c 'size=%s bytes owner=%U:%G mode=%a path=%n' \
        /etc/apt/keyrings/docker.asc
else
    echo "No docker.asc file exists."
fi

echo
echo "=== DOCKER APT SOURCE ==="
if sudo test -f /etc/apt/sources.list.d/docker.sources; then
    sudo cat /etc/apt/sources.list.d/docker.sources
else
    echo "No docker.sources file exists."
fi

echo
echo "=== DOCKER PACKAGE STATUS ==="
for package in docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin; do
    dpkg-query -W -f='${db:Status-Status} ${Version}\n' "$package" 2>/dev/null \
        || echo "not installed: $package"
done
~~~

**نحوهٔ تفسیر نتیجه:**

- اگر endpoint کلید یا `InRelease` مقدار `HTTP=403` بدهد، همان URL توسط سرور مقصد یا یک واسطهٔ شبکه رد شده است. متن خطای اصلی، زمان اجرا، DNS/مسیر شبکه و هر proxy تنظیم‌شده را بررسی می‌کنیم؛ از mirror ناشناس یا خاموش‌کردن اعتبارسنجی TLS استفاده نکن.
- اگر هر دو `HTTP=200` بدهند، دسترسی فعلی به endpointها برقرار است؛ خطای قبلی ممکن است موقتی باشد یا در مرحلهٔ دیگری مثل `apt update` رخ داده باشد. خروجی اصلی خطا برای تعیین دقیق لازم است.
- اگر خطا timeout/TLS/DNS باشد و HTTP code نداشته باشد، این خطای 403 نیست و باید همان خطای شبکه بررسی شود.
- پس از بررسی، فقط اگر فایل repository و کلید درست باشند، مرحلهٔ نصب را ادامه بده. اگر `docker.sources` وجود دارد، آن را کورکورانه بازنویسی نکن.

~~~

### مرحله ۱۸ — تأیید Docker پس از نصب

**وضعیت: انجام شد؛ نصب و اجرای container آزمایشی موفق بود.**

خروجی واقعی کاربر:
- `Docker version 29.9.1, build dfc520d`
- `systemctl is-active docker`: `ACTIVE`
- `docker info`: `Server=29.9.1 Containers=0 Images=0` (این نمونه‌برداری قبل از pull شدن `hello-world` بود)
- `docker run --rm hello-world`: image برای معماری amd64 دریافت شد و پیام `Hello from Docker!` نمایش داده شد.
- فضای root پس از تست: 112 GiB کل، 80 GiB مصرف‌شده، 27 GiB آزاد.
- `/var/lib/docker` در خروجی `du` برابر 232 KiB گزارش شد؛ این فقط اندازهٔ مسیر گزارش‌شده در همان لحظه است و دلیل پاک‌کردن یا دستکاری storage نیست.
- خطای 403 قبلی در تست endpointها تکرار نشد؛ هر دو URL رسمی HTTP=200 داده بودند.

Docker Engine اکنون آماده است و مرحلهٔ نصب را تکرار نکن. تست `hello-world` ثابت می‌کند CLI، daemon، دریافت image و اجرای یک container ساده کار کرده‌اند؛ هنوز ثابت نمی‌کند Wings وصل شده یا CS2 قابل اجراست.

### مرحلهٔ ۱۹ — شناسایی نسخهٔ دقیق Panel پیش از نصب Wings

**وضعیت: قدم بعدی فعلی؛ Wings را هنوز نصب نکن.** نسخهٔ دقیق Panel باید از خود برنامه خوانده شود؛ نسخهٔ Laravel به‌تنهایی نسخهٔ Pterodactyl را مشخص نمی‌کند. دستور رسمی `p:info` نسخهٔ Panel و جزئیات تنظیمات را نشان می‌دهد. [مستندات رسمی Artisan](https://docs.pterodactyl.io/v2/guides/tutorials/artisan).

در Debian اجرا کن:

~~~bash
cd /var/www/pterodactyl
sudo -u www-data php8.3 artisan p:info
~~~

این فرمان فقط اطلاعات می‌خواند. خروجی کامل را قبل از ارسال مرور کن و هر مقدار حساس احتمالی را حذف کن؛ هیچ رمز، token، APP_KEY یا محتوای کامل `.env` را نفرست.

بعد از مشاهدهٔ نسخه:
1. شاخهٔ رسمی Panel (پایدار 1.x یا پیش‌انتشار 2.0) را مشخص می‌کنیم.
2. نسخهٔ Wings سازگار را از مستندات همان شاخه انتخاب می‌کنیم؛ به `latest` کورکورانه تکیه نمی‌کنیم.
3. سپس در Admin UI، Location و Node مخصوص `fc-node-01` را ایجاد می‌کنیم و config محرمانه را از تب Configuration دریافت می‌کنیم.
4. Wings را foreground/debug آزمایش می‌کنیم و تنها پس از موفقیت، systemd service می‌سازیم.

مرجع نسخه‌ها:
- [Pterodactyl 2.0 docs — هنوز Pre-release](https://docs.pterodactyl.io/v2)
- [Installing Wings — نسخهٔ 2.0](https://docs.pterodactyl.io/v2/wings/installing)
- [Installing Wings — شاخهٔ پایدار 1.x](https://docs.pterodactyl.io/v1/wings/installing)
- [Panel releases](https://github.com/pterodactyl/panel/releases)
- [Wings releases](https://github.com/pterodactyl/wings/releases)

### مرحله ۱۹ — قبل از نصب Wings

**وضعیت: Pending.** اول نسخهٔ دقیق Panel را تأیید کن:

~~~bash
cd /var/www/pterodactyl
sudo -u www-data php8.3 artisan p:info
~~~

پیش از ارسال خروجی، password یا secret را حذف کن. نسخهٔ Laravel 12.64.0 که قبلاً دیدیم به‌تنهایی نسخهٔ دقیق Panel را ثابت نمی‌کند. آن را با release رسمی تطبیق بده و سپس Wings سازگار انتخاب کن:

- Panel releases: https://github.com/pterodactyl/panel/releases
- Wings releases: https://github.com/pterodactyl/wings/releases
- Wings install docs: https://docs.pterodactyl.io/v1/wings/installing

پس از آن، از Admin UI یک Location و Node بساز، config همان Node را از Panel دریافت کن و token/secret آن را در Git یا چت قرار نده. بعد باینری Linux/amd64 نسخهٔ سازگار را نصب و طبق راهنمای همان Release ابتدا foreground/debug و سپس systemd را پیکربندی کن. Wings هنوز روی این VM نصب نشده است.

### مرحله ۲۰ — مراحل بعد از Wings

1. Wings باید از Panel Online دیده شود.
2. Allocationهای واقعی و پورت‌های موردنیاز Egg تنظیم شوند.
3. Egg اختصاصی CS2 از منبع معتبر وارد و تأیید شود.
4. پیش از ایجاد سرور CS2، فصل ۴ باید روش امن استفادهٔ مجدد از فایل‌های بازی در /home/steam را طراحی و تست کند.
5. فصل ۵ باید DNS/دامنه، HTTPS، NGINX عمومی، firewall، قوانین Docker/iptables، VMware bridged networking، روتر و Port Forwarding را جداگانه کامل کند.

### چک‌لیست وضعیت

- [x] Debian 13/VMware/IP/SSH بررسی شده
- [x] PHP 8.3 و Composer 2 بررسی شده
- [x] MariaDB/Redis فعال و DB اختصاصی Panel تنظیم شده
- [x] .env/APP_KEY/Composer dependencies آماده‌اند
- [x] Cache permission رفع و write test موفق شده
- [x] Setup wizard و database wizard تکمیل شده‌اند
- [x] Migrations/seeds و حساب مدیر ساخته شده‌اند
- [x] NGINX تنظیم شده و ورود LAN موفق است
- [x] Egg query اجرا شد؛ فقط CS:GO پیدا شد
- [x] فضای دیسک بررسی و /home/steam مشخصاً برای حفظ علامت‌گذاری شد
- [x] Docker preflight انجام شد
- [x] Docker Engine نصب و سرویس فعال شد
- [x] docker info و hello-world موفق شدند
- [ ] نسخهٔ دقیق Panel تأیید و Wings سازگار انتخاب شود
- [ ] Node ساخته، Wings نصب و سرویس فعال شود
- [ ] Wings در Panel Online شود
- [ ] Egg اختصاصی CS2 اضافه شود
- [ ] فصل ۴ ادغام CS2/KUS انجام شود
- [ ] فصل ۵ دسترسی عمومی/HTTPS انجام شود

این سند راهنمای نصب و وضعیت است؛ نصب واقعی Docker یا Wings تا زمانی که خروجی فرمان‌ها ثبت نشود موفق محسوب نمی‌شود.

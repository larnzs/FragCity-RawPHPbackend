# FragCity — نصب واقعی Pterodactyl Panel در LAN

**مرجع اجرایی واحد و ترتیب Copy/Paste:** [راهنمای مادر FragCity](FRAGCITY-MASTER-COPY-PASTE-RUNBOOK.md). این سند برای توضیحات تفصیلی Panel و سابقهٔ تنظیمات نگه داشته شده است؛ برای اجرای مرحله‌ای از روی فایل مادر جلو برو.


**وضعیت:** نصب پنل و ورود از مرورگر Windows موفق شده است. این سند هم مسیر تکرارپذیر را توضیح می‌دهد و هم وضعیت واقعی نصب فعلی را ثبت می‌کند.  
**محدوده:** آزمایش LAN روی Debian 13 در VMware؛ هنوز Production نیست.  
**آدرس پنل فعلی:** `http://192.168.1.5`  
**مسیر پنل:** `/var/www/pterodactyl`

> این راهنما برای آموزش است. روی نصب موجود، مرحله‌های ساخت دیتابیس، کپی `.env`، تولید `APP_KEY`، migration یا ساخت مدیر را کورکورانه دوباره اجرا نکن. آن‌ها در ماشین فعلی انجام شده‌اند.

## 1. معماری و مرز این فصل

- **Panel** وب‌اپلیکیشن Laravel است که از مرورگر مدیریت می‌شود.
- **MariaDB** دیتابیس داخلی خود Panel است. برای Panel یک دیتابیس و حساب جدا ایجاد شده است؛ از حساب DB با دسترسی root استفاده نمی‌کنیم.
- **Redis** برای cache/session/queue انتخاب شده است. Panel فعلاً به Redis از طریق loopback وصل می‌شود.
- **NGINX + PHP-FPM** درخواست HTTP را به کد PHP/Laravel تحویل می‌دهند.
- **Wings و Docker** نصب Panel نیستند؛ این دو جزء در فصل جداگانهٔ Wings قرار دارند و هنوز روی این VM نصب نشده‌اند.
- همه‌چیز فعلاً فقط برای LAN است. دامنهٔ Production، TLS عمومی، port forwarding و firewall اینترنتی در فصل شبکه انجام می‌شوند.

## 2. وضعیت واقعی ماشین در ۱۸ اکتبر ۲۰۲۶

| آیتم | وضعیت تأییدشده |
|---|---|
| Hostname | `fc-node-01` |
| OS | Debian GNU/Linux 13 (Trixie) |
| Virtualization | VMware |
| معماری | `x86_64` / `amd64` |
| CPU | 4 vCPU در VM |
| RAM | حدود 5.6 GiB |
| Root disk | 112 GiB؛ حدود 80 GiB مصرف‌شده و 27 GiB آزاد در آخرین snapshot |
| PHP CLI | 8.3.35 |
| Composer | 2.10.3 در `/usr/local/bin/composer` |
| MariaDB | 11.8.6، سرویس active |
| Redis | سرویس active؛ بررسی اتصال در مقطعی `PONG` برگرداند |
| PHP-FPM | سرویس `php8.3-fpm` active |
| NGINX | سرویس active؛ سایت Panel از پورت 80 در LAN باز شده است |
| Panel | `/var/www/pterodactyl`; صفحهٔ Login از Windows باز و ورود مدیر موفق شد |
| دیتابیس Panel | `panel` |
| DB user | `pterodactyl`، متصل از `127.0.0.1` |
| Migration/seed | موفق |
| حساب مدیر | ایجاد شده؛ رمز در این سند ذخیره نمی‌شود |
| Docker/Wings | Docker 29.9.1 نصب و تأیید شده؛ Wings هنوز نصب/متصل نشده |
| دامنه/TLS عمومی | هنوز تنظیم نشده |

### هشدار نسخه

خروجی واقعی `sudo -u www-data php8.3 artisan p:info` نسخهٔ Panel را **1.15.1** گزارش کرد. خروجی `Latest Version: error` دریافت Latest را ناموفق نشان داد، اما `Up-to-Date: Yes` هم گزارش شد. برای انتخاب Wings، راهنمای رسمی شاخهٔ 1.x و Releaseهای رسمی Wings ملاک‌اند؛ فرمان جاری در [راهنمای مادر](FRAGCITY-MASTER-COPY-PASTE-RUNBOOK.md) ثبت شده است.

## 3. پیش‌نیازها و نقش بسته‌ها

- PHP CLI و extensionهای لازم، اجرای فرمان‌های Laravel را فراهم می‌کنند.
- PHP-FPM کد PHP را برای NGINX اجرا می‌کند.
- Composer dependencyهای PHP را طبق `composer.lock` نصب می‌کند.
- MariaDB داده‌های Panel، کاربران و تنظیمات را نگه می‌دارد.
- Redis برای صف‌ها و cache/session طبق تنظیم wizard استفاده می‌شود.
- NGINX درخواست HTTP را دریافت می‌کند و مسیرهای PHP را به PHP-FPM می‌فرستد.

نسخهٔ دقیق و نیازمندی‌های Panel را از راهنمای Release همان نسخه بررسی کن: <https://docs.pterodactyl.io/>.

## 4. ترتیب Quick Setup برای نصب تازه

این توالی برای بازسازی از صفر است؛ روی نصب فعلی فقط گام‌های لازم را با توجه به وضعیت اجرا کن.

### 4.1 بررسی سیستم‌عامل و منابع

روی Debian اجرا شود، نه PowerShell:

```bash
cat /etc/os-release
uname -m
systemd-detect-virt || true
free -h
df -h /
hostname -I
```

**چرا؟** نسخهٔ Debian، معماری، منابع و IP را پیش از انتخاب بسته‌ها یا تنظیم Node تأیید می‌کنیم.

### 4.2 نصب و بررسی سرویس‌های پایه

این ماشین قبلاً PHP 8.3، extensionهای منتخب، NGINX، MariaDB، Redis، Git، curl و ابزار archive را دریافت کرده است. وضعیت فعلی را بررسی کن:

```bash
php8.3 -v
/usr/local/bin/composer --version
sudo systemctl is-active nginx mariadb redis-server php8.3-fpm
sudo ss -ltnp | grep -E ':(22|80|443|3306|6379)\\b' || true
```

در setup فعلی MariaDB روی `127.0.0.1:3306` و Redis روی loopback گوش می‌دادند. این سرویس‌ها را برای راحتی به اینترنت یا LAN باز نکن.

### 4.3 مالکیت و مجوزهای فایل‌ها

Panel باید مالکیت درست داشته باشد و مسیرهای runtime برای حساب سرویس `www-data` قابل‌نوشتن باشند. در نصب فعلی، Composer باعث شد آزمون نوشتن cache با خطای permission روبه‌رو شود. این اصلاح موفق بود:

```bash
cd /var/www/pterodactyl

sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 750 {} +
sudo find storage bootstrap/cache -type f -exec chmod 640 {} +

sudo -u www-data sh -c \
  'touch storage/framework/cache/data/.fragcity-permission-test &&
   rm storage/framework/cache/data/.fragcity-permission-test &&
   echo "OK: cache write access"'
```

**معنی دستورات:**
- `chown` مالک و گروه فایل‌ها را عوض می‌کند.
- `chmod 750` به مالک اجازهٔ خواندن/نوشتن/ورود به پوشه و به گروه اجازهٔ خواندن/ورود می‌دهد؛ دیگران دسترسی ندارند.
- `chmod 640` به مالک خواندن/نوشتن و به گروه فقط خواندن می‌دهد.
- آزمون `touch` نوشتن واقعی حساب `www-data` را بررسی می‌کند.

از `chmod 777` استفاده نکن. برای نصب تازه، مالکیت ریشهٔ Panel و مجوز پوشه‌های runtime را طبق راهنمای Release انتخاب‌شده تنظیم کن.

### 4.4 فایل محیطی و dependencyها

فقط در نصب تازه که `.env` وجود ندارد:

```bash
cd /var/www/pterodactyl
sudo cp .env.example .env
sudo chown www-data:www-data .env
sudo chmod 640 .env
sudo -u www-data /usr/local/bin/composer install --no-dev --optimize-autoloader
sudo -u www-data php8.3 artisan key:generate
```

**هشدار:** روی سیستم موجود `.env` را overwrite نکن. `APP_KEY` را فقط هنگام نصب تازه و یک‌بار تولید کن؛ تولید دوبارهٔ آن می‌تواند داده‌های رمزگذاری‌شدهٔ قبلی را غیرقابل‌استفاده کند. فایل `.env` و مقدار APP_KEY هرگز به Git یا چت ارسال نمی‌شوند.

### 4.5 ساخت دیتابیس/کاربر در MariaDB

در نصب تازه ابتدا بررسی کن دیتابیس و حساب موردنظر از قبل وجود نداشته باشند. برای ورود محلی مدیریتی:

```bash
sudo env MYSQL_HISTFILE=/dev/null mariadb
```

سپس داخل prompt مربوط به MariaDB، یک رمز قوی و منحصربه‌فرد به‌جای متن نمونه وارد کن:

```sql
CREATE DATABASE panel
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER 'pterodactyl'@'127.0.0.1'
    IDENTIFIED BY 'REPLACE_WITH_A_UNIQUE_PASSWORD';

GRANT ALL PRIVILEGES ON panel.*
    TO 'pterodactyl'@'127.0.0.1';

SHOW GRANTS FOR 'pterodactyl'@'127.0.0.1';
EXIT;
```

اگر دیتابیس یا کاربر از قبل وجود دارد، این دستورات را بدون بررسی دوباره اجرا نکن. رمز کاربر را با `ALTER USER` تغییر می‌دهند؛ مقدار واقعی رمز نباید در لاگ یا Git ثبت شود.

### 4.6 اجرای wizardهای محیط

```bash
cd /var/www/pterodactyl
sudo -u www-data php8.3 artisan p:environment:setup
sudo -u www-data php8.3 artisan p:environment:database
```

مقادیر LAN فعلی:

| سؤال | مقدار |
|---|---|
| Application URL | `http://192.168.1.5` |
| Timezone | `Asia/Tehran` |
| Cache | `redis` |
| Session | `redis` |
| Queue | `redis` |
| Redis Host | `127.0.0.1` |
| Redis Port | `6379` |
| DB Host | `127.0.0.1` |
| DB Port | `3306` |
| DB Name | `panel` |
| DB User | `pterodactyl` |

Redis Password در wizard ثبت‌شده خالی گذاشته شد. اگر Redis را واقعاً با احراز هویت پیکربندی کردی، رمز/تنظیمات سرویس و Panel باید با هم تطبیق داده شوند. واردکردن رمز در wizard به‌تنهایی Redis را رمزدار نمی‌کند.

برای تست LAN از IP Debian استفاده می‌کنیم. `localhost` در مرورگر Windows به خود Windows اشاره دارد؛ برای دسترسی به این VM آدرس `http://192.168.1.5` است.

### 4.7 ساخت schema، seed و مدیر Panel

این مراحل در نصب فعلی موفق شدند:

```bash
cd /var/www/pterodactyl
sudo -u www-data php8.3 artisan migrate --seed --force
sudo -u www-data php8.3 artisan p:user:make
```

- `migrate --seed --force` جدول‌ها، migrationها و داده‌های پیش‌فرض Nest/Egg را می‌سازد.
- `p:user:make` حساب Panel می‌سازد؛ مدیر باید با دسترسی Administrator مشخص شود.
- این دو فرمان برای ثبت وضعیت کافی‌اند؛ روی نصب فعلی دوباره اجرا نکن مگر با دلیل مشخص.

Egg موجود در بررسی SQL فعلی فقط `Counter-Strike: Global Offensive` بود؛ Egg اختصاصی CS2 هنوز تأیید نشده است.

### 4.8 تنظیم NGINX

Document root باید پوشهٔ `public` پروژه باشد؛ فایل‌های ریشه، `.env` و `vendor` نباید مستقیماً از وب قابل دریافت باشند.

مقادیر تأییدشده در پیکربندی LAN:

- Config: `/etc/nginx/sites-available/pterodactyl.conf`
- Enabled symlink: `/etc/nginx/sites-enabled/pterodactyl.conf`
- Web root: `/var/www/pterodactyl/public`
- PHP-FPM socket: `/run/php/php8.3-fpm.sock`
- Host: `192.168.1.5`
- HTTP/TCP: `80`

قبل از reload، همیشه syntax را بررسی کن:

```bash
sudo /usr/sbin/nginx -t
sudo systemctl reload nginx
sudo systemctl is-active nginx
```

نتیجهٔ واقعی: پنل از مرورگر Windows در `http://192.168.1.5` باز شد و login موفق بود. این به معنی آماده‌بودن HTTPS یا دسترسی عمومی نیست.

## 5. وضعیت کارهای تکمیل‌شده و باقی‌مانده

### انجام‌شده و تأییدشده

- PHP 8.3.35 و Composer 2.10.3.
- MariaDB و Redis فعال.
- ساخت `.env` و تولید APP_KEY.
- اصلاح مجوزهای Cache پس از مشاهدهٔ خطای `Permission denied`.
- تنظیم محیط و دیتابیس از طریق Artisan.
- Migration/seed و ایجاد حساب مدیر.
- پیکربندی NGINX و ورود موفق از Windows در LAN.
- بررسی Eggها؛ Egg اختصاصی CS2 فعلاً وجود ندارد.

### هنوز انجام‌نشده یا نیازمند بررسی جداگانه

- ثبت قطعی Release/commit دقیق Panel؛ شواهد Laravel 12.64.0 هستند، اما version/tag نیاز به تطبیق دارد.
- Queue Worker و Scheduler/Cron را مطابق مستندات Release نصب‌شده بررسی و تکمیل کن.
- Docker و Wings؛ به سند [Wings Install](WINGS-INSTALL.md) برو.
- Egg اختصاصی CS2 و اولین server container.
- طراحی و آزمایش استفادهٔ مجدد از فایل‌های فعلی `/home/steam`.
- HTTPS، دامنه و دسترسی WAN؛ فقط در فصل شبکهٔ آینده.

## 6. محدودیت فضای دیسک و حفاظت KUS

آخرین snapshot: root filesystem برابر 112 GiB و فضای آزاد حدود 27 GiB است. حدود 71 GiB در `/home/steam` مصرف می‌شود؛ کاربر تأیید کرده این فایل‌ها شامل بازی CS2 و پروژهٔ `cs2-kus-addons` هستند.

**این فایل‌ها را حذف، جابه‌جا یا overwrite نکن.** Docker آن‌ها را خودکار به containerهای Pterodactyl متصل نمی‌کند. تا وقتی طراحی storage/shared base در فصل ۴ انجام و آزمایش نشده، یک CS2 instance تازه از Egg اجرا نکن؛ ممکن است بازی را دوباره دانلود کرده و فضای دیسک پر شود.

## 7. قواعد امنیتی این فصل

- هیچ رمز، APP_KEY، token یا credential در Git/README/لاگ عمومی قرار نگیرد.
- MariaDB و Redis فقط روی loopback بمانند.
- نصب فعلی LAN-only و HTTP است؛ به اینترنت باز نشده.
- قبل از تغییر مالکیت یا حذف فایل، علت و اثر تغییر را بررسی کن.
- برای وضعیت واقعی فعلی این فایل و [Current Status](CHANGELOG-STATUS.md) را بررسی کن؛ داده‌های تاریخی را بدون توجه به تاریخ snapshot، وضعیت فعلی فرض نکن.

## منابع رسمی

- Panel documentation: <https://docs.pterodactyl.io/>
- Panel releases: <https://github.com/pterodactyl/panel/releases>
- Wings install: <https://docs.pterodactyl.io/v2/wings/installing>
- Docker Debian install: <https://docs.docker.com/engine/install/debian/>

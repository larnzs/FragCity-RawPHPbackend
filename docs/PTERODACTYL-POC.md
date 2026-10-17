# FragCity — Pterodactyl PoC روی fc-node-01

> آخرین ثبت: ۱۷ اکتبر ۲۰۲۶  
> وضعیت: **بسته‌های پایه و Composer 2 نصب و بررسی شده‌اند؛ دریافت و پیکربندی Panel هنوز انجام نشده است.** این محیط VMware آزمایشی است و Production محسوب نمی‌شود.

## ۱. تصمیم فعلی پروژه

- سایت Flute CMS جداگانه توسعه داده می‌شود و در این فاز به Pterodactyl merge یا embed نمی‌شود.
- فعلاً Pterodactyl Panel، دیتابیس محلی آن، Wings و Docker روی همان Debian موجود با hostname برابر fc-node-01 آزمایش می‌شوند.
- دیتابیس Panel مستقل و محلی است. دیتابیس MariaDB روی هاست اشتراکی cPanel به‌صورت مستقیم به Panel یا Node وصل نمی‌شود.
- اتصال آیندهٔ سایت به Pterodactyl از طریق API امن HTTPS طراحی می‌شود؛ اشتراک مستقیم دیتابیس بین سایت و Panel جزو این برنامه نیست.
- هدف نخست، بالا آوردن Panel و سپس اثبات اجرای یک CS2 Server از طریق Pterodactyl است. تا قبولی این آزمایش هیچ فرضی دربارهٔ Production یا ظرفیت چند Instance تأییدشده نیست.

## ۲. نسخهٔ کاندید و وابستگی‌ها

بررسی منابع رسمی در تاریخ ثبت:

- **Pterodactyl Panel:** نسخهٔ پایدار کاندید v1.15.1. شاخهٔ 2.0 در وب‌سایت پروژه Open Beta معرفی شده است؛ فعلاً از Beta برای این PoC استفاده نمی‌کنیم.
- **Wings:** نسخهٔ پایدار کاندید v1.13.4.
- **PHP:** Panel v1.15.1 به PHP در بازهٔ ^8.2 یا ^8.3 نیاز دارد و شرط آن PHP 8.4 را شامل نمی‌کند. مخزن شخص ثالث SURY با keyring اختصاصی و گزینهٔ Signed-By اضافه شد؛ پس از apt update، candidate برابر 8.3.35-1+0~20260925.87+debian13~1.gbp19383d گزارش شد. SURY مخزن رسمی Debian نیست و این وابستگی خارجی باید برای به‌روزرسانی‌های آینده حفظ و پایش شود. APT پیام داد که SURY مخزن i386 ارائه نمی‌کند؛ چون candidate معماری amd64 موجود است، این پیام مانع نصب نسخهٔ موردنیاز amd64 نیست.
- **وب‌سرور:** Nginx.
- **دیتابیس Panel:** MariaDB محلی، با database و user جداگانه برای Panel.
- **Queue/cache:** Redis محلی.
- **سرورهای بازی:** Wings و Docker؛ نصب و پیکربندی آن‌ها بعد از بالا آمدن Panel و بررسی فضای دیسک انجام می‌شود.

منابع رسمی:
- Panel stable releases: https://github.com/pterodactyl/panel/releases
- Wings releases: https://github.com/pterodactyl/wings/releases
- Pterodactyl 1.x Panel install: https://docs.pterodactyl.io/v1/panel/getting-started
- راهنمای Debian 11/12/13: https://docs.pterodactyl.io/v1/guides/panel-installation/debian
- Wings install: https://docs.pterodactyl.io/v1/wings/installing

Releaseها قبل از نصب واقعی دوباره بررسی شوند؛ این سند نسخه‌های کاندید ثبت‌شده در تاریخ بالاست و به معنی نصب شدن آن‌ها نیست.

## ۳. Snapshot واقعی Debian

خروجی گزارش‌شدهٔ کاربر در ۱۷ اکتبر ۲۰۲۶:

| مورد | مقدار مشاهده‌شده |
|---|---|
| سیستم‌عامل | Debian GNU/Linux 13 (trixie) |
| Kernel | 6.12.113+deb13-amd64 |
| Virtualization | vmware |
| CPU قابل مشاهده | ۴ vCPU |
| RAM | ۵٫۶ GiB کل؛ ۳٫۷ GiB available در زمان نمونه‌برداری |
| Swap | ۶٫۱ GiB کل؛ حدود ۲۶۸ KiB مصرف‌شده |
| فایل‌سیستم / | ۱۱۲ GiB کل؛ ۷۹ GiB مصرف؛ ۲۸ GiB آزاد؛ ۷۵٪ مصرف |

### وضعیت نرم‌افزارها

بررسی command -v گزارش کرد که موارد زیر نصب/قابل‌دسترسی نیستند:

- PHP و Composer
- Nginx
- MariaDB/MySQL client/server binaries
- Redis server
- Docker
- Wings

خروجی بررسی serviceها inactive نشان داده است؛ با توجه به نبود executableهای بالا، این snapshot را به‌عنوان نشانهٔ نبود سرویس فعال ثبت می‌کنیم، نه اثبات تنظیمات نهایی systemd.

pgrep -a -x cs2 خروجی No active CS2 process داد. این یعنی هنگام snapshot سرور اجرا نبود؛ نصب فایل‌های native CS2 در مرحلهٔ پیشین موفق گزارش شده بود.

### شبکه و پورت‌های listening — تأییدشده در خروجی بعدی

| مورد | نتیجه |
|---|---|
| IPv4 ماشین | 192.168.1.5/24 روی ens33 |
| Default gateway | 192.168.1.1 |
| TCP | 22 روی همهٔ رابط‌ها برای SSH؛ 631 فقط روی loopback برای CUPS |
| UDP | 5353 و 43110 برای Avahi؛ 546 روی IPv6 link-local برای NetworkManager |
| پورت‌های هدف پنل | در زمان snapshot listener روی 80، 443، 3306، 6379، 8080 یا 2022 مشاهده نشد |
| sudo | دستور sudo ss -lntup با موفقیت اجرا و خروجی گرفت |

قبل از نصب Nginx، پورت‌های وب 80/443 در زمان snapshot listener نداشتند. پورت 22 باید برای مدیریت SSH حفظ شود؛ CUPS و Avahi فعلاً تغییری داده نمی‌شوند. هیچ Port Forwarding عمومی جدیدی برای این مرحله لازم نیست؛ ابتدا دسترسی آزمایش از LAN را بررسی می‌کنیم.

### نسخه‌های بسته‌های موجود در مخازن فعلی Debian

خروجی apt-cache policy:

| بسته | Candidate مشاهده‌شده |
|---|---|
| php8.3 | موجود نیست (Candidate: none) |
| php8.4 | 8.4.26-1~deb13u1 |
| mariadb-server | 1:11.8.6-0+deb13u1 |
| redis-server | 5:8.0.2-3+deb13u3 |
| nginx | 1.26.3-3+deb13u9 |
| docker.io | 26.1.5+dfsg1-9+deb13u1 |

این‌ها snapshot زمان بررسی‌اند، نه pin نهایی برای نصب. MariaDB و Redis موجود در Debian 13 با محدودهٔ موردنیاز Panel سازگار به نظر می‌رسند؛ نسخهٔ PHP باید مطابق محدودهٔ Composer انتخاب شود.

### اندازهٔ Runtime بازی موجود

- /home/steam/cs2: حدود 70 GiB.
- /home/steam/steamrt: حدود 797 MiB.
- /var/lib/docker: در خروجی فعلی اندازه‌ای گزارش نشد؛ Docker نصب نیست/مسیرش هنوز وجود ندارد.

در نتیجه فضای آزاد / حدود 28 GiB است و نباید Egg استاندارد CS2 را کورکورانه اجرا کنیم؛ ممکن است دوباره بازی را دانلود و دیسک را پر کند. قبل از اولین server container باید مشخص شود Egg از نصب native موجود استفاده می‌کند یا مسیر ذخیره‌سازی جدا می‌خواهد.

## ۴. محدودیت مهم Storage و اجرای CS2

فضای آزاد فعلی / فقط حدود **۲۸ GiB** است. فایل‌های نصب native فعلی CS2 در /home/steam/cs2 قرار دارند. به همین دلیل:

- نصب Panel و وابستگی‌های وب می‌تواند گام بعدی باشد، اما قبل از ایجاد CS2 server container نباید فرض کنیم فضای کافی برای دریافت نسخهٔ دوم بازی وجود دارد.
- ابتدا اندازهٔ واقعی /home/steam/cs2، /home/steam/steamrt و دایرکتوری‌های Docker بررسی می‌شود.
- معماری FragCity برای PoC استفاده از Base Game مشترک را هدف می‌گیرد؛ این هدف با bind mount یا overlay در Docker هنوز پیاده‌سازی یا تأیید نشده است.
- فعلاً هیچ فایل بازی را حذف نمی‌کنیم تا فضا آزاد کنیم و هیچ path اشتراکی را بدون تست به کانتینر mount نمی‌کنیم.
- اگر Egg انتخابی به نصب جداگانهٔ CS2 نیاز داشته باشد، پیش از اجرا باید دربارهٔ افزایش دیسک یا روش اشتراک امن Base تصمیم بگیریم.

RAM موجود برای Panel آزمایشی کافی به نظر می‌رسد، اما ظرفیت اجرای بازی، تعداد Instanceها و محدودیت منابع باید جداگانه اندازه‌گیری شوند.

## ۵. ترتیب اجرایی این PoC

### P2.1 — Preflight شبکه و مسیر دیسک

- [ ] تأیید کارکرد sudo.
- [ ] ثبت IP با ip -br address و مسیر پیش‌فرض.
- [ ] ثبت پورت‌های listening قبل از نصب Nginx.
- [ ] ثبت اندازهٔ native CS2/Steam Runtime و فضای آزاد.
- [ ] اطمینان از نبود CS2 process پیش از maintenance.

### P2.2 — نصب Panel و database stack

- [ ] نصب وابستگی‌های نسخهٔ منتخب بر اساس راهنمای جاری Pterodactyl.
- [ ] نصب/فعال‌سازی Nginx و PHP-FPM.
- [ ] نصب MariaDB و Redis.
- [ ] ساخت database به نام panel و user محلی محدود به loopback؛ رمز فقط در terminal/config خصوصی و نه در Git.
- [ ] دریافت archive رسمی Panel، تنظیم .env، Composer dependencies، key، migrations/seed و کاربر admin.
- [ ] تنظیم queue worker و cron scheduler.
- [ ] بالا آوردن Panel در شبکهٔ آزمایش و تست ورود مدیر.

ابتدا فقط دسترسی شبکهٔ محلی آزمایش می‌شود. دامنه عمومی، port forwarding اینترنتی و production TLS در این مرحله هدف نیستند.

### P2.3 — نصب Wings و Docker

- [ ] نصب Docker طبق مستندات رسمی Debian 13/Wings.
- [ ] بررسی docker info و سازگاری kernel.
- [ ] ساخت Node آزمایشی در Panel و ثبت allocations مناسب IP داخلی.
- [ ] تولید config برای Wings از خود Panel و راه‌اندازی کنترل‌شده.
- [ ] بررسی firewall/NAT، storage root و مسیرهای کانتینر پیش از اولین server creation.

نصب Docker می‌تواند قواعد firewall/NAT و مصرف دیسک را تغییر دهد؛ بنابراین بعد از ثبت وضعیت پورت‌ها انجام می‌شود، نه قبل از آن.

### P2.4 — اولین CS2 تحت مدیریت Pterodactyl

- [ ] انتخاب Egg/Startup سازگار و بازبینی اسکریپت نصب آن.
- [ ] حل اختلاف میان نصب native فعلی و filesystem داخل Docker.
- [ ] ابتدا یک server آزمایشی؛ تأیید install/start/console/stop/restart.
- [ ] بررسی RAM/CPU/disk/port و لاگ‌ها.
- [ ] بعد از قبولی فقط، بررسی model مربوط به Base Game مشترک و Instanceهای جدا.

## ۶. معیار پذیرش و منع‌های عملیاتی

PoC هنوز موفق محسوب نمی‌شود تا Panel به‌صورت پایدار باز شود، Wings به Panel متصل شود و حداقل یک CS2 server از داخل Panel lifecycle صحیح داشته باشد.

تا آن زمان:

- Flute CMS جدا و دست‌نخورده می‌ماند.
- دیتابیس cPanel از Panel قابل‌دسترسی مستقیم فرض نمی‌شود.
- secrets، APP_KEY، رمزهای DB و API key وارد Git/سند عمومی نمی‌شوند.
- فایل‌های فعلی CS2 حذف یا به‌صورت خطرناک جابه‌جا نمی‌شوند.
- نصب خودکار سرور از طریق create-instance.sh یا اجرای مستقیم watchdog.sh جایگزین Pterodactyl/Wings تلقی نمی‌شود.
- موفقیت native setup، سازگاری خودکار Docker/Shared Base یا ظرفیت ۵ تا ۱۰ سرور را اثبات نمی‌کند.

## ۷. وضعیت همین لحظه — پس از نصب وابستگی‌ها

**تأییدشده بر اساس لاگ کاربر در ۱۷ اکتبر ۲۰۲۶:**
- مخزن SURY و keyring آن نصب شده‌اند؛ APT کاندید PHP 8.3 را از مخزن amd64 دریافت کرده است.
- بسته‌های PHP 8.3 و extensionهای انتخاب‌شده، Nginx، MariaDB، Redis، Git، curl، tar و unzip نصب شده‌اند.
- نسخهٔ PHP CLI برابر 8.3.35 است؛ همهٔ extensionهای بررسی‌شده از جمله `bcmath`, `curl`, `gd`, `intl`, `mbstring`, `PDO`, `pdo_mysql`, `xml`, `zip` و Zend OPcache در خروجی ظاهر شده‌اند.
- سرویس‌های `nginx`, `mariadb`, `redis-server` و `php8.3-fpm` همگی `active` گزارش شده‌اند.
- Nginx روی TCP/80 گوش می‌دهد. MariaDB روی `127.0.0.1:3306` و Redis روی `127.0.0.1:6379` و `[::1]:6379` گوش می‌دهند؛ در snapshot پورت 3306 و 6379 روی رابط شبکهٔ عمومی باز نیستند.
- Composer 2.10.3 نصب و تأیید شده است. فایل‌های Panel، database/user اختصاصی Panel، `.env`، APP_KEY، migrations، حساب مدیر، queue/cron، Docker و Wings هنوز نصب/تنظیم نشده‌اند.
- دستور `nginx -v` در shell کاربر با پیام `command not found` مواجه شده، درحالی‌که سرویس Nginx active و پورت 80 در حال listen است. به‌احتمال زیاد مسیر `/usr/sbin` در PATH این shell نیست؛ نسخهٔ باینری را در ادامه با `sudo /usr/sbin/nginx -v` و نسخهٔ بسته را با `dpkg-query` بررسی می‌کنیم. این پیام را به‌عنوان شکست نصب Nginx ثبت نمی‌کنیم.
- فضای آزاد همچنان حدود 28 GiB است و نصب فعلی native بازی CS2 در `/home/steam/cs2` حدود 70 GiB است؛ اخطار storage پیش از ساخت container به قوت خود باقی است.

**مرحلهٔ بعد:** بررسی دوبارهٔ release پایدار Panel و سپس نوشتن/اجرای گام دریافت archive و پیکربندی Panel، با ثبت خروجی هر مرحله. هنوز دیتابیس یا `.env` پنل ساخته نشده است.

## ٨. نتیجهٔ افزودن مخزن PHP 8.3 — ۱۷ اکتبر ۲۰۲۶

کاربر این مراحل را انجام داد:

1. نصب/تأیید ca-certificates و curl.
2. دریافت و نصب بستهٔ debsuryorg-archive-keyring نسخهٔ 2025.11.18.
3. ساخت فایل /etc/apt/sources.list.d/sury-php.sources با Signed-By: /usr/share/keyrings/debsuryorg-archive-keyring.gpg.
4. اجرای موفق sudo apt update.

خروجی واقعی apt-cache policy php8.3:

~~~text
Installed: (none)
Candidate: 8.3.35-1+0~20260925.87+debian13~1.gbp19383d
500 https://packages.sury.org/php trixie/main amd64 Packages
~~~

پیام Skipping ... binary-i386/Packages از SURY به‌دلیل پشتیبانی‌نشدن معماری i386 در این مخزن بود؛ APT هم‌زمان index معماری amd64 را دریافت کرد و candidate را نشان داد.

**وضعیت در زمان ثبت این بخش:** فقط مخزن و keyring نصب شده بودند و بسته‌های PHP 8.3، Nginx، MariaDB و Redis هنوز نصب نشده بودند. این وضعیت تاریخی است و با اجرای موفق مرحلهٔ ۹ جایگزین شده است؛ وضعیت کنونی در بخش‌های ۷ و ۹ و گام بعدی در بخش ۱۰ ثبت شده‌اند. Composer/Panel، Wings و Docker همچنان نصب نشده‌اند.


## ۹. نتیجهٔ نصب بسته‌های پایهٔ Panel — انجام‌شده

### فرمان اجراشده

کاربر با موفقیت فرمان زیر را اجرا کرد؛ خروجی apt نشان می‌دهد ۵۱ بسته نصب شدند، بدون این‌که هیچ بسته‌ای حذف شود:

~~~bash
sudo apt install -y \
  php8.3 php8.3-cli php8.3-common php8.3-gd php8.3-mysql \
  php8.3-mbstring php8.3-bcmath php8.3-xml php8.3-curl \
  php8.3-zip php8.3-fpm php8.3-intl php8.3-opcache \
  mariadb-server redis-server nginx \
  git curl tar unzip
~~~

### نسخه‌ها و خروجی‌های واقعی

| بخش | نتیجهٔ خروجی |
|---|---|
| Debian | Debian GNU/Linux 13 (trixie) |
| PHP CLI | 8.3.35 |
| PHP extensions | bcmath, curl, gd, intl, mbstring, PDO, pdo_mysql, xml, zip و Zend OPcache مشاهده شدند |
| MariaDB client/server package | خروجی `mariadb --version`: MariaDB 11.8.6 |
| Redis | 8.0.2 |
| Nginx package | هنگام apt نصب شد؛ نسخهٔ باینری به‌خاطر PATH باید با مسیر کامل تأیید شود |
| nginx service | active |
| mariadb service | active |
| redis-server service | active |
| php8.3-fpm service | active |
| Nginx listener | `0.0.0.0:80` و `[::]:80` |
| MariaDB listener | فقط `127.0.0.1:3306` |
| Redis listener | `127.0.0.1:6379` و `[::1]:6379` |
| HTTPS | هنوز listener روی 443 وجود ندارد |
| Disk | حدود 28 GiB فضای آزاد؛ `/home/steam/cs2` حدود 70 GiB |
| Composer | نصب و تأیید شده: 2.10.3 |
| Panel / Docker / Wings | هنوز نصب نشده‌اند |

در خروجی فیلتر extension، `Zend OPcache` دو بار دیده شد؛ یک بار برای extension و یک بار در بخش Zend Engine. این duplication خطا نیست. اجرای `nginx -v` در shell کاربر `command not found` داد، اما systemd سرویس را active گزارش کرد و `ss` نیز listener مربوط به Nginx را نشان داد. بنابراین بررسی نسخهٔ باینری با مسیر کامل بخشی از مرحلهٔ بعد است.

### بررسی دوبارهٔ وضعیت

~~~bash
echo "=== PHP ==="
php --version
php -m | grep -Ei '^(bcmath|curl|gd|intl|mbstring|PDO|pdo_mysql|xml|zip|Zend OPcache)

echo
echo "=== NGINX BINARY AND PACKAGE ==="
sudo /usr/sbin/nginx -v
dpkg-query -W -f='nginx package: ${Version}\n' nginx

echo
echo "=== PACKAGE VERSIONS ==="
mariadb --version
redis-server --version

echo
echo "=== SERVICES ==="
for svc in nginx mariadb redis-server php8.3-fpm; do
    printf '%-15s' "$svc"
    systemctl is-active "$svc" || true
done

echo
echo "=== DATABASE / REDIS / WEB LISTENERS ==="
sudo ss -lntp | grep -E ':(80|443|3306|6379|8080|2022)\b' || true

echo
echo "=== DISK ==="
df -h /
sudo du -sh /home/steam/cs2 /home/steam/steamrt 2>/dev/null
~~~

این بررسی، به‌ویژه محدود ماندن MariaDB و Redis به loopback را ثبت می‌کند. فعلاً نیازی نیست این پورت‌ها از شبکهٔ LAN یا اینترنت در دسترس باشند.

## ۱۰. نصب Composer 2 — انجام‌شده در ۱۷ اکتبر ۲۰۲۶

### فرمان اجراشده

این فرمان در لاگ کاربر با checksum رسمی همان زمان اجرا شد. checksum نصب‌کننده ممکن است با نسخه/تاریخ تغییر کند؛ برای اجرای مجدد، مقدار روز را از صفحهٔ رسمی Composer بررسی کنید و آن را به‌عنوان مقدار ثابت دائمی تلقی نکنید.

~~~bash
set -eu
cd /tmp

EXPECTED_CHECKSUM='c8b085408188070d5f52bcfe4ecfbee5f727afa458b2573b8eaaf77b3419b0bf2768dc67c86944da1544f06fa544fd47'

php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"

if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]; then
    echo 'ERROR: Composer installer checksum mismatch; refusing to execute it.' >&2
    rm -f composer-setup.php
    exit 1
fi

echo 'Composer installer checksum verified.'
sudo php composer-setup.php --2 --install-dir=/usr/local/bin --filename=composer
rm -f composer-setup.php

composer --version
command -v composer

sudo /usr/sbin/nginx -v
dpkg-query -W -f='nginx package: ${Version}\n' nginx
~~~

### خروجی واقعی

| بررسی | نتیجه |
|---|---|
| امضای فایل نصب‌کننده | پیام `Composer installer checksum verified.` |
| Composer | نسخهٔ 2.10.3؛ تاریخ build نمایش‌داده‌شده: 2026-08-27 13:34:23 |
| مسیر Composer | `/usr/local/bin/composer` |
| PHP مورد استفاده | 8.3.35 در `/usr/bin/php8.3` |
| Nginx | `nginx/1.26.3` |
| بستهٔ Debian Nginx | `1.26.3-3+deb13u9` |

**نتیجه:** گام نصب Composer پذیرفته شد. تأیید checksum نشان می‌دهد installer با مقدار رسمی ثبت‌شده در زمان اجرا تطبیق داشت. نصب Composer فقط ابزار مدیریت dependency را آماده کرده و به معنی نصب‌شدن خود Pterodactyl نیست.

### توضیح آموزشی

- `EXPECTED_CHECKSUM` مقدار قابل‌اعتماد مورد انتظار است؛ `ACTUAL_CHECKSUM` از محتوای واقعی installer محاسبه می‌شود.
- `set -eu` باعث توقف اسکریپت در خطای فرمان یا استفاده از متغیر تعریف‌نشده می‌شود.
- گزینهٔ `--2` شاخهٔ اصلی Composer 2 را نصب می‌کند.
- `/usr/local/bin` محل ابزارهای نصب‌شده به‌صورت سراسری و خارج از مدیریت مستقیم بسته‌های APT است.
- اجرای `sudo /usr/sbin/nginx -v` مشکل قبلی `nginx: command not found` را برطرف کرد؛ سرویس Nginx از قبل فعال بود و باینری فقط در PATH کاربر عادی پیدا نمی‌شد.

منابع:
- https://getcomposer.org/download/
- https://docs.pterodactyl.io/v1/panel/getting-started

## ۱۱. مرحلهٔ بعد: دریافت و نصب Panel — هنوز اجرا نشده

در ادامه، قبل از اجرای فرمان دانلود، release پایدار و راهنمای رسمی Panel دوباره بررسی می‌شوند. سپس فرمان‌های هر مرحله به همین سند افزوده خواهند شد و نتیجهٔ واقعی اجرای کاربر در [Debian PoC Log](DEBIAN-POC.md) ثبت می‌شود.

ترتیب اجرای مرحلهٔ بعد:
1. بررسی release فعلی و انتخاب archive همان نسخه.
2. ایجاد مسیر امن برنامه و دریافت/استخراج archive رسمی.
3. تنظیم مالکیت و دسترسی فایل‌ها.
4. نصب dependencyهای PHP با Composer از داخل پوشهٔ Panel.
5. آماده‌سازی دیتابیس محلی و user محدود Panel؛ رمز فقط در ترمینال/فایل خصوصی، نه Git.
6. ساخت `.env` و APP_KEY، اجرای migration/seed و ساخت admin طبق مستندات همان release.
7. تنظیم Nginx، queue worker و cron؛ سپس بررسی ورود به پنل.

این‌ها هنوز برنامهٔ مرحلهٔ بعد هستند و هیچ‌کدام در لاگ فعلی اجرا نشده‌اند. پیش از ساخت DB/secret یا دانلود archive، نسخه و فرمان‌های فعلی دوباره تأیید خواهند شد.

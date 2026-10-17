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

## ۵. راهنمای اجرایی و چک‌لیست مرحله‌به‌مرحله

این بخش نقشهٔ عملیاتی کل PoC است. علامت‌ها معنی مشخص دارند:

- `[x]` یعنی اجرای آن مرحله از روی لاگ یا خروجی قابل مشاهده تأیید شده است.
- `[ ]` یعنی هنوز انجام نشده یا شواهد کافی برای تأیید آن نداریم.
- در هر جلسه فقط مرحلهٔ بعدیِ بدون تیک را اجرا کن. مراحل انجام‌نشده را جلوتر از ترتیب اجرا نکن.

### P2.1 — بررسی اولیهٔ Debian، شبکه و Storage

- [x] تأیید کارکرد `sudo`.
- [x] ثبت نسخهٔ Debian و kernel.
- [x] ثبت CPU، RAM، swap و فضای دیسک.
- [x] ثبت IP، gateway و پورت‌های listening پیش از نصب وب‌سرور.
- [x] ثبت اندازهٔ native CS2 و Steam Runtime و نبود process فعال CS2 در زمان snapshot.
- [x] ثبت محدودیت Storage: فضای آزاد root حدود 28 GiB است، اما نصب native CS2 حدود 70 GiB فضا دارد.

### P2.2 — آماده‌سازی و نصب Panel

- [x] افزودن مخزن PHP 8.3 از SURY با keyring اختصاصی و `Signed-By`.
- [x] نصب PHP 8.3، extensionهای منتخب، Nginx، MariaDB، Redis و ابزارهای لازم.
- [x] تأیید extensionهای PHP بررسی‌شده و active بودن Nginx، MariaDB، Redis و PHP-FPM.
- [x] تأیید اینکه MariaDB و Redis فقط روی loopback گوش می‌دهند.
- [x] نصب Composer 2.10.3 و تأیید checksum نصب‌کننده در زمان نصب.
- [x] بررسی Nginx با مسیر کامل؛ نسخهٔ باینری 1.26.3 و بستهٔ Debian برابر 1.26.3-3+deb13u9 است.
- [ ] دریافت archive نسخهٔ پایدار Panel v1.15.1، بررسی archive و استخراج فایل‌ها در `/var/www/pterodactyl` — **گام بعدی**.
- [ ] ساخت database و کاربر محدود مخصوص Panel؛ رمز در Git یا مستند عمومی نوشته نشود.
- [ ] نصب dependencyهای PHP با Composer و بررسی `composer check-platform-reqs --no-dev`.
- [ ] ساخت `.env`، واردکردن تنظیمات محیط، تولید APP_KEY و پشتیبان امن از آن.
- [ ] اجرای migrations/seed و ساخت admin user.
- [ ] تنظیم مالکیت و permissionهای نهایی فایل‌ها.
- [ ] ساخت تنظیم Nginx مخصوص Panel؛ قبل از reload اجرای `nginx -t` الزامی است.
- [ ] تنظیم queue worker و cron scheduler.
- [ ] آزمایش بازشدن Panel از مرورگر شبکهٔ آزمایش و ورود مدیر.

**قانون شبکه در این PoC:** هنوز port forwarding اینترنتی یا دامنهٔ عمومی تنظیم نمی‌کنیم. HTTP بدون TLS فقط برای شبکهٔ آزمایشگاهیِ محدود است و نباید با رمز واقعی از اینترنت در دسترس قرار گیرد.

### P2.3 — نصب Wings و Docker (بعد از قبولی Panel)

- [ ] بازبینی راهنمای رسمی Docker و نصب نسخهٔ سازگار.
- [ ] اجرای `docker info` و بررسی سازگاری kernel.
- [ ] ایجاد Node آزمایشی در Panel و تعریف allocations بر اساس IP واقعی Node.
- [ ] گرفتن تنظیم Wings از خود Panel؛ token/secret وارد Git نشود.
- [ ] راه‌اندازی Wings با systemd و بررسی log/service status.
- [ ] بررسی storage root و پورت‌های کانتینر پیش از ایجاد اولین سرور.

نصب Docker می‌تواند قواعد firewall/NAT و مصرف دیسک را تغییر دهد؛ به همین دلیل بعد از ثبت وضعیت شبکه انجام می‌شود.

### P2.4 — اولین CS2 تحت مدیریت Pterodactyl

- [ ] بررسی Egg و Startup، به‌ویژه اسکریپت نصب و حجم دانلود.
- [ ] حل محدودیت Storage پیش از ساخت container؛ ایجاد سرور CS2 تا آن زمان **مسدود است**.
- [ ] ساخت فقط یک سرور آزمایشی پس از تأیید فضای کافی یا روش Storage قابل اعتماد.
- [ ] تست install/start/console/stop/restart و ثبت خروجی واقعی.
- [ ] بررسی RAM/CPU/disk/network و لاگ‌ها.
- [ ] پس از قبولی، بررسی Shared Base و جداسازی payload هر Instance.

### معیار موفقیت PoC

PoC فقط زمانی موفق است که Panel پایدار باز شود، Wings به Panel متصل بماند و حداقل یک سرور CS2 از داخل Panel چرخهٔ Start/Stop/Restart موفق و تکرارپذیر داشته باشد. نصب بسته‌ها یا بازشدن صفحهٔ login به‌تنهایی موفقیت نهایی نیست.


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
- دستور `nginx -v` در shell کاربر با پیام `command not found` مواجه شد، اما سرویس active بود و پورت 80 را listen می‌کرد. بررسی بعدی با `sudo /usr/sbin/nginx -v` موفق شد و نسخهٔ `nginx/1.26.3` و بستهٔ `1.26.3-3+deb13u9` را تأیید کرد؛ پس این مورد مشکل PATH کاربر بود، نه شکست نصب Nginx.
- فضای آزاد همچنان حدود 28 GiB است و نصب فعلی native بازی CS2 در `/home/steam/cs2` حدود 70 GiB است؛ اخطار storage پیش از ساخت container به قوت خود باقی است.

**مرحلهٔ بعد:** دریافت و استخراج archive رسمی Panel v1.15.1 در `/var/www/pterodactyl`. نسخه و URL رسمی بازبینی شده‌اند؛ فرمان کامل و خروجی مورد انتظار در بخش ۱۱ آمده است. هنوز دیتابیس یا `.env` پنل ساخته نشده است.

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

**وضعیت در زمان ثبت این بخش:** فقط مخزن و keyring نصب شده بودند و بسته‌های PHP 8.3، Nginx، MariaDB و Redis هنوز نصب نشده بودند. این وضعیت تاریخی است و با اجرای موفق مرحله‌های ۹ و ۱۰ جایگزین شده است؛ وضعیت کنونی در بخش ۷ و گام بعدی در بخش ۱۱ ثبت شده است. در زمان آن snapshot، Panel، Wings و Docker نصب نشده بودند.


## ۹. نتیجهٔ نصب بسته‌های پایهٔ Panel — انجام‌شده

> **بازبینی نحو فرمان:** در بازبینی تکمیلی ۱۷ اکتبر، نقل‌قول پایانی الگوی `grep` در بلوک بررسی PHP جا افتاده بود و آن بلوک را از نظر shell نامعتبر می‌کرد. نقل‌قول و انتهای الگو اصلاح شد؛ از این نسخهٔ اصلاح‌شده استفاده کن.

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
| Nginx package/binary | نسخهٔ باینری `nginx/1.26.3`؛ بستهٔ `1.26.3-3+deb13u9` |
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

در خروجی فیلتر extension، `Zend OPcache` دو بار دیده شد؛ یک بار برای extension و یک بار در بخش Zend Engine. این duplication خطا نیست. `nginx -v` در shell کاربر `command not found` داد، اما بررسی با مسیر کامل `sudo /usr/sbin/nginx -v` نسخهٔ Nginx را تأیید کرد.

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

## ۱۱. گام بعدی: دریافت و استخراج Panel v1.15.1 — هنوز اجرا نشده

### چرا v1.15.1؟

در بازبینی ۱۷ اکتبر ۲۰۲۶، GitHub صفحهٔ release رسمی را با v1.15.1 به‌عنوان آخرین release پایدار 1.x نشان می‌دهد؛ مستندات رسمی، خط 1.x را Stable و 2.0 را Open Beta معرفی می‌کنند. asset رسمی همین tag برابر `panel.tar.gz` است. فایل `composer.json` این tag محدودهٔ PHP را `^8.2 || ^8.3` اعلام می‌کند و با PHP 8.3.35 نصب‌شده سازگار است. برای نصب تکرارپذیر از tag ثابت استفاده می‌کنیم، نه URL متغیر `latest/download`.

منابع رسمی:
- Release: https://github.com/pterodactyl/panel/releases/tag/v1.15.1
- فایل رسمی: https://github.com/pterodactyl/panel/releases/download/v1.15.1/panel.tar.gz
- Getting Started نسخهٔ 1.x: https://docs.pterodactyl.io/v1/panel/getting-started
- `composer.json` همان tag: https://raw.githubusercontent.com/pterodactyl/panel/v1.15.1/composer.json

### پیش از اجرا

این گام را در **ترمینال Debian ماشین `fc-node-01`** اجرا کن، نه در Windows. این مرحله فقط archive را دانلود، بررسی و استخراج می‌کند؛ database، رمز، `.env`، APP_KEY، Composer dependencies، Nginx virtual host، Docker یا Wings را نمی‌سازد.

اگر مسیر `/var/www/pterodactyl` از قبل وجود داشته باشد، اسکریپت عمداً متوقف می‌شود تا فایل‌های احتمالی قبلی بازنویسی نشوند. در آن صورت پوشه را حذف نکن؛ خروجی را بفرست تا اول بررسی شود.

### فرمان کامل مرحله

~~~bash
set -eu

PANEL_VERSION='v1.15.1'
PANEL_DIR='/var/www/pterodactyl'
ARCHIVE="/tmp/pterodactyl-panel-${PANEL_VERSION}.tar.gz"
FILE_LIST="/tmp/pterodactyl-panel-${PANEL_VERSION}-files.txt"
PANEL_URL="https://github.com/pterodactyl/panel/releases/download/${PANEL_VERSION}/panel.tar.gz"

echo "=== PRE-FLIGHT ==="
echo "Panel release: ${PANEL_VERSION}"
df -h /

if sudo test -e "$PANEL_DIR"; then
    echo "STOP: $PANEL_DIR already exists. No files were changed."
    sudo ls -la "$PANEL_DIR"
    exit 1
fi

echo
echo "=== DOWNLOAD OFFICIAL RELEASE ASSET ==="
curl --fail --location --show-error --output "$ARCHIVE" "$PANEL_URL"
test -s "$ARCHIVE"
ls -lh "$ARCHIVE"

echo
echo "=== VERIFY ARCHIVE CAN BE READ ==="
tar -tzf "$ARCHIVE" > "$FILE_LIST"
sed -n '1,15p' "$FILE_LIST"

echo
echo "=== CREATE TARGET DIRECTORY AND EXTRACT ==="
sudo install -d -m 0755 "$PANEL_DIR"
sudo tar -xzf "$ARCHIVE" -C "$PANEL_DIR"

echo
echo "=== CHECK REQUIRED PANEL FILES ==="
for required_file in artisan .env.example composer.json public/index.php; do
    if ! sudo test -f "$PANEL_DIR/$required_file"; then
        echo "ERROR: expected file is missing: $PANEL_DIR/$required_file" >&2
        exit 1
    fi
    echo "OK: $required_file"
done

echo
echo "=== SET INITIAL STORAGE / CACHE PERMISSIONS ==="
sudo chmod -R 755 "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"

echo
echo "=== PANEL DIRECTORY SUMMARY ==="
sudo ls -ld "$PANEL_DIR" "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"
sudo du -sh "$PANEL_DIR"
df -h /
~~~

### توضیح سادهٔ فرمان

- `set -eu`: در صورت خطای فرمان یا متغیر تعریف‌نشده، اجرای بلوک متوقف می‌شود.
- `PANEL_VERSION`: نسخهٔ دقیق را ثابت می‌کند تا نصب قابل تکرار باشد.
- بررسی وجود پوشه: مانع بازنویسی ناخواستهٔ نصب قبلی می‌شود.
- `curl --fail --location`: فایل را از Release رسمی دریافت می‌کند و خطای HTTP را موفق فرض نمی‌کند.
- `tar -tzf`: قبل از استخراج، بررسی می‌کند archive قابل خواندن است و فهرست فایل‌ها را نگه می‌دارد.
- `install -d`: پوشهٔ مقصد را می‌سازد؛ `tar` فایل‌ها را در آن استخراج می‌کند.
- آزمون چهار فایل: وجود فایل‌های اصلی برنامه، فایل نمونهٔ محیط، فهرست dependencyها و front controller را بررسی می‌کند.
- `chmod`: مجوز اولیهٔ Storage و Cache را تنظیم می‌کند؛ مالکیت نهایی در مرحلهٔ بعد اعمال می‌شود.

### خروجی مورد انتظار

- `Panel release: v1.15.1`
- دانلود archive و `tar -tzf` بدون خطا.
- چهار پیام `OK` برای `artisan`, `.env.example`, `composer.json`, `public/index.php`.
- نمایش مسیرهای Panel/Storage/Cache و اندازهٔ پوشه.

### اگر خطا دیدی

- اگر پیام `already exists` آمد، متوقف شو و پوشه را حذف نکن.
- اگر `curl`، `tar` یا آزمون فایل‌ها خطا داد، ادامه نده.
- اگر موفق شد، کل خروجی را بفرست. در این گام رمز یا secret ساخته نمی‌شود؛ پیش از ارسال خروجی هر اطلاعات خصوصی را بازبینی کن.

**این گام هنوز اجرا نشده است.** پس از دیدن خروجی، نتیجه را در Runbook، Debian Log، README، Roadmap و Change Log ثبت می‌کنیم؛ سپس سراغ Database و نصب dependencyهای PHP می‌رویم.


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

## ۱۱. گام بعدی: دریافت و استخراج Panel v1.15.1 — هنوز اجرا نشده

### چرا v1.15.1؟

در بازبینی ۱۷ اکتبر ۲۰۲۶، GitHub صفحهٔ release رسمی را با v1.15.1 به‌عنوان آخرین release پایدار 1.x نشان می‌دهد؛ مستندات رسمی، خط 1.x را Stable و 2.0 را Open Beta معرفی می‌کنند. asset رسمی همین tag برابر `panel.tar.gz` است. فایل `composer.json` این tag محدودهٔ PHP را `^8.2 || ^8.3` اعلام می‌کند و با PHP 8.3.35 نصب‌شده سازگار است. برای نصب تکرارپذیر از tag ثابت استفاده می‌کنیم، نه URL متغیر `latest/download`.

منابع رسمی:
- Release: https://github.com/pterodactyl/panel/releases/tag/v1.15.1
- فایل رسمی: https://github.com/pterodactyl/panel/releases/download/v1.15.1/panel.tar.gz
- Getting Started نسخهٔ 1.x: https://docs.pterodactyl.io/v1/panel/getting-started
- `composer.json` همان tag: https://raw.githubusercontent.com/pterodactyl/panel/v1.15.1/composer.json

### پیش از اجرا

این گام را در **ترمینال Debian ماشین `fc-node-01`** اجرا کن، نه در Windows. این مرحله فقط archive را دانلود، بررسی و استخراج می‌کند؛ database، رمز، `.env`، APP_KEY، Composer dependencies، Nginx virtual host، Docker یا Wings را نمی‌سازد.

اگر مسیر `/var/www/pterodactyl` از قبل وجود داشته باشد، اسکریپت عمداً متوقف می‌شود تا فایل‌های احتمالی قبلی بازنویسی نشوند. در آن صورت پوشه را حذف نکن؛ خروجی را بفرست تا اول بررسی شود.

### فرمان کامل مرحله

~~~bash
set -eu

PANEL_VERSION='v1.15.1'
PANEL_DIR='/var/www/pterodactyl'
ARCHIVE="/tmp/pterodactyl-panel-${PANEL_VERSION}.tar.gz"
FILE_LIST="/tmp/pterodactyl-panel-${PANEL_VERSION}-files.txt"
PANEL_URL="https://github.com/pterodactyl/panel/releases/download/${PANEL_VERSION}/panel.tar.gz"

echo "=== PRE-FLIGHT ==="
echo "Panel release: ${PANEL_VERSION}"
df -h /

if sudo test -e "$PANEL_DIR"; then
    echo "STOP: $PANEL_DIR already exists. No files were changed."
    sudo ls -la "$PANEL_DIR"
    exit 1
fi

echo
echo "=== DOWNLOAD OFFICIAL RELEASE ASSET ==="
curl --fail --location --show-error --output "$ARCHIVE" "$PANEL_URL"
test -s "$ARCHIVE"
ls -lh "$ARCHIVE"

echo
echo "=== VERIFY ARCHIVE CAN BE READ ==="
tar -tzf "$ARCHIVE" > "$FILE_LIST"
sed -n '1,15p' "$FILE_LIST"

echo
echo "=== CREATE TARGET DIRECTORY AND EXTRACT ==="
sudo install -d -m 0755 "$PANEL_DIR"
sudo tar -xzf "$ARCHIVE" -C "$PANEL_DIR"

echo
echo "=== CHECK REQUIRED PANEL FILES ==="
for required_file in artisan .env.example composer.json public/index.php; do
    if ! sudo test -f "$PANEL_DIR/$required_file"; then
        echo "ERROR: expected file is missing: $PANEL_DIR/$required_file" >&2
        exit 1
    fi
    echo "OK: $required_file"
done

echo
echo "=== SET INITIAL STORAGE / CACHE PERMISSIONS ==="
sudo chmod -R 755 "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"

echo
echo "=== PANEL DIRECTORY SUMMARY ==="
sudo ls -ld "$PANEL_DIR" "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"
sudo du -sh "$PANEL_DIR"
df -h /
~~~

### توضیح سادهٔ فرمان

- `set -eu`: در صورت خطای فرمان یا متغیر تعریف‌نشده، اجرای بلوک متوقف می‌شود.
- `PANEL_VERSION`: نسخهٔ دقیق را ثابت می‌کند تا نصب قابل تکرار باشد.
- بررسی وجود پوشه: مانع بازنویسی ناخواستهٔ نصب قبلی می‌شود.
- `curl --fail --location`: فایل را از Release رسمی دریافت می‌کند و خطای HTTP را موفق فرض نمی‌کند.
- `tar -tzf`: قبل از استخراج، بررسی می‌کند archive قابل خواندن است و فهرست فایل‌ها را نگه می‌دارد.
- `install -d`: پوشهٔ مقصد را می‌سازد؛ `tar` فایل‌ها را در آن استخراج می‌کند.
- آزمون چهار فایل: وجود فایل‌های اصلی برنامه، فایل نمونهٔ محیط، فهرست dependencyها و front controller را بررسی می‌کند.
- `chmod`: مجوز اولیهٔ Storage و Cache را تنظیم می‌کند؛ مالکیت نهایی در مرحلهٔ بعد اعمال می‌شود.

### خروجی مورد انتظار

- `Panel release: v1.15.1`
- دانلود archive و `tar -tzf` بدون خطا.
- چهار پیام `OK` برای `artisan`, `.env.example`, `composer.json`, `public/index.php`.
- نمایش مسیرهای Panel/Storage/Cache و اندازهٔ پوشه.

### اگر خطا دیدی

- اگر پیام `already exists` آمد، متوقف شو و پوشه را حذف نکن.
- اگر `curl`، `tar` یا آزمون فایل‌ها خطا داد، ادامه نده.
- اگر موفق شد، کل خروجی را بفرست. در این گام رمز یا secret ساخته نمی‌شود؛ پیش از ارسال خروجی هر اطلاعات خصوصی را بازبینی کن.

**این گام هنوز اجرا نشده است.** پس از دیدن خروجی، نتیجه را در Runbook، Debian Log، README، Roadmap و Change Log ثبت می‌کنیم؛ سپس سراغ Database و نصب dependencyهای PHP می‌رویم.


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

## ۱۱. گام بعدی: دریافت و استخراج Panel v1.15.1 — هنوز اجرا نشده

### چرا v1.15.1؟

در بازبینی ۱۷ اکتبر ۲۰۲۶، GitHub صفحهٔ release رسمی را با v1.15.1 به‌عنوان آخرین release پایدار 1.x نشان می‌دهد؛ مستندات رسمی، خط 1.x را Stable و 2.0 را Open Beta معرفی می‌کنند. asset رسمی همین tag برابر `panel.tar.gz` است. فایل `composer.json` این tag محدودهٔ PHP را `^8.2 || ^8.3` اعلام می‌کند و با PHP 8.3.35 نصب‌شده سازگار است. برای نصب تکرارپذیر از tag ثابت استفاده می‌کنیم، نه URL متغیر `latest/download`.

منابع رسمی:
- Release: https://github.com/pterodactyl/panel/releases/tag/v1.15.1
- فایل رسمی: https://github.com/pterodactyl/panel/releases/download/v1.15.1/panel.tar.gz
- Getting Started نسخهٔ 1.x: https://docs.pterodactyl.io/v1/panel/getting-started
- `composer.json` همان tag: https://raw.githubusercontent.com/pterodactyl/panel/v1.15.1/composer.json

### پیش از اجرا

این گام را در **ترمینال Debian ماشین `fc-node-01`** اجرا کن، نه در Windows. این مرحله فقط archive را دانلود، بررسی و استخراج می‌کند؛ database، رمز، `.env`، APP_KEY، Composer dependencies، Nginx virtual host، Docker یا Wings را نمی‌سازد.

اگر مسیر `/var/www/pterodactyl` از قبل وجود داشته باشد، اسکریپت عمداً متوقف می‌شود تا فایل‌های احتمالی قبلی بازنویسی نشوند. در آن صورت پوشه را حذف نکن؛ خروجی را بفرست تا اول بررسی شود.

### فرمان کامل مرحله

~~~bash
set -eu

PANEL_VERSION='v1.15.1'
PANEL_DIR='/var/www/pterodactyl'
ARCHIVE="/tmp/pterodactyl-panel-${PANEL_VERSION}.tar.gz"
FILE_LIST="/tmp/pterodactyl-panel-${PANEL_VERSION}-files.txt"
PANEL_URL="https://github.com/pterodactyl/panel/releases/download/${PANEL_VERSION}/panel.tar.gz"

echo "=== PRE-FLIGHT ==="
echo "Panel release: ${PANEL_VERSION}"
df -h /

if sudo test -e "$PANEL_DIR"; then
    echo "STOP: $PANEL_DIR already exists. No files were changed."
    sudo ls -la "$PANEL_DIR"
    exit 1
fi

echo
echo "=== DOWNLOAD OFFICIAL RELEASE ASSET ==="
curl --fail --location --show-error --output "$ARCHIVE" "$PANEL_URL"
test -s "$ARCHIVE"
ls -lh "$ARCHIVE"

echo
echo "=== VERIFY ARCHIVE CAN BE READ ==="
tar -tzf "$ARCHIVE" > "$FILE_LIST"
sed -n '1,15p' "$FILE_LIST"

echo
echo "=== CREATE TARGET DIRECTORY AND EXTRACT ==="
sudo install -d -m 0755 "$PANEL_DIR"
sudo tar -xzf "$ARCHIVE" -C "$PANEL_DIR"

echo
echo "=== CHECK REQUIRED PANEL FILES ==="
for required_file in artisan .env.example composer.json public/index.php; do
    if ! sudo test -f "$PANEL_DIR/$required_file"; then
        echo "ERROR: expected file is missing: $PANEL_DIR/$required_file" >&2
        exit 1
    fi
    echo "OK: $required_file"
done

echo
echo "=== SET INITIAL STORAGE / CACHE PERMISSIONS ==="
sudo chmod -R 755 "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"

echo
echo "=== PANEL DIRECTORY SUMMARY ==="
sudo ls -ld "$PANEL_DIR" "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"
sudo du -sh "$PANEL_DIR"
df -h /
~~~

### توضیح سادهٔ فرمان

- `set -eu`: در صورت خطای فرمان یا متغیر تعریف‌نشده، اجرای بلوک متوقف می‌شود.
- `PANEL_VERSION`: نسخهٔ دقیق را ثابت می‌کند تا نصب قابل تکرار باشد.
- بررسی وجود پوشه: مانع بازنویسی ناخواستهٔ نصب قبلی می‌شود.
- `curl --fail --location`: فایل را از Release رسمی دریافت می‌کند و خطای HTTP را موفق فرض نمی‌کند.
- `tar -tzf`: قبل از استخراج، بررسی می‌کند archive قابل خواندن است و فهرست فایل‌ها را نگه می‌دارد.
- `install -d`: پوشهٔ مقصد را می‌سازد؛ `tar` فایل‌ها را در آن استخراج می‌کند.
- آزمون چهار فایل: وجود فایل‌های اصلی برنامه، فایل نمونهٔ محیط، فهرست dependencyها و front controller را بررسی می‌کند.
- `chmod`: مجوز اولیهٔ Storage و Cache را تنظیم می‌کند؛ مالکیت نهایی در مرحلهٔ بعد اعمال می‌شود.

### خروجی مورد انتظار

- `Panel release: v1.15.1`
- دانلود archive و `tar -tzf` بدون خطا.
- چهار پیام `OK` برای `artisan`, `.env.example`, `composer.json`, `public/index.php`.
- نمایش مسیرهای Panel/Storage/Cache و اندازهٔ پوشه.

### اگر خطا دیدی

- اگر پیام `already exists` آمد، متوقف شو و پوشه را حذف نکن.
- اگر `curl`، `tar` یا آزمون فایل‌ها خطا داد، ادامه نده.
- اگر موفق شد، کل خروجی را بفرست. در این گام رمز یا secret ساخته نمی‌شود؛ پیش از ارسال خروجی هر اطلاعات خصوصی را بازبینی کن.

**این گام هنوز اجرا نشده است.** پس از دیدن خروجی، نتیجه را در Runbook، Debian Log، README، Roadmap و Change Log ثبت می‌کنیم؛ سپس سراغ Database و نصب dependencyهای PHP می‌رویم.


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

## ۱۱. گام بعدی: دریافت و استخراج Panel v1.15.1 — هنوز اجرا نشده

### چرا v1.15.1؟

در بازبینی ۱۷ اکتبر ۲۰۲۶، GitHub صفحهٔ release رسمی را با v1.15.1 به‌عنوان آخرین release پایدار 1.x نشان می‌دهد؛ مستندات رسمی، خط 1.x را Stable و 2.0 را Open Beta معرفی می‌کنند. asset رسمی همین tag برابر `panel.tar.gz` است. فایل `composer.json` این tag محدودهٔ PHP را `^8.2 || ^8.3` اعلام می‌کند و با PHP 8.3.35 نصب‌شده سازگار است. برای نصب تکرارپذیر از tag ثابت استفاده می‌کنیم، نه URL متغیر `latest/download`.

منابع رسمی:
- Release: https://github.com/pterodactyl/panel/releases/tag/v1.15.1
- فایل رسمی: https://github.com/pterodactyl/panel/releases/download/v1.15.1/panel.tar.gz
- Getting Started نسخهٔ 1.x: https://docs.pterodactyl.io/v1/panel/getting-started
- `composer.json` همان tag: https://raw.githubusercontent.com/pterodactyl/panel/v1.15.1/composer.json

### پیش از اجرا

این گام را در **ترمینال Debian ماشین `fc-node-01`** اجرا کن، نه در Windows. این مرحله فقط archive را دانلود، بررسی و استخراج می‌کند؛ database، رمز، `.env`، APP_KEY، Composer dependencies، Nginx virtual host، Docker یا Wings را نمی‌سازد.

اگر مسیر `/var/www/pterodactyl` از قبل وجود داشته باشد، اسکریپت عمداً متوقف می‌شود تا فایل‌های احتمالی قبلی بازنویسی نشوند. در آن صورت پوشه را حذف نکن؛ خروجی را بفرست تا اول بررسی شود.

### فرمان کامل مرحله

~~~bash
set -eu

PANEL_VERSION='v1.15.1'
PANEL_DIR='/var/www/pterodactyl'
ARCHIVE="/tmp/pterodactyl-panel-${PANEL_VERSION}.tar.gz"
FILE_LIST="/tmp/pterodactyl-panel-${PANEL_VERSION}-files.txt"
PANEL_URL="https://github.com/pterodactyl/panel/releases/download/${PANEL_VERSION}/panel.tar.gz"

echo "=== PRE-FLIGHT ==="
echo "Panel release: ${PANEL_VERSION}"
df -h /

if sudo test -e "$PANEL_DIR"; then
    echo "STOP: $PANEL_DIR already exists. No files were changed."
    sudo ls -la "$PANEL_DIR"
    exit 1
fi

echo
echo "=== DOWNLOAD OFFICIAL RELEASE ASSET ==="
curl --fail --location --show-error --output "$ARCHIVE" "$PANEL_URL"
test -s "$ARCHIVE"
ls -lh "$ARCHIVE"

echo
echo "=== VERIFY ARCHIVE CAN BE READ ==="
tar -tzf "$ARCHIVE" > "$FILE_LIST"
sed -n '1,15p' "$FILE_LIST"

echo
echo "=== CREATE TARGET DIRECTORY AND EXTRACT ==="
sudo install -d -m 0755 "$PANEL_DIR"
sudo tar -xzf "$ARCHIVE" -C "$PANEL_DIR"

echo
echo "=== CHECK REQUIRED PANEL FILES ==="
for required_file in artisan .env.example composer.json public/index.php; do
    if ! sudo test -f "$PANEL_DIR/$required_file"; then
        echo "ERROR: expected file is missing: $PANEL_DIR/$required_file" >&2
        exit 1
    fi
    echo "OK: $required_file"
done

echo
echo "=== SET INITIAL STORAGE / CACHE PERMISSIONS ==="
sudo chmod -R 755 "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"

echo
echo "=== PANEL DIRECTORY SUMMARY ==="
sudo ls -ld "$PANEL_DIR" "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"
sudo du -sh "$PANEL_DIR"
df -h /
~~~

### توضیح سادهٔ فرمان

- `set -eu`: در صورت خطای فرمان یا متغیر تعریف‌نشده، اجرای بلوک متوقف می‌شود.
- `PANEL_VERSION`: نسخهٔ دقیق را ثابت می‌کند تا نصب قابل تکرار باشد.
- بررسی وجود پوشه: مانع بازنویسی ناخواستهٔ نصب قبلی می‌شود.
- `curl --fail --location`: فایل را از Release رسمی دریافت می‌کند و خطای HTTP را موفق فرض نمی‌کند.
- `tar -tzf`: قبل از استخراج، بررسی می‌کند archive قابل خواندن است و فهرست فایل‌ها را نگه می‌دارد.
- `install -d`: پوشهٔ مقصد را می‌سازد؛ `tar` فایل‌ها را در آن استخراج می‌کند.
- آزمون چهار فایل: وجود فایل‌های اصلی برنامه، فایل نمونهٔ محیط، فهرست dependencyها و front controller را بررسی می‌کند.
- `chmod`: مجوز اولیهٔ Storage و Cache را تنظیم می‌کند؛ مالکیت نهایی در مرحلهٔ بعد اعمال می‌شود.

### خروجی مورد انتظار

- `Panel release: v1.15.1`
- دانلود archive و `tar -tzf` بدون خطا.
- چهار پیام `OK` برای `artisan`, `.env.example`, `composer.json`, `public/index.php`.
- نمایش مسیرهای Panel/Storage/Cache و اندازهٔ پوشه.

### اگر خطا دیدی

- اگر پیام `already exists` آمد، متوقف شو و پوشه را حذف نکن.
- اگر `curl`، `tar` یا آزمون فایل‌ها خطا داد، ادامه نده.
- اگر موفق شد، کل خروجی را بفرست. در این گام رمز یا secret ساخته نمی‌شود؛ پیش از ارسال خروجی هر اطلاعات خصوصی را بازبینی کن.

**این گام هنوز اجرا نشده است.** پس از دیدن خروجی، نتیجه را در Runbook، Debian Log، README، Roadmap و Change Log ثبت می‌کنیم؛ سپس سراغ Database و نصب dependencyهای PHP می‌رویم.


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

## ۱۱. گام بعدی: دریافت و استخراج Panel v1.15.1 — هنوز اجرا نشده

### چرا v1.15.1؟

در بازبینی ۱۷ اکتبر ۲۰۲۶، GitHub صفحهٔ release رسمی را با v1.15.1 به‌عنوان آخرین release پایدار 1.x نشان می‌دهد؛ مستندات رسمی، خط 1.x را Stable و 2.0 را Open Beta معرفی می‌کنند. asset رسمی همین tag برابر `panel.tar.gz` است. فایل `composer.json` این tag محدودهٔ PHP را `^8.2 || ^8.3` اعلام می‌کند و با PHP 8.3.35 نصب‌شده سازگار است. برای نصب تکرارپذیر از tag ثابت استفاده می‌کنیم، نه URL متغیر `latest/download`.

منابع رسمی:
- Release: https://github.com/pterodactyl/panel/releases/tag/v1.15.1
- فایل رسمی: https://github.com/pterodactyl/panel/releases/download/v1.15.1/panel.tar.gz
- Getting Started نسخهٔ 1.x: https://docs.pterodactyl.io/v1/panel/getting-started
- `composer.json` همان tag: https://raw.githubusercontent.com/pterodactyl/panel/v1.15.1/composer.json

### پیش از اجرا

این گام را در **ترمینال Debian ماشین `fc-node-01`** اجرا کن، نه در Windows. این مرحله فقط archive را دانلود، بررسی و استخراج می‌کند؛ database، رمز، `.env`، APP_KEY، Composer dependencies، Nginx virtual host، Docker یا Wings را نمی‌سازد.

اگر مسیر `/var/www/pterodactyl` از قبل وجود داشته باشد، اسکریپت عمداً متوقف می‌شود تا فایل‌های احتمالی قبلی بازنویسی نشوند. در آن صورت پوشه را حذف نکن؛ خروجی را بفرست تا اول بررسی شود.

### فرمان کامل مرحله

~~~bash
set -eu

PANEL_VERSION='v1.15.1'
PANEL_DIR='/var/www/pterodactyl'
ARCHIVE="/tmp/pterodactyl-panel-${PANEL_VERSION}.tar.gz"
FILE_LIST="/tmp/pterodactyl-panel-${PANEL_VERSION}-files.txt"
PANEL_URL="https://github.com/pterodactyl/panel/releases/download/${PANEL_VERSION}/panel.tar.gz"

echo "=== PRE-FLIGHT ==="
echo "Panel release: ${PANEL_VERSION}"
df -h /

if sudo test -e "$PANEL_DIR"; then
    echo "STOP: $PANEL_DIR already exists. No files were changed."
    sudo ls -la "$PANEL_DIR"
    exit 1
fi

echo
echo "=== DOWNLOAD OFFICIAL RELEASE ASSET ==="
curl --fail --location --show-error --output "$ARCHIVE" "$PANEL_URL"
test -s "$ARCHIVE"
ls -lh "$ARCHIVE"

echo
echo "=== VERIFY ARCHIVE CAN BE READ ==="
tar -tzf "$ARCHIVE" > "$FILE_LIST"
sed -n '1,15p' "$FILE_LIST"

echo
echo "=== CREATE TARGET DIRECTORY AND EXTRACT ==="
sudo install -d -m 0755 "$PANEL_DIR"
sudo tar -xzf "$ARCHIVE" -C "$PANEL_DIR"

echo
echo "=== CHECK REQUIRED PANEL FILES ==="
for required_file in artisan .env.example composer.json public/index.php; do
    if ! sudo test -f "$PANEL_DIR/$required_file"; then
        echo "ERROR: expected file is missing: $PANEL_DIR/$required_file" >&2
        exit 1
    fi
    echo "OK: $required_file"
done

echo
echo "=== SET INITIAL STORAGE / CACHE PERMISSIONS ==="
sudo chmod -R 755 "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"

echo
echo "=== PANEL DIRECTORY SUMMARY ==="
sudo ls -ld "$PANEL_DIR" "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"
sudo du -sh "$PANEL_DIR"
df -h /
~~~

### توضیح سادهٔ فرمان

- `set -eu`: در صورت خطای فرمان یا متغیر تعریف‌نشده، اجرای بلوک متوقف می‌شود.
- `PANEL_VERSION`: نسخهٔ دقیق را ثابت می‌کند تا نصب قابل تکرار باشد.
- بررسی وجود پوشه: مانع بازنویسی ناخواستهٔ نصب قبلی می‌شود.
- `curl --fail --location`: فایل را از Release رسمی دریافت می‌کند و خطای HTTP را موفق فرض نمی‌کند.
- `tar -tzf`: قبل از استخراج، بررسی می‌کند archive قابل خواندن است و فهرست فایل‌ها را نگه می‌دارد.
- `install -d`: پوشهٔ مقصد را می‌سازد؛ `tar` فایل‌ها را در آن استخراج می‌کند.
- آزمون چهار فایل: وجود فایل‌های اصلی برنامه، فایل نمونهٔ محیط، فهرست dependencyها و front controller را بررسی می‌کند.
- `chmod`: مجوز اولیهٔ Storage و Cache را تنظیم می‌کند؛ مالکیت نهایی در مرحلهٔ بعد اعمال می‌شود.

### خروجی مورد انتظار

- `Panel release: v1.15.1`
- دانلود archive و `tar -tzf` بدون خطا.
- چهار پیام `OK` برای `artisan`, `.env.example`, `composer.json`, `public/index.php`.
- نمایش مسیرهای Panel/Storage/Cache و اندازهٔ پوشه.

### اگر خطا دیدی

- اگر پیام `already exists` آمد، متوقف شو و پوشه را حذف نکن.
- اگر `curl`، `tar` یا آزمون فایل‌ها خطا داد، ادامه نده.
- اگر موفق شد، کل خروجی را بفرست. در این گام رمز یا secret ساخته نمی‌شود؛ پیش از ارسال خروجی هر اطلاعات خصوصی را بازبینی کن.

**این گام هنوز اجرا نشده است.** پس از دیدن خروجی، نتیجه را در Runbook، Debian Log، README، Roadmap و Change Log ثبت می‌کنیم؛ سپس سراغ Database و نصب dependencyهای PHP می‌رویم.


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

## ۱۱. گام بعدی: دریافت و استخراج Panel v1.15.1 — هنوز اجرا نشده

### چرا v1.15.1؟

در بازبینی ۱۷ اکتبر ۲۰۲۶، GitHub صفحهٔ release رسمی را با v1.15.1 به‌عنوان آخرین release پایدار 1.x نشان می‌دهد؛ مستندات رسمی، خط 1.x را Stable و 2.0 را Open Beta معرفی می‌کنند. asset رسمی همین tag برابر `panel.tar.gz` است. فایل `composer.json` این tag محدودهٔ PHP را `^8.2 || ^8.3` اعلام می‌کند و با PHP 8.3.35 نصب‌شده سازگار است. برای نصب تکرارپذیر از tag ثابت استفاده می‌کنیم، نه URL متغیر `latest/download`.

منابع رسمی:
- Release: https://github.com/pterodactyl/panel/releases/tag/v1.15.1
- فایل رسمی: https://github.com/pterodactyl/panel/releases/download/v1.15.1/panel.tar.gz
- Getting Started نسخهٔ 1.x: https://docs.pterodactyl.io/v1/panel/getting-started
- `composer.json` همان tag: https://raw.githubusercontent.com/pterodactyl/panel/v1.15.1/composer.json

### پیش از اجرا

این گام را در **ترمینال Debian ماشین `fc-node-01`** اجرا کن، نه در Windows. این مرحله فقط archive را دانلود، بررسی و استخراج می‌کند؛ database، رمز، `.env`، APP_KEY، Composer dependencies، Nginx virtual host، Docker یا Wings را نمی‌سازد.

اگر مسیر `/var/www/pterodactyl` از قبل وجود داشته باشد، اسکریپت عمداً متوقف می‌شود تا فایل‌های احتمالی قبلی بازنویسی نشوند. در آن صورت پوشه را حذف نکن؛ خروجی را بفرست تا اول بررسی شود.

### فرمان کامل مرحله

~~~bash
set -eu

PANEL_VERSION='v1.15.1'
PANEL_DIR='/var/www/pterodactyl'
ARCHIVE="/tmp/pterodactyl-panel-${PANEL_VERSION}.tar.gz"
FILE_LIST="/tmp/pterodactyl-panel-${PANEL_VERSION}-files.txt"
PANEL_URL="https://github.com/pterodactyl/panel/releases/download/${PANEL_VERSION}/panel.tar.gz"

echo "=== PRE-FLIGHT ==="
echo "Panel release: ${PANEL_VERSION}"
df -h /

if sudo test -e "$PANEL_DIR"; then
    echo "STOP: $PANEL_DIR already exists. No files were changed."
    sudo ls -la "$PANEL_DIR"
    exit 1
fi

echo
echo "=== DOWNLOAD OFFICIAL RELEASE ASSET ==="
curl --fail --location --show-error --output "$ARCHIVE" "$PANEL_URL"
test -s "$ARCHIVE"
ls -lh "$ARCHIVE"

echo
echo "=== VERIFY ARCHIVE CAN BE READ ==="
tar -tzf "$ARCHIVE" > "$FILE_LIST"
sed -n '1,15p' "$FILE_LIST"

echo
echo "=== CREATE TARGET DIRECTORY AND EXTRACT ==="
sudo install -d -m 0755 "$PANEL_DIR"
sudo tar -xzf "$ARCHIVE" -C "$PANEL_DIR"

echo
echo "=== CHECK REQUIRED PANEL FILES ==="
for required_file in artisan .env.example composer.json public/index.php; do
    if ! sudo test -f "$PANEL_DIR/$required_file"; then
        echo "ERROR: expected file is missing: $PANEL_DIR/$required_file" >&2
        exit 1
    fi
    echo "OK: $required_file"
done

echo
echo "=== SET INITIAL STORAGE / CACHE PERMISSIONS ==="
sudo chmod -R 755 "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"

echo
echo "=== PANEL DIRECTORY SUMMARY ==="
sudo ls -ld "$PANEL_DIR" "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"
sudo du -sh "$PANEL_DIR"
df -h /
~~~

### توضیح سادهٔ فرمان

- `set -eu`: در صورت خطای فرمان یا متغیر تعریف‌نشده، اجرای بلوک متوقف می‌شود.
- `PANEL_VERSION`: نسخهٔ دقیق را ثابت می‌کند تا نصب قابل تکرار باشد.
- بررسی وجود پوشه: مانع بازنویسی ناخواستهٔ نصب قبلی می‌شود.
- `curl --fail --location`: فایل را از Release رسمی دریافت می‌کند و خطای HTTP را موفق فرض نمی‌کند.
- `tar -tzf`: قبل از استخراج، بررسی می‌کند archive قابل خواندن است و فهرست فایل‌ها را نگه می‌دارد.
- `install -d`: پوشهٔ مقصد را می‌سازد؛ `tar` فایل‌ها را در آن استخراج می‌کند.
- آزمون چهار فایل: وجود فایل‌های اصلی برنامه، فایل نمونهٔ محیط، فهرست dependencyها و front controller را بررسی می‌کند.
- `chmod`: مجوز اولیهٔ Storage و Cache را تنظیم می‌کند؛ مالکیت نهایی در مرحلهٔ بعد اعمال می‌شود.

### خروجی مورد انتظار

- `Panel release: v1.15.1`
- دانلود archive و `tar -tzf` بدون خطا.
- چهار پیام `OK` برای `artisan`, `.env.example`, `composer.json`, `public/index.php`.
- نمایش مسیرهای Panel/Storage/Cache و اندازهٔ پوشه.

### اگر خطا دیدی

- اگر پیام `already exists` آمد، متوقف شو و پوشه را حذف نکن.
- اگر `curl`، `tar` یا آزمون فایل‌ها خطا داد، ادامه نده.
- اگر موفق شد، کل خروجی را بفرست. در این گام رمز یا secret ساخته نمی‌شود؛ پیش از ارسال خروجی هر اطلاعات خصوصی را بازبینی کن.

**این گام هنوز اجرا نشده است.** پس از دیدن خروجی، نتیجه را در Runbook، Debian Log، README، Roadmap و Change Log ثبت می‌کنیم؛ سپس سراغ Database و نصب dependencyهای PHP می‌رویم.


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

## ۱۱. گام بعدی: دریافت و استخراج Panel v1.15.1 — هنوز اجرا نشده

### چرا v1.15.1؟

در بازبینی ۱۷ اکتبر ۲۰۲۶، GitHub صفحهٔ release رسمی را با v1.15.1 به‌عنوان آخرین release پایدار 1.x نشان می‌دهد؛ مستندات رسمی، خط 1.x را Stable و 2.0 را Open Beta معرفی می‌کنند. asset رسمی همین tag برابر `panel.tar.gz` است. فایل `composer.json` این tag محدودهٔ PHP را `^8.2 || ^8.3` اعلام می‌کند و با PHP 8.3.35 نصب‌شده سازگار است. برای نصب تکرارپذیر از tag ثابت استفاده می‌کنیم، نه URL متغیر `latest/download`.

منابع رسمی:
- Release: https://github.com/pterodactyl/panel/releases/tag/v1.15.1
- فایل رسمی: https://github.com/pterodactyl/panel/releases/download/v1.15.1/panel.tar.gz
- Getting Started نسخهٔ 1.x: https://docs.pterodactyl.io/v1/panel/getting-started
- `composer.json` همان tag: https://raw.githubusercontent.com/pterodactyl/panel/v1.15.1/composer.json

### پیش از اجرا

این گام را در **ترمینال Debian ماشین `fc-node-01`** اجرا کن، نه در Windows. این مرحله فقط archive را دانلود، بررسی و استخراج می‌کند؛ database، رمز، `.env`، APP_KEY، Composer dependencies، Nginx virtual host، Docker یا Wings را نمی‌سازد.

اگر مسیر `/var/www/pterodactyl` از قبل وجود داشته باشد، اسکریپت عمداً متوقف می‌شود تا فایل‌های احتمالی قبلی بازنویسی نشوند. در آن صورت پوشه را حذف نکن؛ خروجی را بفرست تا اول بررسی شود.

### فرمان کامل مرحله

~~~bash
set -eu

PANEL_VERSION='v1.15.1'
PANEL_DIR='/var/www/pterodactyl'
ARCHIVE="/tmp/pterodactyl-panel-${PANEL_VERSION}.tar.gz"
FILE_LIST="/tmp/pterodactyl-panel-${PANEL_VERSION}-files.txt"
PANEL_URL="https://github.com/pterodactyl/panel/releases/download/${PANEL_VERSION}/panel.tar.gz"

echo "=== PRE-FLIGHT ==="
echo "Panel release: ${PANEL_VERSION}"
df -h /

if sudo test -e "$PANEL_DIR"; then
    echo "STOP: $PANEL_DIR already exists. No files were changed."
    sudo ls -la "$PANEL_DIR"
    exit 1
fi

echo
echo "=== DOWNLOAD OFFICIAL RELEASE ASSET ==="
curl --fail --location --show-error --output "$ARCHIVE" "$PANEL_URL"
test -s "$ARCHIVE"
ls -lh "$ARCHIVE"

echo
echo "=== VERIFY ARCHIVE CAN BE READ ==="
tar -tzf "$ARCHIVE" > "$FILE_LIST"
sed -n '1,15p' "$FILE_LIST"

echo
echo "=== CREATE TARGET DIRECTORY AND EXTRACT ==="
sudo install -d -m 0755 "$PANEL_DIR"
sudo tar -xzf "$ARCHIVE" -C "$PANEL_DIR"

echo
echo "=== CHECK REQUIRED PANEL FILES ==="
for required_file in artisan .env.example composer.json public/index.php; do
    if ! sudo test -f "$PANEL_DIR/$required_file"; then
        echo "ERROR: expected file is missing: $PANEL_DIR/$required_file" >&2
        exit 1
    fi
    echo "OK: $required_file"
done

echo
echo "=== SET INITIAL STORAGE / CACHE PERMISSIONS ==="
sudo chmod -R 755 "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"

echo
echo "=== PANEL DIRECTORY SUMMARY ==="
sudo ls -ld "$PANEL_DIR" "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"
sudo du -sh "$PANEL_DIR"
df -h /
~~~

### توضیح سادهٔ فرمان

- `set -eu`: در صورت خطای فرمان یا متغیر تعریف‌نشده، اجرای بلوک متوقف می‌شود.
- `PANEL_VERSION`: نسخهٔ دقیق را ثابت می‌کند تا نصب قابل تکرار باشد.
- بررسی وجود پوشه: مانع بازنویسی ناخواستهٔ نصب قبلی می‌شود.
- `curl --fail --location`: فایل را از Release رسمی دریافت می‌کند و خطای HTTP را موفق فرض نمی‌کند.
- `tar -tzf`: قبل از استخراج، بررسی می‌کند archive قابل خواندن است و فهرست فایل‌ها را نگه می‌دارد.
- `install -d`: پوشهٔ مقصد را می‌سازد؛ `tar` فایل‌ها را در آن استخراج می‌کند.
- آزمون چهار فایل: وجود فایل‌های اصلی برنامه، فایل نمونهٔ محیط، فهرست dependencyها و front controller را بررسی می‌کند.
- `chmod`: مجوز اولیهٔ Storage و Cache را تنظیم می‌کند؛ مالکیت نهایی در مرحلهٔ بعد اعمال می‌شود.

### خروجی مورد انتظار

- `Panel release: v1.15.1`
- دانلود archive و `tar -tzf` بدون خطا.
- چهار پیام `OK` برای `artisan`, `.env.example`, `composer.json`, `public/index.php`.
- نمایش مسیرهای Panel/Storage/Cache و اندازهٔ پوشه.

### اگر خطا دیدی

- اگر پیام `already exists` آمد، متوقف شو و پوشه را حذف نکن.
- اگر `curl`، `tar` یا آزمون فایل‌ها خطا داد، ادامه نده.
- اگر موفق شد، کل خروجی را بفرست. در این گام رمز یا secret ساخته نمی‌شود؛ پیش از ارسال خروجی هر اطلاعات خصوصی را بازبینی کن.

**این گام هنوز اجرا نشده است.** پس از دیدن خروجی، نتیجه را در Runbook، Debian Log، README، Roadmap و Change Log ثبت می‌کنیم؛ سپس سراغ Database و نصب dependencyهای PHP می‌رویم.


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

## ۱۱. گام بعدی: دریافت و استخراج Panel v1.15.1 — هنوز اجرا نشده

### چرا v1.15.1؟

در بازبینی ۱۷ اکتبر ۲۰۲۶، GitHub صفحهٔ release رسمی را با v1.15.1 به‌عنوان آخرین release پایدار 1.x نشان می‌دهد؛ مستندات رسمی، خط 1.x را Stable و 2.0 را Open Beta معرفی می‌کنند. asset رسمی همین tag برابر `panel.tar.gz` است. فایل `composer.json` این tag محدودهٔ PHP را `^8.2 || ^8.3` اعلام می‌کند و با PHP 8.3.35 نصب‌شده سازگار است. برای نصب تکرارپذیر از tag ثابت استفاده می‌کنیم، نه URL متغیر `latest/download`.

منابع رسمی:
- Release: https://github.com/pterodactyl/panel/releases/tag/v1.15.1
- فایل رسمی: https://github.com/pterodactyl/panel/releases/download/v1.15.1/panel.tar.gz
- Getting Started نسخهٔ 1.x: https://docs.pterodactyl.io/v1/panel/getting-started
- `composer.json` همان tag: https://raw.githubusercontent.com/pterodactyl/panel/v1.15.1/composer.json

### پیش از اجرا

این گام را در **ترمینال Debian ماشین `fc-node-01`** اجرا کن، نه در Windows. این مرحله فقط archive را دانلود، بررسی و استخراج می‌کند؛ database، رمز، `.env`، APP_KEY، Composer dependencies، Nginx virtual host، Docker یا Wings را نمی‌سازد.

اگر مسیر `/var/www/pterodactyl` از قبل وجود داشته باشد، اسکریپت عمداً متوقف می‌شود تا فایل‌های احتمالی قبلی بازنویسی نشوند. در آن صورت پوشه را حذف نکن؛ خروجی را بفرست تا اول بررسی شود.

### فرمان کامل مرحله

~~~bash
set -eu

PANEL_VERSION='v1.15.1'
PANEL_DIR='/var/www/pterodactyl'
ARCHIVE="/tmp/pterodactyl-panel-${PANEL_VERSION}.tar.gz"
FILE_LIST="/tmp/pterodactyl-panel-${PANEL_VERSION}-files.txt"
PANEL_URL="https://github.com/pterodactyl/panel/releases/download/${PANEL_VERSION}/panel.tar.gz"

echo "=== PRE-FLIGHT ==="
echo "Panel release: ${PANEL_VERSION}"
df -h /

if sudo test -e "$PANEL_DIR"; then
    echo "STOP: $PANEL_DIR already exists. No files were changed."
    sudo ls -la "$PANEL_DIR"
    exit 1
fi

echo
echo "=== DOWNLOAD OFFICIAL RELEASE ASSET ==="
curl --fail --location --show-error --output "$ARCHIVE" "$PANEL_URL"
test -s "$ARCHIVE"
ls -lh "$ARCHIVE"

echo
echo "=== VERIFY ARCHIVE CAN BE READ ==="
tar -tzf "$ARCHIVE" > "$FILE_LIST"
sed -n '1,15p' "$FILE_LIST"

echo
echo "=== CREATE TARGET DIRECTORY AND EXTRACT ==="
sudo install -d -m 0755 "$PANEL_DIR"
sudo tar -xzf "$ARCHIVE" -C "$PANEL_DIR"

echo
echo "=== CHECK REQUIRED PANEL FILES ==="
for required_file in artisan .env.example composer.json public/index.php; do
    if ! sudo test -f "$PANEL_DIR/$required_file"; then
        echo "ERROR: expected file is missing: $PANEL_DIR/$required_file" >&2
        exit 1
    fi
    echo "OK: $required_file"
done

echo
echo "=== SET INITIAL STORAGE / CACHE PERMISSIONS ==="
sudo chmod -R 755 "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"

echo
echo "=== PANEL DIRECTORY SUMMARY ==="
sudo ls -ld "$PANEL_DIR" "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"
sudo du -sh "$PANEL_DIR"
df -h /
~~~

### توضیح سادهٔ فرمان

- `set -eu`: در صورت خطای فرمان یا متغیر تعریف‌نشده، اجرای بلوک متوقف می‌شود.
- `PANEL_VERSION`: نسخهٔ دقیق را ثابت می‌کند تا نصب قابل تکرار باشد.
- بررسی وجود پوشه: مانع بازنویسی ناخواستهٔ نصب قبلی می‌شود.
- `curl --fail --location`: فایل را از Release رسمی دریافت می‌کند و خطای HTTP را موفق فرض نمی‌کند.
- `tar -tzf`: قبل از استخراج، بررسی می‌کند archive قابل خواندن است و فهرست فایل‌ها را نگه می‌دارد.
- `install -d`: پوشهٔ مقصد را می‌سازد؛ `tar` فایل‌ها را در آن استخراج می‌کند.
- آزمون چهار فایل: وجود فایل‌های اصلی برنامه، فایل نمونهٔ محیط، فهرست dependencyها و front controller را بررسی می‌کند.
- `chmod`: مجوز اولیهٔ Storage و Cache را تنظیم می‌کند؛ مالکیت نهایی در مرحلهٔ بعد اعمال می‌شود.

### خروجی مورد انتظار

- `Panel release: v1.15.1`
- دانلود archive و `tar -tzf` بدون خطا.
- چهار پیام `OK` برای `artisan`, `.env.example`, `composer.json`, `public/index.php`.
- نمایش مسیرهای Panel/Storage/Cache و اندازهٔ پوشه.

### اگر خطا دیدی

- اگر پیام `already exists` آمد، متوقف شو و پوشه را حذف نکن.
- اگر `curl`، `tar` یا آزمون فایل‌ها خطا داد، ادامه نده.
- اگر موفق شد، کل خروجی را بفرست. در این گام رمز یا secret ساخته نمی‌شود؛ پیش از ارسال خروجی هر اطلاعات خصوصی را بازبینی کن.

**این گام هنوز اجرا نشده است.** پس از دیدن خروجی، نتیجه را در Runbook، Debian Log، README، Roadmap و Change Log ثبت می‌کنیم؛ سپس سراغ Database و نصب dependencyهای PHP می‌رویم.

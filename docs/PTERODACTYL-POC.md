# FragCity — Pterodactyl PoC روی fc-node-01

> آخرین ثبت: ۱۷ اکتبر ۲۰۲۶  
> وضعیت: **آماده‌سازی محیط انجام شده؛ نصب Panel هنوز شروع نشده است.** این محیط VMware آزمایشی است و Production محسوب نمی‌شود.

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
- **PHP:** طبق composer.json نسخهٔ Panel، محدودیت Composer برابر ^8.2 || ^8.3 است. مستند نصب Pterodactyl 1.x برای Debian 13 بسته‌های PHP 8.3 را نشان می‌دهد؛ نسخه و extensionهای نهایی قبل از نصب با راهنمای جاری و composer check-platform-reqs تطبیق داده می‌شوند.
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

### اطلاعات ناقص که باید دوباره جمع‌آوری شوند

فرمان بررسی پورت‌ها به علت رد شدن رمز sudo دو بار کامل اجرا نشد. در این snapshot هنوز موارد زیر را نداریم:

- IP فعلی Debian
- فهرست دقیق پورت‌های listening و فرایندهای مالک آن‌ها
- تأیید احراز هویت sudo در نشست فعلی

قبل از نصب Nginx باید این موارد دوباره بررسی شوند.

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

## ۷. وضعیت همین لحظه

**انجام‌شده:** snapshot سیستم و منابع از خروجی کاربر ثبت شده؛ تصمیم اجرای PoC روی همان fc-node-01 ثبت شده؛ Flute CMS و cPanel database خارج از محدودهٔ نصب فعلی هستند.

**انجام‌نشده:** هیچ package مربوط به Panel، PHP، Nginx، MariaDB، Redis، Docker یا Wings نصب نشده؛ پورت‌ها و IP هنوز تأیید نشده‌اند؛ Panel، Node و CS2 container هنوز ساخته نشده‌اند.

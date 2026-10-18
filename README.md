# FragCity
## نیازمندی‌ها، معماری و نقشهٔ راه

> **وضعیت پروژه:** Panel آزمایشی Pterodactyl در LAN نصب شده و ورود مدیر تأیید شده است؛ Docker/Wings و اجرای واقعی CS2 تحت Panel هنوز Pending هستند. سایت Raw PHP همچنان برای تست دیتابیس محلی به جداسازی امن credentialها و seed مستقل نیاز دارد؛ پروژه Production نیست.  
> **آخرین به‌روزرسانی مستندات:** ۱۸ اکتبر ۲۰۲۶  
> **هدف:** ساخت سکوی فروش و مدیریت سرورهای CS2 با تکیه بر پروژه‌های متن‌باز بالغ و کمینه‌کردن کدنویسی اختصاصی.

FragCity قرار نیست صرفاً برنامهٔ C# فعلی را به Linux منتقل کند. هدف، ساخت یک محصول میزبانی سرور است که فروش، حساب مشتری، کیف پول، خرید بسته، ساخت خودکار سرور، مدیریت نودها، افزونه‌ها، API، امنیت و بازیابی را به‌شکل منسجم پوشش دهد.

## الزامات کلیدی محصول

این خلاصهٔ الزامات، نقطهٔ شروع تصمیم‌های فنی است. تعریف کامل و معیارهای پذیرش در [Requirements](docs/REQUIREMENTS.md) آمده است.

| ID | الزام | وضعیت |
|---|---|---|
| REQ-01 | مشتری کیف پولش را شارژ کند و هزینهٔ بسته را هنگام خرید، یک‌بار پرداخت کند | تأییدشده |
| REQ-02 | بسته‌های مدت‌دار: ۱، ۳، ۷، ۱۵ و ۳۰ روز | تأییدشده |
| REQ-03 | تایمر از خرید موفق شروع شود و با خاموش‌بودن سرور متوقف نشود | تأییدشده |
| REQ-04 | هنگام انقضا سرور طبق سیاست مشخص Suspend/Stop شود؛ حذف داده سیاست جدا داشته باشد | اصل مورد توافق؛ جزئیات نیازمند تصمیم |
| REQ-05 | ساخت و مدیریت instance تا حد ممکن با پنل آماده انجام شود، نه Instance Maker سفارشی | هدف معماری |
| REQ-06 | مدیریت چند نود با نصب تکرارپذیر و تنظیمات مجزای هر نود | تأییدشده |
| REQ-07 | ارتباط نود با سایت از HTTPS API باشد؛ Remote MariaDB روی 3306 از هاست فعلی مسیر قابل استفاده‌ای نیست | محدودیت فعلی تأییدشده |
| REQ-08 | دسترسی سرورها و داده‌های مشتریان از یکدیگر جدا باشد | الزام امنیتی |
| REQ-09 | Backup/Restore، لاگ، پایش، retry و جلوگیری از عملیات تکراری | الزام عملیاتی |
| REQ-10 | بهره‌گیری از ریپوهای آماده و نگهداری‌پذیر؛ توسعهٔ اختصاصی فقط برای شکاف واقعی محصول | جهت‌گیری اصلی |

## انتخاب فعلی معماری

**مسیر مرجع برای PoC:** **Pterodactyl Panel + Wings + Paymenter**

این ترکیب هنوز انتخاب قطعی تولید نیست. اول باید ثابت کنیم که ایجاد CS2 instance، تخصیص منابع، اتصال فروش، خرید بسته با کیف پول، ثبت `expires_at` و رفتار زمان انقضا درست کار می‌کند. اگر بخش مدت‌دار در Paymenter به افزونه نیاز داشته باشد، ترجیح این است که شکاف را با extension محدود پر کنیم و هستهٔ محصول را بی‌دلیل fork نکنیم.

**گزینهٔ پشتیبان مقایسه‌ای:** GameAP به‌همراه یک سامانهٔ صورتحساب. تا زمانی که مسیر اصلی مانع واقعی نشان نداده، نباید چند پلتفرم را هم‌زمان نصب و عملیاتی کنیم.

## ریپوهای اصلی

جدول کامل پروژه‌های متعلق به FragCity، گزینه‌های متن‌باز، نقش و وضعیتشان در [Repository Inventory](docs/REPOSITORIES.md) قرار دارد.

| ریپو | نقش | وضعیت در FragCity |
|---|---|---|
| [FragCity-RawPHPbackend](https://github.com/larnzs/FragCity-RawPHPbackend) | سایت PHP فعلی و مستندات معماری | موجود؛ بازبینی لازم |
| [FragCity-WebAPIClient](https://github.com/larnzs/FragCity-WebAPIClient) | Agent/Client فعلی C# و منطق آزمایشی | موجود؛ نمونهٔ اولیه، نه هستهٔ نهایی |
| [cs2-kus-addons](https://github.com/larnzs/cs2-kus-addons) | زیرساخت افزونه‌ها و کانفیگ‌های فعلی CS2 | مستقل و محافظت‌شده؛ در این پروژه تغییر نمی‌کند |
| [pterodactyl/panel](https://github.com/pterodactyl/panel) + [pterodactyl/wings](https://github.com/pterodactyl/wings) | مدیریت سرورها و نودهای بازی | نامزد اول PoC |
| [Paymenter/Paymenter](https://github.com/Paymenter/Paymenter) | فروشگاه، حساب مشتری، صورتحساب و اتصال به Pterodactyl | نامزد اول PoC |
| [gameap/gameap](https://github.com/gameap/gameap) | پنل مدیریت بازی جایگزین | گزینهٔ مقایسه‌ای |

## نقشهٔ راه در یک نگاه

| فاز | خروجی | وضعیت |
|---|---|---|
| ۰. مستندسازی و محیط توسعهٔ محلی | Requirements، معماری، updater محلی، راهنمای Laragon/WAMP | مستندات آماده؛ نصب محلی و جداسازی DB Pending |
| ۱. ممیزی و خط مبنا | بررسی نسخه‌ها، امنیت، داده‌ها و محدودیت‌های میزبانی | ممیزی ایستا اولیه انجام شده؛ اصلاحات Pending |
| ۲. PoC مدیریت سرور | یک نود آزمایشی، یک سرور CS2، منابع و چرخهٔ عمر | Panel در LAN نصب و ورود مدیر تأیید شده؛ Docker/Wings و سرور CS2 تحت مدیریت Panel هنوز Pending |
| ۳. اتصال فروش | سفارش آزمایشی → Provisioning از طریق API پنل | Pending |
| ۴. منطق کیف پول و بسته | کسر یک‌باره، ثبت و تمدید `expires_at`، رفتار انقضا | Pending؛ شرط مسدودکنندهٔ تولید |
| ۵. امنیت و بازیابی | جداسازی مشتریان، Backup/Restore، retry، جلوگیری از تکرار | Pending |
| ۶. داده‌های پلاگین و API | قرارداد HTTPS امن و همگام‌سازی فقط جداول مشخص | Pending |
| ۷. نود دوم و ظرفیت | نصب تکرارپذیر، اندازه‌گیری ۱/۳/۵ سرور و سپس رشد | Pending |
| ۸. مهاجرت محدود | انتقال کنترل‌شده و قابل بازگشت پس از قبولی آزمون‌ها | Pending |

جزئیات فازها و معیار خروج در [Roadmap](docs/ROADMAP.md) است. ماتریس آزمون قابل اجرا در [Platform PoC](docs/PLATFORM-POC.md) قرار دارد.

## اسناد پروژه

| سند | محتوا |
|---|---|
| [Requirements](docs/REQUIREMENTS.md) | نیازهای عملکردی/امنیتی/عملیاتی و معیار پذیرش |
| [Architecture](docs/ARCHITECTURE.md) | معماری اجزا، مرز مسئولیت‌ها و مدل نودها |
| [Repository Inventory](docs/REPOSITORIES.md) | جدول ریپوهای داخلی و متن‌باز، نقش، وضعیت و ریسک |
| [Roadmap](docs/ROADMAP.md) | فازهای اجرا، وابستگی‌ها و نقاط توقف |
| [Platform PoC](docs/PLATFORM-POC.md) | گزینه‌ها، نسخه‌های کاندید و ماتریس آزمون |
| [CS2 Multi-Instance](docs/CS2-MULTI-INSTANCE.md) | معماری چند سرور، گزینه‌های اشتراک فایل بازی و بررسی ریپوهای GitHub |
| [Testing Bench](docs/TESTING-BENCH.md) | برنامهٔ کامل آزمون ابزارهای CS2 بر اساس Linux، Windows و Cross-platform؛ ماتریس و ثبت نتایج |
| [Probably and Suggestions](docs/PROBABLY-AND-SUGGESTIONS.md) | فرضیه‌ها و پیشنهادهای موقت، اولویت‌های بررسی و معیار تغییر پیشنهاد |
| [Architecture Decisions](docs/DECISIONS.md) | تصمیم‌های ثبت‌شده، فرضیه‌ها و پرسش‌های باز |
| [Database & API](docs/DATABASE-API.md) | وضعیت اتصال دیتابیس، API فعلی و مسیر همگام‌سازی |
| [Security Review](docs/SECURITY-AUDIT.md) | یافته‌های ممیزی ایستا و ترتیب اصلاح |
| [Debian PoC Log](docs/DEBIAN-POC.md) | مشخصات واقعی VM، فرمان‌های اجراشده، وضعیت و هشدارهای عملیاتی |
| [Pterodactyl PoC (تاریخی/تحلیلی)](docs/PTERODACTYL-POC.md) | بررسی‌های اولیه، فرضیه‌ها و سابقهٔ تصمیم‌ها؛ برای وضعیت اجرا به Runbook LAN مراجعه کنید |
| [Pterodactyl LAN Setup](docs/PTERODACTYL-LAN-SETUP.md) | راهنمای مبتدی‌پسند نصب واقعی Panel در LAN، دیتابیس، APP_KEY، migration، NGINX، خطاها و وضعیت تأییدشده |
| [Pterodactyl End-to-End Guide](docs/PTERODACTYL-END-TO-END-GUIDE.md) | یک راهنمای پیوسته و شماره‌گذاری‌شده از بررسی Debian و نصب Panel تا خطاهای واقعی، SSH/HeidiSQL، دیسک و گام نصب Docker |
| [Pterodactyl Session Command Log](docs/PTERODACTYL-SESSION-COMMAND-LOG.md) | دفتر زمانی فرمان‌های واقعی/تشخیصی، نتایج ثبت‌شده، خطاها و اصلاح‌ها، تنظیمات LAN و تفکیک موارد اجراشده از Pending |
| [Wings & Docker Install](docs/WINGS-INSTALL.md) | فصل مستقل Docker/Wings: مفاهیم، نصب Engine، Node، config، systemd، Allocation، محدودیت دیسک و مراحل LAN |
| [Local Development](docs/LOCAL-DEVELOPMENT.md) | updater محلی، نصب Laragon/WAMP و موانع امن تست دیتابیس |
| [Current Status](docs/CHANGELOG-STATUS.md) | وضعیت جاری، انتخاب‌های انجام‌شده و مواردی که هنوز اجرا نشده‌اند |
| [Documentation Audit](docs/DOCUMENTATION-AUDIT.md) | محدودهٔ بازبینی مستندات، اصلاحات، پیوندهای بررسی‌شده و استاندارد آموزش مبتدی‌پسند |


## Quick Setup — راه‌اندازی زیرساخت FragCity از صفر تا اولین سرور

این بخش **نقطهٔ شروع اجرایی** مخزن است. برای مسیر کامل فرمان‌به‌فرمان از ابتدا تا مرحلهٔ فعلی، [راهنمای یکپارچهٔ End-to-End](docs/PTERODACTYL-END-TO-END-GUIDE.md) را باز کن؛ جدول زیر نقشهٔ فصل‌ها و وضعیت آنهاست. جزئیات کامل هر خطا و سابقهٔ گفتگو در [دفتر فرمان‌ها](docs/PTERODACTYL-SESSION-COMMAND-LOG.md) است.

> **محیط فعلی فقط LAN است.** Panel از Windows با `http://192.168.1.5` باز می‌شود؛ دامنهٔ واقعی، HTTPS، port forwarding عمومی و فایروال WAN هنوز تنظیم نشده‌اند. هیچ secret، رمز، APP_KEY یا token در README یا Git قرار ندهید.

### نقشهٔ فصل‌ها

| فصل | موضوع | وضعیت فعلی |
|---|---|---|
| ۰ | دانلود Debian ISO، ساخت VM در VMware و نصب سیستم‌عامل | نیازمند تدوین مستقل در آینده |
| ۱ | راه‌اندازی اولیهٔ Debian، شبکه، SSH، به‌روزرسانی و آماده‌سازی فایل‌های موجود | نیازمند تدوین مستقل در آینده؛ وضعیت واقعی VM در [Debian PoC Log](docs/DEBIAN-POC.md) ثبت شده |
| ۲ | نصب Pterodactyl Panel در LAN | **تکمیل شد؛ ورود مدیر از Windows تأیید شده** — [راهنمای فصل Panel](docs/PTERODACTYL-LAN-SETUP.md) |
| ۳ | Docker Engine و Wings، اتصال Node به Panel | **در حال انجام؛ Docker هنوز نصب نشده** — [راهنمای فصل Wings](docs/WINGS-INSTALL.md) |
| ۴ | ادغام `cs2-kus-addons` و فایل‌های موجود CS2 با مدل Egg/Container/Volume در Pterodactyl | Pending؛ فصل پیچیدهٔ بعد از Wings |
| ۵ | دامنه، DNS، HTTPS، NGINX، firewall، VMware/Router port-forwarding و دسترسی عمومی بازی‌ها | Pending؛ جدا از مراحل LAN |

### معماری موردنظر

- **Panel** رابط وب مدیریتی است.
- **Wings** سرویس روی Node است که با Panel ارتباط می‌گیرد.
- **Docker Engine** محیط‌های container را برای اجرای نمونه‌های سرور بازی ایجاد و کنترل می‌کند.
- **Egg** الگوی نصب و اجرای یک نوع بازی است.
- یک **Node** می‌تواند چند server instance را میزبانی کند؛ برای هر سرور بازی روی همان VM الزاماً Node جدا لازم نیست.
- **سرور بازی** به IP و Allocation/port خود متصل می‌شود. DNS مثل `server1.example` به‌تنهایی port متفاوتی انتخاب نمی‌کند. برای چند instance روی یک IP، Allocationهای port مهم‌اند؛ اگر هر server1/server2 ماشین جدا باشد، هرکدام Node خود را دارند.

### وضعیت فعلی تأییدشده: `fc-node-01`

| مورد | نتیجه |
|---|---|
| OS / kernel | Debian 13 (Trixie), `6.12.113+deb13-amd64` |
| VM/CPU | VMware، معماری `x86_64/amd64`، چهار vCPU |
| RAM | حدود 5.6 GiB؛ در snapshot حدود 3 GiB available |
| Disk root | 112 GiB کل، حدود 80 GiB مصرف‌شده، 27 GiB آزاد |
| Panel URL | `http://192.168.1.5`؛ LAN-only، HTTP |
| Panel path | `/var/www/pterodactyl`; web root: `/var/www/pterodactyl/public` |
| PHP/Composer | PHP 8.3.35؛ Composer 2.10.3 |
| Database/cache | MariaDB 11.8.6 و Redis فعال؛ DB مخصوص Panel با نام `panel` و user `pterodactyl` |
| NGINX/PHP-FPM | فعال؛ listener وب روی TCP/80؛ PHP-FPM 8.3 فعال |
| Panel schema/admin | migration/seed موفق، حساب Administrator ساخته شده و ورود از Windows تأیید شده |
| Egg CS2 | SQL فعلاً فقط `Counter-Strike: Global Offensive` را نشان داده؛ Egg اختصاصی CS2 هنوز اضافه/تأیید نشده |
| Docker/Wings | هنوز نصب نشده‌اند |
| Storage بازی | حدود 71 GiB در `/home/steam` شامل فایل‌های موجود CS2/KUS؛ باید حفظ شود |
| دسترسی عمومی | هیچ تنظیم دامنه/TLS/Port Forwarding جدیدی انجام نشده |

**هشدار نسخه:** یک سند تاریخی از Panel `v1.15.1` نام می‌برد، اما لاگ نصب فعلی Laravel `12.64.0` را گزارش کرده و Git metadata در پوشهٔ نصب وجود نداشت. نسخهٔ دقیق Panel هنوز باید از نصب واقعی تأیید شود. پیش از انتخاب باینری Wings نسخه‌ها را تطبیق دهید؛ Laravel version به‌تنهایی نسخهٔ Panel را ثابت نمی‌کند.

## Quick Setup: اجرای مرحله‌به‌مرحله

مراحل زیر خلاصهٔ مسیر نصب‌اند. **روی همین VM، مراحل دارای `[x]` را بی‌دلیل دوباره اجرا نکن.** برای نصب تازه باید نسخهٔ رسمی و ثابت Panel را ابتدا انتخاب و pin کرد و سپس کل runbook را از ابتدا دنبال کرد.

### مرحله 1 — سیستم‌عامل، منابع و شبکه

**هدف:** بدانیم روی کدام سیستم کار می‌کنیم و کدام IP/منابع برای Node در دسترس‌اند.

```bash
cat /etc/os-release
hostname
hostname -I
systemd-detect-virt || true
uname -m
free -h
df -h /
```

**وضعیت:** [x] انجام و ثبت شده. IP LAN رزروشدهٔ Debian برابر `192.168.1.5` است. از Windows برای ورود SSH می‌توان از `ssh fragcityadmin@192.168.1.5` استفاده کرد.

### مرحله 2 — PHP، NGINX، MariaDB، Redis و Composer

**هدف:** فراهم‌کردن runtime وب و ابزارهای لازم Panel.

- PHP 8.3.35 و extensionهای بررسی‌شده نصب شده‌اند.
- MariaDB و Redis فعال‌اند؛ MariaDB روی `127.0.0.1:3306` و Redis روی loopback باقی می‌مانند.
- Composer 2.10.3 نصب و بررسی شده است.
- سرویس PHP-FPM 8.3 و NGINX فعال‌اند.

**وضعیت:** [x] انجام شده. فهرست بسته‌ها و فرمان‌های تاریخی را در [Debian PoC Log](docs/DEBIAN-POC.md) ببینید.

### مرحله 3 — فایل‌های Panel، ownership و Composer

**هدف:** قرار دادن کد Panel در مسیر وب، نصب dependencyها و جلوگیری از خطای write permission.

مسیر کد: `/var/www/pterodactyl`. پنل از پوشهٔ `public` سرو می‌شود؛ فایل `.env` نباید مستقیماً از وب در دسترس باشد.

پس از استخراج archive رسمی:

```bash
cd /var/www/pterodactyl
sudo chown -R www-data:www-data .
sudo find storage bootstrap/cache -type d -exec chmod 750 {} +
sudo find storage bootstrap/cache -type f -exec chmod 640 {} +
```

**معنی:** `chown` مالک/گروه را تنظیم می‌کند؛ `chmod` مجوز خواندن/نوشتن/ورود را کنترل می‌کند. `750` برای پوشه و `640` برای فایل، دسترسی نوشتن را برای owner محدود می‌کند. از `777` استفاده نکن.

روی نصب تازه، فقط اگر `.env` وجود ندارد:

```bash
sudo cp .env.example .env
sudo chown www-data:www-data .env
sudo chmod 640 .env
sudo -u www-data /usr/local/bin/composer install --no-dev --optimize-autoloader
sudo -u www-data php8.3 artisan key:generate
```

**وضعیت:** [x] انجام شده و dependencyهای Composer نصب شده‌اند. `APP_KEY` را دوباره generate نکن و `.env` را overwrite نکن. توضیح کامل: [Pterodactyl LAN Setup](docs/PTERODACTYL-LAN-SETUP.md).

### مرحله 4 — دیتابیس و تنظیم محیط Panel

**هدف:** جداسازی داده‌های Panel از دیتابیس سایت FragCity و تنظیم آدرس، cache، session و queue.

در نصب تازه، پس از بررسی اینکه دیتابیس/user از قبل وجود ندارند، ورود محلی به MariaDB:

```bash
sudo env MYSQL_HISTFILE=/dev/null mariadb
```

داخل MariaDB، `panel` بساز و برای `pterodactyl`@ `127.0.0.1` رمز منحصربه‌فرد تعیین کن؛ نمونهٔ کامل SQL در [Pterodactyl LAN Setup](docs/PTERODACTYL-LAN-SETUP.md) است. هیچ رمز واقعی را در Git/چت قرار نده.

سپس:

```bash
cd /var/www/pterodactyl
sudo -u www-data php8.3 artisan p:environment:setup
sudo -u www-data php8.3 artisan p:environment:database
```

مقادیر فعلی ثبت‌شده: URL `http://192.168.1.5`، timezone `Asia/Tehran`، Cache/Session/Queue روی Redis، Redis host `127.0.0.1:6379` و DB host `127.0.0.1:3306` با DB `panel` و user `pterodactyl`. Redis Password در خروجی wizard خالی ثبت شد؛ اگر خود سرویس Redis را با رمز پیکربندی می‌کنی، باید هر دو سمت هماهنگ شوند.

**وضعیت:** [x] wizardها تکمیل شدند.

### مرحله 5 — Migration، seed و مدیر Panel

```bash
cd /var/www/pterodactyl
sudo -u www-data php8.3 artisan migrate --seed --force
sudo -u www-data php8.3 artisan p:user:make
```

- Migration/seed، جدول‌ها و داده‌های اولیهٔ Nest/Egg را ساخته است.
- حساب Administrator ساخته شده است.
- این دو فرمان روی نصب فعلی دوباره اجرا نشوند مگر اینکه دلیل مشخصی وجود داشته باشد.

**وضعیت:** [x] موفق.

### مرحله 6 — NGINX و ورود از Windows

NGINX باید root را روی `/var/www/pterodactyl/public` بگذارد و PHP را به socket `/run/php/php8.3-fpm.sock` بفرستد. فایل فعال فعلی `/etc/nginx/sites-available/pterodactyl.conf` است.

پس از هر تغییر:

```bash
sudo /usr/sbin/nginx -t
sudo systemctl reload nginx
sudo systemctl is-active nginx
```

در Windows باز کنید: <http://192.168.1.5>

**وضعیت:** [x] Panel باز شده و login موفق بوده است. این مرحله فقط LAN و HTTP است؛ هنوز HTTPS یا دسترسی WAN نداریم. Queue Worker و Scheduler/Cron باید پیش از PoC پایدار و قطعاً پیش از Production جداگانه بررسی/تکمیل شوند.

### مرحله 7 — نصب Docker و آماده‌سازی Wings (گام بعدی)

**Docker چیست؟** Engine اجرای containerهاست. Wings از آن درخواست ساخت/اجرای/توقف containerهای بازی را می‌کند. Docker خود Panel، Wings یا سرور CS2 نیست؛ لایهٔ runtime است.

ابتدا preflight بررسی شد: Debian 13 amd64، VMware، چهار vCPU، RAM حدود 5.6 GiB؛ Docker و repository آن هنوز نصب نشده‌اند، و بسته‌های متعارض بررسی‌شده پیدا نشدند.

در آخرین preflight، فایل repository `/etc/apt/sources.list.d/docker.sources` وجود نداشت. **این بلوک مرحلهٔ نصب بعدی است؛ هنوز روی VM اجرا نشده.** منبع و روش نصب رسمی: <https://docs.docker.com/engine/install/debian/>.

```bash
set -eu

sudo apt update
sudo apt install -y ca-certificates curl
sudo install -m 0755 -d /etc/apt/keyrings

if ! sudo test -s /etc/apt/keyrings/docker.asc; then
    sudo curl -fsSL https://download.docker.com/linux/debian/gpg \
        -o /etc/apt/keyrings/docker.asc
fi
sudo chmod a+r /etc/apt/keyrings/docker.asc

if sudo test -e /etc/apt/sources.list.d/docker.sources; then
    echo "STOP: docker.sources already exists; inspect it before editing."
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

echo "=== VERIFY ENGINE ==="
sudo systemctl is-active docker
sudo docker info
sudo docker run --rm hello-world
df -h /
```

**این فرمان‌ها چه می‌کنند؟** `apt update` فهرست بسته‌ها را تازه می‌کند؛ ابزارهای `ca-certificates` و `curl` برای دانلود HTTPS لازم‌اند؛ فایل `docker.asc` کلید عمومی امضای مخزن است؛ `docker.sources` آدرس، suite و کلید امضای مخزن را برای APT مشخص می‌کند؛ سپس Engine/CLI/container runtime نصب می‌شود. `systemctl enable --now docker` سرویس را اکنون فعال و برای بوت‌های بعدی هم روشن می‌کند. `docker info` ارتباط CLI با daemon را بررسی می‌کند و `hello-world` یک container آزمایشی اجرا می‌کند؛ این تست CS2 را دانلود نمی‌کند.

اگر مخزن `docker.sources` پیش از اجرا وجود داشت، به‌جای اجرای دوباره یا overwrite کردن، فایل را بررسی و با دستورهای راهنمای فصل Wings هماهنگ کن. این نصب ممکن است روی قوانین شبکهٔ میزبان اثر بگذارد؛ فعلاً port forwarding عمومی تعریف نکن.

**وضعیت:** [ ] Pending. برای توضیح مفصل‌تر مفاهیم Docker و تفسیر هر فرمان به [Wings & Docker Install](docs/WINGS-INSTALL.md) بروید. فعلاً کاربر `fragcityadmin` را به گروه `docker` اضافه نکن؛ از `sudo docker` استفاده کن.

### مرحله 8 — ساخت Node و نصب Wings

پس از موفقیت Docker، نسخهٔ دقیق Panel را تأیید کن، سپس از release سازگار Wings انتخاب کن. Node باید از Admin UI پنل ساخته شود؛ آدرس تست LAN فعلی `192.168.1.5` است. پورت‌های پیش‌فرض مورد انتظار Wings معمولاً TCP/8080 و SFTP/2022 هستند، اما باید با UI/config همان نسخه تأیید شوند.

`config.yml` را از صفحهٔ Configuration همان Node تولید کن. این فایل token/secret دارد: در Git، README یا چت قرار نده. Wings را ابتدا foreground/debug آزمایش کن، سپس طبق مستندات همان نسخه systemd service بساز.

**وضعیت:** [ ] Pending.

### مرحله 9 — Egg/Allocation و اولین سرور بازی

بررسی DB نشان داد تنها Egg نام‌برده‌شدهٔ CS فعلاً `Counter-Strike: Global Offensive` است؛ Egg مخصوص CS2 هنوز وارد/تأیید نشده است. پس از Wings، Egg مخصوص CS2 باید از منبع معتبر اضافه و سپس Allocationهای واقعی تنظیم شوند.

**وضعیت:** [ ] Pending.

### مرحله 10 — ادغام فایل‌های موجود CS2/KUS (فصل ۴)

آخرین اندازه‌گیری دیسک: 112 GiB کل، حدود 80 GiB مصرف‌شده، 27 GiB آزاد؛ `/home/steam` حدود 71 GiB دارد و شامل فایل‌های موجود CS2/KUS است.

**حذف، جابه‌جایی یا پاک‌سازی `/home/steam` ممنوع است.** Docker به‌طور خودکار این فایل‌ها را به containerهای Pterodactyl متصل نمی‌کند. Egg استاندارد CS2 فضای بیشتری از فضای آزاد فعلی می‌خواهد؛ قبل از ساخت server instance، راه استفادهٔ مجدد از Base Game/فایل‌های موجود باید طراحی و آزمایش شود. این همان کار فصل ۴ است؛ هیچ تغییری در ریپوی `cs2-kus-addons` بدون درخواست صریح انجام نمی‌شود.

**وضعیت:** [ ] Pending.

### مرحله 11 — دامنه و دسترسی عمومی (فصل شبکهٔ آینده)

این بخش فعلاً انجام نشده:

- تعریف دامنهٔ Panel (مثلاً `panel.fragcity.ir`) و TLS/HTTPS.
- تعریف نام‌های DNS برای Nodeها و در صورت نیاز نام‌های قابل‌حفظ برای سرورهای بازی.
- NGINX، گواهی، Firewall، قواعد Docker/iptables، VMware bridged networking، روتر و Port Forwarding.
- انتخاب پورت و protocol هر Egg براساس پیکربندی واقعی بازی.

**هشدار:** DNS hostname به‌تنهایی port را انتخاب نمی‌کند. چند سرور روی یک Node می‌توانند از یک IP و portهای مجزا استفاده کنند؛ Node جدا فقط برای ماشین جدا لازم است. MariaDB/Redis را برای بازیکن یا اینترنت عمومی باز نکنید.

## راهنمای تفصیلی

| سند | برای چه کاری؟ |
|---|---|
| [Pterodactyl LAN Setup](docs/PTERODACTYL-LAN-SETUP.md) | راهنمای دقیق نصب Panel و رفع خطاهای واقعی |
| [Wings & Docker Install](docs/WINGS-INSTALL.md) | آموزش Docker، نصب Engine/Wings و راه‌اندازی Node |
| [Debian PoC Log](docs/DEBIAN-POC.md) | سابقه و شواهد اجرایی Debian/VMware |
| [Pterodactyl PoC (historical)](docs/PTERODACTYL-POC.md) | بررسی‌ها و طرح اولیهٔ نسخه‌ها؛ به‌دلیل تاریخچهٔ قدیمی، status آن با snapshot فعلی تطبیق داده شود |
| [CS2 Multi-Instance](docs/CS2-MULTI-INSTANCE.md) | طراحی Base Game/instance و فایل‌های محافظت‌شدهٔ KUS |
| [Current Status](docs/CHANGELOG-STATUS.md) | آخرین تغییرات و وضعیت کلی پروژه |
| [Roadmap](docs/ROADMAP.md) | مسیر محصول و معیار خروج هر فاز |

## پیکربندی مرکزی

تنظیمات محیطی و پروفایل‌های دیتابیس از `settings/settings.json` خوانده می‌شوند. نمونهٔ بدون secret در `settings/settings.example.json` است و فایل واقعی در Git نادیده گرفته می‌شود. هنگام استفاده از Nginx، مسیر `/settings/` باید مسدود شود. جزئیات در [Configuration Guide](docs/CONFIGURATION.md) آمده است.

## گردش‌کار توسعهٔ محلی

**پیش‌فرض کار از اینجا به بعد: کد را ابتدا محلی تست می‌کنیم و انتشار روی cPanel جداگانه و دستی است.** فایل ریشهٔ [update.bat](update.bat) فقط شاخهٔ `main` را انتخاب می‌کند و `git pull --ff-only origin main` اجرا می‌کند؛ اگر working tree محلی تغییر commit‌نشده یا فایل untracked داشته باشد، متوقف می‌شود. این اسکریپت چیزی را روی هاست منتشر نمی‌کند و در Debian VM هم اجرا نمی‌شود.

برای محیط وب، **Laragon** انتخاب پیشنهادی است و WAMP جایگزین قابل قبول آن است. راهنمای [Local Development](docs/LOCAL-DEVELOPMENT.md) مسیر clone، VirtualHost و جریان تست را توضیح می‌دهد. با این حال **تست کامل صفحات وابسته به دیتابیس هنوز نباید شروع شود**: secretهای دیتابیس در سورس فعلی وجود دارند و جداسازی تنظیمات محلی از Production، به‌همراه یک seed مستقل، پیش‌نیاز امن اجراست. قوانین جدید `.gitignore` هم فایل‌هایی را که از قبل در Git track شده‌اند خودکار از مخزن خارج نمی‌کند.

## قواعد محافظتی پروژه

1. **هیچ تغییری در `cs2-kus-addons` ایجاد نمی‌شود** مگر اینکه کاربر جداگانه درخواست صریح بدهد.
2. روی دیتابیس تولیدی PoC انجام نمی‌شود؛ از حساب، سفارش، سرور و دادهٔ آزمایشی استفاده خواهد شد.
3. محصول آماده قبل از تست نسخهٔ دقیق، مجوز، Security Advisory، Backup و Rollback وارد محیط واقعی نمی‌شود.
4. secretها، رمزها، کلیدهای API و اطلاعات مشتری در Git، README یا لاگ عمومی قرار نمی‌گیرند.
5. هیچ انتخابی صرفاً با تعداد ستارهٔ GitHub انجام نمی‌شود؛ API، بلوغ، امنیت، قابلیت ارتقا، صورتحساب و آزمون عملی معیار تصمیم‌اند.
6. حذف یا بازنویسی هیچ‌کدام از ریپوهای موجود بخشی از فاز مستندسازی نیست.

**وضعیت نهایی فعلی:** dependencyهای پایه و Composer 2.10.3 روی `fc-node-01` نصب و بررسی شده‌اند؛ فایل‌های Panel v1.15.1 دریافت و استخراج شده‌اند. اصلاح مالکیت، database/user، `.env`، Composer dependencies، APP_KEY، migrations، Nginx/queue/cron، Wings/Docker و اجرای CS2 همچنان Pending هستند. اصلاح کدهای امنیتی نیز برنامه و تست مجزا می‌خواهد.


---

## راهنمای مرحله‌به‌مرحله: اجرای Flute CMS در Laragon Portable با Nginx

این بخش تجربهٔ واقعی راه‌اندازی اولیهٔ Flute در محیط محلی FragCity را ثبت می‌کند. علت و راه‌حل هر خطا را نگه می‌داریم تا هنگام نصب مجدد یا انتقال به سیستم دیگر، مراحل قابل تکرار باشند.

**وضعیت ثبت‌شده:** ریشهٔ سایت روی پوشهٔ `public` تنظیم شد؛ سپس خطای Nginx `404 Not Found` در مسیر `/install` با اصلاح `try_files` برطرف شد.

### مرحلهٔ ۰ — پیش‌نیازها و فرض مسیرها

در این راهنما فرض می‌شود Laragon Portable در مسیر زیر است و فایل‌های Flute مستقیماً در `www` قرار دارند:

- ریشهٔ پروژهٔ Flute: `W:\laragon_portable\www`
- ریشهٔ قابل دسترسی از مرورگر: `W:\laragon_portable\www\public`
- تنظیمات Nginx: `W:\laragon_portable\etc\nginx`
- پوشهٔ Virtual Hostها: `W:\laragon_portable\etc\nginx\sites-enabled`

اگر پروژه داخل یک زیرپوشه مانند `www\FragCity-RawPHPbackend` کلون شده است، مسیرهای این راهنما باید با مسیر واقعی پروژه تطبیق داده شوند؛ `root` همیشه باید به پوشهٔ `public` همان پروژه اشاره کند.

Laragon Portable در محیط ثبت‌شده از **Nginx** استفاده می‌کند؛ مراحل Apache برای این نصب کاربرد ندارند.

### مرحلهٔ ۱ — دریافت بستهٔ درست Flute (Release، نه Source)

مخزن توسعهٔ FragCity از این branch استفاده می‌کند:

- مخزن: [FragCity-RawPHPbackend](https://github.com/larnzs/FragCity-RawPHPbackend)
- branch پایهٔ Flute: [`flute-base`](https://github.com/larnzs/FragCity-RawPHPbackend/tree/flute-base)
- نسخهٔ Release ثبت‌شده: [Flute CMS v1.0.7](https://github.com/Flute-CMS/cms/releases/tag/v1.0.7)

**نکتهٔ مهم دربارهٔ `vendor`:** دریافت Source code از GitHub معمولاً دایرکتوری `vendor` را همراه ندارد؛ اما بستهٔ Release مورد استفاده در این راهنما شامل وابستگی‌های آمادهٔ Composer است. در branch `flute-base` وجود `vendor/autoload.php` و اطلاعات نصب‌شده در `vendor/composer/installed.php` بررسی شده است.

برای clone کردن branch پایه:

```bash
git clone --branch flute-base --single-branch https://github.com/larnzs/FragCity-RawPHPbackend.git
```

این مخزن خصوصی است؛ هنگام clone در صورت درخواست GitHub Authentication از روش احراز هویت مجاز استفاده شود.

پس از clone این فایل را بررسی کنید:

```text
vendor/autoload.php
```

اگر این فایل موجود نیست، قبل از تغییر تنظیمات Nginx بررسی کنید که branch و بستهٔ درست دریافت شده باشد. صرفاً با تغییر DocumentRoot نمی‌توان نبودن `vendor` را حل کرد. برای این baseline، هدف استفاده از بستهٔ کامل Release است؛ `composer update` هم برای حل این وضعیت اجرا نشود، چون وابستگی‌ها را تغییر می‌دهد.

### مرحلهٔ ۲ — تنظیم Document Root روی `public`

خطای نصب Flute ممکن است اعلام کند که وب‌سرور به ریشهٔ اشتباه اشاره می‌کند و مسیر فعلی `W:\laragon_portable\www` است، درحالی‌که مسیر لازم `W:\laragon_portable\www\public` است.

در این پروژه باید ریشهٔ وب فقط پوشهٔ `public` باشد. تنظیم در Nginx انجام می‌شود و نباید پوشهٔ اصلی پروژه یا فایل‌های `vendor` و تنظیمات خصوصی مستقیماً در دسترس وب باشند.

### مرحلهٔ ۳ — پیدا کردن Virtual Host مربوط به `localhost`

۱. این پوشه را با File Explorer باز کنید:

```text
W:\laragon_portable\etc\nginx\sites-enabled
```

۲. فایل تنظیماتی را باز کنید که داخل آن این مقدار وجود دارد:

```nginx
server_name localhost;
```

نام فایل ممکن است بسته به نصب Laragon متفاوت باشد؛ بر اساس محتوای `server_name localhost;` آن را تشخیص دهید و نام فایل را فرض نکنید.

**پیش از ویرایش، یک نسخهٔ پشتیبان بسازید:**

1. فایل دقیق Virtual Host را که پیدا کرده‌اید، در File Explorer کپی کنید.
2. نسخهٔ اصلی را در پوشهٔ `sites-enabled` دست‌نخورده نگه دارید؛ کپی پشتیبان را بیرون از `sites-enabled` ذخیره کنید، مثلاً در `W:\laragon_portable\backup\nginx\`.
3. اگر پوشهٔ `backup\nginx` وجود ندارد، آن را بسازید. نام فایل پشتیبان را واضح انتخاب کنید و مشخص باشد که نسخهٔ قبل از تغییر است.
4. فقط پس از اطمینان از داشتن نسخهٔ پشتیبان، فایل Virtual Host اصلی را باز کنید.

کپی پشتیبان را داخل `sites-enabled` قرار نده؛ بعضی پیکربندی‌ها فایل‌های همان پوشه را با wildcard بارگذاری می‌کنند و ممکن است نسخهٔ کپی‌شده هم به‌عنوان تنظیم فعال خوانده شود.

۴. در همان بلوک `server { ... }` بررسی کنید که Document Root و index به این شکل باشند:

```nginx
root W:/laragon_portable/www/public;
index index.html index.htm index.php;
```

اگر پروژه در زیرپوشه‌ای از `www` است، مسیر `root` را مطابق محل واقعی آن اصلاح کنید.

### مرحلهٔ ۴ — رفع خطای 404 برای `/install`

#### علت خطا

Flute هنگام اجرا، اگر هنوز نصب نشده باشد، از آدرس اصلی سایت به `/install` هدایت می‌کند. این رفتار در کد Installer Flute تعریف شده است.

در Virtual Host اولیهٔ Laragon، این تنظیم وجود داشت:

```nginx
location / {
    try_files $uri $uri/ =404;
    autoindex on;
}
```

پارامتر `=404` باعث می‌شود Nginx برای مسیری مانند `/install` که فایل یا دایرکتوری واقعی نیست، پیش از رسیدن درخواست به router برنامه خطای 404 بدهد.

#### اصلاح انجام‌شده

بلوک `location /` را با این تنظیم جایگزین کنید:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

این کار درخواست‌های غیرواقعی مانند `/install` را به `public/index.php` می‌فرستد تا خود Flute route مناسب را پردازش کند. گزینهٔ `autoindex on` هم حذف شده است تا فهرست فایل‌های دایرکتوری‌ها خودکار نمایش داده نشود.

**فقط همین بلوک را اصلاح کنید.** بلوک PHP/FastCGI موجود در Laragon، به‌خصوص `location ~ \.php$` و `fastcgi_pass php_upstream;` را بدون دلیل تغییر ندهید. بلوک `location /` تکراری ایجاد نکنید و تنظیمات سایر Virtual Hostها را دست‌کاری نکنید.

### مرحلهٔ ۵ — ری‌استارت و بررسی

۱. فایل Virtual Host را ذخیره کنید.
۲. در Laragon گزینهٔ **Restart All** را بزنید.
۳. اگر Laragon بعد از تغییر Nginx متوقف شد یا ری‌استارت خطا داد، دوباره تنظیمات را تصادفی تغییر نده. از نسخهٔ پشتیبان بیرون از `sites-enabled` فایل اصلی را برگردان، سپس Restart All را دوباره امتحان کن. برای یافتن علت، پیام خطای Nginx را از لاگ واقعی همان نصب Laragon بررسی کن؛ مسیر لاگ با نسخه و چیدمان نصب می‌تواند متفاوت باشد.
۴. در مرورگر ابتدا باز کنید:

```text
http://localhost/
```

اگر نصب هنوز انجام نشده باشد، Flute باید به `/install` هدایت شود و ویزارد نصب را نمایش دهد.

در صورت بروز مشکل، این آدرس‌ها را جداگانه آزمایش کنید:

- `http://localhost/`
- `http://localhost/index.php`
- `http://localhost/install`

اگر صفحهٔ خطا footer مانند `nginx/1.14.0` نشان می‌دهد، با وجود به‌روزرسانی Nginx بررسی کنید Laragon واقعاً کدام باینری را اجرا کرده است؛ ممکن است نسخهٔ قدیمی هنوز فعال باشد. این نکته را از روی footer به‌تنهایی قطعی فرض نکنید.

اگر خطا پابرجا بود، محتوای کامل همان بلوک `server { ... }` که `server_name localhost;` دارد و نتیجهٔ تست سه آدرس را ثبت کنید. به‌جای تغییر تصادفی فایل‌های PHP یا نصب دوبارهٔ Flute، ابتدا تأیید کنید درخواست به Virtual Host درست و سپس به `index.php` می‌رسد.

### مرحلهٔ ۶ — محدودهٔ این موفقیت

رفع خطای 404 به معنای تکمیل نصب Flute یا آماده‌بودن محیط Production نیست؛ این مرحله فقط مسیریابی Nginx به ورودی برنامه را اصلاح می‌کند. نصب را ابتدا با دیتابیس آزمایشی جداگانه انجام دهید، secretهای Production را در محیط محلی قرار ندهید و پیش از استفادهٔ واقعی، نسخه‌های PHP، افزونه‌ها و دیتابیس موردنیاز Flute را با محیط هدف تطبیق دهید.

**نتیجهٔ ثبت‌شده:** در Laragon Portable با Nginx، خطای `/install` ناشی از `try_files $uri $uri/ =404;` بود؛ با تغییر آن به `try_files $uri $uri/ /index.php?$query_string;` و Restart All، ویزارد نصب قابل دسترسی شد.


---

## گردش‌کار Git برای Flute و FragCity

برای جلوگیری از مخلوط‌شدن کد اختصاصی FragCity با سورس رسمی Flute، از این سه شاخه استفاده می‌کنیم:

| Branch | مسئولیت |
|---|---|
| `main` | سایت PHP قدیمی FragCity و مستندات موجود؛ تا پایان مهاجرت حفظ می‌شود |
| `flute-base` | baseline رسمی Flute؛ آپدیت‌های Release اینجا ثبت می‌شوند تا تغییرات نسخه‌ها قابل مقایسه باشند |
| `www` | شاخهٔ فعال توسعهٔ سایت FragCity بر پایهٔ Flute؛ تغییرات محلی و تغییرات اختصاصی اینجا قرار می‌گیرند |

### اسکریپت‌های شاخهٔ `www`

- [`upload-changes.bat`](https://github.com/larnzs/FragCity-RawPHPbackend/blob/www/upload-changes.bat) — تغییرات فایل‌های محلی را بررسی می‌کند، فایل‌های رایج محرمانه/دادهٔ محلی را برای جلوگیری از آپلود تصادفی مسدود می‌کند، commit می‌سازد و `www` را به GitHub push می‌کند. اگر از `flute-base` اجرا شود، تلاش می‌کند ابتدا به `www` محلی/remote سوئیچ کند.
- [`update.bat`](https://github.com/larnzs/FragCity-RawPHPbackend/blob/www/update.bat) — فقط شاخهٔ `www` را از GitHub با `git pull --ff-only` به‌روز می‌کند. اگر فایل محلی تغییرکرده یا untracked وجود داشته باشد، متوقف می‌شود و آن‌ها را پاک نمی‌کند.

روش معمول:

1. کد را در Laragon و branch محلی `www` تغییر بده.
2. `upload-changes.bat` را اجرا کن تا changes بررسی و به GitHub ارسال شوند.
3. وقتی تغییرات جدیدی روی GitHub در شاخهٔ `www` ثبت شد، بعد از commit/push کردن یا پشتیبان‌گیری از تغییرات محلی، `update.bat` را اجرا کن.

هر دو اسکریپت بدون force-push کار می‌کنند. در صورت خطای Git، تاریخچه را بازبینی کن؛ برای حل تعارض به‌صورت کورکورانه از `reset --hard` یا `push --force` استفاده نکن.

### اسکریپت به‌روزرسانی رسمی Flute در `flute-base`

- [`update-flute-release.sh`](https://github.com/larnzs/FragCity-RawPHPbackend/blob/flute-base/update-flute-release.sh) — اسکریپت Bash محلی؛ آخرین Stable Release رسمی را از GitHub API پیدا می‌کند، ZIPهای Release را بررسی می‌کند و فقط بسته‌ای را می‌پذیرد که `vendor/autoload.php`، `composer.json` و `public/index.php` داشته باشد. سپس baseline را به‌روز و به branch `flute-base` روی GitHub Push می‌کند.
- دیگر فایل‌های `update-flute-release.bat` و PowerShell helper لازم نیستند؛ updater رسمی فقط `.sh` است و Flute قرار نیست از این worktree اجرا شود. این branch صرفاً baseline برای مقایسه‌ی نسخه‌هاست.

Updater قبل از تغییر فایل‌ها، clean بودن working tree و branch مجاز را بررسی می‌کند. اگر ZIP کامل با `vendor` پیدا نشود، فایل‌های محلی را تغییر نمی‌دهد. فایل‌های untracked/ignored را در صورت برخورد با مسیرهای Release بازنویسی نمی‌کند؛ فایل‌های tracked که در Release جدید حذف شده‌اند به‌صورت فایل‌به‌فایل حذف می‌شوند. اسکریپت از force-push استفاده نمی‌کند.

**محل اجرا:** اسکریپت را از یک checkout یا Git worktree جدا برای baseline اجرا کن، نه از پوشه‌ی Laragon که branch `www` را سرو می‌کند. Bash و ابزارهای `git`، `curl`، `jq`، `unzip`، `find`، `realpath`، `wc`، `grep`، `cmp`، `mktemp`، `cp`، `rm`، `mkdir` و `dirname` باید نصب باشند.

در یک clone محلی از مخزن، از ریشه‌ی repository اجرا کن:

```bash
git fetch origin
git worktree add -b flute-base-updater ../fragcity-flute-base origin/flute-base
cd ../fragcity-flute-base
bash ./update-flute-release.sh
```

اگر worktree محلی `flute-base-updater` از قبل ساخته شده، `git worktree add` را دوباره اجرا نکن؛ فقط وارد همان پوشه شو و `bash ./update-flute-release.sh` را اجرا کن. اسکریپت commit را در worktree می‌سازد و با دستور بدون force به remote branch یعنی `flute-base` Push می‌کند. branch فعال توسعه‌ی FragCity یعنی `www` جدا باقی می‌ماند.

### مرز مهم: Git در برابر دیتابیس

این اسکریپت‌ها **فایل‌های Git** را همگام می‌کنند؛ آن‌ها تنظیماتی را که Flute داخل دیتابیس محلی ذخیره کرده، به GitHub آپلود یا از آن دانلود نمی‌کنند. همچنین فایل‌های واقعیِ رمز، API token، اتصال دیتابیس یا اطلاعات مشتری نباید به مخزن push شوند. هر تنظیمی که باید در نصب جدید تکرارپذیر باشد، باید به‌صورت الگوی امن، migration/seed قابل بازبینی یا راهنمای نصب مستند شود.

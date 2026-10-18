# FragCity
## نیازمندی‌ها، معماری و نقشهٔ راه

> **وضعیت پروژه:** Panel آزمایشی Pterodactyl نسخهٔ 1.15.1 در LAN نصب شده و ورود مدیر تأیید شده است؛ Docker Engine نسخهٔ 29.9.1 نصب و با `docker run --rm hello-world` تأیید شده؛ گام جاری نصب Wings و ساخت Node روی همان VM یعنی `fc-node-01` است. برای اجرای فرمان‌ها از نصب Debian بدون Desktop Environment تا مراحل بعدی، سند مادر [FRAGCITY-MASTER-COPY-PASTE-RUNBOOK](docs/FRAGCITY-MASTER-COPY-PASTE-RUNBOOK.md) مرجع اجرایی واحد است. پروژه هنوز Production نیست؛ فایل‌های `/home/steam` باید حفظ شوند.
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
| ۲. PoC مدیریت سرور | یک نود آزمایشی، یک سرور CS2، منابع و چرخهٔ عمر | Panel در LAN و Docker Engine تأیید شده؛ Wings و سرور CS2 تحت مدیریت Panel هنوز Pending |
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
| [راهنمای مادر Copy/Paste](docs/FRAGCITY-MASTER-COPY-PASTE-RUNBOOK.md) | **مرجع اجرایی واحد:** از نصب Debian بدون GUI تا نصب Panel/Docker و ادامهٔ Wings؛ فرمان‌ها، خروجی‌های واقعی و مراحل بعدی |
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


## شروع اجرایی — راهنمای مادر

**برای کار عملی فقط از این فایل شروع کن:** [راهنمای مادر Copy/Paste](docs/FRAGCITY-MASTER-COPY-PASTE-RUNBOOK.md).

این فایل به‌عنوان دفترچهٔ اجرایی واحد طراحی شده است: نصب Debian بدون محیط گرافیکی، آماده‌سازی SSH و شبکه، PHP و سرویس‌های موردنیاز، نصب Panel، دیتابیس، تنظیم NGINX، رفع خطای Cache، Docker، تأیید نسخهٔ Panel و نصب Wings را به‌ترتیب پوشش می‌دهد. نتیجه‌های واقعی، خطاهای قبلی و بلوک‌های بعدی پس از اجرا در همان فایل ثبت می‌شوند.

### وضعیت فعلی

- Panel **1.15.1** نصب است و ورود از Windows به `http://192.168.1.5` تأیید شده است.
- Docker **29.9.1** فعال است و آزمون `hello-world` موفق بوده است.
- Wings هنوز نصب/پیکربندی نشده؛ مرحلهٔ جاری دانلود باینری و ساخت Node `fc-node-01` است.
- سرور CS2 تحت مدیریت Pterodactyl هنوز ساخته نشده است؛ فضای آزاد و استفاده از فایل‌های موجود زیر `/home/steam` باید قبل از آن طراحی شود.
- این PoC فقط LAN/HTTP است؛ دامنه، TLS عمومی و Port Forwarding عمومی هنوز تنظیم نشده‌اند.
- هیچ‌یک از فایل‌های پروژهٔ `cs2-kus-addons` بدون درخواست صریح تغییر نمی‌کنند.

### قاعدهٔ به‌روزرسانی مستندات

پس از هر مرحله، فرمان‌هایی که واقعاً اجرا شده‌اند، خروجی قابل‌تأیید، خطاهای رخ‌داده و دستور مرحلهٔ بعد به **همان فایل مادر** افزوده می‌شود. فرمان‌های مربوط به فازهای پیاده‌سازی‌نشده به‌عنوان دستور Production فرضی ثبت نمی‌شوند.

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

**وضعیت فعلی:** Panel نسخهٔ 1.15.1 در LAN نصب و ورود مدیر موفق بوده است؛ Docker Engine نسخهٔ 29.9.1 نصب و با `hello-world` تأیید شده. Wings هنوز نصب/متصل نشده؛ گام جاری دانلود Wings v1.13.4 و ساخت Node روی همین VM است. اجرای CS2 تحت Pterodactyl، ادغام امن فایل‌های موجود CS2/KUS، queue/cron، hardening، backup و دسترسی عمومی هنوز مراحل جداگانه‌اند. فرمان‌های اجرایی به ترتیب در [راهنمای مادر Copy/Paste](docs/FRAGCITY-MASTER-COPY-PASTE-RUNBOOK.md) ثبت می‌شوند.


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

# FragCity — Change Log & Current Status

## آخرین وضعیت ثبت‌شده

### بازبینی ساختار مستندات و راهنمای مرحله بعد — ۱۷ اکتبر ۲۰۲۶

- ابتدا ۱۸ فایل Markdown (README و ۱۷ سند زیر `docs/`) از نظر ساختار، مسیرهای داخلی، ادعاهای وضعیت جاری و مسیر یادگیری بررسی شدند. پس از افزودن گزارش ممیزی، کل مجموعه شامل README و ۱۸ سند `docs/`، یعنی ۱۹ فایل، دوباره بررسی شد.
- در بررسی نهایی همهٔ ۱۹ فایل، هیچ پیوند داخلی شکسته و هیچ code fence نامتوازن پیدا نشد.
- README اکنون نقش راهنمای ورود را روشن می‌کند و به Runbook اجرایی، لاگ خروجی‌ها و گزارش ممیزی مستندات پیوند می‌دهد؛ تکرار همهٔ فرمان‌ها در README عمدی نیست تا نسخه‌های متناقض ساخته نشوند.
- در Pterodactyl PoC، چک‌لیست‌های P2.1/P2.2 با اجرای واقعی تا نصب Composer همگام شد؛ مرحلهٔ بعد به‌صورت یک گام محدود و قابل بررسی نوشته شد.
- نسخهٔ پایدار v1.15.1 از صفحهٔ Release رسمی بازبینی و برای تکرارپذیری pin شد؛ فقط دریافت/استخراج archive برنامه‌ریزی شده است، نه نصب کامل Panel.
- گزارش ممیزی و استاندارد مستندسازی مبتدی‌پسند در [Documentation Audit](DOCUMENTATION-AUDIT.md) قرار دارد.
- هیچ فرمان جدیدی روی Debian در جریان بازبینی مستندات اجرا نشده و هیچ تغییری در `cs2-kus-addons` انجام نشده است.


### نصب Composer 2 و تأیید نسخهٔ Nginx — ۱۷ اکتبر ۲۰۲۶

- نصب‌کنندهٔ Composer با SHA-384 مورد انتظار تطبیق کرد؛ پیام `Composer installer checksum verified.` در لاگ دیده شد.
- Composer نسخهٔ 2.10.3 با PHP 8.3.35 در مسیر `/usr/local/bin/composer` نصب شد.
- بررسی Nginx با مسیر کامل موفق بود: `nginx/1.26.3`؛ نسخهٔ Debian package برابر `1.26.3-3+deb13u9` گزارش شد.
- وضعیت کنونی گام بعدی: دریافت و پیکربندی Panel هنوز انجام نشده است؛ دیتابیس اختصاصی، `.env`, APP_KEY، migrations، admin user، queue/cron، Docker/Wings و CS2 container هنوز pending هستند.
- راهنمای اجرایی و توضیح آموزشی در [Pterodactyl PoC، بخش ۱۰](PTERODACTYL-POC.md) و خروجی ثبت‌شده در انتهای [Debian PoC Log](DEBIAN-POC.md) آمده‌اند.
- مقدار checksum ثبت‌شده، شاهد اجرای موفق همین نصب است و نباید در آینده بدون بررسی صفحهٔ رسمی Composer به‌عنوان مقدار همیشگی استفاده شود.


### نصب dependencyهای پایهٔ Pterodactyl — ۱۷ اکتبر ۲۰۲۶

- کاربر نصب بسته‌های PHP 8.3 و extensionهای موردنیاز، PHP-FPM، Nginx، MariaDB، Redis و ابزارهای پایه را روی `fc-node-01` اجرا کرد؛ لاگ apt پایان عادی نصب ۵۱ بسته را نشان می‌دهد.
- PHP CLI 8.3.35، MariaDB 11.8.6 و Redis 8.0.2 از خروجی‌ها شناسایی شدند؛ سرویس‌های `nginx`, `mariadb`, `redis-server` و `php8.3-fpm` همگی active بودند.
- listenerها بررسی شدند: Nginx روی TCP/80؛ MariaDB فقط روی `127.0.0.1:3306`؛ Redis فقط روی loopback IPv4/IPv6. پورت 443 هنوز listener نداشت.
- `nginx -v` در shell کاربر به علت پیدا نشدن فرمان شکست خورد، اما سرویس و listener فعال بودند؛ تأیید نسخه با `sudo /usr/sbin/nginx -v` و `dpkg-query` در گام Composer ثبت شده است.
- در زمان ثبت این snapshot وابستگی‌ها، Composer هنوز نصب نشده بود؛ نصب Composer 2.10.3 در بخش جدیدتر بالای این فایل ثبت شده است. خود Panel، دیتابیس و کاربر اختصاصی Panel، `.env`، APP_KEY، queue/cron، Docker و Wings هنوز نصب/ساخته نشده‌اند.
- فضای آزاد همچنان حدود 28 GiB است، در برابر نصب native بازی CS2 حدود 70 GiB؛ روش امن استفاده از Base Game پیش از ساخت container باید حل شود.
- فرمان و آموزش Composer پیش از اجرا در [Pterodactyl PoC، بخش ۱۰](PTERODACTYL-POC.md) آمده بود؛ نصب اکنون با موفقیت انجام شده و نتیجه در ابتدای این Change Log ثبت شده است.
- این تغییر فقط مستندات `FragCity-RawPHPbackend` را به‌روز کرده است؛ در این گام هیچ تغییری در `cs2-kus-addons` انجام نشده است.


### Shared Base، payload اولیهٔ Instance و File Manager — ۱۶ اکتبر ۲۰۲۶

- تصمیم‌های این مرحله در [ADR-011/012](DECISIONS.md) ثبت شد: هر Instance ابتدا payload کامل addons/cfg/GameMode text را در مسیر خصوصی خودش دریافت می‌کند؛ preset مود در Provisioning اولیه اعمال نمی‌شود و کانفیگ Aim/HS، Public Fun یا DM/FFA بعداً جداگانه انجام می‌شود.
- [CS2 Multi-Instance](CS2-MULTI-INSTANCE.md) و [Roadmap](ROADMAP.md) اصلاح شدند تا اسکریپت Deploy را از SteamCMD و اجرای سرور جدا کنند و Base مشترک را حفظ کنند.
- [Platform PoC](PLATFORM-POC.md) آزمون T19 را برای File Manager و مجوزهای دقیق مشتری اضافه کرد؛ [Testing Bench](TESTING-BENCH.md) جداسازی دو Instance و ویرایش فایل‌ها از رابط وب را پوشش می‌دهد.
- Pterodactyl دارای File Manager داخلی و مجوزهای جداگانهٔ فایل/کنسول/power است؛ اما اتصال آن به سایت یا پنل C# فعلی (لینک‌دهی/SSO) هنوز تصمیم نشده است.
- آدرس پنل فعلی FragCity از طریق ابزار وب قابل دسترسی نبود؛ دربارهٔ جزئیات پیاده‌سازی فعلی آن نتیجه‌گیری نشده است.
- این مرحله فقط مستندات `FragCity-RawPHPbackend` را تغییر می‌دهد؛ `cs2-kus-addons` تغییر نکرده و هیچ Pterodactyl یا Update script روی Debian نصب/اجرا نشده است.

### توسعهٔ محلی Windows — به‌روزرسانی ۱۶ اکتبر ۲۰۲۶

- فایل `update.bat` در ریشهٔ ریپو اضافه شد: فقط `main` را انتخاب و `git pull --ff-only origin main` اجرا می‌کند؛ برای دیدن نتیجه در پایان مکث دارد.
- اگر working tree تغییر commit‌نشده یا فایل untracked داشته باشد، updater بدون switch/pull متوقف می‌شود.
- راهنمای `docs/LOCAL-DEVELOPMENT.md` و قوانین اولیهٔ `.gitignore` اضافه شدند. Laragon گزینهٔ پیشنهادی است؛ WAMP هم می‌تواند استفاده شود.
- هنوز clone در کامپیوتر کاربر، نصب Laragon/WAMP، ساخت VirtualHost یا تست عملی انجام نشده است.
- اجرای امن صفحات دیتابیسی تا جداسازی credentialهای hard-code شده از سورس و آماده‌شدن schema/seed محلی مسدود است.
- updater فقط کد را از GitHub به clone محلی می‌آورد؛ هیچ deploy یا تغییر دیتابیس روی cPanel و هیچ عملیات روی Debian VM انجام نمی‌دهد.

- مدل فروش تأیید شده: بستهٔ مدت‌دار پیش‌پرداخت با کسر یک‌باره از Wallet و تایمر مستقل از روشن/خاموش‌بودن سرور.
- Remote MariaDB روی 3306 در هاست اشتراکی فعلی گزینهٔ اجرایی نیست؛ HTTPS API مسیر کاندید فعلی است.
- مسیر مرجع PoC: Paymenter + Pterodactyl/Wings؛ GameAP برای مقایسه نگه داشته می‌شود.
- API/Agent فعلی C# و PHP مشکلات شناخته‌شده دارند؛ ممیزی اولیه فقط static review بوده و اصلاح نشده.
- Debian VM فعلی، محیط آزمایشی است. مشخصات و فرمان‌های ثبت‌شده در [Debian PoC Log](DEBIAN-POC.md) آمده‌اند.
- هیچ پنل جدیدی نصب نشده و هیچ مهاجرت Production انجام نشده است.
- ریپوی `cs2-kus-addons` تغییر نکرده و به‌عنوان زیرساخت محافظت‌شده باقی می‌ماند.

## سابقهٔ مستندات

جزئیات تاریخی نصب/بررسی Debian و گزارش‌های اولیه در [Debian PoC Log](DEBIAN-POC.md) حفظ شده‌اند. معماری فعلی و تصمیم‌های اصلاح‌شده در [Architecture](ARCHITECTURE.md)، [Decisions](DECISIONS.md) و [Roadmap](ROADMAP.md) معیار جاری‌اند؛ پیشنهادهای قدیمی که با تصمیم‌های جدید تعارض دارند، مبنای اجرا نیستند.


## ثبت مستندات — ۱۶ اکتبر ۲۰۲۶

- سند [CS2 Multi-Instance](CS2-MULTI-INSTANCE.md) دربارهٔ یک Panel/چند Server، جداسازی Volumeها، مدل‌های اشتراک فایل و بررسی ریپوهای GitHub اضافه شد.
- [Platform PoC](PLATFORM-POC.md) آزمون T18 را برای Shared Base/Update هماهنگ‌شده و T19 را برای File Manager و مجوزهای مشتری نگه می‌دارد؛ K4ryuu VPK Sync فقط گزینهٔ مقایسه‌ای اختیاری است.
- [Repository Inventory](REPOSITORIES.md) گزینه‌های K4ryuu/CS2-Egg، GamesTwoLife/cs2-multiserver، Trimeon/cs2-install، Auto-Tournament/cs2-server-manager و 1zc/CS2-Pterodactyl را ثبت می‌کند.
- این تغییرات مستندسازی‌اند؛ روی Debian چیزی نصب یا اجرا نشده و shared files فعال نشده‌اند.


## ثبت مستندات — Testing Bench و جداسازی سیستم‌عامل‌ها، ۱۶ اکتبر ۲۰۲۶

- [Testing Bench](TESTING-BENCH.md) ایجاد شد تا هر candidate و روش ذخیره‌سازی با status، مراحل، معیار قبولی و قالب ثبت شواهد تست شود.
- [Probably and Suggestions](PROBABLY-AND-SUGGESTIONS.md) ایجاد شد تا ایده‌های محتمل از انتخاب Production و نتایج واقعی جدا بمانند.
- [CS2 Multi-Instance](CS2-MULTI-INSTANCE.md) گزینه‌ها را به Linux native، Windows native و Cross-platform/WSL2 تفکیک می‌کند.
- WindowsGSH، Game-Server-Admin، CS2 Server CLI، MSM، cs2-install، K4ryuu VPK Sync و Pterodactyl Eggها در لاین‌های مناسب ثبت شدند.
- **هیچ ابزار تازه‌ای نصب یا زنده تست نشده است**؛ status همهٔ candidateها Pending/Blocked است تا نتیجه با evidence ثبت شود.
- این تغییرات فقط مستندات `FragCity-RawPHPbackend` را تغییر داده؛ `cs2-kus-addons` تغییر نکرده است.


## Linux CS2 baseline — progress checkpoint, ۱۶ اکتبر ۲۰۲۶

- طبق گزارش کاربر، Git و `ca-certificates` نصب شده و Clone مخزن خصوصی `larnzs/cs2-kus-addons` روی شاخهٔ `integration/kus-monorepo` انجام شده است.
- مسیر محلی مورد انتظار: `/home/fragcityadmin/projects/cs2-kus-addons`. خروجی نهایی `git status --short --branch` و `git log -1 --oneline` هنوز در گفتگو ثبت نشده و باید هنگام ادامه کار ثبت شود.
- تصمیم جدید: هیچ فایل بازی/پلاگینی از Windows جابه‌جا یا کپی نمی‌شود؛ ابتدا نصب native Linux و سلامت payload خود Repository بررسی می‌شود.
- هیچ installer، repository updater یا plugin updater روی Clone Linux اجرا نشده است. نصب CS2، اجرای Instance و بررسی واقعی Load شدن پلاگین‌ها همچنان Pending است.
- [Debian PoC Log](DEBIAN-POC.md) مراحل انجام‌شده و فرمان‌های بعدی را ثبت می‌کند؛ [Testing Bench](TESTING-BENCH.md) کاندید L-00 را برای خط مبنای KUS Linux پیگیری می‌کند.
- در نسخهٔ بررسی‌شده، برای خط مبنای بدون plugin updater نباید `linux/install.sh` اجرا شود؛ آن مسیر صریحاً `RUN_PLUGIN_UPDATER=1` را به `setup.sh` می‌دهد و updater می‌تواند برای push به GitHub تلاش کند. گزینهٔ آزمایشی مستندشده `linux/setup.sh` با `RUN_PLUGIN_UPDATER=0` است، پس از Snapshot و بررسی مجدد اسکریپت.


## ثبت تصمیم تکمیلی — baseline خصوصی و updater انتخابی

- هنگام Provisioning اولیه، baseline کامل `addons/`، `cfg/` و فایل‌های `.txt` تعیین‌شده از شاخهٔ `integration/kus-monorepo` در مسیر private هر Instance قرار می‌گیرد.
- طبق سیاست updater موجود `cs2-kus-addons`، قبل از Startهای بعدی فقط پلاگین‌ها و Runtimeهای مدیریت‌شده به‌روزرسانی می‌شوند؛ `cfg/`، فایل‌های `.txt` تنظیماتی/GameMode و کل `addons/counterstrikesharp/configs/` محافظت می‌شوند و نباید بازنویسی یا حذف شوند.
- payload هرگز روی فایل‌های فیزیکی Base Game کپی یا merge نمی‌شود؛ سرور از Base مشترک و مسیر خصوصی Instance به‌صورت جداگانه استفاده می‌کند.
- [Platform PoC](PLATFORM-POC.md) آزمون T20 را برای اثبات تغییر انتخابی فایل‌ها، ثابت‌ماندن checksum مسیرهای محافظت‌شده و جلوگیری از Start پس از updater ناقص نگه می‌دارد.
- تغییرات کانفیگ جدید در ریپو به‌صورت خودکار روی Instanceهای موجود اعمال نمی‌شوند؛ انتقال عمدی کانفیگ در صورت نیاز، فرایندی جداگانه خواهد بود.
- این مرحله فقط مستندات FragCity را تغییر می‌دهد؛ ریپوی `cs2-kus-addons`، فایل‌های Debian و نصب/اجرای Pterodactyl دست‌نخورده می‌مانند.


## ثبت تصمیم — Linux مسیر میزبانی؛ Windows محیط تست دستی، ۱۶ اکتبر ۲۰۲۶

- طبق ADR-015، Debian/Linux native محیط مرجع برای نصب و Update CS2، Instanceها، Base مشترک، updaterها، Pterodactyl/Wings و آزمایش MySQL/MariaDB محلی است.
- Windows native فقط برای اجرای دستی CS2، بررسی Console/log، آزمایش پلاگین‌ها و تنظیم دستی کانفیگ‌ها استفاده می‌شود؛ تست Windows جایگزین قبولی Linux نیست.
- انتقال خودکار فایل‌های بازی/کانفیگ از Windows به Linux در نظر گرفته نمی‌شود. تنظیمات موردنیاز باید به‌صورت کنترل‌شده در Instance خصوصی لینوکس اعمال و تست شوند.
- بررسی ابزارهای مدیریت Windows و cross-platform فعلاً Deferred است و فقط با یک blocker روشن در مسیر Linux بازبینی می‌شود.
- سایت، Billing و اتصال فروش همچنان خارج از دامنهٔ فعلی هستند تا مرحلهٔ Linux/Instance/MySQL آزمایشگاهی به نتیجه برسد.
- این تغییر فقط مستندات `FragCity-RawPHPbackend` را تغییر داد؛ ریپوی `cs2-kus-addons` و Debian دست‌نخورده ماندند.


## Debian Node Golden Image — ثبت طرح، ۱۶ اکتبر ۲۰۲۶

- سند [Node Golden Image & Cloning Policy](NODE-GOLDEN-IMAGE.md) اضافه شد تا تهیهٔ Template را پس از قبولی PoC پایه، نه قبل از تست، استاندارد کند.
- برای VMware باید کل VM خاموش و کل پوشهٔ VM همراه فایل پیکربندی و دیسک‌های وابسته Clone شود؛ VMDK تنها، VM کامل نیست. هنگام اجرای هم‌زمان منبع و Clone، هویت جدید VMware انتخاب شود.
- برای Bare Metal، مسیر موردنظر Provisioning تکرارپذیر/اسکریپت یا ابزار مدیریت پیکربندی است؛ VMDK روش مستقیم استقرار روی سرور فیزیکی فرض نمی‌شود.
- طرح لایهٔ مشترک را از هویت و secret مختص Node جدا می‌کند؛ `machine-id`، SSH host keys، hostname/network، Wings config/token، TLS keys و credential/data مختص نود نباید بدون بازتولید یا تنظیم مستقل به Clone منتقل شوند.
- وضعیت واقعی: فقط مستندات تغییر کرده‌اند. Golden Image ساخته نشده، Clone آزمایشی انجام نشده و هیچ فرمانی روی Debian اجرا نشده است. `cs2-kus-addons` تغییر نکرده است.

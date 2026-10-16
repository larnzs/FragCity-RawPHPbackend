# FragCity — Change Log & Current Status

## آخرین وضعیت ثبت‌شده

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

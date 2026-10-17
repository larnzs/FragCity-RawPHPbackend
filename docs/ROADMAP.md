# FragCity — Roadmap

> ترتیب فازها بر اساس وابستگی و ریسک است؛ شروع یک فاز به معنی قبولی فازهای قبلی نیست.

## نمای کلی

| فاز | وضعیت | خروجی اصلی | شرط خروج |
|---|---|---|---|
| P0 — Requirements & Local Development | مستندات آماده؛ اجرا Pending | الزامات، معماری، updater محلی، Laragon/WAMP و مرزبندی Production | clone محلی آماده، DB کاملاً جدا و تست اولیه قابل تکرار باشد |
| P1 — Security & Baseline | ممیزی ایستا اولیه انجام؛ اصلاح Pending | بازبینی secret، API، ورودی‌ها و مدل مالکیت داده | خطرهای مسدودکننده شناسایی و برنامه اصلاح تصویب شود |
| P2 — Game Panel PoC | بسته‌های پایهٔ PHP 8.3/Nginx/MariaDB/Redis نصب و سرویس‌ها active؛ Composer/Panel pending | fc-node-01 آزمایشگاهی؛ Panel/DB محلی، سپس Wings/Docker و یک CS2 server | چرخهٔ عمر، جداسازی Instanceها، حفاظت کانفیگ، File Manager و Update هماهنگ‌شده پایدار باشد |
| P3 — Billing Integration | Pending | سفارش Sandbox/ساختگی تا Provisioning | duplicate و سفارش ناموفق به‌شکل امن مدیریت شود |
| P4 — Fixed-Duration Product | Pending | بسته‌ها، Wallet، `expires_at` و expiry action | تمام تست‌های مدل مالی قبول شوند |
| P5 — Isolation & Recovery | Pending | مجوز مشتری، Backup/Restore، retry و rollback | تست‌های امنیتی/بازیابی قبول شوند |
| P6 — Plugin Data API | Pending | sync فقط جداول تعیین‌شده از طریق HTTPS | API امن و رفتار قطع اتصال/retry آزموده شود |
| P7 — Node 2 & Capacity | Pending | ثبت نود دوم و تست ۱/۳/۵ سرور | افزودن نود با config؛ ظرفیت با اندازه‌گیری مستند شود |
| P8 — Pilot & Migration | Pending | پایلوت محدود و انتقال قابل بازگشت | هیچ شرط مسدودکنندهٔ باز باقی نماند |

## P0 — قفل‌کردن الزامات

- [x] مدل فروش بستهٔ ثابت مدت‌دار تعریف شده: ۱/۳/۷/۱۵/۳۰ روز.
- [x] تایمر با خرید موفق شروع می‌شود و خاموش‌بودن سرور آن را متوقف نمی‌کند.
- [x] اتصال Remote MariaDB روی 3306 در cPanel فعلی قابل اتکا نیست؛ مسیر API/HTTPS مبنای فعلی است.
- [x] مسیر PoC مرجع: Paymenter + Pterodactyl/Wings.
- [x] فایل ریشهٔ `update.bat` برای دریافت محلی تغییرات از `origin/main`؛ فقط `git switch main` و `git pull --ff-only origin main`، با محافظ working tree.
- [x] راهنمای توسعهٔ محلی Laragon/WAMP اضافه شد؛ updater هیچ deployی روی cPanel یا Debian انجام نمی‌دهد.
- [ ] clone ریپو در DocumentRoot محلی و VirtualHost آزمایشی ایجاد شود.
- [ ] پیش از اجرای صفحات وابسته به DB، hard-coded credentials از سورس جدا و local-only configuration اعمال شود؛ تا آن زمان تست DB مسدود است.
- [ ] schema واقعی و seed ساختگی/ناشناس‌شده برای دیتابیس محلی تهیه و تست شود.
- [ ] قاعدهٔ نهایی تمدید قبل/بعد انقضا تصویب شود.
- [ ] سیاست suspend/stop و مهلت نگهداری/حذف داده بعد انقضا تصویب شود.
- [ ] درگاه پرداخت، واحد پول، شرایط refund و قابلیت Wallet در Billing منتخب مشخص شود.
- [ ] محل استقرار Panel/Billing تعیین شود؛ cPanel برای سرویس دائمی بدون بررسی قابلیت‌ها فرض نمی‌شود.

## P1 — امنیت و خط مبنا

- [ ] Secretهای داخل سورس/config با برنامهٔ Rotation امن مدیریت شوند؛ قبل از تغییر، وابستگی سرویس‌ها و زمان‌بندی قطعی بررسی شود.
- [ ] جریان صدور، ثبت، انقضا و ابطال توکن‌ها یکپارچه شود.
- [ ] endpointهای API با token مستقل، حداقل scope، allowlist داده، validation و محدودیت نرخ محافظت شوند.
- [ ] رفتار backup و rollback قبل از تغییر دیتابیس آزمایش شود.
- [ ] هیچ عملیات مخرب روی دیتابیس تولیدی انجام نشود.

## P2 — PoC پنل بازی

1. Release پایدار و patched دقیق Panel/Wings انتخاب و ثبت شود.
2. در فاز فعلی، همان fc-node-01 به‌عنوان آزمایشگاه یکپارچهٔ Panel و Node استفاده می‌شود؛ این انتخاب به‌معنی Production بودن نود یا مناسب‌بودن دائمی یک ماشین برای همهٔ سرویس‌ها نیست. Flute CMS و دیتابیس cPanel فعلاً جدا می‌مانند. پیش از نصب Nginx، IP و پورت‌های فعلی ثبت شوند.
3. یک Node و allocation کوچک ثبت شود.
4. Egg/template مناسب CS2 انتخاب و مجوز SteamCMD/اجزای لازم تأیید شود.
5. یک instance آزمایشی نصب/اجرا و stop/restart/delete/backup/restore تست شود.
6. Console، فایل‌ها، محدودیت RAM/CPU/Disk و پورت‌ها بررسی شود.
7. هنگام Provisioning اولیه، baseline کامل `addons/`، `cfg/` و فایل‌های متنی تعیین‌شده از شاخهٔ `integration/kus-monorepo` ریپوی `cs2-kus-addons` به فضای private هر Instance کپی شود. پیش از Startهای بعدی updater فقط پلاگین‌ها و Runtimeهای مدیریت‌شده را به‌روزرسانی کند؛ `cfg/`، فایل‌های `.txt` تنظیماتی و `addons/counterstrikesharp/configs/` نباید تغییر کنند و هیچ فایل payload به Base Game کپی/merge نشود. این updater مسئول اجرای SteamCMD یا CS2 نیست.
8. در اولین ساخت هیچ preset مود نهایی اعمال نکن؛ ابتدا سلامت payload، اجرای سرور و جداسازی فایل‌ها را ثابت کن. سپس هر Instance را جداگانه کانفیگ کن.
9. File Manager داخلی Pterodactyl را برای ویرایش cfg و تنظیمات افزونه تست کن؛ مجوزهای فایل، SFTP و Console را جداگانه بررسی کن. سایت/پنل C# فعلی حذف‌شده فرض نشود؛ نیاز به لینک‌دهی/SSO و شکاف‌های UI بعداً ارزیابی می‌شود.
10. Update مرکزی Base و updater انتخابی payload دو عملیات جدا هستند: Update بازی همهٔ Instanceهای متکی را متوقف کرده و SteamCMD را یک‌بار روی Base اجرا می‌کند؛ updater پیش از هر Start فقط پلاگین‌ها و Runtimeهای مدیریت‌شده را در مسیر private همان Instance به‌روزرسانی می‌کند و مسیرهای محافظت‌شدهٔ کانفیگ را تغییر نمی‌دهد. بازگشت فقط Instanceهای قبلاً Running انجام شود. جزئیات در [CS2 Multi-Instance](CS2-MULTI-INSTANCE.md) آمده است.
11. مسیر Linux/Pterodactyl معیار پذیرش اصلی است. Windows فقط برای تست دستی CS2، لاگ‌ها، Load پلاگین‌ها و تنظیم config استفاده می‌شود؛ آزمون managerهای Windows و cross-platform تا زمان بروز blocker مشخص در مسیر Linux، Deferred می‌ماند. شواهد هر تست در [Testing Bench](TESTING-BENCH.md) ثبت شود.
12. پیشنهادهای اولیه در [Probably and Suggestions](PROBABLY-AND-SUGGESTIONS.md) نگهداری شوند؛ این فهرست به‌تنهایی انتخاب Production محسوب نمی‌شود.

**شرط عبور:** پنل، وضعیت واقعی سرور و مالکیت منابع هماهنگ باشند؛ محدودیت‌های امنیتی مشتری قابل تست باشند.

## P3 — اتصال فروش

1. Paymenter در نسخهٔ پایدار patched نصب شود.
2. integration رسمی Pterodactyl پیکربندی شود.
3. محصول Sandbox یا سفارش ساختگی ساخته شود.
4. callback ناموفق، تکراری و retry آزمایش شوند.
5. برای هر سفارش شناسهٔ یکتا وجود داشته باشد تا ساخت duplicate انجام نشود.

**شرط عبور:** سفارش فقط یک instance بسازد و شکست Provisioning قابل شناسایی/جبران باشد.

## P4 — مدل Wallet و انقضا

1. بسته‌های ۱/۳/۷/۱۵/۳۰ روزه تعریف شوند.
2. debit کیف پول و سفارش به‌صورت قابل ممیزی ثبت شود.
3. `expires_at` با مرجع UTC و قاعدهٔ مصوب محاسبه شود.
4. تایمر هنگام stop/crash/offline بودن سرور نیز ادامه یابد.
5. تمدید، خرید پس از انقضا، خریدهای هم‌زمان و شکست Provisioning آزمایش شوند.
6. scheduler/action انقضا در زمان درست stop/suspend را اجرا کند و در صورت آفلاین‌بودن نود، پس از بازگشت reconcile شود.

**شرط عبور:** کسر یا برگشت تکراری رخ ندهد؛ وضعیت مالی و سرور قابل تطبیق باشد.

## P5 — امنیت، Backup و بازیابی

- دو مشتری آزمایشی با دو سرور مجزا بساز.
- دسترسی فایل، کنسول، Backup، API و metadata را بین آن‌ها آزمایش کن.
- Backup تهیه و روی محیط جدا restore شود.
- قطع شبکه و API، retry، restart و پردازش تکراری سفارش آزمایش شوند.
- هیچ secret در Git، UI مشتری یا log نباشد.

## P6 — API و داده‌های پلاگین

- فهرست plugin/table/column و منبع حقیقت مشخص شود.
- از sync عمومی کل دیتابیس اجتناب شود؛ فقط جدول‌های مجاز و نیازمند sync.
- احراز هویت نود، scope، rate limit، payload limit، idempotency و error contract تعریف شود.
- منطق upsert/delete/collision و دادهٔ هم‌زمان چند نود آزموده شود.
- Agent/Client فعلی فقط اگر gap مشخصی باقی ماند توسعه یابد؛ انتقال کامل فعلاً اولویت نیست.

## P7 — نود دوم و ظرفیت

- نود دوم با install runbook و تنظیمات مستقل اضافه شود.
- پس از قبولی PoC یک Node و جداسازی دو Instance، برای افزودن نودها Golden Image/Template نسخه‌بندی‌شده تهیه و Clone آزمایشی مستقل انجام شود؛ جزئیات و فهرست هویت‌ها/secretهای یکتا در [Node Golden Image](NODE-GOLDEN-IMAGE.md) است.
- برای VMهای VMware کل VM خاموش و کل پوشهٔ VM کپی/Clone شود؛ برای Bare Metal مسیر Provisioning تکرارپذیر لازم است و VMDK به‌تنهایی روش نصب روی سخت‌افزار نیست.
- Template تا زمان قبولی آزمون Clone و health check صرفاً آزمایشی می‌ماند؛ افزودن Node باید با config مختص نود انجام شود و به تغییر کد سایت نیاز نداشته باشد.
- ابتدا یک Base مشترک با یک Instance، سپس دو Instance برای آزمون جداسازی، و بعد ۳ و ۵ سرور برای سنجش ظرفیت اجرا شوند.
- RAM، CPU، disk I/O، latency، packet loss، crash و log ثبت شود.
- ۸ تا ۱۰ سرور فقط پس از داده واقعی بررسی شود.

## P8 — پایلوت و مهاجرت

- migration plan و rollback مشخص باشد.
- داده‌ها، مشتری‌ها، شناسه‌های سفارش/سرور و اعتبار wallet تطبیق داده شوند.
- هیچ قابلیت تولیدی تا قبولی آزمون‌های مسدودکننده منتقل نشود.
- در ابتدا تعداد کمی مشتری/instance در پایلوت باشند و معیارهای توقف روشن بمانند.

## معیارهای توقف اجباری

پایلوت در هر یک از شرایط زیر متوقف می‌شود: دسترسی یک مشتری به دیگری، اختلاف غیرقابل توضیح در Wallet، ساخت duplicate instance کنترل‌نشده، از دست رفتن داده، شکست بازیابی، token/API بدون مجوز، یا آسیب‌پذیری بحرانی بدون patch.


## P2B — Testing Bench برای CS2 Multi-Instance

این فاز مستقل از تست Billing است. مسیر اصلی و معیار پذیرش، Linux native روی Debian همراه Pterodactyl/Wings است. Windows فقط برای تست دستی سرور CS2، لاگ‌ها، Load پلاگین‌ها و تنظیم config استفاده می‌شود و مدیرهای جایگزین Windows/cross-platform فعلاً Deferred هستند؛ فقط در صورت blocker مشخص در مسیر Linux دوباره بررسی می‌شوند. هیچ Shared Base یا ابزار lifecycle دوم روی سرور اصلی فعال نمی‌شود تا آزمون isolation و recovery موفق شود. مرجع authoritative برای وضعیت هر candidate و نتیجه‌ها [Testing Bench](TESTING-BENCH.md) است.

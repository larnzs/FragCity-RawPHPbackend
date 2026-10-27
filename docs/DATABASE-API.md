# FragCity — Database & API

> تصمیم جاری (۲۷ اکتبر ۲۰۲۶): پورت 3306 برای دسترسی مستقیم از مسیر بیرونی استفاده نمی‌شود. برای WeaponPaints، API امضاشدهٔ HTTPS روی `api.fragcity.ir` روی Debian پیاده‌سازی شده و تست‌های signed `ping_db` و read-only `get_selection` موفق بوده‌اند؛ تست نوشتن هنوز کامل نیست، پس این مسیر فعلاً تأیید PoC است، نه Production نهایی.

## 1. نتیجهٔ تست Remote Database

تست از Debian انجام شد:

```bash
nc -vz -w 5 89.39.208.82 3306
```

خروجی:

```text
nc: connect to 89.39.208.82 port 3306 (tcp) timed out: Operation now in progress
```

کاربر بررسی cPanel و وضعیت میزبان را تأیید کرده است: مسیر Remote MariaDB روی پورت 3306 برای این پلن قابل استفاده نیست و پورت‌های در دسترس هاست طبق اطلاعات فعلی 21، 80 و 443 هستند. این سند نتیجه را به‌عنوان محدودیت معماری ثبت می‌کند؛ در صورت تغییر سرویس هاست، امکان Remote DB می‌تواند جداگانه دوباره ارزیابی شود.

## 2. مسیر ارتباطی فعلی

```text
FragCity Raw PHP website (shared hosting)
        |
        | HTTPS :443 + HMAC-signed request
        v
api.fragcity.ir (Debian / Nginx / PHP-FPM)
        |
        | localhost / 127.0.0.1:3306
        v
GLOBAL_weaponpaints (current WeaponPaints API target)
        ^
        | shared plugin data read by the CS2 containers
WeaponPaints plugin on CS2 servers
```

این نمودار مسیر فعلی API مربوط به WeaponPaints را نشان می‌دهد؛ APIهای Ranks، VIP و SimpleAdmin هنوز ساخته/تأیید نشده‌اند. پورت MariaDB نباید عمومی شود و credential مورد استفادهٔ API باید محدود به جداول لازم باشد.

## 3. API لینوکسی فعلی برای WeaponPaints

کد این API در پوشهٔ `linuxapi/` قرار دارد؛ راهنمای نصب و تست کامل در [`linuxapi/SETUP.md`](../linuxapi/SETUP.md) است.

- آدرس API: `https://api.fragcity.ir`؛ endpoint اصلی: `POST /v1/weaponpaints`؛ health endpoint عمومی: `GET /health`.
- روی Debian با Nginx و PHP-FPM اجرا می‌شود و به MariaDB محلی از طریق `127.0.0.1:3306` وصل می‌شود.
- دیتابیس هدف فعلی این API فقط `GLOBAL_weaponpaints` است؛ `GLOBAL_simpleadmin` را نباید به‌عنوان دیتابیس WeaponPaints فرض کرد.
- درخواست‌های POST با HMAC-SHA256 امضا می‌شوند؛ امضا روی timestamp، nonce، method، path و hash بدنهٔ دقیق درخواست محاسبه می‌شود. سرور timestamp محدود، nonce ضد replay و مقایسهٔ امن امضا را بررسی می‌کند.
- SQL با prepared statement اجرا می‌شود و actionها allowlist هستند؛ caller نمی‌تواند نام جدول یا قطعهٔ SQL دلخواه ارسال کند.

### وضعیت آزمون — ۲۷ اکتبر ۲۰۲۶

- HTTPS health check روی origin مستقیم موفق شد (`HTTP/2 200`). این تست به‌تنهایی دسترسی از تمام شبکه‌های بیرونی را ثابت نمی‌کند.
- signed `ping_db` موفق شد: HTTP 200، `success: true`، `data.database: ok` و `PASS: signed ping_db`.
- signed read-only `get_selection` نیز موفق شد و داده‌های موجود را برای `skins`، `knife`، `agents`، `music` و `pins` برگرداند؛ خروجی با `PASS: read-only get_selection` پایان یافت.
- `get_selection` فعلی `wp_player_gloves` را query نمی‌کند؛ بنابراین خواندن gloveها هنوز با این تست تأیید نشده است.
- write/read-back های کنترل‌شده هنوز انجام نشده‌اند. تا پایان آن‌ها و بررسی‌های تکمیلی، سایت shared-host باید روی `transport: local` بماند.
- resolver پیش‌فرض خود Debian برای `api.fragcity.ir` با مسیر DNS-tunnel فعلی پاسخ `NXDOMAIN` داده است؛ authoritative/public DNS رکورد درست دارد. تست‌های CLI روی Debian از mapping موقت و محدود hostname استفاده کردند. تنظیمات ISP DNS/NetworkManager را برای این workaround تغییر ندهید.

## 4. APIهای قدیمی Agent و C# — نیازمند بازبینی

در ریپوی `FragCity-RawPHPbackend` مسیرهایی مانند این‌ها وجود دارند:
- `agent/agent_login.php`
- `agent/heartbeat.php`
- `agent/fetch_tasks.php`
- `agent/sync_bulk.php`
- `agent/db.php`

این فایل‌ها یک نقطهٔ شروع موجود هستند، نه API آمادهٔ Production. قبل از تکیه به آن‌ها، احراز هویت و validation باید اصلاح و قراردادهای ورودی/خروجی تثبیت شوند.

### نکته‌های کشف‌شده از بررسی ایستا
- بعضی endpointها Bearer token را الزامی می‌کنند؛ C# bulk sync در نسخه بررسی‌شده آن header را ارسال نمی‌کند.
- جریان login یک token تولید می‌کند، ولی ثبت token و انتقال token در همه مسیرها هماهنگ به نظر نمی‌رسد.
- در heartbeat عبارت نحوی مشکوک/نامعتبر برای fetch رکورد دیده شده و باید با PHP lint تأیید شود.
- نام جدول به map محدود شده، اما نام ستون از payload وارد SQL می‌شود؛ برای شناسهٔ جدول/ستون باید allowlist مجاز داشته باشیم.
- sync مستقیم عمومی دیتابیس C# از `DELETE FROM` پیش از درج استفاده می‌کند و برای هدف چندنودی/دادهٔ مشترک گزینهٔ امنی نیست.
- برخی فایل‌ها credential را داخل سورس نگه می‌دارند؛ Rotation و انتقال به تنظیمات امن باید با برنامه انجام شود، نه با تغییر ناگهانی که سرویس زنده را از کار بیندازد.

شرح کامل در [Security Review](SECURITY-AUDIT.md) است.

## 5. تصمیم دربارهٔ C# فعلی

فعلاً کل C# Agent را به Debian منتقل نکنیم. ابتدا پنل بازی و فروشگاه را در PoC ارزیابی می‌کنیم؛ ممکن است مدیریت سرور، کنسول و بخشی از provisioning را کاملاً جایگزین کنند. پس از تست، فقط مسئولیت‌هایی باقی می‌مانند که محصولات منتخب پوشش نمی‌دهند.

اگر برای داده‌های plugin به worker نیاز باشد، worker جدید باید:
- فقط schema/table/columnهای تأییدشده را بخواند.
- هر نود را با credential مستقل و مجوز محدود بشناسد.
- payload محدود، idempotency و retry با backoff داشته باشد.
- داده را روی HTTPS بفرستد و نتیجه API را بررسی کند.
- برای خطاها log امن و قابل ردیابی بنویسد.
- امکان reconciliation پس از offline بودن نود/API داشته باشد.
- هرگز تمام دیتابیس‌ها را با suffix matching یا پاک‌کردن کلی جدول‌ها synchronize نکند.

## 6. قواعد داده

برای هر موجودیت باید مالک داده روشن باشد. نمونه:
- مشتری/کیف پول/سفارش: منبع حقیقت Billing منتخب.
- وضعیت اجرای سرور و منابع تخصیص‌یافته: پنل بازی منتخب.
- وضعیت runtime نود: API/پنل نود.
- دادهٔ یک plugin: دیتابیس همان plugin، با سیاست اشتراک مشخص؛ API only for whitelisted data.
- تاریخ انقضا: منبع حقیقت Billing یا ماژول lifecycle مشخص، نه یک مقدار متناقض در چند جدول.

## 7. آزمون‌های لازم

1. درخواست API بدون token و با token منقضی باید امن رد شود.
2. نود A نتواند با credential خود دادهٔ نود B یا مشتری دیگر را تغییر دهد.
3. نام جدول/ستون ناشناخته، payload بزرگ، نوع دادهٔ نامعتبر و rowهای ناقص رد شوند.
4. درخواست تکراری نتواند دوباره رکورد مالی یا عملیات provisioning را اعمال کند.
5. timeout، 5xx و قطعی شبکه با retry کنترل‌شده و log امن بازیابی شوند.
6. قبل از هر migration یا sync تغییردهنده، Backup و تست Restore وجود داشته باشد.

## 8. الگوی پیشنهادی برای Ranks، VIP و SimpleAdmin

هدف این است که در integrationهای بعدی، **مدل امنیتی و روش استقرار** API فعلی دوباره استفاده شود؛ نه اینکه منطق WeaponPaints یا مجوزهای دیتابیس آن بی‌قیدوشرط کپی شود.

1. از HTTPS و لایهٔ امضای درخواست HMAC، timestamp، nonce ضد replay، اعتبارسنجی payload و prepared statements به‌عنوان الگو استفاده شود. برای کلاینت‌ها/دامنه‌های مختلف، secretهای جدا یا تفکیک‌پذیر را بررسی کنید تا امکان rotation یا revoke مستقل وجود داشته باشد.
2. هر دامنه endpoint و allowlist مشخص داشته باشد؛ مثال‌های طراحی آینده می‌توانند `/v1/ranks`، `/v1/vip` و `/v1/simpleadmin` باشند. این مسیرها فعلاً نمونه‌اند، نه endpointهای پیاده‌سازی‌شده. از یک endpoint عمومی با table/column name قابل انتخاب توسط caller استفاده نشود.
3. قبل از کدنویسی، نسخهٔ واقعی هر پلاگین، schema، primary key، معنا و رفتار فعلی read/write، و نیاز global-vs-node-local بودن داده‌ها بررسی شود. برای SimpleAdmin دیتابیس `GLOBAL_simpleadmin` را مستقلاً بررسی کنید؛ مجوز یا رفتار WeaponPaints را به آن تعمیم ندهید.
4. برای هر integration حداقل دسترسی لازم تعیین شود. حساب `fragcity_weaponpaints_api` را برای راحتی گسترش ندهید؛ مجوزهای لازم برای هر پلاگین/جدول و محل امن نگهداری nonceها باید مستقل طراحی و مستند شوند.
5. تست‌ها به‌ترتیب اجرا شوند: signed health/ping، query فقط‌خواندنی، سپس write با بازیکن آزمایشی، و در پایان read-after-write/مقایسهٔ ردیف‌های دیتابیس. قبل از تغییر داده‌های واقعی، backup و روش recovery مشخص باشد.
6. مشخص کنید کدام سرویس منبع حقیقت داده است؛ برای نمونه، سایت می‌تواند درخواست تغییر rank/VIP را بفرستد، اما قرارداد باید روشن کند وضعیت نهایی کجا ذخیره می‌شود، کدام سرورها آن را می‌خوانند و چه زمانی اعمال می‌شود. از ایجاد چند نسخهٔ متناقض از یک وضعیت جلوگیری شود.

هیچ API برای Ranks، VIP یا SimpleAdmin تا قبل از این بررسی‌ها، تعریف قرارداد و تست مجزا، آماده یا تأییدشده محسوب نمی‌شود.
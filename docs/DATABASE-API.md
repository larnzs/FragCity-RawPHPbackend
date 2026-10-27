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

## 4. API قدیمی Agent/CMD — خارج از مسیر فعال سایت

در شاخهٔ پاک‌سازی `cleanup/remove-legacy-agent-api`، فایل‌های endpoint قدیمی زیر `agent/` از درخت کاری ریپو حذف شده‌اند؛ این مسیرها جزو API فعلی سایت نیستند و نباید برای توسعهٔ جدید استفاده شوند.

- API فعال سایت برای WeaponPaints فقط از `linuxapi/` و کلاینت `class/FragCityLinuxApiClient.php` استفاده می‌کند.
- `api/wallet_api.php` یک مسیر جدا برای رابط کیف پول سایت است و حذف نشده است.
- ریپوی `FragCity-WebAPIClient` یک پروژهٔ مستقل C# است؛ پاک‌سازی این ریپو به معنی حذف یا تغییر آن پروژه نیست.
- حذف فایل‌ها از شاخهٔ جاری، نسخه‌های قدیمی را از تاریخچهٔ Git پاک نمی‌کند. اگر credentialی قبلاً در سورس یا لاگ بوده، باید جداگانه بررسی و در صورت نیاز rotate شود.

گزارش امنیتی قدیمی هنوز می‌تواند برای فهم ریسک‌های تاریخی مفید باشد، اما ارجاع‌های آن به فایل‌های `agent/` وضعیت فعلی شاخهٔ پاک‌سازی را توصیف نمی‌کنند.

## 5. تصمیم دربارهٔ برنامهٔ C# فعلی

توسعه و مهاجرت برنامهٔ C# Agent/CMD در محدودهٔ فعلی سایت نیست. این ریپو روی سایت Raw PHP، تنظیمات محیطی و APIهای HTTPS موردنیاز سایت تمرکز دارد. اگر در آینده نیاز مشخصی به قابلیت‌های آن برنامه پیدا شد، باید جداگانه و با تعریف دامنهٔ کار بررسی شود.

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

## 9. Website database remains on shared hosting — no Linux MySQL connection

The main website database `kyonxcbt_fragcity_services` is owned by the website and remains on shared hosting. The Linux API does not connect to that database and must not be given its host, username, or password.

For Agent/CMD traffic, `api.fragcity.ir/v1/agent/...` acts as a signed HTTPS gateway. It forwards the request to `https://fragcity.ir/api/internal_agent_bridge.php`; that PHP endpoint runs on shared hosting and opens the local `databases.main` PDO connection. The original database remains the source of truth; no replica or database dump is copied to Linux.

The bridge uses a separate secret stored in the private website setting `integrations.linuxapi_site_bridge.shared_secret` and the Linux runtime setting `website_bridge.shared_secret`. This secret must differ from the WeaponPaints API secret. `linuxapi/site-bridge-schema.sql` creates nonce and login-throttling tables on the shared-hosting main DB only; never execute it on Linux.

The first migration preserves only actions with an understood contract. The legacy billing/expiry route `registered_servers_api.php` stays disabled (HTTP 410) until the planned panel/wallet design is specified. The legacy tasks poll returns an empty list because the provided database inventory does not include a `tasks` table. Generic bulk sync is constrained to an explicit plugin-table allowlist and column metadata; it cannot select arbitrary tables or write `kyonxcbt_fragcity_services`.

The new bridge is not production-ready merely because the code exists. Verify the real website schema, deploy the bridge and site-only schema on shared hosting, configure separate matching secrets, validate the signed bridge health check, and test each mutating action against a controlled node. The C# WebAPIClient repository is reference-only for this workstream; no C# code changes or client-switching work belong in this PR.


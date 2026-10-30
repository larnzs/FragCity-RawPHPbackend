# FragCity — Database & API

> وضعیت فعلی: وب‌سایت و Linux API دو Integration مستقل برای WeaponPaints و Server Browser دارند. دیتابیس اصلی سایت شامل همان ده جدول اعلام‌شده است؛ دیتابیس `GLOBAL_weaponpaints` همچنان روی Debian قرار دارد و فقط از طریق Linux API دسترسی داده می‌شود.

## 1. مسیرهای داده

### دیتابیس اصلی وب‌سایت

`kyonxcbt_fragcity_services` توسط وب‌سایت/هاست مدیریت می‌شود. در محیط توسعه یک کپی محلی از این دیتابیس در Laragon استفاده می‌کنیم. جدول‌های موردتأیید آن:

- حساب‌ها و نقش‌ها: `users`
- کیف پول: `user_wallets`, `wallet_transactions`, `withdraw_requests`
- کدهای VIP: `vip_codes`, `vip_usage`
- پشتیبانی: `tickets`, `ticket_replies`
- بازیابی رمز: `password_resets`
- کمک‌های مالی: `donations`

### اطلاعات پایدار حساب Steam

ورود از `steam/steam_login.php` اطلاعات عمومی Steam Community را می‌گیرد. پس از اعمال migration زیر، مشخصات عمومی حساب در همان ردیف `users` ذخیره می‌شوند:

برای جلوگیری از خواندن حساب از یک دیتابیس و ذخیرهٔ اطلاعات Steam در دیتابیس دیگر، مسیر ورود Steam و ثبت‌نام از پروفایل دیتابیسی تعیین‌شده در `files.index.database_profile` استفاده می‌کنند. روی هاست اشتراکی، فایل runtime `settings/settings.json` باید این پروفایل را به اتصال دیتابیس اصلی هاست نگاشت کند؛ معمولاً نام پروفایل `main` است و مقادیر واقعی host/name/username/password باید فقط در همان فایل runtime قرار بگیرند، نه Git. تنظیمات محلی Laragon و هاست اشتراکی جدا هستند.

- `steam_personaname`, `steam_profileurl`, وضعیت آنلاین و visibility، تاریخ ساخت، آخرین خروج، کشور و بازی جاری.
- `steam_avatar_url` آدرس امن آواتار Steam و `steam_avatar_path` مسیر نسخهٔ cacheشده روی سرور را نگه می‌دارند.
- فایل تصویر در `img/steam-avatars/` نگهداری می‌شود؛ آواتار سفارشی و قابل‌ویرایش کاربر در `img/avatars/` جدا و با اولویت باقی می‌ماند. این طراحی از ذخیرهٔ باینری عکس در هر خواندن ردیف کاربر جلوگیری می‌کند.

قبل از تست، از دیتابیس هدف backup بگیر و فایل `database/migrations/20261030_add_steam_profile_fields_to_users.sql` را **یک‌بار** با حساب دیتابیسی دارای مجوز `ALTER` اجرا کن؛ این کار را جداگانه برای کپی محلی Laragon و دیتابیس Production انجام بده. تا قبل از migration، ورود Steam همچنان مجاز می‌ماند اما ذخیرهٔ مشخصات ممکن است انجام نشود؛ `php tools/site-bridge-preflight.php` پس از migration باید ستون‌های جدید را هم تأیید کند.

هیچ جدول قدیمی Windows Agent جزو این schema نیست. در مسیر فعال PHP نباید queryای به `agent_tokens`, `agent_nodes`, `pending_servers`, `registered_servers`, `server_ports`, `server_configs`, `agent_commands`, `game_servers`, `server_runtime_stats` یا `rcon_command_logs` اجرا شود.

### عیب‌یابی ذخیره‌سازی اطلاعات Steam روی هاست

پس از انتشار کد جدید، از Terminal هاست (با runtime settings همان هاست) اجرا کن:

```bash
php tools/steam-profile-preflight.php 7656119xxxxxxxxxx
```

شناسهٔ نمونه را با SteamID64 واقعیِ یک حسابی جایگزین کن که ثبت‌نامش در سایت کامل شده است. این ابزار فقط‌خواندنی است: رمزها و کلید API را چاپ نمی‌کند، یک درخواست خواندن به Steam Web API می‌زند و بررسی می‌کند که کلید تعریف شده، اتصال به کدام دیتابیس انجام شده، ستون‌های `users` وجود دارند، ردیف کاربر حاضر است و فیلدها پر شده‌اند یا نه. اگر دریافت Steam شکست بخورد، دلیل با جزئیات امن در `error_log` سمت PHP ثبت می‌شود. این اسکریپت را با PHP CLI اجرا کن؛ از طریق URL در مرورگر قابل اجرا نیست.

### WeaponPaints

```text
PHP website / Laragon
       |
       | HTTPS + HMAC + timestamp + nonce
       v
https://api.fragcity.ir/v1/weaponpaints
       |
       | localhost / 127.0.0.1:3306
       v
GLOBAL_weaponpaints on Debian
       |
       v
CS2 plugin containers
```

- API فعال: `GET /health` و `POST /v1/weaponpaints`.
- دیتابیس هدف در Linux: `GLOBAL_weaponpaints`.
- کلاینت وب‌سایت: `class/FragCityLinuxApiClient.php`.
- مخزن دادهٔ وب‌سایت: `class/WeaponPaintsRepository.php` که API-only است.
- `settings/settings.example.json` باید `files.database.weaponpaints_plugin.transport = "api"` داشته باشد.
- سایت نباید به `GLOBAL_weaponpaints` وصل شود و نباید پروفایل مستقیم قدیمی `kyonxcbt_weaponpaints_plugin` را استفاده کند.
- credentialهای دیتابیس Linux نباید در `settings.json` سایت قرار بگیرند.

API از HMAC-SHA256، زمان درخواست، nonce ضد replay، TLS validation، prepared statements و action allowlist استفاده می‌کند. نام جدول یا SQL دلخواه از طرف وب‌سایت ارسال نمی‌شود.

### Server Browser عمومی

Server Browser فعال برخلاف WeaponPaints نیازی به Linux API جداگانه ندارد:

- \`public_server_scan_targets\` نگهدارندهٔ IP و بازهٔ پورت مجاز است.
- \`public_server_scan_ports\` وضعیت هر پورت، نتیجهٔ A2S و زمان Query بعدی را نگه می‌دارد.
- Worker زمان‌بندی‌شدهٔ \`tools/refresh-public-server-browser.php\` از طریق UDP و در batchهای محدود Query می‌گیرد.
- صفحهٔ سایت فقط \`class/PublicServerBrowserRepository.php\` را برای خواندن رکوردهای آنلاین استفاده می‌کند.
- هیچ درخواست UDP یا اسکن پورت هنگام بازدید کاربر اجرا نمی‌شود.
- پورت‌های بدون پاسخ تا یک ساعت دوباره Query نمی‌شوند؛ سرورهای معتبر آنلاین با فاصلهٔ کوتاه‌تری به‌روزرسانی می‌شوند.

Migration و راه‌اندازی Cron در \`docs/PUBLIC-SERVER-SCANNER.md\` مستند شده است. بخش مستقل Pterodactyl برای استفادهٔ احتمالی آینده با نام Pterodactyl Standby نگهداری می‌شود.

## 2. وضعیت Agent ویندوزی قدیمی

API قدیمی Agent/InstanceMaker بازنشسته شده است. `/v1/agent/*` از Linux API پاسخ HTTP 410 می‌گیرد و `api/internal_agent_bridge.php` نیز تنها پاسخ بازنشستگی می‌دهد؛ هیچ‌کدام به جدول‌های سایت وصل نمی‌شوند. schema اضافه‌ای برای nonce/throttle این bridge دیگر لازم نیست و نباید `linuxapi/site-bridge-schema.sql` قدیمی اجرا شود.

ریپوی C# `WebAPIClient` در این کار فقط برای مرجع قراردادهای قدیمی است و سورس آن تغییر نمی‌کند.

## 3. خرید و مدیریت سرور

خرید سرور، لیست سرورهای مشتری و کنترل‌های قدیمی پنل به جدول‌های حذف‌شدهٔ Agent وابسته بودند. اکنون مسیرهای قدیمی به‌صورت fail-closed غیرفعال‌اند؛ هیچ پرداختی برای خرید سرور آغاز یا تأیید نمی‌شود و از این صفحات موجودی کیف پول کسر نمی‌شود.

مرحلهٔ بعد برای بازگردانی این قابلیت‌ها، پیاده‌سازی اتصال به Pterodactyl API و طراحی روشن منبع حقیقت سفارش/وضعیت است. تا آن زمان از schema قدیمی استفاده نکن و جدول‌های حذف‌شده را صرفاً برای pass کردن تست دوباره نساز.

## 4. Preflight محلی

از ریشهٔ ریپو و با PHP Laragon اجرا کن:

```powershell
php tools/site-bridge-preflight.php
php linuxapi/tools/architecture-check.php
```

اولی فقط اتصال و schema ده جدول `kyonxcbt_fragcity_services` را read-only بررسی می‌کند. دومی یک بررسی static برای تضمین جدایی دیتابیس WeaponPaints، خاموش‌بودن bridge قدیمی و عدم query به schema Windows Agent در مسیرهای فعال است. هیچ‌یک جایگزین تست‌های end-to-end نمی‌شود.

## 5. آزمون‌های API

قبل از فعال‌کردن writeهای واقعی:
1. ابتدا `GET /health` را بررسی کن.
2. یک `ping_db` امضاشده اجرا کن.
3. `get_selection` را با SteamID تستی بررسی کن.
4. برای write فقط از حساب/SteamID آزمایشی استفاده کن و پس از آن read-after-write را بررسی کن.
5. در تست محلی، مراقب باش آدرس API به سرور واقعی اشاره می‌کند و می‌تواند دادهٔ واقعی را تغییر دهد.

این مستندات تغییرات معماری داخل Git را توضیح می‌دهد؛ به معنی deploy شدن تغییرات جدید روی cPanel یا Debian نیست.

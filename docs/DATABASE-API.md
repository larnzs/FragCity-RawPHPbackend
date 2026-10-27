# FragCity — Database & API

> وضعیت فعلی: وب‌سایت و API WeaponPaints دو مسیر مستقل دارند. دیتابیس اصلی سایت شامل همان ده جدول اعلام‌شده است؛ دیتابیس `GLOBAL_weaponpaints` همچنان روی Debian قرار دارد و فقط از طریق Linux API دسترسی داده می‌شود.

## 1. مسیرهای داده

### دیتابیس اصلی وب‌سایت

`kyonxcbt_fragcity_services` توسط وب‌سایت/هاست مدیریت می‌شود. در محیط توسعه یک کپی محلی از این دیتابیس در Laragon استفاده می‌کنیم. جدول‌های موردتأیید آن:

- حساب‌ها و نقش‌ها: `users`
- کیف پول: `user_wallets`, `wallet_transactions`, `withdraw_requests`
- کدهای VIP: `vip_codes`, `vip_usage`
- پشتیبانی: `tickets`, `ticket_replies`
- بازیابی رمز: `password_resets`
- کمک‌های مالی: `donations`

هیچ جدول قدیمی Windows Agent جزو این schema نیست. در مسیر فعال PHP نباید queryای به `agent_tokens`, `agent_nodes`, `pending_servers`, `registered_servers`, `server_ports`, `server_configs`, `agent_commands`, `game_servers`, `server_runtime_stats` یا `rcon_command_logs` اجرا شود.

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

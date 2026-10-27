# نقشهٔ مهاجرت API قدیمی به Linux API

> وضعیت این سند: inventory و طراحی مهاجرت؛ هنوز به معنی پیاده‌سازی یا آماده‌بودن endpointهای Agent در Linux API نیست.
> شاخهٔ کاری: feature/linuxapi-legacy-api-migration

## هدف

قراردادهای API قدیمی که قبلاً زیر fragcity.ir/agent/*.php اجرا می‌شدند باید، پس از تعیین منبع حقیقت داده‌ها، به سرویس Debian زیر https://api.fragcity.ir منتقل شوند. API جدید WeaponPaints مسیر مستقل خود را دارد و باید تا پایان مهاجرت بدون تغییر رفتاری باقی بماند.

## آنچه اکنون فعال است

- GET /health
- POST /v1/weaponpaints با درخواست HMAC، timestamp، nonce ضد replay و action allowlist
- عملیات WeaponPaints فعلی: ping_db، get_selection، save_glove، save_agent، save_music، save_pin، save_knife، save_skin

این endpointها فقط به دیتابیس GLOBAL_weaponpaints وصل‌اند. حساب دیتابیس فعلی مجوز محدود روی جدول‌های WeaponPaints و جدول nonce دارد؛ پس نباید فرض کنیم همین اتصال یا credential برای کل API قدیمی کافی است.

## Inventory قراردادهای قدیمی

| فایل PHP قدیمی | عملیات/مصرف‌کنندهٔ مشاهده‌شده | داده‌های اصلی | تصمیم مهاجرت |
|---|---|---|---|
| agent/agent_login.php | ApiClient.cs؛ ورود Agent با login id/password | users، agent_tokens | نیازمند طراحی مجدد auth و منبع هویت |
| agent/heartbeat.php | ApiClient.cs؛ گزارش وضعیت نود | agent_tokens، agent_nodes | قابل طراحی به‌عنوان endpoint نود؛ کد قدیمی خطای syntax دارد |
| agent/fetch_tasks.php | ApiClient.cs؛ دریافت task | agent_tokens، tasks | قبل از انتقال، نیاز واقعی و schema/قرارداد task تعیین شود |
| agent/sync_bulk.php | ApiClient.cs؛ ارسال bulk sync | agent_tokens و جداول plugin | به‌صورت عمومی و با columnهای ورودی منتقل نشود؛ باید per-plugin allowlist داشته باشد |
| agent/agent_getservers_api.php | AgentConfig.cs؛ fetch/sync وضعیت سرورها | pending_servers، agent_nodes، game_servers، server_runtime_stats و احتمالاً registered_servers | نیازمند انتقال schema و تعیین مالک وضعیت سرور |
| agent/rcon_commands_api.php | AgentService.cs؛ صف فرمان‌های RCON | agent_tokens، جدول صف/لاگ RCON | نیازمند auth نود و schema مشخص |
| agent/server_configs_api.php | GameModeManager.cs؛ اطلاعات سرور/کانفیگ | registered_servers | مالک داده و مجوز خواندن باید مشخص شود |
| agent/api_sync_skins.php | SkinSyncService.cs؛ همگام‌سازی قدیمی skin | جداول wp_player_* | endpoint قدیمی با token ثابت و خواندن کل جدول است؛ نباید عیناً منتقل شود. مسیر WeaponPaints جدید باید منبع حقیقت باشد |
| agent/agent_commands_api.php | در snapshot فعلی C# ارجاع مستقیمی پیدا نشد | agent_tokens، agent_commands | فعلاً orphan/legacy؛ قبل از حذف نهایی callerهای بیرونی بررسی شوند |
| agent/server_ports_api.php | در snapshot فعلی C# ارجاع مستقیمی پیدا نشد | server_ports | فعلاً orphan/legacy؛ نیاز واقعی تأیید شود |
| agent/registered_servers_api.php | در snapshot فعلی C# ارجاع مستقیمی پیدا نشد | registered_servers، agent_nodes، agent_commands | این endpoint دقیقهٔ باقی‌مانده را کم می‌کند و سرور منقضی را suspend/stop می‌کند؛ انتقال کور آن خطر مالی/داده‌ای دارد |
| agent/webhook_api.php | helper قدیمی برای درخواست به agent روی IP/port | HTTP /api/execute روی agent | endpoint server-side helper است، نه API عمومی self-contained؛ فعلاً پورت نمی‌شود |

## وابستگی‌های C# که در سورس فعلی دیده شدند

- FragCity.Agent.Core/Api/ApiClient.cs:
  - agent_login.php
  - heartbeat.php
  - fetch_tasks.php
  - sync_bulk.php
- FragCity.Agent.Core/Config/AgentConfig.cs:
  - agent_getservers_api.php
- FragCity.Agent.Core/Services/AgentService.cs:
  - rcon_commands_api.php
- FragCity.Agent.Core/Database/agent_commands/GameModeManager.cs:
  - server_configs_api.php
- FragCity.Agent.Core/Database/weaponpaints_plugin/SkinSyncService.cs:
  - api_sync_skins.php

این فهرست بر مبنای سورس ریپوی C# فعلی است؛ ممکن است نسخهٔ کامپایل‌شده یا deployment قدیمی endpointهای بیشتری مصرف کند.

## Blocker مهم: API فقط کد نیست؛ داده هم باید منتقل شود

کد PHP قدیمی به جداولی روی دیتابیس سایت/هاست متکی است. Linux API فعلی فقط به GLOBAL_weaponpaints دسترسی دارد و schema/credential حسابش برای جدول‌های Agent، کاربران، صف‌ها، سفارش‌ها و billing تعریف نشده است.

قبل از فعال‌کردن routeهای جدید باید:
1. برای هر جدول منبع حقیقت و محل میزبانی مشخص شود.
2. schema واقعی جدول‌ها از محیط موجود export و بررسی شود؛ schema را از روی نام ستون‌ها حدس نمی‌زنیم و dump حاوی password hash یا دادهٔ کاربران وارد Git نمی‌شود.
3. با توجه به تصمیم آینده برای بازطراحی ورود، پنل و wallet، منطق users/billing/registered_servers به‌صورت موقت و ناخواسته دو منبع حقیقت پیدا نکند.
4. برای Linux API دیتابیس/مجوز مستقل و حداقل‌دسترسی تعریف شود؛ credential دیتابیس اصلی WeaponPaints گسترش داده نشود.
5. قراردادهای ورودی/خروجی و auth کلاینت تعیین شوند. endpointهای old-style که token را خودکار ثبت می‌کنند، token را در URL می‌گیرند، کل جدول plugin را برمی‌گردانند یا SQL column identifier از payload می‌سازند، عیناً port نمی‌شوند.

## قرارداد پیشنهادی Linux API

- مسیر واحد و versioned برای Agent، مانند POST /v1/agent، با action allowlist و schema معتبر برای هر action.
- عملیات نود، command queue، ports و metrics از عملیات billing و user authentication جدا باشند.
- هیچ requestی نام جدول/ستون آزاد، SQL، یا مسیر فایل دلخواه نپذیرد.
- از HMAC فعلی فقط به‌عنوان الگوی پیاده‌سازی signing/replay protection استفاده شود؛ secret وب‌سایت و credential نودها یکسان/مشترک فرض نشوند.
- عملیات مالی/expiry فقط بعد از تعیین مالک داده و تعریف idempotency/transaction منتقل شوند.
- api_sync_skins.php با دسترسی table-wide جایگزین endpoint فیلترشده بر اساس SteamID و قرارداد WeaponPaints جدید شود؛ secret قدیمی باید افشاشده فرض شود.

## ترتیب اجرایی

1. تثبیت inventory و schema واقعی؛ حفظ endpoint WeaponPaints فعلی.
2. انتقال داده‌های لازم به ساختار دیتابیس مستقل Linux با طرح migration/rollback.
3. ساخت auth و route جدید Agent با allowlist و تست‌های contract/unit.
4. انتقال actionهای نود/metrics/queue/ports که data owner روشن دارند.
5. بازطراحی و انتقال SkinSync در صورت تأیید اینکه هنوز لازم است.
6. انتقال login/billing/server lifecycle فقط پس از تصمیم معماری ورود، پنل و wallet.
7. تغییر کلاینت فقط برای actionهایی که قرار است حفظ شوند؛ سپس تست integration روی حساب و نود آزمایشی.
8. حذف باقی‌مانده‌های legacy بعد از اثبات عدم وجود consumer در production.

## Implemented in the current working branch

- Linux API gateway aliases now exist under /v1/agent/<legacy-file>.php and forward requests to the site-side connector over signed HTTPS.
- Website-side connector source is api/internal_agent_bridge.php. It opens only the website-local main database using settings/loader.php; the Linux API does not use a network connection to kyonxcbt_fragcity_services.
- linuxapi/site-bridge-schema.sql creates replay-protection and login-throttling tables on the shared-hosting website DB only.
- The Linux runtime config has a separate website_bridge secret and URL; it is distinct from the existing WeaponPaints shared secret.
- Preserved legacy-safe operations include Agent login, heartbeat, get-pending/update-status/sync-metrics, RCON command polling/result updates, node config reads, port operations, agent command queue, allowlisted plugin-table bulk upsert, and paged skin export.
- registered_servers_api.php is intentionally disabled with HTTP 410 pending the planned billing/panel redesign. fetch_tasks.php returns an empty task list because tasks is not present in the supplied schema inventory.
- The old query-string skin API token is not accepted. Skin export now requires an authenticated Agent token and paginated requests.

## Deployment / validation status

This is still a development branch. The site connector has not been deployed to shared hosting, the two runtime secrets have not been generated/configured here, and the site-only SQL schema has not been imported. No production database rows have been migrated or changed by this repository work.

The existing Linux WeaponPaints route remains separate and unchanged. After the branch passes PHP lint, the next step is to deploy the site connector and schema to the shared host, configure the matching bridge secret on both sides, run the signed bridge health check, and only then update the C# client URLs and token handling. Do not connect the website database to Linux or change its MySQL port/firewall settings.

# مهاجرت API قدیمی Agent — وضعیت نهایی

> به‌روزرسانی: ۲۷ اکتبر ۲۰۲۶. API قدیمی Windows Agent/InstanceMaker دیگر استفاده نمی‌شود. این سند تاریخچه و محدودهٔ حذف را ثبت می‌کند، نه اینکه دستورالعمل راه‌اندازی routeهای قدیمی باشد.

## تصمیم جاری

- ریپوی C# `WebAPIClient` فقط مرجع خواندن قراردادهای گذشته است؛ سورس آن در این کار تغییر نمی‌کند.
- هیچ endpoint فعالی نباید به جداول قدیمی `agent_tokens`, `agent_nodes`, `pending_servers`, `registered_servers`, `server_ports`, `server_configs`, `agent_commands`, `game_servers`, `server_runtime_stats`, یا `rcon_command_logs` متصل شود.
- endpointهای قدیمی Agent روی Linux API با HTTP 410 بازنشسته شده‌اند.
- `api/internal_agent_bridge.php` عمداً fail-closed است و اتصال دیتابیس، token creation یا query به schema قدیمی انجام نمی‌دهد.
- SQL مربوط به nonce/throttling bridge قدیمی حذف شده است؛ آن را دوباره اجرا نکن.

## تنها schema تأییدشدهٔ دیتابیس سایت

دیتابیس `kyonxcbt_fragcity_services` اکنون فقط با این فهرست به‌عنوان مرجع کار می‌کند:

`donations`, `password_resets`, `tickets`, `ticket_replies`, `users`, `user_wallets`, `vip_codes`, `vip_usage`, `wallet_transactions`, `withdraw_requests`.

اگر یک قابلیت به جدول دیگری نیاز داشته باشد، باید ابتدا تصمیم معماری/مالک داده و migration مستقل داشته باشد؛ جدول legacy را بی‌دلیل بازسازی نکن.

## مسیر فعال WeaponPaints

وب‌سایت از `class/FragCityLinuxApiClient.php` استفاده می‌کند و `class/WeaponPaintsRepository.php` صرفاً named actions را از طریق `POST /v1/weaponpaints` می‌فرستد. Linux API به `GLOBAL_weaponpaints` روی localhost Debian متصل می‌شود. سایت مستقیماً به دیتابیس WeaponPaints وصل نمی‌شود.

## خرید و کنترل سرور

صفحات/endpointهای قدیمی خرید و کنترل سرور به جداول Agent ویندوزی وابسته بودند. آن‌ها اکنون امن غیرفعال‌اند تا منطق جدید بر پایهٔ Pterodactyl API پیاده‌سازی شود. هیچ سفارش، پرداخت سرور، کسر کیف پول یا فرمان سروری از مسیرهای قدیمی نباید اجرا شود.

## بررسی و تست

از ریشهٔ ریپو در محیط Laragon:

```powershell
php tools/site-bridge-preflight.php
php linuxapi/tools/architecture-check.php
```

این بررسی‌ها فقط schema ارائه‌شده و قیود static را بررسی می‌کنند؛ آن‌ها ثابت نمی‌کنند که bridge قدیمی deploy شده است (قرار نیست deploy شود)، یا که اتصال جدید Pterodactyl آماده است. اجرای smoke test و write/read-back واقعی API WeaponPaints نیز مستقل از این بررسی static است.

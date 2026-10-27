# FragCity — راهنمای تنظیمات محیطی

> وضعیت معماری در ۲۷ اکتبر ۲۰۲۶: دیتابیس اصلی سایت شامل فقط ده جدول اعلام‌شده است. API قدیمی Agent/InstanceMaker ویندوزی و جدول‌های وابسته به آن دیگر بخشی از مسیر فعال سایت نیستند.

## ۱. فایل‌های تنظیمات

- `settings/loader.php`: تنظیمات JSON و اتصال PDO به پروفایل `main` را فراهم می‌کند.
- `settings/settings.example.json`: نمونهٔ قابل‌ثبت در Git؛ credentialهای دیتابیس خالی هستند.
- `settings/settings.json`: تنظیمات خصوصی همان محیط؛ در Git ثبت نمی‌شود.
- `integrations.weaponpaints_api`: آدرس HTTPS و HMAC secret کلاینت Linux API برای WeaponPaints.
- `files.database.weaponpaints_plugin.transport`: باید `api` باشد. وب‌سایت نباید مستقیماً به دیتابیس WeaponPaints متصل شود.

## ۲. دیتابیس‌ها و مالک داده

### دیتابیس اصلی سایت

پروفایل `databases.main` برای دیتابیس محلی/سایت `kyonxcbt_fragcity_services` است. تنها جداول تأییدشدهٔ این دیتابیس:

`donations`, `password_resets`, `tickets`, `ticket_replies`, `users`, `user_wallets`, `vip_codes`, `vip_usage`, `wallet_transactions`, `withdraw_requests`.

ورود و حساب کاربری از `users` استفاده می‌کنند؛ کیف پول از `user_wallets` و `wallet_transactions`؛ پشتیبانی از `tickets` و `ticket_replies`؛ و سایر قابلیت‌ها فقط از جدول‌های موجود در همین فهرست استفاده می‌کنند.

### WeaponPaints

دیتابیس `GLOBAL_weaponpaints` روی سرور Linux قرار دارد. وب‌سایت با `class/FragCityLinuxApiClient.php` و `class/WeaponPaintsRepository.php` از طریق `https://api.fragcity.ir/v1/weaponpaints` درخواست امضاشده می‌فرستد. در `settings.json` نباید پروفایل اتصال مستقیم `weaponpaints` یا نام دیتابیس قدیمی `kyonxcbt_weaponpaints_plugin` تعریف شود.

### قابلیت‌های سرور

جداولی مانند `agent_tokens`, `agent_nodes`, `pending_servers`, `registered_servers`, `server_ports`, `agent_commands` و `server_configs` مربوط به سیستم قدیمی Windows Agent بودند و در schema جاری وجود ندارند. endpointهای قدیمی Agent با HTTP 410 بازنشسته شده‌اند. خرید و مدیریت سرور تا تکمیل اتصال جدید به Pterodactyl، عمداً غیرفعال است و نباید پرداخت/کسر کیف پول یا query به جدول‌های قدیمی انجام دهد.

## ۳. ساخت تنظیمات محلی Laragon

از ریشهٔ ریپو اجرا کن:

```powershell
Copy-Item .\settings\settings.example.json .\settings\settings.json
```

اگر فایل `settings.json` از قبل وجود دارد، آن را بازنویسی نکن. مقادیر محلی را چنین تنظیم کن:

- `app.environment`: `local`
- `app.site_base_url`: آدرس Virtual Host محلی؛ برای مثال `http://fragcity.test`
- `databases.main.host`: معمولاً `127.0.0.1`
- `databases.main.port`: معمولاً `3306`
- `databases.main.name`: `kyonxcbt_fragcity_services`
- `databases.main.username/password`: فقط credential دیتابیس محلی Laragon
- `integrations.weaponpaints_api.base_url`: `https://api.fragcity.ir` فقط اگر عمدتاً می‌خواهی به API واقعی درخواست بفرستی.
- `integrations.weaponpaints_api.shared_secret`: secret معتبر این API؛ آن را در چت، Git یا مرورگر قرار نده.

**هشدار مهم:** API واقعی WeaponPaints ممکن است اطلاعات واقعی `GLOBAL_weaponpaints` را بخواند یا تغییر دهد. برای تست save از حساب/SteamID تستی استفاده کن. دیتابیس کپی‌شدهٔ `kyonxcbt_fragcity_services` را با API واقعی اشتباه نگیر؛ این دو دیتابیس مستقل‌اند.

## ۴. کنترل‌های قبل از اجرا

1. اطمینان پیدا کن `settings/settings.json` وارد Git نشده باشد.
2. پوشهٔ `settings` را از دسترسی HTTP عمومی مسدود کن یا فایل تنظیمات runtime را بیرون DocumentRoot بگذار.
3. تنظیمات نمونه هیچ credential واقعی نداشته باشد.
4. برای بررسی ساختار دیتابیس سایت از این فرمان read-only استفاده کن:

   ```powershell
   php tools/site-bridge-preflight.php
   ```

   این اسکریپت فقط ده جدول مشخص‌شدهٔ بالا و ستون‌های موردنیاز فعلی را کنترل می‌کند. هیچ جدول یا داده‌ای ایجاد/ویرایش/حذف نمی‌کند. اجرای آن در محیط محلی Laragon است؛ برای آن نیاز نیست چیزی روی هاست آپلود شود.

5. workflow در GitHub syntax فایل‌های PHP و بررسی معماری API را کنترل می‌کند، اما جایگزین تست UI، اتصال محلی، callback پرداخت یا تست write/read-back نمی‌شود.

## ۵. نسخهٔ PHP و انتشار

کد سایت از امکانات PHP 8.x استفاده می‌کند. برای Laragon نسخهٔ سازگار با کد و افزونه‌های PDO/cURL را انتخاب کن. نسخهٔ PHP مربوط به سایت را با نسخهٔ PHP پنل Pterodactyl روی Debian اشتباه نگیر.

هر انتشار Production باید جداگانه، پس از backup و تست، به‌صورت دستی انجام شود. pull کردن `main` روی ویندوز هیچ فایلی را روی cPanel یا Debian منتشر نمی‌کند.

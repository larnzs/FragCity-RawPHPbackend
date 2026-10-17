# FragCity — راهنمای تنظیمات محیطی

> این سند توضیح می‌دهد برنامه تنظیمات هر محیط را از کجا می‌خواند و چگونه فایل تنظیمات محلی را بسازی. وجود Loader مرکزی به معنی مهاجرت کامل همهٔ صفحات قدیمی نیست.

## ۱. قبل از شروع

در ریپو فایل‌های `settings/loader.php` و `settings/settings.example.json` وجود دارند. بااین‌حال، ممیزی امنیتی چند مسیر PHP قدیمی با credentialهای hard-coded دیتابیس را ثبت کرده است. تا وقتی همهٔ این مسیرها بررسی نشده و credentialهای قبلی به‌شکل هماهنگ Rotate نشده‌اند، سایت را به دیتابیس Production وصل نکن.

تنظیمات محیطی در JSON دسته‌بندی می‌شوند: اطلاعات هر ماژول زیر `files`، پروفایل‌های اتصال زیر `databases` و سرویس‌های بیرونی زیر `integrations` قرار می‌گیرند.

## ۲. فایل‌ها

- `settings/loader.php`: shared JSON loader, dotted-key lookup, timezone setup, database profile inheritance, and PDO factory.
- `settings/settings.example.json`: tracked example with blank DB credentials and payment merchant ID.
- `settings/settings.json`: runtime settings for one environment; ignored by Git.
- `settings/.htaccess`: protects settings under Apache.
- `settings/nginx-protect.conf`: snippet to copy into the Laragon Nginx server block. Nginx ignores .htaccess; block `/settings/` or put runtime JSON outside DocumentRoot and set `FRAGCITY_SETTINGS_FILE`.

## ۳. پروفایل‌های دیتابیس

- `main`: site, wallet, server records, Agent and panel pages.
- `weaponpaints`: WeaponPaints plugin DB.
- `plugin_sync`: database selected for mapped plugin bulk synchronization.
- `ranks_plugin` and `simpleadmin_plugin`: databases used by the ranks and ban-list adapters.
- `legacy_services`: explicitly separate profile for legacy password-reset pages.

پروفایل‌ها می‌توانند با فیلد `extends` اطلاعات اتصال را از پروفایل پایه به ارث ببرند و فقط نام دیتابیس را تغییر دهند. اگر فیلدهای لازم خالی باشند، تابع مرکزی اتصال ایجاد نمی‌کند. این رفتار فقط برای مسیرهایی تضمین شده است که واقعاً از Loader استفاده می‌کنند؛ صفحات قدیمی باید جداگانه ممیزی شوند و نباید فرض کرد همهٔ آن‌ها از credentialهای hard-coded مستقل شده‌اند.

## ۴. بخش‌های تنظیم‌شده در نمونه

The settings example defines blocks for the main index, customer/weaponpaint DB wrappers, Agent endpoints, plugin sync, Steam login routes, Zarinpal wallet/server request and callback routes, server purchase/list pages, VIP and password recovery, legacy test index, and CS2 panel configuration/settings/overview/players/servers.

The JSON example also holds the CS2 cfg allowlist, plugin table-to-database map, site URL, timezone and Zarinpal merchant/API/payment-gateway endpoints. Translation dictionaries and business logic remain in PHP; runtime settings and data lists belong in JSON.

## ۵. آماده‌سازی محلی — مرحله‌به‌مرحله

این دستورها را از ریشهٔ clone محلی ریپو اجرا کن؛ یعنی همان پوشه‌ای که زیرپوشهٔ `settings` در آن وجود دارد.

### مرحلهٔ ۱ — کپی نمونه به تنظیمات محلی

در Windows Command Prompt:

```bat
copy settings\settings.example.json settings\settings.json
```

در PowerShell:

```powershell
Copy-Item .\settings\settings.example.json .\settings\settings.json
```

اگر فایل `settings.json` از قبل وجود دارد، آن را بازنویسی نکن؛ ابتدا نسخهٔ موجود را بررسی کن.

### مرحلهٔ ۲ — تنظیم مقادیر مخصوص Local

فایل `settings/settings.json` را باز و مقادیر زیر را مطابق دیتابیس آزمایشی خودت تنظیم کن:

- `app.environment`: مقدار `local`.
- `app.site_base_url`: Virtual Host واقعی محلی، برای مثال `http://fragcity.test`.
- `databases.main.host`: معمولاً `127.0.0.1` وقتی DB روی همین Windows اجرا می‌شود.
- `databases.main.name`, `username` و `password`: فقط credentialهای دیتابیس آزمایشی اختصاصی؛ credentialهای Production را وارد نکن.
- پروفایل‌های دیتابیس پلاگین را فقط بعد از ایجاد DBهای آزمایشی پر کن.
- شناسهٔ پذیرنده و secretهای درگاه پرداخت را برای محیط محلی خالی بگذار و پرداخت واقعی را اجرا نکن.

### مرحلهٔ ۳ — محافظت از فایل تنظیمات

اگر Document Root به ریشهٔ ریپو اشاره می‌کند، پوشهٔ `settings` نباید از مرورگر خوانده شود.

- در Nginx، محتوای `settings/nginx-protect.conf` را داخل بلوک فعال `server { ... }` مربوط به همین Virtual Host وارد کن. این فایل فقط snippet است و خودکار load نمی‌شود.
- در Apache، فایل `.htaccess` را بررسی کن و مطمئن شو Apache آن را پردازش می‌کند.
- می‌توان فایل runtime را بیرون از Document Root گذاشت و `FRAGCITY_SETTINGS_FILE` را در محیط واقعی process وب‌سرور تعریف کرد. ست‌کردن آن فقط در یک ترمینال، لزوماً به process وب‌سرور منتقل نمی‌شود.

### مرحلهٔ ۴ — بررسی JSON

از ریشهٔ ریپو اجرا کن:

```bash
php -r "json_decode(file_get_contents('settings/settings.json'), true, 512, JSON_THROW_ON_ERROR); echo 'JSON OK', PHP_EOL;"
```

خروجی مورد انتظار: `JSON OK`. این تست فقط سینتکس JSON را بررسی می‌کند؛ اتصال دیتابیس، schema یا مهاجرت تمام endpointها را ثابت نمی‌کند.

### مرحلهٔ ۵ — قبل از اجرای کامل سایت

اجرای کامل سایت با دسترسی DB تا تکمیل موارد زیر مسدود است: ساخت دیتابیس و seed آزمایشی مستقل، بررسی صفحات قدیمی و حذف اتکای آن‌ها به credentialهای hard-coded، اثبات اینکه هر endpoint حساس از پروفایل صحیح استفاده می‌کند و غیرفعال بودن پرداخت/APIهای Production.

## ۶. نسخهٔ PHP و منطقهٔ زمانی

این ریپو از قابلیت‌های PHP 8.x مثل `match` و `str_starts_with` استفاده می‌کند؛ برای اجرای سایت محلی در Laragon نسخهٔ PHP باید با کد تطبیق داده شود و در محیط فعلی نسخهٔ 8.4 انتخاب شده است. این موضوع جدا از PHP 8.3 نصب‌شده برای Pterodactyl Panel روی Debian است؛ نیاز این دو برنامه را با هم اشتباه نگیر.

Loader از `app.timezone` استفاده می‌کند و پیش‌فرض UTC است. خطای Parse که به علت نسخهٔ PHP قدیمی رخ دهد، با تغییر timezone برطرف نمی‌شود.

## ۷. انتشار

برای هر محیط فایل تنظیمات runtime جدا بساز. URL، DB و تنظیمات سرویس بیرونی باید مخصوص همان محیط باشند. پیش از انتشار backup بگیر، دسترسی HTTP به فایل تنظیمات را مسدود کن، smoke test اجرا کن و راه rollback را نگه دار. credentialهای Production هیچ‌وقت نباید وارد Git شوند.

**وضعیت فعلی:** این راهنما وجود تنظیمات مرکزی را توضیح می‌دهد؛ کامل‌شدن مهاجرت تمام فایل‌های PHP قدیمی، آماده‌شدن DB آزمایشی و موفقیت تست کل سایت هنوز تأیید نشده‌اند. یافته‌های باز را در [Security Audit](SECURITY-AUDIT.md) ببین.

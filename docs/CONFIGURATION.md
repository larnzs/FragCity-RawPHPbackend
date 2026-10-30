# FragCity — راهنمای تنظیمات محیطی

> این مستند و فایل‌های Example باید با مصرف‌کننده‌های واقعی تنظیمات هماهنگ بمانند. فایل Runtime هر محیط مستقل است و هیچ Secret واقعی نباید وارد Git شود.

## ۱. فایل‌های تنظیمات

- `settings/loader.php`: فایل JSON محیط جاری را می‌خواند و اتصال PDO را از پروفایل‌های `databases.*` می‌سازد.
- `settings/settings.example.json`: نمونهٔ کامل و قابل‌ثبت در Git برای تنظیمات سایت؛ دارای placeholder و فیلدهای `_comment` برای توضیحات inline است.
- `settings/settings.json`: تنظیمات خصوصی همان محیط؛ در Git نادیده گرفته می‌شود. این فایل را از Example فقط در نصب اولیه بساز و بعداً overwrite نکن.
- `FRAGCITY_SETTINGS_FILE`: در صورت تعریف، مسیر فایل JSON خصوصی جایگزین را به Loader می‌دهد؛ در غیر این صورت `settings/settings.json` خوانده می‌شود.
- `settings/*.integration.example.json`: نمونه‌های کوچک Integration هستند و خودکار بارگذاری نمی‌شوند؛ فقط بخش `integrations` آن‌ها را در تنظیمات خصوصی ادغام کن.
- `linuxapi/config.example.php`: نمونهٔ کامل تنظیمات Runtime لینوکس؛ نسخهٔ واقعی در `/etc/fragcity-api/config.php` قرار می‌گیرد و خارج از Git است.

JSON کامنت استاندارد ندارد؛ برای همین Example کامل از کلیدهای متنی `_comment` استفاده می‌کند. این کلیدها فقط توضیح هستند و Loader در زمان اجرا آن‌ها را مصرف نمی‌کند. فایل همچنان JSON معتبر است.

## ۲. Integrationهای Website ↔ Linux API

هر Integration یک آدرس HTTPS و یک HMAC Secret مستقل دارد. `base_url` فقط Origin است؛ مسیر `/v1/...` را به آن اضافه نکن.

| تنظیم وب‌سایت | Secret متناظر در Linux | مسیر |
|---|---|---|
| `integrations.weaponpaints_api` | ریشهٔ `shared_secret` | `POST /v1/weaponpaints` |
| `integrations.pterodactyl_standby_api` | `pterodactyl_standby.shared_secret` | `POST /v1/pterodactyl-standby` |
| `integrations.pterodactyl_monitoring_api` | `pterodactyl_monitoring.shared_secret` | `POST /v1/pterodactyl-monitoring` |

برای هر Secret به‌صورت مستقل روی Linux اجرا کن:

```bash
openssl rand -hex 32
```

هر مقدار را فقط در دو جای متناظر خودش قرار بده. Secret مربوط به WeaponPaints را با Standby یا Monitoring یکی نکن. Pterodactyl Application API token و Wings token فقط در فایل Runtime لینوکس هستند؛ هیچ‌وقت آن‌ها را در تنظیمات سایت یا JavaScript قرار نده.

Exampleهای جزئی:

- `settings/weaponpaints-api.integration.example.json`
- `settings/pterodactyl-standby.integration.example.json`
- `settings/pterodactyl-monitoring.integration.example.json`

## ۳. مرز دیتابیس‌ها

### دیتابیس اصلی سایت

پروفایل `databases.main` برای دیتابیس وب‌سایت `kyonxcbt_fragcity_services` است. Laragon باید credential دیتابیس محلی خودش را داشته باشد و هاست اشتراکی باید credential مخصوص Production را در Runtime خصوصی خودش نگه دارد.

### WeaponPaints

دیتابیس `GLOBAL_weaponpaints` روی سرور Linux قرار دارد. سایت فقط با `class/FragCityLinuxApiClient.php` و `class/WeaponPaintsRepository.php` و مسیر امضاشدهٔ `/v1/weaponpaints` به آن دسترسی دارد. در `settings.json` پروفایل مستقیم `databases.weaponpaints` نساز و `files.database.weaponpaints_plugin.transport` را روی `api` نگه دار.

### پروفایل‌های Plugin

`plugin_sync`, `ranks_plugin` و `simpleadmin_plugin` فقط زمانی باید به دیتابیس مستقل اشاره کنند که schema و user محدود آن‌ها واقعاً ایجاد شده باشند. مقدار `name` خالی در Example عمداً آن‌ها را غیرفعال/نامشخص نگه می‌دارد.

## ۴. Pterodactyl Server Browser

فهرست عمومی سرورها از `PterodactylStandbyRepository` و مسیر امضاشدهٔ `POST /v1/pterodactyl-standby` استفاده می‌کند. Linux API با Application API فقط سرورهای مجاز و Default Allocation را پیدا می‌کند و روی همان پورت‌ها A2S_INFO می‌فرستد. اسکن بازه‌ای پورت و Cron برای مسیر فعال لازم نیست. Cache به‌صورت on-demand و با TTL پیش‌فرض ۱۰ ثانیه تازه می‌شود. پیاده‌سازی فعلی Standby تک‌Node است.

## ۵. Pterodactyl Monitoring

صفحهٔ Monitoring در `panels/cs2/monitoring.php` فقط برای ادمین است. سایت از مسیر داخلی خودش درخواست را احراز هویت می‌کند و سپس از Linux API با HMAC درخواست امضاشده می‌فرستد. Linux API اطلاعات Pterodactyl/Wings را جمع می‌کند؛ Application API token و Wings token هرگز به Browser نمی‌رسند. این قابلیت Secret مستقل دارد و به Cron نیاز ندارد.

## ۶. تنظیمات محلی Laragon

در نصب اولیه:

```powershell
Copy-Item .\settings\settings.example.json .\settings\settings.json
```

اگر `settings/settings.json` از قبل وجود دارد، این دستور را اجرا نکن؛ فایل خصوصی فعلی را باز کن و فقط کلیدهای جدید را Merge کن.

- `app.environment`: `local`
- `app.site_base_url`: آدرس Virtual Host محلی؛ برای نمونه `http://fragcity.test`
- `databases.main`: فقط اتصال دیتابیس محلی Laragon
- سه Integration امضاشده: فقط در صورت تست واقعی آن قابلیت، Secret متناظر را تکمیل کن.
- پرداخت واقعی زرین‌پال را در محیط محلی فعال نکن.

**هشدار:** تست نوشتن WeaponPaints از Laragon می‌تواند مستقیماً روی دیتابیس واقعی Linux اثر بگذارد. برای تست ذخیره‌سازی از حساب/SteamID اختصاصی تست استفاده کن.

## ۷. تنظیمات Linux API

نمونهٔ کامل PHP در `linuxapi/config.example.php` است. آن را فقط برای نصب تازه به `/etc/fragcity-api/config.php` کپی کن. برای سرور موجود، بلوک‌های جدید را Merge کن؛ جایگزین‌کردن کل فایل باعث از بین‌رفتن Database password و Secretهای فعلی می‌شود.

- `shared_secret`: مخصوص WeaponPaints.
- `pterodactyl_standby.shared_secret`: مخصوص Server Browser.
- `pterodactyl_monitoring.shared_secret`: مخصوص Monitoring.
- `pterodactyl_standby.pterodactyl.application_api_key` و `pterodactyl_monitoring.pterodactyl.application_api_key`: توکن Application API فقط‌خواندنی Pterodactyl؛ فقط روی Linux.
- `database`: دیتابیس محلی WeaponPaints و جدول nonceهای HMAC؛ هیچ‌وقت دیتابیس سایت نیست.

تنظیمات Standby شامل `node_id`، Allowlist مربوط به Egg/Image، `query_host` داخلی، `public_connect_host`، TTL و مسیر Cache است. Monitoring علاوه بر Allowlist نودها، تنظیمات Wings و در صورت نیاز `local_node_id` و `host_metrics_disk_path` دارد.

## ۸. محافظت از تنظیمات

- فایل `settings/settings.json` نباید وارد Git شود؛ الگوی آن در `.gitignore` وجود دارد.
- در Apache فایل `settings/.htaccess` دسترسی مستقیم را مسدود می‌کند.
- Nginx فایل `.htaccess` را اجرا نمی‌کند؛ `settings/nginx-protect.conf` را داخل server block سایت Include کن یا فایل Runtime را بیرون DocumentRoot قرار بده و `FRAGCITY_SETTINGS_FILE` را تنظیم کن.
- فایل `/etc/fragcity-api/config.php` باید بیرون DocumentRoot و فقط برای PHP-FPM قابل‌خواندن باشد؛ PHP-FPM نباید بتواند آن را بنویسد.
- فایل‌های Nginx API در `linuxapi/nginx/` نمونه هستند؛ vhost پنل Pterodactyl را با آن‌ها جایگزین نکن.
- هیچ Secret، Application API token، Wings token یا Database password را در Git، چت یا مرورگر قرار نده.

## ۹. کنترل قبل از اجرا

```powershell
php -r "$j=json_decode(file_get_contents('settings/settings.example.json'),true); if(!is_array($j)) {fwrite(STDERR,json_last_error_msg().PHP_EOL); exit(1);} echo 'Valid website settings example'.PHP_EOL;"
php -l linuxapi/config.example.php
php linuxapi/tools/architecture-check.php
```

این بررسی‌ها ساختار و معماری را کنترل می‌کنند؛ جایگزین تست واقعی اتصال HTTPS، امضای HMAC، Pterodactyl، A2S، دیتابیس یا UI نیستند. انتشار Production نیز جدا از Git pull و فقط پس از Backup و تست انجام می‌شود.

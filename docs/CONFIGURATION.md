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

`base_url` فقط Origin است؛ مسیر `/v1/...` را به آن اضافه نکن. WeaponPaints و SimpleAdmin عمداً از یک HMAC Secret مشترک استفاده می‌کنند؛ کلیدهای Pterodactyl جدا می‌مانند.

| تنظیم وب‌سایت | Secret متناظر در Linux | مسیر |
|---|---|---|
| `integrations.weaponpaints_api` | ریشهٔ `shared_secret` مشترک | `POST /v1/weaponpaints` و `POST /v1/simpleadmin` |
| `integrations.pterodactyl_standby_api` | `pterodactyl_standby.shared_secret` | `POST /v1/pterodactyl-standby` |
| `integrations.pterodactyl_monitoring_api` | `pterodactyl_monitoring.shared_secret` | `POST /v1/pterodactyl-monitoring` |

برای هر Secret به‌صورت مستقل روی Linux اجرا کن:

```bash
openssl rand -hex 32
```

مقدار `integrations.weaponpaints_api.shared_secret` را با `shared_secret` ریشهٔ فایل Linux یکسان نگه دار؛ همین کلید برای دو مسیر WeaponPaints و SimpleAdmin استفاده می‌شود. آن را با Standby یا Monitoring یکی نکن. Pterodactyl Application API token و Wings token فقط در فایل Runtime لینوکس هستند؛ هیچ‌وقت آن‌ها را در تنظیمات سایت یا JavaScript قرار نده.

Exampleهای جزئی:

- `settings/weaponpaints-api.integration.example.json`
- `settings/pterodactyl-standby.integration.example.json`
- `settings/pterodactyl-monitoring.integration.example.json`
- `settings/faceit-api.integration.example.json`

## ۳. مرز دیتابیس‌ها

### دیتابیس اصلی سایت

پروفایل `databases.main` برای دیتابیس وب‌سایت `kyonxcbt_fragcity_services` است. Laragon باید credential دیتابیس محلی خودش را داشته باشد و هاست اشتراکی باید credential مخصوص Production را در Runtime خصوصی خودش نگه دارد.

### WeaponPaints

دیتابیس `GLOBAL_weaponpaints` روی سرور Linux قرار دارد. سایت فقط با `class/FragCityLinuxApiClient.php` و `class/WeaponPaintsRepository.php` و مسیر امضاشدهٔ `/v1/weaponpaints` به آن دسترسی دارد. در `settings.json` پروفایل مستقیم `databases.weaponpaints` نساز و `files.database.weaponpaints_plugin.transport` را روی `api` نگه دار.

### پروفایل‌های Plugin

`plugin_sync`, `ranks_plugin` و `simpleadmin_plugin` فقط زمانی باید به دیتابیس مستقل اشاره کنند که schema و user محدود آن‌ها واقعاً ایجاد شده باشند. مقدار `name` خالی در Example عمداً آن‌ها را غیرفعال/نامشخص نگه می‌دارد.

## ۴. Pterodactyl Server Browser

فهرست عمومی سرورها از `PterodactylStandbyRepository` و مسیر امضاشدهٔ `POST /v1/pterodactyl-standby` استفاده می‌کند. Linux API با Application API فقط سرورهای مجاز و Default Allocation را پیدا می‌کند و روی همان پورت‌ها A2S_INFO می‌فرستد. اسکن بازه‌ای پورت و Cron برای مسیر فعال لازم نیست. Cache به‌صورت on-demand و با TTL پیش‌فرض ۱۰ ثانیه تازه می‌شود. پیاده‌سازی فعلی Standby تک‌Node است.

### ۴.۱. FACEIT Data API

`integrations.faceit_api.api_key` یک کلید **Server-side** از FACEIT Developer Portal است. کلید فقط در فایل خصوصی `settings/settings.json` قرار می‌گیرد و هرگز به مرورگر یا Linux API ارسال نمی‌شود. سایت برای هر SteamID64 معتبر از مسیر `GET https://open.faceit.com/data/v4/players?game=cs2&game_player_id=...` استفاده می‌کند و Level را از `games.cs2.skill_level` می‌خواند. نتیجه‌ها در Cache خصوصی موقت سرور نگهداری می‌شوند تا برای هر بازیکن در هر بار بازشدن مودال درخواست تکراری ارسال نشود.

**تأمین SteamID:** پروتکل `A2S_PLAYER` شناسه Steam برنمی‌گرداند؛ بنابراین نام فعلی هر بازیکن با جدول `sa_players_ips` در دیتابیس SimpleAdmin تطبیق داده می‌شود. فقط وقتی تطبیق دقیق به یک SteamID64 یکتا برسد، FACEIT Query انجام می‌شود. نام‌های تکراری یا پیدا‌نشده بدون رتبه باقی می‌مانند؛ IP بازیکنان خوانده یا منتقل نمی‌شود.

### ۴.۲. SimpleAdmin Player Identity API

سرویس `POST /v1/simpleadmin` روی Linux API فقط برای تبدیل نام فعلی بازیکنان A2S به SteamID64 استفاده می‌شود. این مسیر از همان HMAC Secret ریشه‌ای WeaponPaints استفاده می‌کند، اما به دیتابیس جداگانهٔ `GLOBAL_simpleadmin` و یک حساب MariaDB فقط‌خواندنی وصل می‌شود. این API فقط ستون‌های `name`, `steamid` و `used_at` از جدول `sa_players_ips` را می‌خواند و هرگز ستون `address` یا IP را Query یا به وب‌سایت ارسال نمی‌کند.

پیش‌نیازها:
- Migration شمارهٔ 013 باید اجرا شده باشد تا ستون `sa_players_ips.name` وجود داشته باشد.
- Migration شمارهٔ 015 باید اجرا شده باشد تا SteamIDها در قالب BIGINT ذخیره شوند.
- ایندکس جست‌وجوی نام را یک‌بار روی دیتابیس `GLOBAL_simpleadmin` بساز. پیش از اجرا با `SHOW INDEX FROM sa_players_ips` بررسی کن که از قبل وجود نداشته باشد:

```sql
ALTER TABLE sa_players_ips
    ADD INDEX idx_sa_players_ips_name_steamid_used_at (name, steamid, used_at);
```

حساب API فقط‌خواندنی را با حداقل دسترسی بساز و Host را با اتصال واقعی Linux API هماهنگ کن:

```sql
CREATE USER 'fragcity_simpleadmin_api'@'127.0.0.1'
    IDENTIFIED BY 'REPLACE_WITH_A_UNIQUE_LONG_PASSWORD';
GRANT SELECT (steamid, name, used_at)
    ON GLOBAL_simpleadmin.sa_players_ips
    TO 'fragcity_simpleadmin_api'@'127.0.0.1';
FLUSH PRIVILEGES;
```

در `/etc/fragcity-api/config.php` فقط بلوک `simpleadmin` را از `linuxapi/config.example.php` Merge کن؛ فایل Runtime را کامل overwrite نکن. بعد از قراردادن credentialهای واقعی، `enabled` را روی `true` بگذار.

Resolver فقط وقتی نام دقیقاً به یک SteamID یکتا برسد آن را برمی‌گرداند. اگر نام به چند SteamID تعلق داشته باشد، وضعیت `ambiguous` می‌دهد تا رتبهٔ FACEIT به فرد اشتباه نسبت داده نشود. درخواست‌های FACEIT همچنان از PHP روی هاست اشتراکی و با کلید `integrations.faceit_api.api_key` انجام می‌شوند؛ Linux API هیچ درخواست FACEIT ارسال نمی‌کند.

## ۵. Pterodactyl Monitoring

مسیر read-only مانیتورینگ Pterodactyl از طریق endpoint امضاشدهٔ سایت و Linux API موجود است و فقط نقش admin به آن دسترسی دارد. داشبورد قدیمی CS2 و پوشهٔ `panels/` حذف شده‌اند؛ هیچ صفحهٔ مدیریتی برای این قابلیت در سایت باقی نمانده است. Application API token و Wings token به مرورگر ارسال نمی‌شوند و Secret مانیتورینگ مستقل است.

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

- ریشهٔ `shared_secret`: مشترک بین WeaponPaints و SimpleAdmin.
- `simpleadmin.database`: اتصال فقط‌خواندنی به `GLOBAL_simpleadmin`.
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
php -r '$j=json_decode(file_get_contents("settings/settings.example.json"),true); if(!is_array($j)) {fwrite(STDERR,json_last_error_msg().PHP_EOL); exit(1);} echo "Valid website settings example".PHP_EOL;'
php -l linuxapi/config.example.php
php linuxapi/tools/architecture-check.php
```

این بررسی‌ها ساختار و معماری را کنترل می‌کنند؛ جایگزین تست واقعی اتصال HTTPS، امضای HMAC، Pterodactyl، A2S، دیتابیس یا UI نیستند. انتشار Production نیز جدا از Git pull و فقط پس از Backup و تست انجام می‌شود.

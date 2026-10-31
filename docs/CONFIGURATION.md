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

`plugin_sync` و `ranks_plugin` فقط زمانی باید به دیتابیس مستقل اشاره کنند که Schema و دسترسی‌های لازم آن‌ها واقعاً ایجاد شده باشند. SimpleAdmin از طریق Linux API و با همان حساب Plugin سمت لینوکس خوانده می‌شود؛ پروفایل مستقیم `simpleadmin_plugin` در تنظیمات وب‌سایت دیگر استفاده نمی‌شود.

## ۴. Pterodactyl Server Browser

فهرست عمومی سرورها از `PterodactylStandbyRepository` و مسیر امضاشدهٔ `POST /v1/pterodactyl-standby` استفاده می‌کند. Linux API با Application API فقط سرورهای مجاز و Default Allocation را پیدا می‌کند و روی همان پورت‌ها A2S_INFO می‌فرستد. اسکن بازه‌ای پورت و Cron برای مسیر فعال لازم نیست. Cache به‌صورت on-demand و با TTL پیش‌فرض ۱۰ ثانیه تازه می‌شود. پیاده‌سازی فعلی Standby تک‌Node است.

### ۴.۱. FACEIT Data API

`integrations.faceit_api.api_key` یک کلید **Server-side** از FACEIT Developer Portal است. کلید فقط در فایل خصوصی `settings/settings.json` قرار می‌گیرد و هرگز به مرورگر یا Linux API ارسال نمی‌شود. سایت برای هر SteamID64 معتبر از مسیر `GET https://open.faceit.com/data/v4/players?game=cs2&game_player_id=...` استفاده می‌کند و Level را از `games.cs2.skill_level` می‌خواند. نتیجه‌ها در Cache خصوصی موقت سرور نگهداری می‌شوند تا برای هر بازیکن در هر بار بازشدن مودال درخواست تکراری ارسال نشود.

**تأمین SteamID:** پروتکل `A2S_PLAYER` شناسه Steam برنمی‌گرداند؛ بنابراین نام فعلی هر بازیکن با جدول `sa_players_ips` در دیتابیس SimpleAdmin تطبیق داده می‌شود. فقط وقتی تطبیق دقیق به یک SteamID64 یکتا برسد، FACEIT Query انجام می‌شود. نام‌های تکراری یا پیدا‌نشده بدون رتبه باقی می‌مانند؛ IP بازیکنان خوانده یا منتقل نمی‌شود.

### ۴.۲. SimpleAdmin Read-only Data API

سرویس `POST /v1/simpleadmin` روی Linux API با HMAC Secret ریشه‌ای WeaponPaints احراز هویت می‌شود و از همان اکانت دیتابیسی Plugin که در بخش `database` فایل Runtime لینوکس تنظیم شده برای اتصال به `GLOBAL_simpleadmin` استفاده می‌کند؛ هیچ یوزر یا پسورد جدیدی ساخته یا تعریف نمی‌شود.

**دامنهٔ دسترسی دیتابیس، کل Schema است، نه فقط جدول IPها.** صفحه Ban List به جدول‌های `sa_bans`, `sa_unbans`, `sa_mutes`, `sa_unmutes` و `sa_warns` نیاز دارد و اطلاعات هویتی/تاریخچه بازیکن نیز از جدول‌هایی مثل `sa_players_ips` و `sa_renames` خوانده می‌شود. بنابراین کاربر Plugin به دسترسی خواندن روی تمام جدول‌های `GLOBAL_simpleadmin` نیاز دارد. این به معنی ارائهٔ دسترسی SQL خام به وب‌سایت نیست: Linux API باید فقط actionهای مشخص و allowlist‌شده را اجرا کند و فیلدهای لازم هر خروجی را برگرداند؛ هیچ عملیات نوشتن لازم نیست.

مسیر SimpleAdmin دو action خواندنی دارد: `resolve_player_names` برای تبدیل نام A2S به SteamID64 و `get_records` برای دریافت صفحه‌بندی‌شدهٔ اطلاعات Ban List. action دوم بخش‌های `bans`, `unbans`, `mutes`, `unmutes`, `warns` و `playerips` را از جدول‌های اصلی SimpleAdmin می‌خواند؛ جست‌وجو، مرتب‌سازی ثابت و شمارش کل نتایج در Linux API اجرا می‌شود. صفحه `pages/banlist.php` از `class/SimpleAdminRepository.php` و همین API استفاده می‌کند و دیگر اتصال مستقیم دیتابیس ندارد. در resolver هویت، IPها خوانده نمی‌شوند؛ در Ban List نیز فیلد IP فقط برای درخواست امضاشدهٔ backend ادمین برگردانده می‌شود.

پیش‌نیازهای resolver:
- Migration شمارهٔ 013 باید اجرا شده باشد تا ستون `sa_players_ips.name` وجود داشته باشد.
- Migration شمارهٔ 015 باید اجرا شده باشد تا SteamIDها در قالب BIGINT ذخیره شوند.
- ایندکس جست‌وجوی نام را یک‌بار روی دیتابیس `GLOBAL_simpleadmin` بساز. پیش از اجرا با `SHOW INDEX FROM sa_players_ips` بررسی کن که از قبل وجود نداشته باشد:

```sql
ALTER TABLE sa_players_ips
    ADD INDEX idx_sa_players_ips_name_steamid_used_at (name, steamid, used_at);
```

به همان کاربر دیتابیسی موجودی که در `database.username` و `database.password` فایل `/etc/fragcity-api/config.php` برای Pluginها تنظیم شده، دسترسی خواندن روی کل Schema بده. نام کاربر و Host را دقیقاً مطابق حساب موجود MariaDB جایگزین کن؛ **دستور CREATE USER اجرا نکن**:

```sql
GRANT SELECT ON GLOBAL_simpleadmin.*
    TO 'EXISTING_PLUGINS_DB_USER'@'EXACT_DB_HOST';
FLUSH PRIVILEGES;
```

از `ALL PRIVILEGES` استفاده نکن؛ این Integration فقط به `SELECT` روی `GLOBAL_simpleadmin.*` نیاز دارد و نباید امکان تغییر Banها، کاربران یا تنظیمات SimpleAdmin را از طریق حساب سایت داشته باشد. Host باید دقیقاً با Host حساب موجود منطبق باشد، مثل `localhost` یا `127.0.0.1`؛ این دو را یکی فرض نکن.

در `/etc/fragcity-api/config.php` فقط بلوک `simpleadmin` را از `linuxapi/config.example.php` Merge کن؛ این بلوک فقط نام دیتابیس را تعیین می‌کند و credential را از تنظیمات دیتابیس موجود به ارث می‌برد. فایل Runtime را کامل overwrite نکن. پس از بررسی مجوز و ایندکس، `enabled` را روی `true` بگذار.

**حفاظت از IP بازیکنان:** تب `playerips` و ستون‌های IP مربوط به Ban/Unban برای کاربر واردشده‌ای فعال‌اند که مقدار `users.insmanager = 1` در دیتابیس اصلی FragCity داشته باشد؛ دسترسی موجود نقش `admin` نیز برای سازگاری حفظ شده است. صفحه، SteamID64 نشست را با یک Query آماده (`users.steamid64`) به رکورد واقعی کاربر متصل می‌کند و مقدار `insmanager` را در هر درخواست از دیتابیس می‌خواند؛ این مجوز در Session یا پارامتر URL ذخیره/دریافت نمی‌شود. اگر کاربر وارد نشده باشد، SteamID معتبر نباشد، اتصال PDO موجود نباشد یا Query مجوز شکست بخورد، دسترسی به IPها رد می‌شود. فقط پس از این بررسی، backend سایت درخواست امضاشدهٔ SimpleAdmin را با `include_sensitive = true` می‌فرستد. برای کاربران دیگر، API نه ستون IP را Query می‌کند و نه آن را در پاسخ برمی‌گرداند. مقدار عددی `sa_players_ips.address` نیز در API با `INET_NTOA()` به IPv4 قابل‌خواندن تبدیل می‌شود.

Resolver فقط وقتی نام دقیقاً به یک SteamID یکتا برسد آن را برمی‌گرداند. اگر نام به چند SteamID تعلق داشته باشد، وضعیت `ambiguous` می‌دهد تا رتبهٔ FACEIT به فرد اشتباه نسبت داده نشود. خود Level از دیتابیس SimpleAdmin نمی‌آید: بعد از resolve شدن SteamID، درخواست FACEIT از PHP روی هاست اشتراکی و با کلید `integrations.faceit_api.api_key` انجام می‌شود؛ Linux API هیچ درخواست FACEIT ارسال نمی‌کند.
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
- `simpleadmin.database.name`: نام دیتابیس `GLOBAL_simpleadmin`؛ Username، Password، Host و Port از همان Plugin DB connection موجود در `database` استفاده می‌شوند.
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

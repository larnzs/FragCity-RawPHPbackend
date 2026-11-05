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

`base_url` فقط Origin است؛ مسیر `/v1/...` را به آن اضافه نکن. WeaponPaints، SimpleAdmin، VIPCore و Ranks از یک HMAC Secret مشترک استفاده می‌کنند؛ کلیدهای Pterodactyl جدا می‌مانند.

| تنظیم وب‌سایت | Secret متناظر در Linux | مسیر |
|---|---|---|
| `integrations.weaponpaints_api` | ریشهٔ `shared_secret` مشترک | `POST /v1/weaponpaints`, `/v1/simpleadmin`, `/v1/vipcore`, `/v1/ranks` |
| `integrations.pterodactyl_standby_api` | `pterodactyl_standby.shared_secret` | `POST /v1/pterodactyl-standby` |
| `integrations.pterodactyl_monitoring_api` | `pterodactyl_monitoring.shared_secret` | `POST /v1/pterodactyl-monitoring` |

برای هر Secret به‌صورت مستقل روی Linux اجرا کن:

```bash
openssl rand -hex 32
```

مقدار `integrations.weaponpaints_api.shared_secret` را با `shared_secret` ریشهٔ فایل Linux یکسان نگه دار؛ همین کلید برای مسیرهای WeaponPaints، SimpleAdmin، VIPCore و Ranks استفاده می‌شود. آن را با Standby یا Monitoring یکی نکن. Pterodactyl Application API token و Wings token فقط در فایل Runtime لینوکس هستند؛ هیچ‌وقت آن‌ها را در تنظیمات سایت یا JavaScript قرار نده.

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

### Ranks leaderboard

صفحه‌های `pages/leaderboard.php` و `pages/profile.php` آمار بازی را از `RanksRepository` و مسیر امضاشدهٔ `POST /v1/ranks` می‌گیرند. Linux API به `GLOBAL_ranks.lvl_base` با حساب مستقل `fragcity_ranks_api` و دسترسی `SELECT` وصل می‌شود. Schema متعلق به پلاگین است و برای این اتصال هیچ Migration یا تغییری در جدول لازم نیست. تبدیل SteamID64 به کلید Steam2 فقط روی Linux انجام می‌شود.

### پروفایل‌های Plugin

`plugin_sync` فقط زمانی باید به دیتابیس مستقل اشاره کند که Schema و دسترسی‌های لازم آن واقعاً ایجاد شده باشد. پروفایل `ranks_plugin` قدیمی و صرفاً برای سازگاری باقی مانده؛ Leaderboard و آمار پروفایل از Linux API می‌آیند و نباید اتصال مستقیم سایت به `GLOBAL_ranks` پیکربندی شود. SimpleAdmin از طریق Linux API و با همان حساب Plugin سمت لینوکس خوانده می‌شود؛ پروفایل مستقیم `simpleadmin_plugin` در تنظیمات وب‌سایت دیگر استفاده نمی‌شود.

## ۴. Pterodactyl Server Browser

فهرست عمومی سرورها از `PterodactylStandbyRepository` و مسیر امضاشدهٔ `POST /v1/pterodactyl-standby` استفاده می‌کند. Linux API با Application API فقط سرورهای مجاز و Default Allocation را پیدا می‌کند و روی همان پورت‌ها A2S_INFO می‌فرستد. اسکن بازه‌ای پورت و Cron برای مسیر فعال لازم نیست. Cache به‌صورت on-demand و با TTL پیش‌فرض ۱۰ ثانیه تازه می‌شود. پیاده‌سازی فعلی Standby تک‌Node است.

### ۴.۱. FACEIT Data API

`integrations.faceit_api.api_key` یک کلید **Server-side** از FACEIT Developer Portal است. کلید فقط در فایل خصوصی `settings/settings.json` قرار می‌گیرد و هرگز به مرورگر یا Linux API ارسال نمی‌شود. سایت برای هر SteamID64 معتبر از مسیر `GET https://open.faceit.com/data/v4/players?game=cs2&game_player_id=...` استفاده می‌کند و Level را از `games.cs2.skill_level` می‌خواند. نتیجه‌ها در Cache خصوصی موقت سرور نگهداری می‌شوند تا برای هر بازیکن در هر بار بازشدن مودال درخواست تکراری ارسال نشود.

**تأمین SteamID:** پروتکل `A2S_PLAYER` شناسه Steam برنمی‌گرداند. Resolver ابتدا نام زندهٔ بازیکن را با `sa_renames` تطبیق می‌دهد؛ این مسیر برای نام‌های اجباری SimpleAdmin مهم است، چون سورس افزونه نام اصلی را پیش از اعمال Rename ذخیره می‌کند و A2S بعداً نام تغییرکرده را نمایش می‌دهد. اگر نامی در `sa_renames` پیدا نشد، Resolver به تاریخچهٔ `sa_players_ips` رجوع می‌کند. برای هر نام فقط یک SteamID64 معتبر پذیرفته می‌شود؛ در صورت چند تطبیق، وضعیت `ambiguous` برمی‌گردد. همچنین اگر دو بازیکن هم‌زمان نام یکسان داشته باشند، هر دو مبهم علامت می‌خورند و برایشان FACEIT Query اجرا نمی‌شود. تطبیق نام بدون حساسیت به بزرگی/کوچکی حروف انجام می‌شود؛ نرمال‌سازی NFC هم در محیط‌هایی که افزونهٔ `intl` فعال است اعمال می‌شود؛ هیچ IP یا ستون آدرس شبکه‌ای در این Resolver خوانده نمی‌شود. اگر جدول `sa_renames` هنوز در Schema موجود نباشد، Resolver فقط از تاریخچه نام استفاده می‌کند. منبع upstream برای منطق Rename و ذخیرهٔ نام اصلی: [SimpleAdmin PlayerManager.cs](https://github.com/daffyyyy/CS2-SimpleAdmin/blob/03a454b1b511a50422e90076d993656a4a2bc5a8/CS2-SimpleAdmin/Managers/PlayerManager.cs).

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

**حفاظت از IP بازیکنان:** تب `playerips` و ستون‌های IP مربوط به Ban/Unban برای کاربر واردشده‌ای فعال‌اند که مقدار `users.insmaster = 1` در دیتابیس اصلی FragCity داشته باشد؛ دسترسی موجود نقش `admin` نیز برای سازگاری حفظ شده است. صفحه، SteamID64 نشست را با یک Query آماده (`users.steamid64`) به رکورد واقعی کاربر متصل می‌کند و مقدار `insmaster` را در هر درخواست از دیتابیس می‌خواند؛ این مجوز در Session یا پارامتر URL ذخیره/دریافت نمی‌شود. اگر کاربر وارد نشده باشد، SteamID معتبر نباشد، اتصال PDO موجود نباشد یا Query مجوز شکست بخورد، دسترسی به IPها رد می‌شود. فقط پس از این بررسی، backend سایت درخواست امضاشدهٔ SimpleAdmin را با `include_sensitive = true` می‌فرستد. برای کاربران دیگر، API نه ستون IP را Query می‌کند و نه آن را در پاسخ برمی‌گرداند. مقدار عددی `sa_players_ips.address` نیز در API با `INET_NTOA()` به IPv4 قابل‌خواندن تبدیل می‌شود.

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

- ریشهٔ `shared_secret`: مشترک بین WeaponPaints، SimpleAdmin، VIPCore و Ranks.
- `simpleadmin.database.name`: نام دیتابیس `GLOBAL_simpleadmin`؛ Username، Password، Host و Port از همان Plugin DB connection موجود در `database` استفاده می‌شوند.
- `ranks.database`: اتصال مستقل و فقط‌خواندنی به `GLOBAL_ranks` با حساب `fragcity_ranks_api`؛ فقط دسترسی `SELECT` روی `lvl_base` لازم است.
- `simpleadmin.database.name`: نام دیتابیس `GLOBAL_simpleadmin`؛ Username، Password، Host و Port از همان Plugin DB connection موجود در `database` استفاده می‌شوند.
- `pterodactyl_standby.shared_secret`: مخصوص Server Browser.
- `pterodactyl_monitoring.shared_secret`: مخصوص Monitoring.
- `pterodactyl_standby.pterodactyl.application_api_key` و `pterodactyl_monitoring.pterodactyl.application_api_key`: توکن Application API فقط‌خواندنی Pterodactyl؛ فقط روی Linux.
- `database`: دیتابیس محلی WeaponPaints و جدول nonceهای HMAC؛ هیچ‌وقت دیتابیس سایت نیست.

تنظیمات Standby شامل `node_id`، Allowlist مربوط به Egg/Image، `query_host` داخلی، `public_connect_host`، TTL و مسیر Cache است. Monitoring علاوه بر Allowlist نودها، تنظیمات Wings و در صورت نیاز `local_node_id` و `host_metrics_disk_path` دارد.

## ۸. Paymenter Account Bridge

ثبت‌نام و هویت کاربران از FragCity مدیریت می‌شود. Add-on اختصاصی `FragCityAPI` در Paymenter فقط از ارتباط سروربه‌سرور استفاده می‌کند و Core پرداخت‌یار را تغییر نمی‌دهد.

- در Paymenter مسیر **Admin → Extensions → Others → FragCity API** را فعال کن.
- یک Secret تصادفی حداقل ۳۲ بایتی بساز: `openssl rand -hex 32`.
- همان مقدار را در تنظیمات Extension در Paymenter و فایل Runtime خصوصی سایت، زیر `integrations.paymenter_backend.shared_secret` قرار بده.
- `integrations.paymenter_backend.base_url` فقط Origin HTTPS پنل Paymenter است؛ مسیر API به آن اضافه نکن.
- در صورت داشتن IP خروجی ثابت برای هاست FragCity، آن IP را در `Allowed Source IPs` افزونه Paymenter تنظیم کن.
- فایل `database/migrations/20261103_paymenter_user_sync.sql` را فقط یک‌بار روی دیتابیس اصلی FragCity اجرا کن.
- فایل `database/migrations/20261104_client_name_fields.sql` را هم فقط یک‌بار روی همان دیتابیس اجرا کن؛ این Migration ستون‌های `first_name` و `last_name` را اضافه می‌کند. برای جلوگیری از پرشدن نام قانونی با نام نمایشی، رکوردهای قدیمی به‌صورت خودکار حدس زده یا backfill نمی‌شوند؛ کاربران قدیمی هنگام ورود بعدی فرم تکمیل حساب را می‌بینند.
- نام کاربری در فرم ثبت‌نام و تکمیل حساب اجباری است: ۳ تا ۵۰ کاراکتر، فقط حروف انگلیسی، عدد، `_` و `-`، با حرف/عدد در ابتدا و انتها. نام و نام خانوادگی نیز اجباری‌اند.
- Bridge مقدار Username را در Paymenter به‌صورت User Property با کلید `fragcity_username` ذخیره می‌کند؛ Paymenter فیلد native برای username ندارد. نام کوچک و نام خانوادگی در فیلدهای native Paymenter ذخیره می‌شوند.
- افزونه `FragCityCS2` هنگام Provisioning، Username انتخابی و نام‌های native را با Application API در فیلدهای native کاربر Pterodactyl قرار می‌دهد، `external_id` را برابر شناسه کاربر Paymenter تنظیم می‌کند و سپس ساخت سرور را به پیاده‌سازی رسمی Paymenter واگذار می‌کند.
- صفحه محافظت‌شدهٔ `?page=myservers` سرویس‌های متعلق به SteamID واردشده را از Bridge می‌گیرد و کارت‌های سرور را با UUID، شناسه، وضعیت، منابع، تاریخ‌ها و اطلاعات ورود پنل نشان می‌دهد. کلیک روی کارت، جزئیات و فرم تنظیم رمز مستقل پنل را باز می‌کند.
- Bridge نسخه ۱.۳ مسیرهای `POST /api/v1/fragcity/bridge/servers/list` و `POST /api/v1/fragcity/bridge/servers/password` را اضافه می‌کند. این درخواست‌ها فقط از backend سایت، با HMAC و HTTPS ارسال می‌شوند؛ مرورگر هیچ API Key یا HMAC Secret دریافت نمی‌کند.
- مشتری برای تنظیم/تغییر رمز پنل، رمز فعلی FragCity را دوباره وارد می‌کند. رمز جدید باید حداقل ۱۲ کاراکتر، شامل حرف و عدد، بدون فاصله و متفاوت از رمز FragCity باشد. فقط زمان تنظیم رمز در Paymenter به‌صورت Property ذخیره می‌شود؛ رمز خام یا Hash آن در FragCity/Paymenter نگهداری نمی‌شود.
- یک حساب Pterodactyl برای کاربر Paymenter مشترک است؛ بنابراین Username/Email و رمز پنل برای تمام سرورهای CS2 همان مشتری یکسان است. تغییر رمز از هر کارت، دسترسی همان حساب مشترک را تغییر می‌دهد. رمز فعلی قابل بازیابی/نمایش نیست؛ مشتری می‌تواند هر زمان رمز تازه‌ای تنظیم کند.
- فایل‌های PHP جدید را پس از اعمال Migrationها منتشر کن و برای تست ساختار و اتصال از ریشه پروژه اجرا کن: `php tools/paymenter-api-preflight.php`.
- پس از Deploy هر دو Extension و فایل‌های جدید سایت، فایل `database/migrations/20261104_paymenter_username_resync.sql` را فقط یک بار اجرا کن. این Query حساب‌های Paymenter لینک‌شده‌ی فعلی را به وضعیت `pending` می‌برد تا کاربران قدیمی نیز Property جدید `fragcity_username` را دریافت کنند؛ کاربران با ورود عادی و رمز خودکار Sync می‌شوند و نشست‌های Steam/Remember Me در ورود بعدی فرم تکمیل حساب را می‌بینند. این SQL را قبل از Deploy فرم جدید اجرا نکن.

Endpointهای Bridge:
- `POST /api/v1/fragcity/bridge/ping`
- `POST /api/v1/fragcity/bridge/users/sync`
- `POST /api/v1/fragcity/bridge/servers/list`
- `POST /api/v1/fragcity/bridge/servers/password`

هر درخواست با HMAC-SHA256، timestamp و nonce یک‌بارمصرف امضا می‌شود. رمز حساب FragCity فقط در حافظه و در درخواست HTTPS امضاشدهٔ Sync منتقل می‌شود؛ رمز پنل نیز فقط در درخواست HTTPS امضاشدهٔ تغییر رمز از backend سایت به Paymenter فرستاده می‌شود. هیچ‌کدام در لاگ یا به‌صورت متن خام ذخیره نمی‌شوند. Paymenter رمز حساب خودش و Pterodactyl رمز پنل خودش را با Hash استاندارد ذخیره می‌کنند.

برای جلوگیری از ساخت حساب تکراری، ستون `paymenter_user_id` در دیتابیس سایت شناسه حساب متناظر را نگه می‌دارد. تلفن و SteamID در Paymenter به‌صورت User Property ذخیره می‌شوند؛ Paymenter فیلد native شماره تلفن ندارد.

## ۹. محافظت از تنظیمات

- فایل `settings/settings.json` نباید وارد Git شود؛ الگوی آن در `.gitignore` وجود دارد.
- در Apache فایل `settings/.htaccess` دسترسی مستقیم را مسدود می‌کند.
- Nginx فایل `.htaccess` را اجرا نمی‌کند؛ `settings/nginx-protect.conf` را داخل server block سایت Include کن یا فایل Runtime را بیرون DocumentRoot قرار بده و `FRAGCITY_SETTINGS_FILE` را تنظیم کن.
- فایل `/etc/fragcity-api/config.php` باید بیرون DocumentRoot و فقط برای PHP-FPM قابل‌خواندن باشد؛ PHP-FPM نباید بتواند آن را بنویسد.
- فایل‌های Nginx API در `linuxapi/nginx/` نمونه هستند؛ vhost پنل Pterodactyl را با آن‌ها جایگزین نکن.
- هیچ Secret، Application API token، Wings token یا Database password را در Git، چت یا مرورگر قرار نده.

## ۱۰. کنترل قبل از اجرا

```powershell
php -r '$j=json_decode(file_get_contents("settings/settings.example.json"),true); if(!is_array($j)) {fwrite(STDERR,json_last_error_msg().PHP_EOL); exit(1);} echo "Valid website settings example".PHP_EOL;'
php -l linuxapi/config.example.php
php linuxapi/tools/architecture-check.php
```

این بررسی‌ها ساختار و معماری را کنترل می‌کنند؛ جایگزین تست واقعی اتصال HTTPS، امضای HMAC، Pterodactyl، A2S، دیتابیس یا UI نیستند. انتشار Production نیز جدا از Git pull و فقط پس از Backup و تست انجام می‌شود.

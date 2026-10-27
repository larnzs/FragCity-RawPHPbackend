# FragCity — Local Development (Windows)

> **هدف:** توسعه و تست باگ روی Windows قبل از هرگونه انتشار روی هاست. این سند نصب محلی را توصیف می‌کند؛ هیچ چیز در این فرایند خودش روی سایت عمومی deploy نمی‌شود.
>
> **وضعیت فعلی: آماده‌سازی محیط محلی تعریف شده، اما اجرای امن کامل سایت هنوز مسدود است.** ممیزی اولیه secretهای دیتابیس hard-code شده در فایل‌های PHP را یافته است. تا وقتی تنظیمات دیتابیس از سورس جدا نشده و دیتابیس محلی مستقل آماده نشده، سایت را طوری اجرا نکن که بتواند به دیتابیس Production دسترسی پیدا کند.

## 1. تفکیک محیط‌ها

| محیط | نقش | مرز |
|---|---|---|
| Windows + Laragon (پیشنهاد اول) | توسعهٔ PHP، CSS/JS و تست مرورگر | فقط کد و دیتابیس آزمایشی محلی |
| Windows + WAMP | جایگزین Laragon | همان الزامات امنیتی و تنظیمات PHP/Apache |
| Debian VM | آزمایش جداگانهٔ Linux/شبکه و در مراحل بعد پنل/نود CS2 | از محیط توسعهٔ وب جداست؛ فعلاً Production نیست |
| هاست cPanel | سایت عمومی فعلی | updater محلی نباید آن را تغییر دهد |
| GitHub main | منبع نسخهٔ کد | تغییرات ثبت‌شده از اینجا به سیستم محلی pull می‌شوند |

## 2. نصب نخستین نسخه روی Windows

1. **Laragon را انتخاب کن** مگر اینکه WAMP از قبل آماده باشد. نسخهٔ PHP باید با PHP هاست فعلی تا حد ممکن هماهنگ باشد؛ فایل .htaccess فعلی هاست از PHP 8.4 نام می‌برد.
2. Git for Windows، Apache، PHP و MySQL/MariaDB محلی نصب/فعال باشند. نسخه و extensionهای واقعی موردنیاز باید پس از بررسی includeهای دیتابیس ثبت شوند.
3. از GitHub یک clone مجزا در مسیر وب Laragon ایجاد کن. **مسیر `C:\laragon\www\FragCity-RawPHPbackend` در فرمان زیر فقط نمونهٔ نصب معمولی Laragon است.** در نصب Portable ثبت‌شدهٔ این پروژه، Laragon در `W:\laragon_portable` قرار دارد؛ مسیر مقصد را با نصب واقعی خودت هماهنگ کن. پوشهٔ `W:\laragon_portable\www` در README برای Flute Release استفاده شده است؛ آن را با clone ریپوی دیگری بازنویسی یا مخلوط نکن. برای تست RawPHPbackend، مسیر و Virtual Host مستقل تعریف کن. اگر GitHub CLI/Git Credential Manager دسترسی خصوصی را پیکربندی کرده باشد:

   ~~~bat
   git clone --branch main --single-branch https://github.com/larnzs/FragCity-RawPHPbackend.git C:\laragon\www\FragCity-RawPHPbackend
   ~~~

   برای ریپوی خصوصی، هنگام ورود از روش امن Git Credential Manager یا gh auth login استفاده کن؛ توکن را در URL، فایل BAT یا متن دستور ذخیره نکن.
4. ریشهٔ DocumentRoot باید مستقیماً پوشهٔ FragCity-RawPHPbackend باشد؛ سایت را صرفاً زیر یک subdirectory از localhost اجرا نکن، چون کد فعلی از URLهای ریشه‌محور مثل /css/... استفاده می‌کند.
5. در Laragon یک Virtual Host محلی مثل fragcity.test بساز که DocumentRoot آن همان پوشهٔ ریپو باشد. در WAMP نیز یک VirtualHost با همین DocumentRoot تعریف و نام محلی را به 127.0.0.1 نگاشت کن. از نام دامنهٔ Production برای تست استفاده نکن.
6. Apache را فقط برای دسترسی محلی نگه دار و اجازه نده سایت آزمایشی یا phpMyAdmin از اینترنت عمومی قابل دسترس شود.

> فایل .htaccess فعلی شامل rewriteهای وب‌سایت و یک handler تولیدشده توسط cPanel است. ابتدا آن را بررسی کن؛ اگر با Apache محلی سازگار نبود، تنظیم محلی را در VirtualHost یا نسخهٔ آزمایشی انجام بده. تغییر مختص local را بدون بررسی وارد نسخهٔ Production نکن.

## 3. پیکربندی مرکزی JSON و دیتابیس محلی

تنظیمات در `settings/settings.json` قرار می‌گیرند و نمونهٔ بدون secret در `settings/settings.example.json` است:

~~~bat
copy settings\settings.example.json settings\settings.json
~~~

- فایل runtime در Git نادیده گرفته می‌شود.
- نام دیتابیس، username و password در نمونه خالی هستند؛ اتصال تا تکمیل تنظیمات غیرفعال می‌ماند.
- برای توسعه یک دیتابیس مستقل و user محدود بساز؛ credentialهای Production را وارد نکن.
- timezone از `app.timezone` خوانده می‌شود؛ پیش‌فرض UTC است.
- **Nginx قوانین `.htaccess` را اجرا نمی‌کند.** قبل از ساخت `settings.json`، snippet موجود در `settings/nginx-protect.conf` را داخل server block وارد کن، یا JSON را بیرون DocumentRoot نگه دار و `FRAGCITY_SETTINGS_FILE` را تنظیم کن.
- روی cPanel باید runtime settings مخصوص Production جداگانه و امن آماده شود.
- پرداخت واقعی و عملیات مالی در تست محلی غیرفعال بمانند.

**سازگاری PHP:** برنامه از امکانات PHP 8.x مثل `match` و `str_starts_with` استفاده می‌کند. در Laragon از Menu → PHP → Version، نسخهٔ PHP 8.4 را انتخاب کن. timezone Parse error ناشی از نسخهٔ PHP قدیمی را حل نمی‌کند. این انتخاب مخصوص سایت در Windows است؛ Pterodactyl Panel v1.15.1 در Debian جداگانه با PHP 8.3 اجرا می‌شود، پس نسخهٔ PHP این دو محیط را با هم اشتباه نگیر.

## 4. استفاده از updater روزمره

فایل update.bat در ریشهٔ ریپو قرار دارد و فقط ریپوی محلی را به‌روز می‌کند:

1. ابتدا تغییرهای محلی را commit کن یا در stash/مسیر جدا نگه دار.
2. از File Explorer روی update.bat دوبار کلیک کن.
3. اسکریپت ابتدا تمیزبودن working tree را بررسی می‌کند، سپس main را انتخاب می‌کند و فقط git pull --ff-only origin main اجرا می‌کند.
4. نتیجهٔ SUCCESS/FAILED نمایش داده می‌شود و پنجره برای دیدن پیام‌ها منتظر فشردن یک کلید می‌ماند.

اگر فایل tracked تغییرکرده یا فایل untracked وجود داشته باشد، updater عمداً متوقف می‌شود و چیزی را تغییر نمی‌دهد. ابتدا آن فایل را بررسی/commit/stash کن؛ تغییرهای محلی را کورکورانه discard نکن.

### updater چه کارهایی انجام نمی‌دهد؟

- PHP یا Apache را restart نمی‌کند.
- دیتابیس را ایجاد، migrate، پاک یا import نمی‌کند.
- هیچ فایل یا دیتابیسی را روی cPanel منتشر نمی‌کند.
- روی Debian VM یا سرور CS2 فرمانی اجرا نمی‌کند.
- پکیج‌ها، وابستگی‌ها یا secretها را نصب/مدیریت نمی‌کند.

## 5. حلقهٔ کاری روزانه

~~~text
تغییر کد روی GitHub main
        ↓
اجرای update.bat روی Windows
        ↓
Laragon/WAMP + دیتابیس محلی مستقل
        ↓
تست UI، PHP syntax، API و سناریوهای باگ
        ↓
ثبت نتیجه و اصلاح لازم
        ↓
بازبینی و انتشار جداگانه به هاست Production
~~~

توسعه روی سیستم محلی باید پیش‌فرض کار باشد. انتشار Production یک اقدام دستی و جداگانه پس از تست و backup است؛ pull محلی به معنی انتشار نیست.

## 6. بررسی‌های پایه پیش از تأیید هر تغییر

- [ ] تغییر روی محیط محلی تست شده باشد.
- [ ] خطاهای PHP و Apache بررسی شده باشند.
- [ ] صفحات/endpointهای تغییرکرده با دیتابیس آزمایشی تست شده باشند.
- [ ] هیچ تماس ناخواسته با DB، درگاه، ایمیل یا API واقعی انجام نشده باشد.
- [ ] secret، dump دیتابیس، log خصوصی و دادهٔ مشتری وارد Git نشده باشد.
- [ ] تغییرات محلی ناخواسته قبل از اجرای update.bat باقی نمانده باشند.
- [ ] انتشار Production فقط پس از بازبینی و backup برنامه‌ریزی شده باشد.

## 7. گام‌های تکمیلی Pending

1. بررسی و اصلاح امن hard-coded database configuration به‌شکل مستقل از محیط.
2. آماده‌سازی local-only config و ساختار نمونهٔ تنظیمات بدون secret.
3. تکمیل inventory مربوط به schema دیتابیس و ایجاد seed ساختگی.
4. تعیین نسخهٔ دقیق PHP و extensionهای لازم برای Laragon/WAMP.
5. اجرای lint فایل‌های PHP و تست ورود، صفحات، API و عملکردهای وابسته به DB در محیط محلی.
6. تعریف runbook انتشار cPanel شامل backup، تغییر فایل‌ها، بررسی smoke-test و rollback.

این مراحل عمداً از updater جدا هستند: updater فقط کد Git را pull می‌کند؛ وظیفه‌اش انتشار سایت یا تغییر داده نیست.

## Local database — website login and WeaponPaints API

The local runtime file is `settings/settings.json`, ignored by Git. If it does not exist, copy the example once; do not overwrite an existing local file.

For the supplied website schema, set:

- `databases.main.host`: `127.0.0.1`
- `databases.main.port`: `3306`
- `databases.main.name`: `kyonxcbt_fragcity_services`
- `databases.main.username/password`: the credentials for the local Laragon MySQL/MariaDB instance only.

The only confirmed tables in that website database are `donations`, `password_resets`, `tickets`, `ticket_replies`, `users`, `user_wallets`, `vip_codes`, `vip_usage`, `wallet_transactions`, and `withdraw_requests`. Do not add the old Windows-Agent tables to make old screens pass.

WeaponPaints is separate. The actual game data remains in `GLOBAL_weaponpaints` on Debian and the website must access it through `https://api.fragcity.ir/v1/weaponpaints`. Set `integrations.weaponpaints_api.base_url` and the private `integrations.weaponpaints_api.shared_secret`; ensure `files.database.weaponpaints_plugin.transport` is `api`. Do not set a direct `databases.weaponpaints` profile or use `kyonxcbt_weaponpaints_plugin`.

**Warning:** API writes reach the real Linux WeaponPaints database unless a separate test API is provided. Use a dedicated test SteamID/account for save tests. The local website DB copy does not make the remote WeaponPaints API sandboxed.

To check the ten website tables without writes:

```powershell
php tools/site-bridge-preflight.php
```

For the static architecture check:

```powershell
php linuxapi/tools/architecture-check.php
```

Keep `settings/settings.json` out of version control and block web access to `/settings/` when the repository root is the document root.

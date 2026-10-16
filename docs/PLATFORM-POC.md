# FragCity — Platform Selection & PoC Matrix

> امتیازهای این سند ارزیابی اولیه‌اند؛ انتخاب Production تنها بعد از قبولی آزمون‌های عملی انجام می‌شود.

## 1. مسیرهای مقایسه

| ID | ترکیب | مزیت | ریسک/شکاف اصلی | وضعیت |
|---|---|---|---|---|
| A | Paymenter + Pterodactyl Panel/Wings | integration رسمی Billing/Panel و اکوسیستم شناخته‌شده | باید Wallet/بستهٔ مدت‌دار/expiry دقیق آزمایش شود | مسیر مرجع PoC |
| B | Paymenter + GameAP | API و امکانات SSO جالب | integration فروش آماده در همین حد تأیید نشده | گزینهٔ مقایسه‌ای |
| C | FOSSBilling + Pterodactyl | جایگزین متن‌باز برای Billing | وضعیت پروژه و integration جامعه‌ای نیازمند بررسی | گزینهٔ بعدی فقط در صورت نیاز |
| D | PHP فعلی + GameAP + کد اختصاصی | حفظ بخشی از سایت موجود | توسعه و مسئولیت نگهداری بیشتر | آخرین جایگزین |

### چرا مسیر A انتخاب اول است؟

Paymenter integration رسمی برای Pterodactyl دارد. Panel/Wings می‌تواند مدیریت instance، منابع، کنسول، فایل‌ها و چرخهٔ عمر را انجام دهد. این مسیر بیشترین شانس را دارد که کد اختصاصی Instance Maker و RCON را حذف کند.

### چرا هنوز انتخاب قطعی نیست؟

مدل FragCity خرید بسته با قیمت ثابت است: مشتری کیف پول را شارژ می‌کند، بسته را خریداری می‌کند، پول یک‌بار کسر می‌شود و مدت بسته از خرید موفق شروع می‌شود؛ خاموشی سرور تایمر را متوقف نمی‌کند. باید بررسی شود Paymenter این lifecycle را با امکانات موجود پوشش می‌دهد یا یک extension محدود لازم دارد.

## 2. فهرست ریپوهای PoC

| جزء | پروژه | لینک |
|---|---|---|
| مدیریت پنل | Pterodactyl Panel | https://github.com/pterodactyl/panel |
| Agent نود | Pterodactyl Wings | https://github.com/pterodactyl/wings |
| فروشگاه/Billing | Paymenter | https://github.com/Paymenter/Paymenter |
| گزینهٔ مدیریت بازی | GameAP | https://github.com/gameap/gameap |
| Billing جایگزین | FOSSBilling | https://github.com/FOSSBilling/FOSSBilling |
| پنل مقایسه‌ای | Pelican | https://github.com/pelican-dev/panel |
| benchmark رابط CS2 | OVHcloud Game Panel | https://github.com/ovh/game-panel |

## 3. نسخه و امنیت

Releaseهای کاندید در زمان ثبت قبلی مستند شده‌اند؛ قبل از نصب حتماً دوباره بررسی شوند:
- Pterodactyl: https://github.com/pterodactyl/panel/releases
- Wings: https://github.com/pterodactyl/wings/releases
- Paymenter: https://github.com/Paymenter/Paymenter/releases
- GameAP: https://github.com/gameap/gameap/releases

شرط نصب: نسخه پایدار و patched، بررسی advisory، نسخه‌بندی dependencyها، نصب تکرارپذیر و امکان backup/rollback. از Beta برای مسیر Production استفاده نشود. هیچ password، API key یا token در commit/README قرار نگیرد.

## 4. ماتریس امتیازدهی مقدماتی

امتیاز از ۵ است؛ اعداد قضاوت اولیه‌اند و بعد از PoC تغییر می‌کنند.

| معیار | وزن | A: Paymenter+Ptero | B: Paymenter+GameAP | C: FOSSBilling+Ptero |
|---|---:|---:|---:|---:|
| امکانات آماده/کدنویسی کمتر | ۲۰٪ | ۵ | ۳ | ۲٫۵ |
| مدیریت CS2 | ۱۵٪ | ۵ | ۴ | ۵ |
| چندنودی و منابع | ۱۵٪ | ۴ | ۴ | ۴ |
| Billing عمومی | ۱۲٪ | ۴ | ۴ | ۳ |
| بسته مدت‌دار و wallet | ۱۵٪ | ۴ | ۳ | ۳ |
| API/گسترش | ۸٪ | ۴ | ۴ | ۴ |
| امنیت/نگهداری | ۱۰٪ | ۴ | ۳٫۵ | ۳ |
| سهولت استقرار | ۵٪ | ۳ | ۳ | ۳ |
| **امتیاز اولیه** | **۱۰۰٪** | **۸۶/۱۰۰** | **۷۱/۱۰۰** | **۶۹/۱۰۰** |

این امتیاز به تنهایی مجوز Production نیست. هر شکست امنیتی یا مالی یک شرط توقف مستقل است.

## 5. ماتریس آزمون اجرایی

| ID | آزمون | روش | معیار قبولی | اولویت |
|---|---|---|---|---|
| T00 | نسخه و امنیت | بررسی release، advisory، مجوز و وابستگی‌ها | هیچ آسیب‌پذیری بحرانی/بالا بدون patch؛ نسخه Beta در مسیر production نباشد | مسدودکننده |
| T01 | نصب نود | نصب Panel/Wings در محیط آزمایشی و ثبت allocation | نود online و قابل مدیریت؛ secret امن | بالا |
| T02 | ساخت CS2 | تعریف Egg/template و نصب یک instance | سرور اجرا شود؛ پنل و runtime هم‌خوان باشند | مسدودکننده |
| T03 | منابع/پورت | اعمال CPU/RAM/Disk و allocation | پورت دوباره تخصیص داده نشود؛ محدودیت‌ها اعمال شود | مسدودکننده |
| T04 | سفارش تا Provisioning | سفارش ساختگی یا Sandbox | یک سفارش فقط یک instance بسازد؛ retry کنترل شود | بالا |
| T05 | چرخهٔ عمر | Start/Stop/Restart/Reinstall/Delete | عملیات از پنل؛ وضعیت‌ها هماهنگ باشند | بالا |
| T06 | جداسازی مشتری | دو مشتری و دو server | هیچ دسترسی بین فایل، console، backup، API یا metadata وجود نداشته باشد | مسدودکننده |
| T07 | Console/API | فرمان، فایل و log را با دسترسی مشتری تست کن | بدون RCON سفارشی، نیازهای معمول پوشش داده شوند؛ secret افشا نشود | بالا |
| T08 | Backup/Restore | Backup و restore در محیط جدا | داده درست برگردد؛ هیچ restore روی instance اشتباه نباشد | مسدودکننده |
| T09 | بستهٔ مدت‌دار | خرید بستهٔ ۱/۳/۷/۱۵/۳۰ روزه از Wallet آزمایشی؛ stop/start هنگام اعتبار | debit یک‌باره؛ `expires_at` درست؛ خاموشی تایمر را متوقف نکند؛ انقضا action مصوب را اجرا کند | مسدودکننده |
| T10 | Wallet/تراکنش | شارژ، خرید، callback تکراری و شکست provisioning | wallet و ledger هماهنگ؛ عدم debit تکراری؛ جبران خطای ساخت قابل ردیابی | مسدودکننده |
| T11 | Authorization | درخواست بدون token، token باطل، scope اشتباه و دسترسی به مشتری دیگر | 401/403 مناسب؛ هیچ endpoint مدیریتی بدون مجوز | مسدودکننده |
| T12 | Node دوم | اضافه‌کردن نود با runbook مشابه | تغییر کد سایت لازم نباشد | بالا |
| T13 | قطع API | قطع موقت API و بازگرداندن آن | retry/backoff؛ عدم ساخت مجدد سفارش؛ reconciliation موفق | بالا |
| T14 | ظرفیت | اجرای ۱، سپس ۳ و ۵ سرور با بار کنترل‌شده | دادهٔ RAM/CPU/disk/ping ثبت شود؛ OOM/crash غیرقابل قبول نباشد | بالا |
| T15 | Rollback | backup، ارتقا روی آزمایشگاه، rollback | مسیر برگشت مستند و آزموده | بالا |
| T16 | پرداخت | موفق، رد، لغو، callback تکراری، مبلغ دست‌کاری‌شده | فقط تأیید معتبر سمت سرور اثر مالی بگذارد | مسدودکننده |
| T17 | migration | تطبیق شناسه‌ها و داده نمونه | بدون گم‌شدن مالکیت/موجودی؛ گزارش اختلاف و rollback | قبل از مهاجرت |
| T18 | Shared Base و Update هماهنگ‌شده | ساخت دو Instance روی یک Base مرکزی؛ deploy payload کامل addons/cfg/GameMode برای هر کدام؛ Update یک‌باره با SteamCMD | مسیرهای private بین Instanceها مستقل باشد؛ هنگام Update سروری اجرا نشود؛ پس از اعتبارسنجی فقط Instanceهای قبلاً Running برگردند و شکست قابل بازیابی باشد | پس از T02 و پیش از Production |
| T19 | File Manager و مجوزهای مشتری | ویرایش cfg و تنظیمات افزونه از طریق UI مرورگر Pterodactyl؛ آزمون مجوزهای file.update، read-content، SFTP و Console به‌صورت مجزا | مشتری بتواند فایل‌های Instance خودش را طبق مجوز ویرایش کند ولی نتواند Base مشترک یا فایل Instance دیگر را بخواند/تغییر دهد؛ رفتار mount/overlay ثبت شود | مسدودکننده برای مشتریان |
| T20 | Sync خصوصی payload پیش از هر Start | Start/Stop/Start دو Instance؛ بررسی sync از شاخهٔ `integration/kus-monorepo` به مسیر خصوصی هر Instance و بررسی checksum/pathها | هر Start payload اختصاصی را sync/update کند؛ هیچ فایل payload روی Base کپی/merge نشود؛ failure از Start جلوگیری کند؛ قانون overwrite/preserve شخصی‌سازی‌ها قبل از Production تثبیت شود | مسدودکننده |

## 6. ترتیب PoC

1. نسخه‌های دقیق و patched پنل، Wings، Docker و Egg/template را تثبیت کن.
2. فقط یک Debian Node و یک سرور CS2 روی Base مرکزی بساز.
3. چرخهٔ عمر، منابع، کنسول، File Manager، SFTP و Backup را بررسی کن؛ ویرایش فایل‌های private باید از رابط مرورگر عملی باشد.
4. یک Instance را روی Base مرکزی بساز و pre-start sync را طوری تنظیم کن که پیش از اجرای process، addons/cfg و فایل‌های .txt تعیین‌شده را فقط به فضای private آن Instance sync کند؛ هیچ فایل payload روی Base کپی/merge نشود. در این مرحله preset مود نهایی اعمال نکن.
5. Instance دوم را روی همان Base بساز؛ sync باید برای آن نیز مستقل اجرا شود، allocation/portها یکتا بمانند و ویرایش فایل یک Instance دیگری را تغییر ندهد.
6. ترتیب اجرای Base مشترک و payload خصوصی را اثبات کن، سپس رفتار Update هماهنگ‌شده را آزمایش کن: تشخیص Update جدید، توقف همهٔ Instanceهای مصرف‌کننده، اجرای یک‌بارهٔ SteamCMD روی Base، جلوگیری از Start هنگام Update و بازگرداندن فقط Instanceهای قبلاً Running.
7. مجوزهای File Manager و Subuserها را جداگانه بررسی کن؛ دسترسی به فایل‌های Base مشترک و Volume/دادهٔ سایر Instanceها نباید از مسیر مشتری ممکن باشد.
8. Paymenter را در محیط آزمایشی وصل کن؛ یک سفارش ساختگی را تا Provisioning دنبال کن.
9. منطق Wallet، کسر بسته، `expires_at` و expiry را جداگانه آزمایش کن.
10. اگر مسیر A شکست خورد یا توسعهٔ بیش از حد لازم داشت، یک PoC محدود GameAP بساز.
11. فقط بر اساس شواهد آزمون دربارهٔ Production تصمیم بگیر.

## 7. شرط توقف

تست در صورت دسترسی مشتری به دادهٔ دیگری، اختلاف غیرقابل توضیح در Wallet، ایجاد instance تکراری، از دست رفتن داده، شکست Restore یا آسیب‌پذیری بحرانی بدون patch متوقف می‌شود.


## 8. آزمون Shared Base برای FragCity

جهت معماری PoC این است که چند Instance از یک Base Game مرکزی استفاده کنند؛ مدل کپی کامل برای هر Instance انتخاب پیش‌فرض نیست. یک Instance ابتدا برای تأیید نصب و چرخهٔ عمر آزمایش می‌شود، سپس Instance دوم روی همان Base ساخته می‌شود و جداسازی addons/، cfg/، فایل‌های GameMode، پورت‌ها، مجوزها و Backup بررسی می‌شود.

به‌محض تشخیص Update جدید CS2، چرخهٔ Base باید خودکار و هماهنگ اجرا شود: همهٔ Instanceهای مصرف‌کننده متوقف شوند، SteamCMD فقط روی Base مرکزی اجرا شود، نتیجه اعتبارسنجی شود و سپس فقط Instanceهایی که پیش از Update در حال اجرا بودند برگردند. روش دقیق نگاشت مسیرها/mountها و مکانیزم lock/recovery هنوز Pending است. K4ryuu VPK Sync فقط گزینهٔ مقایسه‌ای اختیاری است؛ نصب آن پیش‌شرط Shared Base نیست.

اسکریپت Deploy افزونه‌های FragCity که در آینده با Pterodactyl ادغام می‌شود، payload کامل و مدیریت‌شدهٔ addons/، cfg/ و فایل‌های متنی تعیین‌شدهٔ GameMode را در لایهٔ خصوصی همان Instance قرار می‌دهد؛ SteamCMD، نصب/Update بازی یا Start سرور را اجرا نمی‌کند. فایل‌ها ابتدا به‌صورت baseline عمومی می‌آیند و preset مود برای مرحلهٔ اول اعمال نمی‌شود. مجوز File Manager و دسترسی به overlay هم باید تست شود. مرجع آزمون [Testing Bench](TESTING-BENCH.md) و [CS2 Multi-Instance](CS2-MULTI-INSTANCE.md) است.

## 9. تفکیک OS در تست

- مسیر اصلی Node/Panel، Base Game مشترک و Update هماهنگ‌شده روی Linux native آزمایش می‌شود.
- مدیرهای native Windows در محیط جداگانه بررسی می‌شوند؛ موفقیت Windows جایگزین قبولی Linux نیست.
- ادعاهای cross-platform باید مستقلاً روی هر دو OS ثابت شوند؛ WSL2 یک نتیجهٔ native Windows به‌حساب نمی‌آید.
- فهرست نامزدها و مراحل را در [Testing Bench](TESTING-BENCH.md) و [CS2 Multi-Instance](CS2-MULTI-INSTANCE.md) ببین.

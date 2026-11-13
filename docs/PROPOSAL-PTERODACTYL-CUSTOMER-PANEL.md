# پیشنهاد معماری: پنل مشتری FragCity مبتنی بر Pterodactyl

> **وضعیت:** پیشنهاد فنی در انتظار تأیید نهایی کاربر؛ هنوز تصمیم Production نیست.
>
> **تاریخ بررسی:** ۱۳ نوامبر ۲۰۲۶

## 1. هدف

FragCity به یک پنل عملیاتی یکپارچه برای مدیریت سرورهای بازی نیاز دارد که امروز CS2 و در آینده بازی‌هایی مانند Minecraft و Rust را پشتیبانی کند. هدف، استفاده از قابلیت‌های عمومی Pterodactyl و توسعهٔ حداقلی و ماژولار قابلیت‌های اختصاصی FragCity است.

## 2. مرزبندی محصولات

| سامانه | مسئولیت |
|---|---|
| `fragcity.ir` | وب‌سایت عمومی، برند، معرفی سرویس‌ها، محتوا و راهنما |
| Paymenter (مثلاً `billing.fragcity.ir`) | فروشگاه، محصول و Plan، سفارش، پرداخت، کیف پول، Invoice، تمدید و Provisioning |
| Pterodactyl (مثلاً `panel.fragcity.ir`) | پنل عملیاتی مشتری و ادمین برای سرورها، کنسول، Power، منابع، فایل‌ها، Backup و مدیریت Node |
| Wings | اجرای کانتینرها و عملیات نود |
| FragCity Extensions/Themes | لایهٔ سفارشی‌سازی و قابلیت‌های اختصاصی، بدون کپی Core |

Paymenter فروش و امور مالی را مدیریت می‌کند؛ Pterodactyl اجرای واقعی و مدیریت عملیاتی سرور را. وب‌سایت عمومی نباید دوباره یک پنل کامل مدیریت سرور بسازد.

## 3. چرا Pterodactyl باید سطح اصلی کنترل سرور باشد؟

- قابلیت‌های عمومی مانند Console، Power Actions، File Manager، Backups، Schedules، Resource Statistics و Node Management از قبل در زیرساخت Pterodactyl وجود دارند.
- این قابلیت‌ها به یک بازی خاص وابسته نیستند و برای سرورهای CS2، Minecraft و بازی‌های دیگر قابل استفاده‌اند.
- Pterodactyl منبع اصلی حقیقت برای Server، Node، Allocation، Container و چرخهٔ عمر فرآیند بازی باقی می‌ماند.
- یک UI مستقل در سایت یا Paymenter باعث تکرار صفحات، مجوزها، اتصال‌های WebSocket و عملیات API می‌شود.

## 4. روش سفارشی‌سازی پیشنهادی

### انتخاب اول: Extension و Theme رسمی Pterodactyl 2.x

Pterodactyl 2.0 دارای SDK رسمی Extension است:
- Backend افزونه مانند یک Laravel package اجرا می‌شود و می‌تواند API route، permission، settings، migration و background work داشته باشد.
- مسیرهای Server Extension با مدل Server احرازشده و بررسی دسترسی Pterodactyl اجرا می‌شوند.
- Frontend افزونه React است و می‌تواند screen، detail tab، slot، table column و component replacement ثبت کند.
- Extensionها به‌صورت فایل `.pteroext` بسته‌بندی و نسخه‌بندی می‌شوند.
- Theme برای رنگ، شکل، فونت/دارایی‌ها و استایل ظاهری است؛ افزودن قابلیت و صفحه باید در Extension انجام شود.

**اصل مهم:** این قابلیت‌ها به معنی مجوز ویرایش مستقیم Core نیستند. کد FragCity باید در مخزن اختصاصی خودش بماند و از قراردادهای رسمی SDK استفاده کند.

### ساختار مفهومی ماژول‌ها

- **FragCity Base:** تنظیمات مشترک، اتصال هویت، ناوبری اختصاصی، layout پایه، قراردادها و ثبت فعالیت.
- **Pterodactyl Adapter:** استفاده از Client API و APIهای داخلی مورد نیاز، با رعایت permissionهای همان Panel.
- **Game Modules:** ماژول‌های مستقل CS2، Minecraft و بازی‌های آینده.
- **FragCity Theme:** رنگ‌بندی، لوگو، فونت و دارایی‌های بصری.
- **Release Tooling:** build، test، manifest، version، checksum و بسته‌بندی مستقل.

در شروع نباید برای هر دکمه یک افزونهٔ جدا ساخت. یک Extension اصلی با مرزهای داخلی روشن کافی است؛ جداسازی پکیج‌ها زمانی انجام شود که چرخهٔ انتشار یا وابستگی مستقل واقعاً لازم باشد.

## 5. کنسول و ارتباط با سرورها

برای کنسول مشتری:
- ارسال دستور از Client API رسمی Pterodactyl انجام شود.
- خروجی زنده از WebSocket رسمی Pterodactyl دریافت شود.
- Power Actions، فایل‌ها، Backup و آمار از قابلیت‌های Native/API خود Panel استفاده کنند.
- RCON مستقیم، کلید RCON یا اتصال TCP از مرورگر مشتری استفاده نشود.
- هیچ Application API Key، Client API Key یا Wings credential در JavaScript یا Browser Storage قرار نگیرد.

هویت مشتری در Paymenter و Pterodactyl باید با نگاشت پایدار (ترجیحاً شناسهٔ خارجی ثابت) مرتبط باشد. ورود یکپارچه (SSO) موضوع مستقلی است و باید با token کوتاه‌عمر، یک‌بارمصرف، محدود به audience و دارای اعتبارسنجی سمت سرور طراحی و آزمایش شود؛ همسان‌کردن یا اشتراک رمز عبور دو سامانه راهکار قابل قبول نیست.

## 6. چرا Fork یا ویرایش مستقیم Core انتخاب اول نیست؟

Fork کامل باعث می‌شود FragCity مسئول ادغام تغییرات امنیتی و ساختاری upstream شود. در Pterodactyl 1.x نیز تغییرات سورس Frontend پس از Update با جایگزینی فایل‌های Panel از بین می‌روند و build مجدد React لازم است.

Pterodactyl 2.x برای گسترش بدون تغییر Core، Extension و Theme رسمی ارائه می‌کند. بنابراین همان الگوی موفق FragCity-Paymenter مناسب است: نصب نسخهٔ upstream و انتشار فقط کد Theme/Extension اختصاصی.

Fork فقط در صورت وجود نیاز ضروری و غیرقابل حل با API/Extension/Theme، با ADR جداگانه و برآورد هزینهٔ نگهداری پذیرفته شود.

## 7. وضعیت نسخه و ریسک فعلی در تاریخ بررسی

- شاخهٔ Pterodactyl 1.x Stable است؛ Release جاری که در این بررسی دیده شد `v1.15.2` بود.
- شاخهٔ Pterodactyl 2.0 هنوز **Open Beta** است؛ آخرین پیش‌انتشار مشاهده‌شده `v2.0.0-beta.2` بود.
- راهنمای رسمی Upgrade اعلام می‌کند تست Upgrade روی کپی Panel لازم است و در Known Limitations مواردی مانند چند Node و Extensions هنوز آزموده نشده‌اند.
- Pterodactyl 2.0 endpointهای Application API مربوط به Nest را حذف می‌کند.
- کد رسمی Pterodactyl Server Extension در Paymenter فعلاً endpointهای `/api/application/nests` و `/api/application/nests/{id}/eggs` را فراخوانی می‌کند. FragCityCS2 نیز از آن پیاده‌سازی ارث‌بری می‌کند؛ پس سازگاری Provisioning با Pterodactyl 2.x باید قبل از هر مهاجرت اصلاح و تست شود.

**نتیجه:** در این مرحله Pterodactyl 2.0 روی Production موجود ارتقا داده نشود. ابتدا یک محیط Staging کاملاً جداگانه ساخته شود؛ بدون اتصال کنترل‌کنندهٔ دوم به Nodeها و سرورهای Production.

## 8. مسیر آزمایش و پذیرش

1. ثبت نسخه‌های دقیق فعلی Panel، Wings، Paymenter و Paymenter Pterodactyl Extension.
2. نصب Pterodactyl 2.0 در محیط آزمایشی مستقل و بررسی SDK/Extension/Theme.
3. آزمایش نصب، Update، Disable و Rollback یک Extension ساده.
4. آزمایش Client API برای Console و WebSocket روی یک سرور آزمایشی.
5. آزمایش دسترسی کاربر مالک و Subuser با permissionهای متفاوت.
6. بررسی و اصلاح Paymenter provisioning برای حذف Nest API و سازگاری با ساختار جدید، در صورت نیاز.
7. آزمایش حداقل دو Node آزمایشی و افزودن Node سوم صرفاً با Configuration/Runbook، بدون تغییر کد.
8. آزمایش اتصال هویت Paymenter/Pterodactyl و طراحی SSO امن.
9. Backup و Restore کامل Panel DB، `.env`/APP_KEY، فایل‌های Extension/Theme و تنظیمات Node.
10. انتقال Production فقط پس از Stable شدن شاخهٔ 2.x، قبولی تست‌ها و ثبت Rollback Plan.

## 9. منابع رسمی

- [Pterodactyl Documentation — وضعیت 1.x Stable و 2.0 Open Beta](https://docs.pterodactyl.io/)
- [Pterodactyl 2.0 Extensions](https://docs.pterodactyl.io/v2/extensions)
- [Pterodactyl 2.0 Extension Backend](https://docs.pterodactyl.io/v2/extensions/backend)
- [Pterodactyl 2.0 Extension Frontend](https://docs.pterodactyl.io/v2/extensions/frontend)
- [Pterodactyl 2.0 Themes](https://docs.pterodactyl.io/v2/guides/customization/themes)
- [Pterodactyl Upgrade from 1.x](https://docs.pterodactyl.io/v2/upgrading/upgrading-from-v1)
- [Pterodactyl releases](https://github.com/pterodactyl/panel/releases)
- [Paymenter upstream Pterodactyl extension](https://github.com/Paymenter/Paymenter/blob/master/extensions/Servers/Pterodactyl/Pterodactyl.php)

## 10. مواردی که هنوز تصمیم قطعی نیستند

- تأیید نهایی انتقال UI عملیاتی مشتری به خود Pterodactyl.
- انتخاب زمان مهاجرت از 1.x به 2.x.
- مدل SSO و اتصال نشست Paymenter به Pterodactyl.
- میزان بازطراحی مجاز از طریق Extension SDK در مقابل حفظ صفحات Native.
- تقسیم نهایی پکیج‌ها بین Base Extension، ماژول بازی و Theme.

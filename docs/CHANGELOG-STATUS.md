# FragCity — Change Log & Current Status

## آخرین وضعیت ثبت‌شده

### توسعهٔ محلی Windows — به‌روزرسانی ۱۶ اکتبر ۲۰۲۶

- فایل `update.bat` در ریشهٔ ریپو اضافه شد: فقط `main` را انتخاب و `git pull --ff-only origin main` اجرا می‌کند؛ برای دیدن نتیجه در پایان مکث دارد.
- اگر working tree تغییر commit‌نشده یا فایل untracked داشته باشد، updater بدون switch/pull متوقف می‌شود.
- راهنمای `docs/LOCAL-DEVELOPMENT.md` و قوانین اولیهٔ `.gitignore` اضافه شدند. Laragon گزینهٔ پیشنهادی است؛ WAMP هم می‌تواند استفاده شود.
- هنوز clone در کامپیوتر کاربر، نصب Laragon/WAMP، ساخت VirtualHost یا تست عملی انجام نشده است.
- اجرای امن صفحات دیتابیسی تا جداسازی credentialهای hard-code شده از سورس و آماده‌شدن schema/seed محلی مسدود است.
- updater فقط کد را از GitHub به clone محلی می‌آورد؛ هیچ deploy یا تغییر دیتابیس روی cPanel و هیچ عملیات روی Debian VM انجام نمی‌دهد.

- مدل فروش تأیید شده: بستهٔ مدت‌دار پیش‌پرداخت با کسر یک‌باره از Wallet و تایمر مستقل از روشن/خاموش‌بودن سرور.
- Remote MariaDB روی 3306 در هاست اشتراکی فعلی گزینهٔ اجرایی نیست؛ HTTPS API مسیر کاندید فعلی است.
- مسیر مرجع PoC: Paymenter + Pterodactyl/Wings؛ GameAP برای مقایسه نگه داشته می‌شود.
- API/Agent فعلی C# و PHP مشکلات شناخته‌شده دارند؛ ممیزی اولیه فقط static review بوده و اصلاح نشده.
- Debian VM فعلی، محیط آزمایشی است. مشخصات و فرمان‌های ثبت‌شده در [Debian PoC Log](DEBIAN-POC.md) آمده‌اند.
- هیچ پنل جدیدی نصب نشده و هیچ مهاجرت Production انجام نشده است.
- ریپوی `cs2-kus-addons` تغییر نکرده و به‌عنوان زیرساخت محافظت‌شده باقی می‌ماند.

## سابقهٔ مستندات

جزئیات تاریخی نصب/بررسی Debian و گزارش‌های اولیه در [Debian PoC Log](DEBIAN-POC.md) حفظ شده‌اند. معماری فعلی و تصمیم‌های اصلاح‌شده در [Architecture](ARCHITECTURE.md)، [Decisions](DECISIONS.md) و [Roadmap](ROADMAP.md) معیار جاری‌اند؛ پیشنهادهای قدیمی که با تصمیم‌های جدید تعارض دارند، مبنای اجرا نیستند.

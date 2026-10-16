# FragCity — Repository Inventory

> این جدول نمای نگهداری و تصمیم معماری است. درج یک ریپو در جدول به معنی نصب، تأیید امنیت یا انتخاب نهایی آن نیست.

## 1. ریپوهای متعلق به FragCity

| ریپو | حوزه | وضعیت فعلی | کاربرد/تصمیم |
|---|---|---|---|
| [larnzs/FragCity-RawPHPbackend](https://github.com/larnzs/FragCity-RawPHPbackend) | سایت PHP، APIهای موجود و مستندات | موجود؛ ممیزی ایستا اولیه انجام شده | سایت فعلی فعلاً منبع لازم برای بررسی و API موجود است؛ قبل از اتکای Production باید یافته‌های امنیتی اصلاح شوند. README بازسازی شده و اسناد در `docs/` نگهداری می‌شوند. |
| [larnzs/FragCity-WebAPIClient](https://github.com/larnzs/FragCity-WebAPIClient) | C# Agent, API Client و منطق Instance Maker/RCON/DB Sync | نمونهٔ اولیه؛ بررسی ایستا انجام شده | فعلاً بازنویسی/مهاجرت کامل انجام نشود. بعد از انتخاب پنل مشخص می‌شود کدام مسئولیت‌ها باقی می‌مانند. |
| [larnzs/cs2-kus-addons](https://github.com/larnzs/cs2-kus-addons) | افزونه‌ها، کانفیگ‌ها و runtime بازی فعلی | زیرساخت موجود و مستقل | محافظت‌شده؛ هیچ تغییر یا اجرای اسکریپت تغییردهنده در این پروژه بدون درخواست جداگانه مجاز نیست. |

## 2. مسیر مرجع پیشنهادی PoC

| پروژه | GitHub | نقش | وضعیت انتخاب |
|---|---|---|---|
| Pterodactyl Panel | [pterodactyl/panel](https://github.com/pterodactyl/panel) | API و UI مرکزی برای مدیریت instanceها، منابع، کنسول و چرخهٔ عمر سرور | نامزد اول |
| Pterodactyl Wings | [pterodactyl/wings](https://github.com/pterodactyl/wings) | سرویس نود برای اجرای/مدیریت کانتینرها و سرورها | همراه مسیر Pterodactyl لازم است |
| Paymenter | [Paymenter/Paymenter](https://github.com/Paymenter/Paymenter) | حساب مشتری، محصولات، سفارشات، صورتحساب و integration با Pterodactyl | نامزد اول؛ مدل Wallet/expiry باید تست شود |

## 3. گزینه‌های مقایسه‌ای، نه انتخاب فعلی

| پروژه | GitHub | نقش | دلیل نگه‌داشتن در shortlist | شرط/محدودیت |
|---|---|---|---|---|
| GameAP | [gameap/gameap](https://github.com/gameap/gameap) | پنل مدیریت بازی، API و daemonها | مسیر B برای مقایسه، به‌خصوص API و SSO | اتصال واقعی به فروشگاه و provisioning باید تست شود |
| Pelican Panel | [pelican-dev/panel](https://github.com/pelican-dev/panel) | پنل جایگزین در خانواده Pterodactyl | مقایسهٔ UX و ویژگی‌ها | مجوز AGPL-3.0؛ بلوغ افزونه‌ها و سازگاری Paymenter باید تأیید شود |
| OVHcloud Game Panel | [ovh/game-panel](https://github.com/ovh/game-panel) | مدیریت سرورهای بازی با Docker، شامل CS2 | benchmark برای رابط/مدیریت | چندنودی، صورتحساب و عملیاتی‌بودن باید جداگانه اثبات شود |
| FOSSBilling | [FOSSBilling/FOSSBilling](https://github.com/FOSSBilling/FOSSBilling) | فروشگاه و صورتحساب | جایگزین Paymenter در صورت مشکل سازگاری | وضعیت beta و integration جامعه‌ای Pterodactyl باید بررسی شود |
| LinuxGSM | [GameServerManagers/LinuxGSM](https://github.com/GameServerManagers/LinuxGSM) | نصب/مدیریت CLI سرور بازی | ابزار فرعی یا سرورهای غیرمدیریت‌شده | جایگزین Billing/Panel نیست؛ نباید دو مدیر مستقل یک instance را هم‌زمان کنترل کنند |

## 4. پایش و ابزارهای کمکی در آینده

| پروژه | GitHub | کاربرد | وضعیت |
|---|---|---|---|
| Prometheus Node Exporter | [prometheus/node_exporter](https://github.com/prometheus/node_exporter) | شاخص‌های سیستم Linux | پس از راه‌اندازی PoC/نودها ارزیابی شود |
| Grafana | [grafana/grafana](https://github.com/grafana/grafana) | داشبورد و هشدار metrics | مرحله رشد؛ بررسی مجوز و استقرار لازم |
| Uptime Kuma | [louislam/uptime-kuma](https://github.com/louislam/uptime-kuma) | کنترل دسترس‌پذیری endpointها | گزینهٔ سبک برای پایش availability |

## 5. قانون پذیرش ریپو

برای هر ریپوی منتخب باید پیش از Production این اطلاعات ثبت شود:
1. URL دقیق و نسخه/release pinned.
2. مجوز و محدودیت استفاده/تغییر/توزیع.
3. وضعیت نگهداری، releaseها و security advisories.
4. الزامات استقرار و وابستگی‌ها.
5. روش backup/upgrade/rollback.
6. API رسمی و قابلیت‌های واقعاً آزمایش‌شده.
7. شکاف‌های باقی‌مانده و کد اختصاصی FragCity.

تا زمان ثبت این موارد، وضعیت ریپو در جدول «نامزد» باقی می‌ماند، نه «Production».

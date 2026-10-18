# FragCity — استراتژی Image قابل‌تکرار Debian و Node

**تصمیم:** VMDK فعلی را برای PoC ادامه می‌دهیم؛ آن را هنوز Golden Image قابل‌کپی روی همهٔ Nodeها نمی‌نامیم. هدف، رسیدن به یک قالب استاندارد Debian + Docker + Wings است که با اسکریپت‌های نسخه‌بندی‌شده آماده شود و برای هر ماشین تنظیمات اختصاصی همان ماشین اعمال شود.

## پاسخ مستقیم

- **VMDK فعلی قابل استفادهٔ مجدد است؟** برای پشتیبان‌گیری یا جابه‌جایی همان VM معمولاً بله، اگر همهٔ فایل‌های مرتبط VM و تنظیمات VMware همراه آن باشند. اما کپی‌کردن صرفاً فایل VMDK الزاماً کل ماشین را شامل نمی‌شود؛ تنظیمات سخت‌افزار مجازی، فایل VMX، firmware/NVRAM و snapshotها هم ممکن است اهمیت داشته باشند. برای انتقال یک VM کامل، پوشهٔ خاموش‌شدهٔ VM را نگه دار یا با ابزار VMware به OVF/OVA export/import کن. OVF/OVA هم باید روی hypervisor سازگار آزمایش شود.
- **آیا همین VM را می‌توان برای هر Node کلون کرد؟** نه به شکل فعلی. این VM شامل Panel، دیتابیس Panel، تنظیمات NGINX، هویت شبکه و در آینده Wings خواهد شد. تکثیر آن می‌تواند چند Panel/DB تکراری و هویت/secret متناقض ایجاد کند.
- **آیا می‌توانیم نصب لینوکس را هر بار تکرار نکنیم؟** بله. به‌جای کپی کورکورانهٔ VM فعلی، یک Node Golden Template نقش‌محور می‌سازیم و مراحل نصب را با اسکریپت‌های کوچک و قابل تکرار داخل همین GitHub repository خودکار می‌کنیم. این کار نیاز به حفظ‌کردن همهٔ فرمان‌های Linux را کم می‌کند.
- **آیا برای انتقال فقط تغییر اطلاعات دیتابیس کافی است؟** خیر. دیتابیس Panel مرکزی می‌ماند؛ Wings روی هر Node با آدرس Panel و پیکربندی/توکن اختصاصی همان Node کار می‌کند و برای اتصال عادی به دیتابیس Panel مستقیماً credential دیتابیس نمی‌خواهد.

## معماری مطلوب FragCity

### A. Panel مرکزی — یک نمونهٔ کنترل‌پلین

یک استقرار Panel با موارد زیر نگهداری می‌شود:
- کد Panel و نسخهٔ pin شده؛
- فایل env و APP_KEY خصوصی؛
- دیتابیس panel و backupهای سازگار آن؛
- NGINX/PHP-FPM/Redis و تنظیمات مرتبط؛
- backup/restore و مستندات ارتقا.

این مجموعه، الگوی قابل‌کلون‌کردن برای Nodeهای بازی نیست. برای انتقال Panel از backup/restore کنترل‌شدهٔ دیتابیس، فایل env، فایل‌ها و تنظیمات استفاده می‌کنیم، نه صرفاً کپی دیسک به چند ماشین.

### B. Node Golden Template — قالب تکرارپذیر سرورهای بازی

در قالب Node فقط چیزهای عمومی و غیرمحرمانه قرار می‌گیرند:
1. Debian 13 و بسته‌های پایهٔ موردنیاز؛
2. تنظیمات پایهٔ امنیت، زمان، SSH و diagnostic scripts؛
3. Docker Engine و سرویس آن؛
4. باینری Wings و فایل systemd، با نسخهٔ مشخص؛
5. مسیرها و فضای دادهٔ پیش‌فرض؛
6. اسکریپت bootstrap و health check که در Git نگهداری و نسخه‌بندی می‌شوند.

**نباید** در golden template موارد زیر باقی بمانند:
- فایل واقعی config.yml در مسیر etc/pterodactyl که حاوی توکن است؛
- توکن‌ها/API keys یا secretهای node؛
- کلیدهای میزبان SSH مشترک میان cloneها؛
- هویت machine-id یکسان، hostname و IP ثابت یکسان؛
- دادهٔ واقعی مشتریان، سرورهای بازی یا دیتابیس مرکزی Panel.

بعد از clone، اسکریپت provision باید قبل از اتصال Node جدید هویت ماشین را یکتا کند، hostname و شبکه را تنظیم/تأیید کند، و config/secret مخصوص Node را از Panel بسازد یا با روش امن وارد کند. تا وقتی این مراحل خودکار و آزمایش نشده‌اند، image را برای تولید چند Node استفاده نمی‌کنیم.

### C. داده‌های بازی و cs2-kus-addons — لایهٔ جدا

فایل‌های بازی نباید به‌عنوان بخشی از دیسک عمومی سیستم‌عامل برای هر Node کپی شوند. فایل‌های فعلی تقریباً 71 GiB در /home/steam باید حفظ شوند. در فصل ۴، روش امن reuse/migrate آن‌ها به ساختار مورد انتظار Pterodactyl، Docker volumes و backup را آزمایش می‌کنیم. پیش از آن، هیچ سرور CS2 جدیدی نساز چون ممکن است دانلود مجدد بازی فضای دیسک را پر کند.

## تفاوت backup، clone و golden image

| مورد | چه چیزی نگه می‌دارد؟ | کاربرد |
|---|---|---|
| Snapshot VMware | وضعیت برگشت سریع همان VM در زمان مشخص؛ جای backup مستقل را نمی‌گیرد | قبل از تغییرات مهم |
| Backup کامل VM یا export OVF/OVA | بسته/پوشهٔ VM به همراه دیسک‌ها و تنظیمات پشتیبانی‌شده | جابه‌جایی یا disaster recovery همان VM |
| Clone فعلی | کپی وضعیت و هویت فعلی، از جمله تنظیمات و احتمالاً secretها | برای ساخت Nodeهای جدید بدون پاک‌سازی و provision امن، ممنوع |
| Node Golden Template | سیستم‌عامل و اجزای عمومی، بدون هویت/توکن/دادهٔ هر Node | ساخت تکرارپذیر ماشین‌های جدید |
| Bootstrap scripts در Git | تنظیمات و مراحل قابل بازتولید، بدون secrets | نصب/تنظیم یکنواخت؛ مرجع اصلی برای تغییرات |

## فرآیند مقصد: «کلون کن، config امن اعمال کن، بررسی کن»

بعد از اینکه Wings روی Node فعلی کار کرد، مسیر نهایی را این‌گونه پیاده می‌کنیم:

1. **پایدارسازی Node فعلی:** سلامت Docker، Wings و یک Node آزمایشی را تأیید کن؛ هنوز CS2 را تا طراحی فصل ۴ اجرا نکن.
2. **مستندسازی و backup:** نسخه‌ها، package list، سرویس‌ها و تنظیمات غیرمحرمانه را ثبت کن؛ backup Panel/DB و فایل‌های ضروری را جدا بساز.
3. **ساخت Golden Template نقش‌محور:** ترجیحاً از Debian تازهٔ تمیز، فقط اجزای مشترک Node را نصب کن؛ Panel و دیتابیس مرکزی را در این قالب قرار نده.
4. **ساخت ابزار provision:** اسکریپت‌های نسخه‌بندی‌شده در GitHub، با ورودی‌هایی مثل hostname، IP و روش شبکه، node name و تنظیمات storage؛ secrets فقط از Panel یا محل امن خارج از Git وارد شوند.
5. **Clone/Import برای محیط مقصد:** ترجیحاً VMware template/OVF برای VMware؛ روی hypervisor یا bare metal متفاوت باید روش نصب/import جدا آزمایش شود.
6. **First boot:** hostname و machine identity/SSH host keys یکتا، IP و route قابل دسترس، Docker فعال، فضای دیسک تأییدشده و زمان سیستم درست شود.
7. **ساخت Node در Panel مرکزی:** در Admin → Nodes، Node جدا بساز؛ allocationها و پورت‌ها را با شبکهٔ واقعی هماهنگ کن.
8. **پیکربندی مختص همان Node:** config Wings را از پنل تولید/دریافت کن؛ token را وارد Git، README، log عمومی یا چت نکن.
9. **Health check:** تأیید Docker، Wings و Online شدن Node در Panel؛ تست اتصال و allocation فقط در LAN.
10. **جداگانه انتقال دادهٔ بازی:** بازی/افزونه‌ها بر اساس طراحی و backup فصل ۴ منتقل شوند، نه با کپی تصادفی whole-disk.

## سیاست استفاده برای محیط‌های مختلف

- **همین VM:** محیط PoC/آموزشی فعلی؛ در این مرحله Panel و نود آینده ممکن است موقتاً هم‌مکان باشند.
- **ماشین‌های Node جدید:** Node template و bootstrap اختصاصی؛ به Panel مرکزی متصل می‌شوند.
- **محیط Production:** قبل از عرضه، Panel/Node، secrets، backup/restore، firewall، HTTPS، منابع و rollback باید تست شوند.
- **سیستم فیزیکی بدون VMware:** VMDK مستقیماً همانند installer روی کامپیوتر نصب نمی‌شود؛ برای bare metal باید Debian را نصب و bootstrap scripts را اجرا کرد یا راهکار imaging/provisioning مخصوص آن سخت‌افزار طراحی کرد.

## حداقل دانش لازم برای کاربر

قرار نیست همهٔ Bash را حفظ کنی. هر اسکریپت باید در GitHub:
- توضیح هدف و پیش‌نیاز داشته باشد؛
- قبل از تغییر، وضعیت را بررسی کند و اگر دادهٔ قبلی ممکن است overwrite شود متوقف شود؛
- امن، قابل اجرای مجدد، نسخه‌بندی‌شده و دارای خروجی واضح باشد؛
- secret را چاپ یا commit نکند؛
- در پایان health check بدهد و نتیجهٔ واقعی به راهنمای مرحله‌ای اضافه شود.

بااین‌حال چهار مفهوم باید قابل تشخیص بمانند: IP/hostname، backup و restore، secret/config، و تفاوت Panel با Node. این‌ها برای نگه‌داری امن سرویس ضروری‌اند.

## وضعیت ثبت‌شده در ۱۸ اکتبر ۲۰۲۶

- Debian 13/Trixie و معماری amd64 روی VMware.
- Panel در LAN فعال و ورود مدیر موفق است.
- آخرین تست endpointها: Docker GPG URL و آدرس dists/trixie/InRelease هر دو HTTP=200.
- خروجی dpkg-query نشان داد docker-ce، docker-ce-cli، containerd.io، docker-buildx-plugin و docker-compose-plugin نصب هستند.
- **Docker نصب و تأیید شد:** Docker 29.9.1/build dfc520d، سرویس ACTIVE، docker info موفق و hello-world پیام موفقیت داد. Wings و Node همچنان Pending هستند.
- /home/steam حدود 71 GiB فایل CS2/KUS دارد؛ حفظ شود.
- تمام زیرساخت فعلاً LAN-only است؛ دامنه، HTTPS و دسترسی عمومی به فصل شبکه موکول‌اند.

## منابع رسمی

- [Docker Engine روی Debian](https://docs.docker.com/engine/install/debian/)
- [Pterodactyl — نصب Wings](https://docs.pterodactyl.io/v2/wings/installing)
- [Pterodactyl — تنظیمات حساس Wings](https://docs.pterodactyl.io/v2/wings/configuration)
- [Pterodactyl — ایجاد Node و Allocation](https://docs.pterodactyl.io/v2/guides/configuration/add-node)
- [Broadcom — export فرمت OVF/OVA](https://knowledge.broadcom.com/external/article/373304/exporting-a-virtual-machine-as-a-single.html)

# FragCity — معماری چندنمونه‌ای CS2 و اشتراک فایل‌های بازی

> تاریخ بررسی: ۱۶ اکتبر ۲۰۲۶  
> وضعیت: بررسی معماری و ریپوهای کاندید؛ هنوز روی Debian آزمایش یا برای Production تأیید نشده است.

## 1. هدف

هدف این سند مشخص‌کردن راه مدیریت چند سرور مستقل CS2 روی یک Node لینوکسی است؛ به‌نحوی که هر مشتری سرور، تنظیمات، فایل‌های قابل‌نوشتن، پورت‌ها و چرخهٔ عمر خودش را داشته باشد، ولی فایل‌های حجیم و مشترک بازی در صورت امن‌بودن قابل استفادهٔ اشتراکی باشند.

مسیر مرجع PoC در FragCity همچنان **Pterodactyl Panel + Wings + Paymenter** است. این سند دربارهٔ مدل ذخیره‌سازی و اجرای CS2 در این معماری است و تصمیم نهایی Production محسوب نمی‌شود.

## 2. یک Panel، یک Node، چند Server

در Pterodactyl معمولاً یک Panel مرکزی چند Node را مدیریت می‌کند. روی هر Node، Wings سرورهای ثبت‌شده را اجرا و مدیریت می‌کند. هر سرور یک رکورد و هویت مستقل در Panel، allocationها و محدودیت منابع، محیط اجرایی و Volume دادهٔ خودش دارد.

- لازم نیست برای هر CS2 instance یک نصب جداگانه از خود Pterodactyl Panel داشته باشیم.
- می‌توان هر instance را به حساب/مشتری مناسب واگذار کرد و مجوزهای همان سرور را در Panel مدیریت کرد.
- «پنل جدا» در مدل معمول یعنی هر مشتری فقط سرورهای خودش را در همان Panel مرکزی می‌بیند؛ نه اینکه برای هر instance یک پنل مستقل نصب شود.
- هر سرور باید پورت‌های موردنیاز خودش را بگیرد. پورت بازی و پورت‌های جانبی نباید با سرورهای دیگر تداخل کنند.
- CPU، RAM، فضای دیسک و دیگر منابع باید برای هر سرور تعریف و در عمل تست شوند؛ ظرفیت Node فقط از تعداد سرورها استنتاج نمی‌شود.

### سرورهای موجود به‌صورت خودکار وارد Panel نمی‌شوند

داشتن چند process یا چند پوشهٔ instance روی Debian به این معنی نیست که Panel آن‌ها را خودکار شناسایی و مالکیتشان را ثبت می‌کند. برای هر سرور باید یک Server entry ایجاد شود، سپس فایل‌ها، مسیرهای موردنیاز، دستور Startup، متغیرهای محیطی، پورت‌ها، منابع و مالکیت داده با دقت منتقل و تطبیق داده شوند.

مهاجرت مستقیم پوشهٔ موجود بدون نقشهٔ انتقال، Backup و آزمون مجزا توصیه نمی‌شود. ابتدا یک سرور آزمایشی ساخته شود؛ سپس دومین instance برای آزمون جداسازی اضافه شود.

## 3. مدل‌های ذخیره‌سازی

### مدل A — کپی کامل برای هر سرور

هر instance نسخهٔ مستقل خودش از فایل‌های بازی، افزونه‌ها، cfgها و سایر داده‌ها را دارد.

**مزیت‌ها:** جداسازی روشن، بازیابی و حذف ساده‌تر، کمترین وابستگی بین سرورها و رفتار قابل پیش‌بینی برای Update و Reinstall.

**هزینه‌ها:** فضای دیسک بیشتر و دریافت/اعتبارسنجی مکرر فایل‌های مشترک.

**پیشنهاد:** مدل شروع PoC باشد، چون جداسازی و رفتار پایه را بدون پیچیدگی اشتراک فایل‌ها ثابت می‌کند.

### مدل B — فایل‌های پایهٔ مشترک، داده‌های متغیر مستقل

یک نصب مرکزی بازی نگهداری می‌شود و فایل‌های مشترک و نسبتاً تغییرناپذیر می‌توانند بین سرورها به اشتراک گذاشته شوند؛ در مقابل، cfgها، تنظیمات افزونه‌ها، داده‌های runtime و هر فایل قابل‌نوشتنی که ممکن است بین مشتریان متفاوت باشد باید مستقل بماند.

این جداسازی باید بر اساس نوع فایل و رفتار واقعی CS2 انجام شود؛ **اشتراک کل پوشهٔ game/ به‌صورت قابل‌نوشتن توصیه نمی‌شود.** اگر چند سرور یک فایل تنظیمات، افزونه یا log را تغییر دهند، ممکن است سرورها روی یکدیگر اثر بگذارند. اشتراک Read-only هم تنها برای مسیرهایی مناسب است که واقعاً نیازی به نوشتن ندارند.

### مدل C — بهینه‌سازی فایل‌های VPK در Pterodactyl

ریپوی [K4ryuu/CS2-Egg — VPK Sync](https://github.com/K4ryuu/CS2-Egg/blob/main/docs/features/vpk-sync.md) یک گزینهٔ مستقیم برای بررسی در معماری Pterodactyl است. مستندات این پروژه یک نصب مرکزی CS2 را با SteamCMD به‌روزرسانی می‌کند و فایل‌های VPK را به Volumeهای سرورهای ثبت‌شده می‌رساند؛ روش ارائه‌شده می‌تواند symlink، hardlink یا copy باشد و daemon برای سرورهای جدید نیز در نظر گرفته شده است.

تفاوت مهم: این راهکار بر **همگام‌سازی فایل‌های VPK** تمرکز دارد؛ نباید بدون بازرسی فرض کنیم که تمام فایل‌ها، افزونه‌ها و مسیرهای قابل‌نوشتن را می‌توان به همان شکل مشترک کرد.

- symlink می‌تواند استفادهٔ دیسک را کاهش دهد، اما مقصد آن باید از داخل container در دسترس باشد و فایل مشترک برای سرورهای متکی به آن عملاً مشترک است.
- hardlink نیازهای filesystem خود را دارد؛ طبق مستندات ریپو، مبدأ و Volumeها باید روی یک filesystem باشند. quota پنل هم الزاماً صرفه‌جویی واقعی دیسک را بازتاب نمی‌دهد.
- copy جداسازی نوشتن را ساده‌تر می‌کند، ولی صرفه‌جویی فضای دیسک آن کمتر است.
- به‌روزرسانی central install ممکن است روی تمام instanceهای متکی به آن اثر بگذارد؛ قفل، ترتیب Update، سلامت فایل‌ها و Rollback باید آزموده شود.
- نصب‌کننده و daemon با دسترسی سطح میزبان کار می‌کنند؛ سورس اسکریپت، مجوزها، سرویس systemd، cron، مسیرها و رفتار Self-Update باید پیش از اجرای آزمایشی بازبینی شود.

اعداد صرفه‌جویی فضای دیسک در README پروژه تخمین خود آن پروژه‌اند و نباید به‌عنوان نتیجهٔ قطعی روی Node ما تلقی شوند.

### مدل D — mount سفارشی در container

Pterodactyl از Mountهای اضافی پشتیبانی می‌کند، اما وجود یک Mount به معنی دسترسی معمول از File Manager یا SFTP سرور نیست. مسیر host، حالت Read-only و مجوزها باید جداگانه مدیریت شوند. این مدل می‌تواند برای داده‌های مشترک مفید باشد، ولی به‌خودی‌خود همهٔ ملاحظات lifecycle، Update و isolation را حل نمی‌کند.

## 4. گزینه‌ها بر اساس سیستم‌عامل

این تفکیک مهم است: ابزار لینوکسی که shared-base را پشتیبانی می‌کند، الزاماً روی Windows native اجرا نمی‌شود؛ و نرم‌افزار Windows که چند process را مدیریت می‌کند، لزوماً فایل‌های بازی را مشترک نمی‌کند یا tenant isolation لازم برای SaaS را فراهم نمی‌کند.

### 4.1 Linux native — گزینه‌های اصلی برای Nodeهای میزبانی

| پروژه | ایده و کاربرد | پیشنهاد در FragCity | موانع/شرط آزمون |
|---|---|---|---|
| [Pterodactyl Panel + Wings](https://github.com/pterodactyl/panel) | پنل مرکزی مدیریت Server entityها، دسترسی مشتری، allocations، منابع و lifecycle روی Linux/Docker | پایهٔ اصلی PoC مدیریت مشتریان | نصب و نسخهٔ patched، جداسازی مشتری، backup/restore، Egg و چرخهٔ عمر CS2 باید در Linux واقعی تست شوند. |
| [K4ryuu/CS2-Egg VPK Sync](https://github.com/K4ryuu/CS2-Egg/blob/main/docs/features/vpk-sync.md) | همگام‌سازی VPK از نصب مرکزی به Volumeهای سرورهای Pterodactyl/Pelican؛ symlink/hardlink/copy و daemon | اولین گزینهٔ shared storage بعد از قبولی baseline | installer/daemon دارای سطح دسترسی میزبان است؛ مسیرها، مجوزها، lock، Self-Update، failure recovery و rollback باید بررسی شوند. |
| [GamesTwoLife/cs2-multiserver](https://github.com/GamesTwoLife/cs2-multiserver) | Bash manager برای instanceهای مستقل با base install مشترک، پورت‌دهی، tmux، systemd/cron و addon helper | مرجع معماری و مقایسهٔ standalone | این ابزار خودش Pterodactyl Server entry، tenant permissions یا billing ایجاد نمی‌کند؛ دو manager نباید هم‌زمان یک instance را کنترل کنند. |
| [Trimeon/cs2-install](https://github.com/Trimeon/cs2-install) | یک `cs2-master` و instanceهای جدا که فایل‌های پایه را لینک می‌کنند؛ Docker اختیاری | گزینهٔ standalone برای مقایسه با MSM | بررسی update propagation، directory layout، permissions، addons و backup/restore لازم است. |
| [Auto-Tournament/cs2-server-manager](https://github.com/Auto-Tournament/cs2-server-manager) | اجرای چند سرور از نصب مشترک و کنترل زمان Update | منبع فنی برای الگوهای shared files و update | README مجوز PolyForm Noncommercial را نشان می‌دهد؛ مجوز/شرایط تجاری باید پیش از هر استفادهٔ تجاری حل شوند. |
| [1zc/CS2-Pterodactyl](https://github.com/1zc/CS2-Pterodactyl) | Docker image و Egg برای اجرای CS2 در Pterodactyl | گزینهٔ baseline برای مقایسهٔ Egg | خودِ Egg راهکار کامل shared-storage نیست؛ تطابق نسخه و Startup parameters بررسی شود. |

**WSL2:** README پروژهٔ GamesTwoLife می‌گوید Bash تحت Linux یا WSL2 روی Windows قابل استفاده است. این مسیر را فقط در مسیر compatibility جداگانه تست می‌کنیم؛ قبولی WSL2 مساوی اجرای native Windows یا تأیید Linux production نیست.

### 4.2 Windows native — ابزارهای مدیریت روی Windows

| پروژه | قابلیت ادعاشده/قابل بررسی | پیشنهاد در FragCity | موانع/شرط آزمون |
|---|---|---|---|
| [WindowsGSH](https://github.com/WindowsGSH/WindowsGSH) + [CounterStrike2 module](https://github.com/WindowsGSH/WindowsGSH.CounterStrike2) | اپلیکیشن دسکتاپ Windows برای نصب، تنظیم، اجرا، query و Backup سرورهای بازی | گزینهٔ جدی برای آزمایش native Windows | ماژول CS2 در README خودش `NEEDS LIVE TEST` دارد؛ RCON و مدیریت addonها محدودیت اعلام‌شده دارند. مجوز WindowsGSH منبع‌در‌دسترس است، OSI-approved نیست، و طبق README استفادهٔ تجاری به اجازهٔ کتبی قبلی نیاز دارد؛ بنابراین استفادهٔ تجاری تا رفع blocker مجاز فرض نشود. |
| [fpaezf/Game-Server-Admin](https://github.com/fpaezf/Game-Server-Admin) | GUI با bulk install/update/start/stop، watchdog و خواندن console | گزینهٔ مقایسه‌ای روی Windows | README آن را Work in Progress معرفی می‌کند. وضعیت مجوز، release، محافظت secretها، اجرای بدون UI، جداسازی pathها و رفتار crash باید تست شود. |
| [fpaezf/CS2-server-manager](https://github.com/fpaezf/CS2-server-manager) | ابزار قدیمی مدیریت CS2 روی Windows | فقط منبع تاریخی، نه انتخاب اول تست | README صراحتاً Deprecated شده و به Game-Server-Admin ارجاع می‌دهد؛ برای تست اصلی نسخهٔ جدیدتر ارجح است. |
| **FragCity Windows deployment workflow** | خط مبنای مقایسه برای install/update، حفظ cfg و رفتار instance فعلی | baseline آزمایشی داخلی | تست باید در task مجزا و با copy/snapshot کنترل‌شده انجام شود. هیچ تغییر در `cs2-kus-addons` در این پروژه/سند انجام نمی‌شود. |

**نکته:** مدیریت چند process در Windows با داشتن فایل‌های مشترک برابر نیست. برای هر ابزار باید ثابت کنیم نصب سرورها مستقل/مشترک چگونه پیاده شده، فایل‌های writable چطور جداسازی می‌شوند، Update به چه پوشه‌ای می‌نویسد و حذف یک instance چه اثری روی بقیه دارد.

### 4.3 Cross-platform — نیازمند اثبات روی هر دو OS

| پروژه | توضیح | پیشنهاد در FragCity | شرط |
|---|---|---|---|
| [maxdeepfield/cs2-server-cli](https://github.com/maxdeepfield/cs2-server-cli) | README ادعای پشتیبانی Windows، Linux و macOS، و multi-server، config، backup/restore و مدیریت plugin را دارد | نامزد آزمایشی مشترک | باید از source/build اثبات شود که هر فرمان واقعاً روی Windows و Linux کار می‌کند؛ shared base تأیید نشده و نباید فرض شود. |
| [GamesTwoLife/cs2-multiserver via WSL2](https://github.com/GamesTwoLife/cs2-multiserver) | قابلیت اجرای Bash از طریق WSL2 را مستند کرده است | آزمایش سازگاری اختیاری | WSL2 یک runtime لینوکسی روی Windows است؛ نتیجه به‌عنوان پشتیبانی native Windows ثبت نشود. |

### 4.4 خلاصهٔ بررسی فعلی

این لیست بر پایهٔ README/مستندات عمومی و بررسی ایستای محدود است؛ اجرای کد، ممیزی کامل امنیتی، تست واقعی CS2 و قبولی هر معیار هنوز ثبت نشده است. تمام نامزدها و statusها در [Testing Bench](TESTING-BENCH.md) نگهداری می‌شوند؛ فرضیه‌ها و پیشنهادهای موقت در [Probably and Suggestions](PROBABLY-AND-SUGGESTIONS.md) قرار دارند.

## 5. معماری پیشنهادی مرحله‌ای برای FragCity

### مرحلهٔ اول — تثبیت اجرای مستقل

1. یک Debian Node آزمایشی با Panel/Wings و Egg منتخب آماده شود.
2. فقط یک CS2 instance نصب و Start/Stop/Restart، Console، File Manager، SFTP، allocation، محدودیت RAM/CPU، Backup و Restore آن آزموده شود.
3. یک instance دوم ساخته شود و تفاوت Server ID، Volume، پورت‌ها و مجوزهای مشتری تأیید شود.
4. ابتدا نسخهٔ کامل و مستقل فایل‌ها استفاده شود تا baseline قابل اعتماد به‌دست آید.

### مرحلهٔ دوم — ارزیابی اشتراک فایل

1. از سرور آزمایشی و تنظیمات آن Backup گرفته شود.
2. VPK Sync پروژهٔ K4ryuu در محیط آزمایشی جدا بررسی شود؛ قبل از نصب، اسکریپت‌ها و مجوزهای root، daemon، cron و مسیرهای فایل خوانده شوند.
3. ابتدا فقط یک فایل/گروه فایل مناسب تست شود؛ سپس اندازه‌گیری مصرف واقعی دیسک و صحت اجرا انجام شود.
4. Update مرکزی فقط با قفل و در پنجرهٔ کنترل‌شده اجرا شود؛ هیچ سروری نباید فایل‌های نیمه‌به‌روزشده را ببیند.
5. آزمون شود که cfg، افزونه‌ها، تنظیمات مشتری، logها و فایل‌های سفارشی واقعاً مجزا می‌مانند.
6. رفتار حذف، Reinstall، Upgrade و Restore یک سرور بررسی شود تا فایل‌های مشترک ناخواسته پاک یا جایگزین نشوند.

### مرحلهٔ سوم — تصمیم بعد از دادهٔ واقعی

اگر صرفه‌جویی دیسک قابل‌توجه و جداسازی فایل‌ها پایدار بود، shared VPK می‌تواند بخشی از استاندارد Nodeهای FragCity شود. در غیر این صورت، کپی مستقل برای هر instance حفظ شود. استفاده از symlink کامل به base install تنها بعد از اثبات سازگاری همهٔ مسیرهای موردنیاز CS2، افزونه‌ها و ابزارهای Backup مجاز است.

## 6. چک‌لیست پذیرش فنی

| ID | آزمون | معیار قبولی |
|---|---|---|
| MI-01 | دو instance با cfg/افزونه‌های متفاوت | تغییرات هر مشتری فقط روی سرور خودش اثر بگذارد. |
| MI-02 | جداسازی Panel، فایل‌ها و SFTP | مشتری A نتواند فایل، کنسول، Backup یا اطلاعات مشتری B را مشاهده یا تغییر دهد. |
| MI-03 | تخصیص پورت | پورت بازی و پورت‌های جانبی همهٔ instanceها یکتا و قابل اتصال باشند. |
| MI-04 | اشتراک فایل | فقط فایل‌های تأییدشده مشترک باشند؛ مسیرهای writable موردنیاز از هم جدا بمانند. |
| MI-05 | Update مرکزی | قفل فعال باشد، سرور به فایل نیمه‌نوشته‌شده دسترسی پیدا نکند و نتیجه قابل تطبیق باشد. |
| MI-06 | Update افزونه و cfg | به‌روزرسانی یا خاموش‌کردن افزونه در یک instance به بقیه سرایت نکند، مگر آنکه عمداً سیاست مشترک تعریف شده باشد. |
| MI-07 | Reinstall/Delete یک سرور | حذف/نصب مجدد یک instance، base install یا داده‌های instanceهای دیگر را حذف نکند. |
| MI-08 | Backup/Restore | restore یک سرور، داده یا لینک‌های اشتراکی را اشتباه روی سرور دیگر ننویسد. |
| MI-09 | خطای همگام‌سازی | اگر daemon یا Update شکست خورد، سرور با فایل ناقص شروع نشود؛ هشدار و مسیر برگشت مشخص باشد. |
| MI-10 | اندازه‌گیری منابع | مصرف واقعی disk، RAM، CPU و زمان Start روی ۱ و سپس ۲ و ۵ instance ثبت شود. |

## 7. تصمیم ثبت‌شده فعلی

- **انتخاب فعلی برای مدیریت مشتریان و چرخهٔ عمر:** Pterodactyl Panel + Wings؛ هنوز در PoC.
- **اولین نامزد برای اشتراک فایل در همان معماری:** K4ryuu/CS2-Egg VPK Sync.
- **مراجع معماری چند instance با shared base:** GamesTwoLife/cs2-multiserver و Trimeon/cs2-install.
- **اقدام فعلی:** ابتدا یک CS2 سرور مستقل، سپس instance دوم؛ shared VPK تا قبل از قبولی آزمون‌ها فعال نشود.
- **ممنوعیت عملیاتی:** نصب/Update/اجرای ابزار مدیریت دوم روی یک instance که از قبل تحت کنترل Pterodactyl است، مگر اینکه مرز مسئولیت‌ها و جلوگیری از تداخل ثابت شده باشد.

## منابع

- [K4ryuu/CS2-Egg — VPK Sync](https://github.com/K4ryuu/CS2-Egg/blob/main/docs/features/vpk-sync.md)
- [GamesTwoLife/cs2-multiserver](https://github.com/GamesTwoLife/cs2-multiserver)
- [dasisdormax/cs2-multiserver](https://github.com/dasisdormax/cs2-multiserver)
- [Trimeon/cs2-install](https://github.com/Trimeon/cs2-install)
- [Auto-Tournament/cs2-server-manager](https://github.com/Auto-Tournament/cs2-server-manager)
- [1zc/CS2-Pterodactyl](https://github.com/1zc/CS2-Pterodactyl)
- [Pterodactyl issue: shared CS2 files and server.cfg behavior](https://github.com/1zc/CS2-Pterodactyl/issues/49)

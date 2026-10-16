# FragCity — معماری چندنمونه‌ای CS2 و اشتراک فایل‌های بازی

> تاریخ بررسی: ۱۶ اکتبر ۲۰۲۶  
> وضعیت: جهت معماری Shared Base برای PoC تأیید شده؛ سازوکار فنی و اجرای روی Debian هنوز تست یا برای Production تأیید نشده است.

## 1. هدف

هدف، مدیریت چند Instance مستقل CS2 روی یک Node لینوکسی با یک Base Game مشترک است. هر Instance باید تنظیمات، افزونه‌ها، فایل‌های قابل‌نوشتن، پورت‌ها، دسترسی‌ها و چرخهٔ عمر خودش را داشته باشد؛ فایل‌های اصلی و ثابت CS2 بین Instanceها مشترک می‌مانند. این جهت معماری برای PoC پذیرفته شده، اما روش mount/link و سازگاری اجرایی هنوز باید آزمایش شود.

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

## 3. مدل ذخیره‌سازی هدف FragCity

### 3.1 Base Game مشترک

روی هر Node یک نصب مرکزی از CS2 نگهداری می‌شود. SteamCMD فقط همین Base مرکزی را نصب یا Update می‌کند؛ اسکریپت نصب/Deploy هر Instance نباید SteamCMD اجرا کند و نباید برای هر سرور کل بازی را مجدداً دریافت کند.

فایل‌های پایهٔ بازی که بین Instanceها مشترک‌اند، از دید Instanceها غیرقابل‌تغییر در نظر گرفته می‌شوند. فقط عملیات هماهنگ‌شدهٔ مرکزی اجازه دارد نسخهٔ Base را عوض کند. چگونگی در دسترس قرار دادن این فایل‌ها داخل هر container/Volume هنوز انتخاب نشده است؛ بنابراین در این مرحله symlink، hardlink، bind mount، overlay یا copy را پیاده‌سازی منتخب تلقی نمی‌کنیم.

### 3.2 مسیرهای اختصاصی هر Instance

| مسیر/داده | قاعدهٔ فعلی |
|---|---|
| game/csgo/addons/ | محتوای مود/افزونه برای هر Instance جدا؛ تغییر یک سرور نباید روی دیگری اثر بگذارد. |
| game/csgo/cfg/ | تنظیمات هر Instance مستقل است. |
| فایل‌های متنی مرتبط با GameMode | هر Instance نسخهٔ خودش را دارد؛ نام و مسیر دقیق فایل‌ها در مرحلهٔ نقشه‌برداری فنی ثبت می‌شود. |
| سایر فایل‌های اصلی Base Game | مشترک و از دید Instanceها غیرقابل‌تغییر؛ تغییرشان فقط از مسیر Update مرکزی انجام می‌شود. |
| log، runtime و هر فایل دیگری که خود سرور می‌نویسد | باید بر اساس رفتار واقعی CS2 شناسایی شود و در صورت نوشتنی/متحول بودن، در فضای مستقل Instance قرار گیرد. |

اشتراک نباید باعث شود cfg، افزونه‌ها، فایل‌های GameMode یا دادهٔ runtime یک مشتری در اختیار مشتری دیگر قرار گیرد. اسم پوشه به‌تنهایی برای تشخیص قابل‌نوشتن بودن کافی نیست؛ مسیرها باید در PoC بررسی شوند.

### 3.3 منبع payload و اسکریپت Deploy در Pterodactyl

ریپوی larnzs/cs2-kus-addons منبع مدیریت‌شدهٔ FragCity باقی می‌ماند و شامل بستهٔ فعلی game/csgo است. با این حال، وجود کل بسته در ریپو به این معنی نیست که باید کل پوشهٔ بازی در هر Instance کپی شود یا SteamCMD از داخل اسکریپت Instance اجرا شود.

بعداً یک اسکریپت Deploy متصل به روش مدیریت Pterodactyl ساخته می‌شود که فقط پوشهٔ تعیین‌شدهٔ مود/افزونه را به فضای اختصاصی Instance منتقل کند؛ این اسکریپت **SteamCMD را اجرا نمی‌کند، Base Game را نصب/Update نمی‌کند و سرور را Start نمی‌کند**. cfg و فایل‌های GameMode هم باید اختصاصی بمانند، اما سازوکار توزیع/تنظیم آن‌ها هنوز در این مرحله تعیین نشده است. فایل‌های ریپوی خودمان مرجع اصلی payload هستند؛ کد نصب یا افزونه‌های bundleشده در پروژه‌های چند-Instance ثالث به‌عنوان مرجع FragCity کپی یا تحمیل نمی‌شوند.

### 3.4 سیاست Update برای Base مشترک

به‌محض تشخیص انتشار Update جدید CS2، چرخهٔ هماهنگ‌شده باید خودکار آغاز شود و این ترتیب را اجرا کند:

1. جلوی شروع/راه‌اندازی مجدد Instanceهای متکی به Base را بگیرد و همهٔ آن‌ها را متوقف کند.
2. SteamCMD را یک‌بار روی نصب مرکزی Base اجرا کند؛ Update جداگانهٔ بازی برای هر Instance مجاز نیست.
3. نتیجهٔ Update و سلامت فایل‌های Base را بررسی کند و قفل/حالت Maintenance را فقط پس از پایان امن عملیات بردارد.
4. پس از موفقیت، Instanceهایی را که پیش از Update در حال اجرا بوده‌اند دوباره راه‌اندازی کند؛ Instanceهایی که از قبل خاموش بوده‌اند خاموش بمانند.
5. اگر Update شکست خورد یا نیمه‌کاره ماند، هیچ Instanceای روی Base ناقص Start نشود و مسیر بازیابی/rollback اجرا شود.

سیاست پذیرفته‌شده این است که همهٔ سرورها برای Update Base مشترک متوقف شوند. روش فنی تشخیص انتشار Update، پیاده‌سازی lock، گزارش خطا و rollback باید در فاز اجرایی طراحی و آزمایش شود. برای این تصمیم، Update به‌صورت زمان‌بندی‌شده یا صرفاً با انتخاب دستی تعریف نشده؛ trigger موردنظر انتشار Update جدید است.

### 3.5 گزینه‌های فنی برای مقایسه، نه تصمیم منتخب

#### K4ryuu/CS2-Egg VPK Sync

ریپوی [K4ryuu/CS2-Egg — VPK Sync](https://github.com/K4ryuu/CS2-Egg/blob/main/docs/features/vpk-sync.md) می‌تواند در صورت نیاز به‌عنوان گزینهٔ مقایسه‌ای بررسی شود. مستندات آن Sync فایل‌های VPK از نصب مرکزی به Volumeهای سرورهای Pterodactyl/Pelican را با روش‌هایی مانند symlink، hardlink یا copy توصیف می‌کند. این گزینه انتخاب فعلی FragCity نیست و الزام معماری هم محسوب نمی‌شود.

- symlink می‌تواند مصرف دیسک را کاهش دهد، ولی مقصد باید داخل container قابل دسترس باشد و فایل مقصد میان مصرف‌کنندگان مشترک است.
- hardlink به محدودیت‌های filesystem وابسته است؛ سهمیهٔ پنل لزوماً مصرف فیزیکی واقعی را نشان نمی‌دهد.
- copy جداسازی نوشتن را آسان‌تر می‌کند، اما صرفه‌جویی دیسکی کمتری دارد.
- Update مرکزی باید با توقف Instanceها، lock، اعتبارسنجی و مسیر بازیابی هماهنگ شود.
- نصب‌کننده/daemon با دسترسی میزبان باید قبل از استفاده از نظر مجوزها، سرویس‌ها، cron، self-update و عملیات مخرب بازبینی شود.

#### ابزارهای standalone مدیریت چند Instance

پروژه‌هایی مثل [GamesTwoLife/cs2-multiserver](https://github.com/GamesTwoLife/cs2-multiserver) و [Trimeon/cs2-install](https://github.com/Trimeon/cs2-install) فقط به‌عنوان مرجع فنی الگوهای shared base بررسی می‌شوند. آن‌ها به‌خودی خود Server entity، مالکیت مشتری، Billing یا مجوزهای Pterodactyl را فراهم نمی‌کنند. یک ابزار standalone نباید هم‌زمان با Wings مالک چرخهٔ عمر یک Instance باشد، مگر ادغام و مرز مسئولیت‌ها صریحاً طراحی و آزموده شده باشد.

### 3.6 مدل fallback

اگر روش اشتراک Base در PoC از نظر امنیت، پایداری یا سازگاری شکست بخورد، کپی مستقل فایل‌های بازی می‌تواند fallback فنی باشد؛ اما این بازگشت به مدل پیش‌فرض محسوب نمی‌شود و نیاز به ثبت دلیل و تصمیم جداگانه دارد.

## 4. گزینه‌ها بر اساس سیستم‌عامل

این تفکیک مهم است: ابزار لینوکسی که shared-base را پشتیبانی می‌کند، الزاماً روی Windows native اجرا نمی‌شود؛ و نرم‌افزار Windows که چند process را مدیریت می‌کند، لزوماً فایل‌های بازی را مشترک نمی‌کند یا tenant isolation لازم برای SaaS را فراهم نمی‌کند.

### 4.1 Linux native — گزینه‌های اصلی برای Nodeهای میزبانی

| پروژه | ایده و کاربرد | پیشنهاد در FragCity | موانع/شرط آزمون |
|---|---|---|---|
| [Pterodactyl Panel + Wings](https://github.com/pterodactyl/panel) | پنل مرکزی مدیریت Server entityها، دسترسی مشتری، allocations، منابع و lifecycle روی Linux/Docker | پایهٔ اصلی PoC مدیریت مشتریان | نصب و نسخهٔ patched، جداسازی مشتری، backup/restore، Egg و چرخهٔ عمر CS2 باید در Linux واقعی تست شوند. |
| [K4ryuu/CS2-Egg VPK Sync](https://github.com/K4ryuu/CS2-Egg/blob/main/docs/features/vpk-sync.md) | همگام‌سازی VPK از نصب مرکزی به Volumeهای سرورهای Pterodactyl/Pelican؛ symlink/hardlink/copy و daemon | گزینهٔ مقایسه‌ای در صورت نیاز؛ انتخاب نشده | installer/daemon دارای سطح دسترسی میزبان است؛ مسیرها، مجوزها، lock، Self-Update، failure recovery و rollback باید بررسی شوند. |
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

## 5. مراحل PoC برای معماری مشترک

### مرحلهٔ اول — Base مشترک و یک Instance

1. یک Debian Node آزمایشی با Panel/Wings و Egg/template قابل بررسی آماده شود.
2. Base Game در مسیر مرکزی تعیین‌شده نصب شود؛ نصب مرکزی و مسیر SteamCMD ثبت شود.
3. یک Instance ساخته شود و Start/Stop/Restart، Console، File Manager، SFTP، allocations، محدودیت RAM/CPU، Backup و Restore آزموده شود.
4. تأیید شود که اسکریپت Deploy موردنظر SteamCMD را اجرا نمی‌کند و وظیفهٔ نصب/Update Base را ندارد.
5. فایل‌های shared و private در نقشهٔ مسیرها ثبت شوند؛ مسیرهای نوشتنی runtime بر پایهٔ آزمون واقعی شناسایی شوند.

### مرحلهٔ دوم — دو Instance با داده‌های خصوصی

1. Instance دوم روی همان Base بساز.
2. برای دو Instance مقادیر متفاوت hostname، تنظیمات cfg، مود/افزونه و فایل‌های GameMode قرار بده.
3. تغییر در addons/، cfg/ یا فایل‌های اختصاصی GameMode یکی نباید Instance دیگر را تغییر دهد.
4. هویت Server ID، Volume، پورت‌ها، منابع، SFTP/File Manager، Console و مجوز مشتری را بررسی کن.
5. حذف یا Reinstall یک Instance نباید Base مرکزی یا Instance دیگر را خراب کند.

### مرحلهٔ سوم — آزمون Update هماهنگ‌شدهٔ Base

1. چند Instance آزمایشی را هم‌زمان اجرا کن و فهرست سرورهای Running را ثبت کن.
2. Update جدید را در محیط آزمایش اعمال کن؛ ابتدا همهٔ Instanceهای مصرف‌کننده باید متوقف شوند.
3. SteamCMD باید فقط یک‌بار روی Base مرکزی اجرا شود؛ هم‌زمان هیچ Start/Restart دیگری مجاز نباشد.
4. موفقیت/شکست Update، version/build، اعتبار فایل‌ها و رفتار بازگشت از Maintenance ثبت شود.
5. پس از موفقیت فقط Instanceهایی که قبل از Update Running بودند دوباره Start شوند؛ در صورت شکست، هیچ Instanceای روی Base ناقص راه نیفتد.
6. شکست/قطع فرایند Update و بازیابی یا rollback را در محیط دورریختنی آزمایش کن.

### مرحلهٔ چهارم — انتخاب روش اتصال فایل‌ها

پس از آزمون‌های بالا روش فنی دسترسی Instance به Base (مانند mount، link، overlay یا روش دیگر) انتخاب می‌شود. هیچ روش صرفاً بر اساس README یا ادعای صرفه‌جویی انتخاب نمی‌شود. K4ryuu VPK Sync فقط در صورتی بررسی می‌شود که نیاز مشخصی را بهتر از راه‌حل ساده‌تر برطرف کند.

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

- **مدیریت مشتریان و چرخهٔ عمر:** Pterodactyl Panel + Wings مسیر مرجع PoC است؛ Production هنوز تأیید نشده.
- **مدل فایل:** یک Base Game مرکزی و مشترک؛ addons/، cfg/ و فایل‌های تعیین‌شدهٔ GameMode در هر Instance مستقل‌اند.
- **SteamCMD:** فقط برای نصب/Update Base مرکزی اجرا می‌شود؛ اسکریپت Deploy هر Instance نباید SteamCMD اجرا کند یا سرور را Start کند.
- **سیاست Update:** توقف تمام Instanceهای مصرف‌کننده، Update یک‌بارهٔ Base، اعتبارسنجی و سپس بازگرداندن Instanceهایی که قبل از Update Running بودند.
- **منبع payload:** ریپوی خود FragCity یعنی larnzs/cs2-kus-addons؛ هیچ تغییری در آن ریپو در این مرحله انجام نشده است.
- **نامزدهای ثالث:** فقط مرجع مقایسه‌ای‌اند؛ K4ryuu VPK Sync یا یک standalone manager انتخاب نشده است.
- **موارد پیاده‌سازی باز:** مسیر Base، روش mount/link/overlay، نام دقیق فایل‌های GameMode، provisioning cfg، trigger/lock/recovery برای Update و استقرار امن script در Pterodactyl.

## منابع

- [K4ryuu/CS2-Egg — VPK Sync](https://github.com/K4ryuu/CS2-Egg/blob/main/docs/features/vpk-sync.md)
- [GamesTwoLife/cs2-multiserver](https://github.com/GamesTwoLife/cs2-multiserver)
- [dasisdormax/cs2-multiserver](https://github.com/dasisdormax/cs2-multiserver)
- [Trimeon/cs2-install](https://github.com/Trimeon/cs2-install)
- [Auto-Tournament/cs2-server-manager](https://github.com/Auto-Tournament/cs2-server-manager)
- [1zc/CS2-Pterodactyl](https://github.com/1zc/CS2-Pterodactyl)
- [Pterodactyl issue: shared CS2 files and server.cfg behavior](https://github.com/1zc/CS2-Pterodactyl/issues/49)

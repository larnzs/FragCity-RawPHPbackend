# FragCity — ساخت Golden Image قابل‌تکرار برای Nodeهای Debian

**هدف در فاز آینده:** یک بار Debian تمیز با Docker و Wings آماده کنیم؛ سپس از VM خاموش‌شده کپی بگیریم. فعلاً طبق تصمیم جاری این فصل اجرا نمی‌شود و تمرکز روی تکمیل Wings روی همین `fc-node-01` است.

**وضعیت فعلی:** راهنما و اسکریپت‌ها آماده‌اند اما **فعلاً اجرا نمی‌شوند**. Docker روی VM فعلی نصب و آزمایش شده؛ قدم جاری نصب Wings روی همین ماشین است. این سند پس از اثبات PoC دوباره دنبال می‌شود.

## تصمیم معماری — خیلی مهم

- VM فعلی را به‌عنوان **Panel آزمایشی/Control Plane** حفظ می‌کنیم؛ آن را پاک‌سازی یا برای ساخت Template تبدیل نمی‌کنیم.
- یک **VM جدید و تازه از Debian 13** به نام `fc-node-template` می‌سازیم؛ آن VM فقط نقش Node بازی را آماده می‌کند.
- قالب قابل‌کلون شامل Debian، Docker، باینری Wings با نسخهٔ سازگار و سرویس systemd است؛ **Panel، دیتابیس Panel، فایل‌های CS2، دادهٔ مشتری، `.env`، توکن یا `/etc/pterodactyl/config.yml` داخلش قرار نمی‌گیرند.**
- پیکربندی Wings مختص هر Node باید از Panel تولید/دریافت شود؛ هر clone hostname، machine-id، SSH host keys، MAC/IP و password اختصاصی خواهد داشت.
- فایل‌های فعلی `/home/steam` روی VM اصلی باید دست‌نخورده بمانند. CS2 در Golden Image نصب نمی‌شود؛ ادغام فایل‌های بازی در فصل ۴ انجام می‌شود.
- همه‌چیز فعلاً LAN-only است. هیچ Port Forwarding عمومی، HTTPS عمومی یا دامنهٔ واقعی در این فصل تنظیم نمی‌شود.

## تنظیمات پیشنهادی VM Template

| مورد | مقدار/انتخاب | دلیل |
|---|---|---|
| نام VM در VMware | `fc-node-template` | قابل تشخیص به‌عنوان VM مادر؛ پس از نهایی‌سازی روشن نشود |
| Guest OS | Debian GNU/Linux 13 (Trixie), 64-bit | یک پایهٔ یکنواخت برای Nodeها |
| vCPU برای آماده‌سازی | 2 vCPU | تنظیم سخت‌افزار واقعی را می‌توان پس از clone تغییر داد |
| RAM برای آماده‌سازی | 4 GiB | کافی برای نصب/تست پایه؛ ظرفیت واقعی Node بعداً طبق بار تنظیم شود |
| ظرفیت Virtual Disk | 300 GB | برای رشد آیندهٔ داده‌های بازی و containerها |
| نوع دیسک | Growable / Thin؛ گزینهٔ “Allocate all disk space now” خاموش | ظرفیت مجازی 300 GB است، ولی فضای فیزیکی با نوشتن داده مصرف می‌شود |
| فایل دیسک | یک فایل VMDK، اگر میزبان فضای کافی و ابزار copy مناسبی دارد | مدیریت/کپی ساده‌تر؛ growable را با preallocated اشتباه نگیر |
| شبکه | همان روش شبکهٔ LAN که Panel را می‌بیند؛ ترجیحاً Bridged در LAN فعلی | Node باید به `192.168.1.5` دسترسی داشته باشد |
| آدرس شبکه در Template | DHCP، نه IP ثابت `192.168.1.5` | IP پنل محفوظ است و نباید در cloneها تکرار شود |
| نرم‌افزار Debian | OpenSSH server + standard system utilities؛ Desktop Environment نصب نکن | Node از Windows با SSH و WinSCP مدیریت می‌شود؛ منابع برای بازی می‌ماند |
| فایل‌های بازی | هیچ‌کدام | حجم و نسخهٔ بازی جدا از OS image است |

**توضیح دیسک:** 300 GB ظرفیت مجازی به معنی 300 GB فضای فیزیکی از همان ابتدا نیست، اگر Growable انتخاب شود. فایل دیسک با استفاده بزرگ می‌شود؛ بااین‌حال میزبان Windows باید برای مصرف واقعی آینده، backupها و snapshotها فضای فیزیکی کافی داشته باشد. فایل‌های بازی بعداً ممکن است ده‌ها گیگابایت مصرف کنند.

## ترتیب اجرا

### مرحله ۰ — تأیید نسخهٔ دقیق Panel فعلی

روی VM فعلی Panel اجرا کن؛ این فرمان فقط اطلاعات می‌خواند:

~~~bash
cd /var/www/pterodactyl
sudo -u www-data php8.3 artisan p:info
~~~

قبل از ارسال خروجی، هرگونه password، token، APP_KEY یا secret را حذف کن. نسخهٔ Laravel به‌تنهایی نسخهٔ Panel را اثبات نمی‌کند. بعد از دیدن نسخه، نسخهٔ Wings سازگار را از سند رسمی همان شاخه انتخاب می‌کنیم. مستندات شاخهٔ 1.x و 2.0 را با هم مخلوط نکن.

### مرحله ۱ — ساخت VM جدید در VMware Workstation

1. ابتدا مطمئن شو Windows میزبان برای نگهداری VM، snapshot/backup و دادهٔ آینده فضای کافی دارد. فایل فعلی VM اصلی را تغییر نده.
2. در VMware گزینهٔ **Create a New Virtual Machine** را انتخاب کن؛ **از VM فعلی Clone نساز** چون Panel/DB و داده‌های بازی را دارد.
3. نام VM را `fc-node-template` قرار بده و ISO رسمی Debian 13 amd64 را انتخاب کن.
4. ظرفیت دیسک را 300 GB بگذار؛ حالت Growable/Allocate all disk space now خاموش باشد.
5. شبکه را مطابق VM فعلی انتخاب کن تا به Panel روی `192.168.1.5` دسترسی داشته باشد، اما MAC جدید بماند.
6. در نصب Debian:
   - Hostname: `fc-node-template`
   - Domain: خالی، مگر اینکه در LAN دامنهٔ داخلی مشخصی داشته باشی
   - Network: DHCP
   - Partitioning: Guided — use entire disk؛ تمام 300 GB را به این VM اختصاص بده (پیشنهاد ساده: یک پارتیشن ریشهٔ اصلی با swap طبق پیشنهاد نصب‌کننده)
   - Software selection: Desktop environment خاموش؛ SSH server و standard system utilities روشن
   - یک حساب مدیریتی محلی مثل `fragcityadmin` بساز. رمز واقعی را فقط روی همان VM تعیین کن؛ در GitHub/چت ثبت نکن.
7. بعد از نصب، VM را reboot کن و از Console وارد شو. ابتدا مطمئن شو hostname و دیسک درست‌اند.

### مرحله ۲ — Preflight روی VM تازه

~~~bash
set -eu

echo "=== OS / HOSTNAME ==="
cat /etc/os-release | grep -E '^(PRETTY_NAME|VERSION_ID|VERSION_CODENAME)='
hostnamectl --static
uname -m

echo
echo "=== NETWORK ==="
ip -br address
ip route

echo
echo "=== RESOURCES ==="
nproc
free -h
df -hT /
df -ih /

echo
echo "=== REQUIRED ADMIN ==="
id fragcityadmin
command -v ssh
command -v ssh-keygen

echo
echo "=== NO GAME DATA IN TEMPLATE ==="
if [ -d /home/steam ] && [ -n "$(find /home/steam -mindepth 1 -print -quit)" ]; then
    echo "STOP: /home/steam is not empty; do not finalize this VM."
    exit 1
else
    echo "OK: no CS2/KUS payload found in /home/steam"
fi
~~~

پیش از ادامه مطمئن شو OS برابر Debian 13/Trixie، معماری `x86_64`/ `amd64`، hostname برابر `fc-node-template` و ظرفیت ریشه حدود 300 GB است. اگر نتیجه متفاوت بود، توقف کن و ابتدا علت را مشخص کن.

### مرحله ۳ — نصب Docker با اسکریپت نسخه‌بندی‌شده

فایل مرجع: [install-docker.sh](../infra/node-template/install-docker.sh)

روی VM **جدید**، با حساب عادی مدیریتی اجرا کن (نه روی VM Panel و نه با فایل‌های CS2/KUS). اسکریپت Debian 13/amd64 را بررسی می‌کند، بسته‌های متعارض را خودکار حذف نمی‌کند، از کلید و مخزن رسمی Docker استفاده می‌کند و در صورت تعارض پیکربندی متوقف می‌شود.

دانلود برای بازبینی:

~~~bash
curl -fsSL \
  https://raw.githubusercontent.com/larnzs/FragCity-RawPHPbackend/main/infra/node-template/install-docker.sh \
  -o /tmp/fragcity-install-docker.sh

less /tmp/fragcity-install-docker.sh
~~~

برای خروج از `less` کلید `q` را بزن. پس از بازبینی فایل:

~~~bash
bash /tmp/fragcity-install-docker.sh
~~~

اسکریپت باید در پایان Docker version، وضعیت service، نتیجهٔ `docker info` و پیام موفقیت `hello-world` را نشان دهد. اگر فایل repository/key موجود و متفاوت باشد یا بستهٔ متعارضی پیدا شود، اسکریپت متوقف می‌شود؛ بدون بررسی دستی ادامه نده.

### مرحله ۴ — نصب Wings با نسخهٔ سازگار

**هنوز انجام نشده؛ نسخهٔ دقیق Panel از مرحلهٔ ۰ لازم است.**

پس از تطبیق نسخه، در همین VM تازه یک اسکریپت مستقل و نسخه‌بندی‌شده به مخزن اضافه می‌کنیم تا:
- باینری Linux/amd64 از Release رسمی همان نسخه را نصب کند؛
- نسخه/hash را ثبت و تأیید کند؛
- systemd unit استاندارد بسازد؛
- `/etc/pterodactyl/` را بسازد؛
- **هیچ** `config.yml` واقعی یا Node token تولید نکند؛
- سرویس Wings را تا زمان clone و دریافت config اختصاصی **disable** نگه دارد.

منبع نصب رسمی: <https://docs.pterodactyl.io/v1/wings/installing> یا <https://docs.pterodactyl.io/v2/wings/installing>؛ فقط نسخهٔ منطبق با Panel فعلی را استفاده کن.

### مرحله ۵ — آزمون پیش از نهایی‌سازی

پس از نصب Wings و ایجاد unit عمومی (بدون config)، این فرمان‌ها را اجرا کن:

~~~bash
set -eu

echo "=== OS / DISK ==="
hostnamectl --static
df -hT /
free -h

echo
echo "=== DOCKER ==="
sudo docker --version
sudo systemctl is-active docker
sudo docker info --format 'Server={{.ServerVersion}} Containers={{.Containers}} Images={{.Images}}'

echo
echo "=== WINGS BINARY ==="
test -x /usr/local/bin/wings
/usr/local/bin/wings --version

echo
echo "=== WINGS UNIT EXISTS BUT IS NOT RUNNING ==="
sudo systemctl cat wings.service
if sudo systemctl is-active --quiet wings.service; then
    echo "STOP: Wings is running; template must not retain an active node identity."
    exit 1
else
    echo "OK: Wings is not running."
fi

echo
echo "=== NO PANEL OR NODE SECRET ==="
test ! -e /var/www/pterodactyl/artisan
test ! -e /etc/pterodactyl/config.yml
echo "OK: no Panel files and no node-specific Wings config."
~~~

اگر نسخهٔ `wings --version` در نسخهٔ انتخابی گزینهٔ دیگری دارد، طبق help/release همان binary عمل کن؛ خروجی را ثبت کن. در Template هیچ Nodeی در Panel ثبت یا پیکربندی نمی‌شود و `config.yml` وجود ندارد. پایان این مرحله هنوز پایان Template نیست؛ هویت سیستم باید برای clone پاک‌سازی شود.

### مرحله ۶ — آخرین آماده‌سازی Golden Image

فایل مرجع: [prepare-golden-image.sh](../infra/node-template/prepare-golden-image.sh)

اسکریپت فقط در VM مادر با hostname دقیق `fc-node-template` و بدون Panel، فایل بازی، container و config خصوصی Wings اجازهٔ اجرا می‌دهد. این کار عمداً هویت Linux را پاک می‌کند؛ بعد از اجرای موفق، **VM مادر را دیگر boot نکن.**

دانلود و بازبینی:

~~~bash
curl -fsSL \
  https://raw.githubusercontent.com/larnzs/FragCity-RawPHPbackend/main/infra/node-template/prepare-golden-image.sh \
  -o /tmp/fragcity-prepare-golden-image.sh

less /tmp/fragcity-prepare-golden-image.sh
~~~

پس از بازبینی، از Console همان VM اجرا کن:

~~~bash
sudo bash /tmp/fragcity-prepare-golden-image.sh
~~~

اسکریپت:
- Wings را disable می‌کند (چون هنوز Node-specific config وجود ندارد)؛
- یک systemd unit می‌سازد تا clone در اولین boot کلیدهای میزبان SSH یکتا تولید کند؛
- image آزمایشی `hello-world` را در صورت وجود حذف می‌کند؛
- apt cache را پاک می‌کند؛
- SSH host keys را حذف و `/etc/machine-id` را خالی می‌کند؛
- بررسی می‌کند config Wings/Panel/server volume یا payload بازی داخل Template نمانده است.

این کار **باید فقط روی VM تازهٔ Template اجرا شود**، نه روی VM فعلی Panel.

### مرحله ۷ — نقطهٔ توقف رسمی: اینجا کپی بگیر

وقتی اسکریپت مرحلهٔ ۶ پیام زیر را نشان داد:

~~~text
READY: shut down now, then copy the entire powered-off VM folder.
~~~

1. همان‌جا در Console این فرمان را اجرا کن:

~~~bash
sudo systemctl poweroff
~~~

2. صبر کن VMware وضعیت VM را **Powered Off** نشان دهد.
3. از کل پوشهٔ VM مادر کپی کامل بگیر یا از قابلیت Clone/Export استفاده کن؛ فقط فایل VMDK را جداگانه کپی نکن. فایل‌های پیکربندی VM هم برای restore/clone مهم‌اند.
4. اسم Template مادر را `fc-node-template` نگه دار و آن را خاموش و دست‌نخورده نگه دار. برای هر Node جدید از آن یک کپی/Clone تازه بساز.
5. هنگام اولین روشن‌کردن کپی در VMware، اگر پرسیده شد **“I copied it”** را انتخاب کن تا VMware برای کپی شناسهٔ مجزا ایجاد کند.
6. پیش از اولین boot کپی، Network Adapter را موقتاً Disconnect کن. پس از boot و آماده‌شدن SSH keys، از Console اسکریپت هویت Node را اجرا کن و یک hostname و password یکتا بده.
7. قبل از وصل‌کردن NIC به LAN، از اسکریپت زیر استفاده کن؛ سپس VM را خاموش کن، Network Adapter را دوباره متصل کن، MAC جدیدش را در روتر به یک IP آزاد و اختصاصی DHCP-reserve کن و VM را روشن کن:

~~~bash
curl -fsSL \
  https://raw.githubusercontent.com/larnzs/FragCity-RawPHPbackend/main/infra/node-template/set-node-identity.sh \
  -o /tmp/fragcity-set-node-identity.sh

less /tmp/fragcity-set-node-identity.sh
sudo bash /tmp/fragcity-set-node-identity.sh fc-node-01 fragcityadmin
~~~

اسکریپت clone hostname را تنظیم می‌کند، machine-id و SSH keys را بررسی/تولید می‌کند و برای حساب مدیر رمز جدید می‌گیرد. آن را از Console محلی اجرا کن، نه در SSHای که ممکن است با تغییر SSH/hostname قطع شود.

**وقتی مرحلهٔ ۶ پیام READY بدهد و VM مادر خاموش شود، برای کپی‌گرفتن توقف می‌کنیم.** سپس اولین clone را آماده و در Panel به‌عنوان Node ثبت می‌کنیم؛ فایل config مخصوص Node فقط پس از ساخته‌شدن Node از Panel گرفته می‌شود.

## پس از Clone (مرحلهٔ بعدی، خارج از ساخت Template)

1. مطمئن شو VM Panel با hostname و IP خودش روشن است؛ پیش از متصل‌کردن clone، hostname ماشین Panel را با Node قاطی نکن. روی VM فعلی hostname قدیمی `fc-node-01` ثبت شده است؛ اگر قرار است اولین clone هم همین hostname را بگیرد، ابتدا پس از backup، hostname ماشین Panel را به `fc-panel-01` (بدون تغییر IP رزروشدهٔ `192.168.1.5`) تغییر بده.
2. داخل Pterodactyl Panel از Admin → Locations یک Location ایجاد/انتخاب کن.
3. از Admin → Nodes یک Node با نام موردنظر بساز و آدرس LAN واقعی کلون را وارد کن.
4. Daemon port و SFTP port را طبق تنظیمات همان Panel انتخاب کن (معمولاً TCP/8080 و TCP/2022)، اما هنوز Port Forwarding عمومی نکن.
5. از تب Configuration پیکربندی همان Node را دریافت کن و به‌صورت امن در `/etc/pterodactyl/config.yml` ذخیره کن؛ آن را وارد GitHub/چت نکن.
6. Wings را ابتدا با `sudo wings --debug` در foreground آزمایش کن؛ پس از موفقیت طبق systemd unit سرویس را فعال کن.
7. سرور CS2 نساز تا فصل ۴، روش استفادهٔ مجدد از فایل‌های موجود و فضای دیسک را طراحی و آزمایش کند.

## منابع رسمی

- [Docker Engine روی Debian](https://docs.docker.com/engine/install/debian/)
- [Wings نصب — مستندات 1.x](https://docs.pterodactyl.io/v1/wings/installing)
- [Wings نصب — مستندات 2.0](https://docs.pterodactyl.io/v2/wings/installing)
- [ساخت Node و Allocation](https://docs.pterodactyl.io/v1/guides/configuration/add-node)
- [VMware — ماشین مجازی کپی‌شده و machine-id یکتا](https://knowledge.broadcom.com/external/article/316620)
- [VMware — ظرفیت دیسک growable](https://knowledge.broadcom.com/external/article/308647)

## وضعیت این راهنما در ۱۸ اکتبر ۲۰۲۶

- [x] Docker روی VM فعلی Panel نصب و با hello-world آزمایش شده
- [ ] نسخهٔ Panel با `p:info` تأیید شود
- [ ] VM تازهٔ `fc-node-template` با دیسک مجازی 300 GB ساخته شود
- [ ] Docker در VM تازه از اسکریپت مخزن نصب و آزمایش شود
- [ ] Wings سازگار و بدون config/token نصب شود
- [ ] آزمون مرحلهٔ ۵ کامل شود
- [ ] مرحلهٔ ۶ پیام READY بدهد
- [ ] VM مادر خاموش شود و کپی کامل گرفته شود

این فایل نقشهٔ اجراست. هیچ مرحله‌ای روی VM تازه تا وقتی خروجی واقعی کاربر ثبت نشود، Completed فرض نمی‌شود.

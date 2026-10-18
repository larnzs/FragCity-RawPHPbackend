# FragCity — فصل Wings و Docker (LAN-only)

**وضعیت:** Docker Engine روی همین VM نصب و با `docker info` و `hello-world` آزمایش شده؛ قدم بعد نصب Wings روی همین `fc-node-01` است. Golden Image فعلاً به آینده موکول شده.  
**Node:** `fc-node-01` · **OS:** Debian 13/Trixie · **Architecture:** amd64/x86_64 · **LAN IP:** `192.168.1.5`  
**Panel:** `http://192.168.1.5` · ورود مدیر از Windows تأیید شده است.  
**محدوده:** شبکهٔ داخلی فقط؛ هنوز دامنهٔ عمومی، HTTPS عمومی، Port Forwarding، فایروال WAN یا سرور بازی Pterodactyl نداریم.

> **اصل ایمنی:** مسیر `/home/steam` حدود 71 GiB از فایل‌های موجود CS2/KUS را نگه می‌دارد. آن را حذف، جابه‌جا، کپی یا mount نکن. ادغام فایل‌های موجود با Egg/volumeهای Pterodactyl در فصل ۴ جداگانه طراحی می‌شود.

## تصمیم اجرایی فعلی: ادامه روی همین VM

برای این PoC، Panel و Wings موقتاً روی همین Debian `fc-node-01` اجرا می‌شوند. این روش برای آزمایش LAN و یادگیری مناسب است؛ **به‌عنوان معماری Production نهایی تلقی نمی‌شود**. Docker روی همین VM تأیید شده است و نصب آن تکرار نمی‌شود.

پیش از نصب Wings، نسخهٔ دقیق Panel را با `sudo -u www-data php8.3 artisan p:info` بخوان. سپس Node همین ماشین را در Admin UI بساز و پیکربندی اختصاصی‌اش را از Panel دریافت کن. `/etc/pterodactyl/config.yml` حاوی token/secret است و نباید در Git یا چت ذخیره شود.

Golden Image فعلاً deferred است؛ بعد از تکمیل PoC به [Reusable Linux Image Strategy](REUSABLE-LINUX-IMAGE-STRATEGY.md) و [Debian Node Golden Image](DEBIAN-NODE-GOLDEN-IMAGE.md) برمی‌گردیم. هیچ اسکریپت finalization/clone identity را روی VM فعلی اجرا نکن.

## 1. اجزای معماری

- **Panel:** رابط وب و دیتابیس کنترل؛ در `/var/www/pterodactyl` نصب شده.
- **Node:** ماشینی که سرورهای بازی روی آن اجرا می‌شوند. این VM همان Node آزمایشی ماست.
- **Wings:** سرویس پس‌زمینهٔ روی Node. فرمان‌ها و تنظیمات سرورها را از Panel می‌گیرد، Docker را کنترل می‌کند و وضعیت/کنسول/فایل‌ها را گزارش می‌دهد.
- **Docker Engine:** runtime برای ایجاد و اجرای containerها؛ خودش Panel، Wings یا CS2 نیست.
- **Image:** الگوی پایهٔ فایل‌سیستم و ابزارهای موردنیاز یک container.
- **Container:** محیط ایزوله‌ای که فرایند سرور بازی در آن اجرا می‌شود.
- **Egg:** تعریف نصب و اجرا برای نوع بازی: Docker image، startup command، install script و متغیرها.
- **Allocation:** IP و port اختصاص‌داده‌شده به یک server instance.

یک Panel می‌تواند چند Node را مدیریت کند؛ یک Node هم می‌تواند چند server instance را میزبانی کند. ساخت Node جدا برای هر سرور بازی در یک ماشین واحد الزامی نیست.

## 2. Docker از صفر: چرا نیازش داریم؟

بدون Docker، برنامه‌های سرور ممکن است کتابخانه‌ها، نسخه‌ها و فایل‌های اجرایی‌شان را با یکدیگر تداخل دهند. Containerها هر سرور را با محیط اجرای مشخص و محدودیت‌های قابل‌مدیریت اجرا می‌کنند. Docker از قابلیت‌های Linux مانند namespaces و cgroups برای جداسازی فرایندها و مدیریت منابع استفاده می‌کند؛ container یک VM کامل با kernel مستقل نیست.

جریان کار:

1. مدیر از Panel درخواست Create/Start می‌دهد.
2. Panel تنظیمات سرور را به Wings مرتبط می‌کند.
3. Wings از Docker می‌خواهد image و container مناسب را آماده کند.
4. فرایند CS2 در container اجرا می‌شود.
5. Wings وضعیت و log/console را به Panel برمی‌گرداند.

بازیکن‌ها مستقیماً به IP و port بازی متصل می‌شوند؛ معمولاً ترافیک بازی از Panel عبور نمی‌کند. Docker اجرای container را فراهم می‌کند، اما فایل‌های native در `/home/steam` را خودکار داخل container قرار نمی‌دهد.

این روش در ۱۸ اکتبر ۲۰۲۶ با [مستند رسمی Docker برای Debian](https://docs.docker.com/engine/install/debian/) بررسی شده است. مستند رسمی Debian 13/Trixie و معماری amd64 را پشتیبانی‌شده معرفی می‌کند.

**هشدار firewall:** مستند رسمی Docker هشدار می‌دهد که پورت‌های منتشرشدهٔ container ممکن است از قواعد UFW/firewalld عبور کنند و Docker از iptables/ip6tables استفاده می‌کند. بنابراین فعلاً سرور بازی نساز و پورت عمومی باز نکن؛ در فصل شبکه قواعد Docker، `DOCKER-USER` و port forwarding را با هم بررسی می‌کنیم.

## 3. Preflight واقعی

آخرین خروجی بررسی‌شده:

| آیتم | نتیجه |
|---|---|
| Debian | 13 (Trixie) |
| Virtualization | VMware |
| Architecture | x86_64 / amd64 |
| Kernel | 6.12.113+deb13-amd64 |
| vCPU | 4 |
| RAM | 5.6 GiB؛ حدود 3 GiB available در نمونه‌برداری |
| Root filesystem | 112 GiB کل، 80 GiB مصرف‌شده، 27 GiB آزاد |
| `/home/steam` | حدود 71 GiB؛ فایل‌های موجود CS2/KUS |
| Docker CLI/daemon | در preflight اولیه پیدا نشد؛ در تست بعدی Docker 29.9.1 فعال و hello-world موفق شد |
| Docker repository | در preflight اولیه وجود نداشت؛ بعداً پیکربندی شد و بسته‌ها نصب شدند |
| بسته‌های متعارض بررسی‌شده | هیچ‌کدام installed گزارش نشدند |
| listenerهای موجود | SSH TCP/22، NGINX TCP/80؛ MariaDB روی loopback:3306، Redis روی loopback:6379 |
| Wings ports | 8080 و 2022 هنوز listener نیستند؛ Wings نصب نشده است |

بررسی بدون تغییر:

```bash
cat /etc/os-release
systemd-detect-virt || true
uname -m
uname -r
lscpu
free -h
df -h /
sudo ss -ltnup
```

آخرین خروجی نشان داد حدود 27 GiB آزاد است. نصب Docker/Wings اولیه ممکن است انجام شود، اما هنوز CS2 instance تازه نساز: Egg کاتالوگ‌شده حدود 33 GB فضای ذخیره‌سازی حداقلی اعلام می‌کند و image/cache/logs هم فضا لازم دارند. ابتدا storage plan فصل ۴ را تکمیل کن.

## 4. نصب Docker Engine از مخزن رسمی

Docker لایه‌ای است که Wings از آن برای اجرای containerها استفاده می‌کند. نصب آن service پس‌زمینهٔ `docker` (daemon) و ابزار CLI را فراهم می‌کند. بسته‌های Docker معمولاً image و metadata را زیر `/var/lib/docker` ذخیره می‌کنند و ممکن است تنظیمات شبکه/iptables را تغییر دهند؛ به همین علت نصب عمومی سرور و بازکردن portها را با این مرحله قاطی نکن.

مرجع رسمی و نسخهٔ جاری را پیش از اجرا بررسی کن: <https://docs.docker.com/engine/install/debian/>.

### 4.1 بررسی بسته‌های متعارض

این بررسی قبلاً موفق شد و هیچ‌کدام از بسته‌های فهرست‌شده نصب نبودند؛ اگر از نو اجرا می‌کنی، دوباره بررسی کن:

```bash
set -eu
for package in docker.io docker-compose docker-doc docker-buildx podman-docker containerd runc; do
    status="$(dpkg-query -W -f='${db:Status-Status}' "$package" 2>/dev/null || true)"
    if [ "$status" = installed ]; then
        printf 'INSTALLED: %s\n' "$package"
    fi
done
```

وجود بسته‌ای در خروجی به معنی حذف خودکار آن نیست؛ توقف کن و سازگاری/مالکیت آن را بررسی کن.

### 4.2 افزودن repository و نصب

**وضعیت تاریخی: این گام انجام شده است.** Docker repository/package setup روی همین VM اجرا شد. تکرار نصب لازم نیست؛ نتیجهٔ واقعی و مرحلهٔ تأیید در بخش بعد ثبت شده است.

```bash
set -eu

sudo apt update
sudo apt install -y ca-certificates curl
sudo install -m 0755 -d /etc/apt/keyrings

if ! sudo test -s /etc/apt/keyrings/docker.asc; then
    sudo curl -fsSL https://download.docker.com/linux/debian/gpg \
        -o /etc/apt/keyrings/docker.asc
fi
sudo chmod a+r /etc/apt/keyrings/docker.asc

if sudo test -e /etc/apt/sources.list.d/docker.sources; then
    if sudo grep -q 'https://download.docker.com/linux/debian' /etc/apt/sources.list.d/docker.sources; then
        echo "Docker repository file exists; inspect before proceeding:"
        sudo cat /etc/apt/sources.list.d/docker.sources
        echo "Stop here and confirm the suite/architecture/signing key."
        exit 1
    else
        echo "Unexpected docker.sources exists; do not overwrite."
        exit 1
    fi
fi

. /etc/os-release
printf 'Detected Debian suite: %s\n' "$VERSION_CODENAME"
printf 'Detected architecture: %s\n' "$(dpkg --print-architecture)"

sudo tee /etc/apt/sources.list.d/docker.sources >/dev/null <<EOF
Types: deb
URIs: https://download.docker.com/linux/debian
Suites: $VERSION_CODENAME
Components: stable
Architectures: $(dpkg --print-architecture)
Signed-By: /etc/apt/keyrings/docker.asc
EOF

sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo systemctl enable --now docker
```

**توضیح فرمان‌ها:**

- `apt update` فهرست بسته‌ها را از repositoryهای پیکربندی‌شده تازه می‌کند؛ خودش پکیج‌ها را نصب نمی‌کند.
- `ca-certificates` گواهی‌های CA را فراهم می‌کند و `curl` برای دانلود استفاده می‌شود.
- `/etc/apt/keyrings/docker.asc` کلید عمومی امضای repository است، نه secret خصوصی.
- `docker.sources` به APT می‌گوید از کدام URI و suite بسته‌ها را دریافت کند و امضا را با کدام keyring بررسی کند.
- `docker-ce` Engine، `docker-ce-cli` فرمان `docker` و `containerd.io` لایهٔ runtime کانتینر را نصب می‌کنند؛ buildx و compose ابزارهای همراه‌اند.
- `systemctl enable --now docker` سرویس را اکنون اجرا و برای بوت آینده فعال می‌کند.

### 4.3 تأیید نصب Docker

**وضعیت: انجام شده — ۱۸ اکتبر ۲۰۲۶.**

خروجی واقعی:
- `docker --version`: Docker version 29.9.1, build dfc520d.
- سرویس systemd: ACTIVE.
- `docker info`: Server 29.9.1؛ ارتباط CLI و daemon موفق.
- `docker run --rm hello-world`: pull موفق image amd64 و چاپ `Hello from Docker!`.
- فضای root پس از تست: 27 GiB آزاد.
- /home/steam دست‌نخورده مانده است؛ هنوز container مربوط به CS2 ساخته نشده.

برای مرجع، بلوک آزمون قابل تکرار (لازم نیست الان دوباره اجرا شود):

~~~bash
sudo docker --version
sudo systemctl is-active docker
sudo docker info --format 'Server={{.ServerVersion}} Containers={{.Containers}} Images={{.Images}}'
sudo docker run --rm hello-world
df -h /
~~~

این تست سلامت اولیهٔ Engine را تأیید می‌کند، نه اتصال Wings یا اجرای CS2.

## 4.4 اگر مرحلهٔ نصب Docker با HTTP 403 متوقف شد

در وضعیت فعلی، مرحلهٔ ۱۷ روی HTTP 403 متوقف شده و Docker نصب‌شده فرض نمی‌شود. VMDK فعلی را بازسازی نکن؛ Panel در LAN کار می‌کند و 403 باید در URL یا مسیر شبکه عیب‌یابی شود. Runbook کامل و بلوک تشخیصی فقط‌خواندنی در [راهنمای یکپارچه، عیب‌یابی مرحلهٔ ۱۷](PTERODACTYL-END-TO-END-GUIDE.md#عیب‌یابی-مرحله-۱۷--خطای-http-403-هنگام-دریافت-docker) قرار دارد.

طبق خروجی بررسی دیسک، `/home/steam` حاوی حدود 71 GiB فایل‌های CS2/KUS است؛ این مسیر را پاک یا جابه‌جا نکن. قبل از تغییرات بیشتر، snapshot بگیر و اگر backup مستقل می‌خواهی، VM را عادی خاموش و کل پوشهٔ VM را کپی کن.

## 5. قبل از نصب Wings: نسخهٔ دقیق Panel و Node

**قدم بعدی فعلی:** در Debian نسخهٔ Panel را از Artisan بخوان، سپس نسخهٔ Wings را طبق همان شاخه انتخاب کن. Pterodactyl 2.0 هنوز در مستندات رسمی Pre-release است؛ مستندات فعلی می‌گویند Wings 1.13.2 و 1.13.3 با 2.0 آزمایش شده‌اند. اگر Panel واقعاً 1.x باشد، باید جدول نسخه‌های 1.x را دنبال کنیم؛ این دو حالت را با حدس از روی Laravel version تعیین نکن.

~~~bash
cd /var/www/pterodactyl
sudo -u www-data php8.3 artisan p:info
~~~

این فرمان فقط اطلاعات تنظیمات و نسخه را نشان می‌دهد. قبل از ارسال، هرگونه رمز، token یا secret را حذف کن؛ اگر خروجی حاوی credential بود آن قسمت را نفرست.

منابع رسمی:
- <https://docs.pterodactyl.io/v2>
- <https://docs.pterodactyl.io/v2/wings/installing>
- <https://docs.pterodactyl.io/v1/wings/installing>
- <https://github.com/pterodactyl/panel/releases>
- <https://github.com/pterodactyl/wings/releases>

پس از تطبیق نسخه:


قبل از دانلود Wings، نسخهٔ دقیق Panel را تأیید کن. لاگ Panel فعلی Laravel `12.64.0` را نشان می‌داد، اما metadata مربوط به Git وجود نداشت؛ یک مستند قدیمی‌تر به archive `v1.15.1` اشاره می‌کند. عدد Laravel به‌تنهایی نسخهٔ Panel را تعیین نمی‌کند. از <https://github.com/pterodactyl/wings/releases> و مستندات همان شاخه برای انتخاب سازگار استفاده کن؛ `latest` را کورکورانه دانلود نکن.

وقتی نسخه تطبیق شد:

1. در Panel به **Admin → Locations** برو و یک Location بساز (اگر هنوز وجود ندارد).
2. به **Admin → Nodes → Create New** برو.
3. نام Node پیشنهادی: `fc-node-01`.
4. برای آزمایش LAN، FQDN/Address را با IP رزروشدهٔ `192.168.1.5` هماهنگ کن و فعلاً SSL را غیرفعال نگه دار؛ این انتخاب فقط برای شبکهٔ قابل‌اعتماد تست است.
5. Daemon port پیشنهادی پیش‌فرض `8080` و SFTP port `2022` است، مگر اینکه نسخه/UI فعلی مقدار دیگری بخواهد.
6. ظرفیت RAM/disk را صادقانه تنظیم کن؛ 27 GiB آزاد به معنی وجود ظرفیت امن برای دانلود CS2 نیست.
7. از تب Configuration کد YAML را تولید/کپی کن. آن فایل حاوی tokenها و secretهای Wings است؛ آن را در Git، README یا چت نگذار.

## 6. نصب باینری Wings و config

این گام **بعد از ثبت نسخهٔ سازگار و ساخت Node در Panel** انجام می‌شود؛ در این مرحله هنوز اجرا نشده است.

راهنمای رسمی همان نسخه را دنبال کن: <https://docs.pterodactyl.io/v2/wings/installing>. برای معماری این VM، asset Linux با معماری `amd64` لازم است. نسخهٔ انتخابی را pin کن و باینری را از release رسمی دریافت کن؛ hash و نسخه را قبل از اجرا بررسی کن.

مسیرهای استاندارد:

- Binary: `/usr/local/bin/wings`
- Configuration: `/etc/pterodactyl/config.yml`
- Service: `wings.service`

ساخت پوشهٔ config در صورت نیاز:

```bash
sudo install -d -m 0750 /etc/pterodactyl
```

محتوای `config.yml` باید از Node Configuration خود Panel باشد و با نسخهٔ دقیق Wings مطابقت کند. محتوا را در این سند paste نکن، چون token و credential دارد. مالکیت و permission نهایی را مطابق مستندات رسمی نسخهٔ نصب‌شده تنظیم کن.

## 7. اجرای foreground و سرویس systemd

ابتدا طبق مستندات همان نسخه یک بار Wings را در حالت debug/foreground اجرا کن تا خطای اتصال، config یا Docker مستقیم دیده شود. اگر موفق شد، آن فرایند را با `Ctrl+C` متوقف و سپس سرویس systemd را مطابق نمونهٔ رسمی همان release بساز. این مرحله هنوز اجرا نشده است.

پس از ساخت service، بررسی‌ها:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now wings
sudo systemctl status wings --no-pager -l
sudo journalctl -u wings -n 100 --no-pager
sudo ss -ltnp | grep -E ':(8080|2022)\\b' || true
```

از اجرای هم‌زمان Wings foreground و سرویس systemd خودداری کن؛ هر دو ممکن است برای پورت/config یکسان رقابت کنند.

## 8. Allocation و آزمون Node

پس از اینکه Wings در Panel به حالت Online رسید:

1. IP مربوط به Node را `192.168.1.5` انتخاب کن.
2. یک allocation از پورت بازی انتخابی و در دسترس اضافه کن، مثلاً UDP/TCP طبق Egg و نیاز بازی؛ protocol را از مستند Egg تأیید کن.
3. فعلاً هیچ port forwarding اینترنتی جدیدی تعریف نکن.
4. چون Egg اختصاصی CS2 هنوز وارد نشده و فضای آزاد کمتر از حداقل اعلام‌شدهٔ Egg است، در این فصل instance واقعی CS2 نساز.
5. اول سلامت Docker/Wings، اتصال Node و logها را تأیید کن. استفادهٔ مجدد از نصب بازی موجود مسئلهٔ فصل ۴ است.

## 9. دامنه، firewall و WAN در فصل آینده

این مورد در دامنهٔ نصب فعلی نیست. برای دسترسی اینترنتی آینده باید به‌صورت یک برنامهٔ یکپارچه بررسی شود:

- DNS برای Panel و Node.
- گواهی و تمدید HTTPS؛ HTTP داخلی راهکار Production نیست.
- پورت مدیریت Wings/API و Wings SFTP فقط طبق نیاز واقعی.
- Docker ممکن است با ruleهای iptables/NAT تعامل داشته باشد؛ صرفاً بازکردن پورت در یک ابزار firewall کافی فرض نشود.
- Port forwarding در روتر/مودم و نوع شبکه VMware.
- پورت/پروتکل واقعی هر Egg و تخصیص‌های جداگانه برای بازی.
- هیچ‌گاه MariaDB روی 3306 یا Redis روی 6379 را به اینترنت باز نکن.
- نام‌های DNS مثل `server1.example` به خودی خود port متفاوت ایجاد نمی‌کنند؛ در یک IP مشترک معمولاً allocationهای port هم مهم‌اند.

## 10. Troubleshooting

### Docker نصب است اما daemon کار نمی‌کند

```bash
sudo systemctl status docker --no-pager -l
sudo journalctl -u docker -n 100 --no-pager
sudo docker info
```

### Wings به Panel وصل نمی‌شود

- آدرس Panel و scheme را با URL فعلی تطبیق بده.
- زمان/تاریخ سیستم را بررسی کن.
- config صادرشده از Node درست است؟ فایل متعلق به همان Node است؟
- token/config را بدون redaction در چت یا Git نفرست؛ فقط متن خطا و نام فیلد را بفرست.

### Disk پر می‌شود

```bash
df -h /
sudo du -xhd1 /var/lib /home/steam 2>/dev/null | sort -h
```

قبل از حذف یا prune از Docker، ابتدا مشخص کن image/volume متعلق به چه چیزی است. مسیر `/home/steam` دارایی موجود CS2/KUS است و نباید پاک‌سازی شود.

## 11. منابع رسمی

- Docker Engine installation on Debian: <https://docs.docker.com/engine/install/debian/>
- Docker Engine concepts: <https://docs.docker.com/engine/>
- Docker security: <https://docs.docker.com/engine/security/>
- Pterodactyl Wings installation: <https://docs.pterodactyl.io/v2/wings/installing>
- Pterodactyl architecture: <https://docs.pterodactyl.io/v2/project/how-it-works>
- Wings releases: <https://github.com/pterodactyl/wings/releases>
- CS2 Egg catalog: <https://eggs.pterodactyl.io/egg/games-counter-strike-2>

## 12. Checklist وضعیت

- [x] Preflight Debian/VMware/architecture انجام شد.
- [x] نبود Docker و repository رسمی در preflight تأیید شد.
- [x] بررسی بسته‌های متعارض: موردی پیدا نشد.
- [x] فضای دیسک و دلیل 71 GiB مصرف در `/home/steam` بررسی شد؛ فایل‌ها باید حفظ شوند.
- [ ] Docker repository نصب/تنظیم شود.
- [ ] Docker Engine نصب شود.
- [ ] `docker info` و `hello-world` موفق شوند.
- [ ] نسخهٔ دقیق Panel ثبت و سازگاری Wings انتخاب شود.
- [ ] Node در Panel ساخته شود.
- [ ] Binary Wings و config مختص Node نصب شوند.
- [ ] سرویس systemd فعال و Wings در Panel آنلاین شود.
- [ ] Allocationها بررسی شوند.
- [ ] اولین سرور CS2 فقط پس از حل storage و فصل ادغام KUS اجرا شود.
- [ ] دسترسی اینترنتی به فصل شبکه موکول شود.

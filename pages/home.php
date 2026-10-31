<?php
/**
 * FragCity public landing page.
 * Dedicated destination for community discovery and private CS2 hosting.
 */
if (!defined('FRAGCITY_INDEX_RENDER')) {
    header('Location: /index.php', true, 303);
    exit;
}
?>

<section class="home-landing" dir="rtl" aria-labelledby="homeHeroTitle">
    <div class="home-atmosphere home-atmosphere-one" aria-hidden="true"></div>
    <div class="home-atmosphere home-atmosphere-two" aria-hidden="true"></div>
    <div class="home-shell">
        <section class="home-hero" data-fc-reveal="up">
            <div class="home-hero-copy">
                <div class="home-eyebrow">
                    <span class="home-live-dot" aria-hidden="true"></span>
                    FRAGCITY <span class="home-eyebrow-divider">/</span> GAME SERVER PLATFORM
                </div>
                <h1 id="homeHeroTitle">سرور گیم تو، <span>با کنترل کامل.</span></h1>
                <p class="home-hero-description">
                    فقط یک سرور بازی نخر؛ زیرساختی داشته باش که بتوانی آن را مدیریت کنی.
                    از منابع اختصاصی و گیم‌مودهای متنوع تا دسترسی تیمی و بکاپ تنظیمات،
                    همه‌چیز برای کامیونیتی خودت آماده است.
                </p>
                <div class="home-hero-actions">
                    <a class="home-button home-button-primary" href="/index.php?page=buyserveropen" data-ajax-nav data-slug="buyserveropen">
                        <span>ساخت سرور اختصاصی</span>
                        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    </a>
                    <a class="home-button home-button-secondary" href="/index.php?page=servers_vps" data-ajax-nav data-slug="servers_vps">
                        <i class="fa-solid fa-server" aria-hidden="true"></i>
                        <span>مشاهده سرورهای عمومی</span>
                    </a>
                </div>
                <div class="home-hero-meta">
                    <span><i class="fa-solid fa-microchip" aria-hidden="true"></i> زیرساخت Linux + Docker</span>
                    <span class="home-meta-separator" aria-hidden="true"></span>
                    <span><i class="fa-solid fa-sliders" aria-hidden="true"></i> مدیریت از پنل اختصاصی</span>
                </div>
            </div>

            <div class="home-hero-visual" aria-label="نمای گرافیکی زیرساخت سرور FragCity">
                <div class="home-visual-topline">
                    <span class="home-visual-status"><i aria-hidden="true"></i> SERVER INFRASTRUCTURE</span>
                    <span class="home-visual-code">FC / NODE 01</span>
                </div>
                <div class="home-server-stack">
                    <div class="home-server-unit">
                        <span class="home-server-unit-icon"><i class="fa-brands fa-linux" aria-hidden="true"></i></span>
                        <span class="home-server-unit-copy">
                            <strong>Linux Host</strong>
                            <small>Dedicated game environment</small>
                        </span>
                        <span class="home-server-unit-state">ONLINE</span>
                    </div>
                    <div class="home-server-connector" aria-hidden="true"><span></span></div>
                    <div class="home-container-grid">
                        <div class="home-container-card"><span class="home-container-icon"><i class="fa-brands fa-docker" aria-hidden="true"></i></span><strong>Container 01</strong><small>Server A</small></div>
                        <div class="home-container-card"><span class="home-container-icon"><i class="fa-brands fa-docker" aria-hidden="true"></i></span><strong>Container 02</strong><small>Server B</small></div>
                        <div class="home-container-card"><span class="home-container-icon"><i class="fa-brands fa-docker" aria-hidden="true"></i></span><strong>Container 03</strong><small>Server C</small></div>
                    </div>
                    <div class="home-visual-footer">
                        <span><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> ISOLATED FILES</span>
                        <span><i class="fa-solid fa-rotate" aria-hidden="true"></i> CENTRAL GAME UPDATES</span>
                    </div>
                </div>
                <div class="home-visual-float home-visual-float-top"><strong>64</strong><span>TICK / SEC</span></div>
                <div class="home-visual-float home-visual-float-bottom"><strong>+25</strong><span>GAME MODES</span></div>
                <div class="home-visual-glow" aria-hidden="true"></div>
            </div>
        </section>

        <section class="home-proof-strip" aria-label="ویژگی‌های کلیدی سرورهای FragCity" data-fc-reveal="up">
            <div class="home-proof-item"><strong>+25</strong><span>گیم‌مود و محتوای متنوع</span></div>
            <div class="home-proof-item"><strong>64 <small>Tick/s</small></strong><span>چرخه‌ی ۶۴ تیک در ثانیه در CS2</span></div>
            <div class="home-proof-item"><strong>زیر ۱ دقیقه</strong><span>هماهنگ‌سازی آپدیت فایل‌های مرکزی</span></div>
            <div class="home-proof-item"><strong>Dedicated</strong><span>منابع اختصاصی برای هر سرور</span></div>
        </section>

        <section class="home-section home-architecture" id="infrastructure" data-fc-reveal="up">
            <div class="home-section-heading home-section-heading-split">
                <div><span class="home-kicker">01 / زیرساخت و معماری</span><h2>هر سرور، محیط مستقل خودش را دارد.</h2></div>
                <p>
                    سرورهای FragCity روی Linux و Docker اجرا می‌شوند. فایل‌های هر سرور
                    از محیط سرورهای دیگر جداست؛ در نتیجه مدیریت و تغییرات هر سرویس،
                    محدوده‌ی مشخص خودش را دارد.
                </p>
            </div>
            <div class="home-architecture-panel">
                <div class="home-architecture-copy">
                    <span class="home-panel-icon"><i class="fa-solid fa-cubes-stacked" aria-hidden="true"></i></span>
                    <h3>جداسازی در سطح کانتینر</h3>
                    <p>
                        هر سرور در کانتینر اختصاصی خودش اجرا می‌شود و فایل‌های اختصاصی
                        همان سرور جداگانه نگهداری می‌شوند. این ساختار، مدیریت چند سرور
                        روی یک Node را منظم‌تر و قابل‌کنترل‌تر می‌کند.
                    </p>
                    <div class="home-inline-points">
                        <span><i class="fa-solid fa-check" aria-hidden="true"></i> محیط اجرایی مستقل</span>
                        <span><i class="fa-solid fa-check" aria-hidden="true"></i> فایل‌های جداگانه</span>
                        <span><i class="fa-solid fa-check" aria-hidden="true"></i> مدیریت منابع هر سرویس</span>
                    </div>
                </div>
                <div class="home-architecture-visual" aria-label="ساختار میزبان لینوکس و کانتینرهای مستقل">
                    <div class="home-architecture-host">
                        <span class="home-architecture-icon"><i class="fa-brands fa-linux" aria-hidden="true"></i></span>
                        <span><strong>Linux Node</strong><small>میزبان اصلی</small></span>
                        <span class="home-host-badge">HOST</span>
                    </div>
                    <div class="home-architecture-lines" aria-hidden="true"><i></i><i></i><i></i></div>
                    <div class="home-architecture-containers">
                        <div><i class="fa-brands fa-docker" aria-hidden="true"></i><strong>Docker</strong><small>Game Server 01</small></div>
                        <div><i class="fa-brands fa-docker" aria-hidden="true"></i><strong>Docker</strong><small>Game Server 02</small></div>
                        <div><i class="fa-brands fa-docker" aria-hidden="true"></i><strong>Docker</strong><small>Game Server 03</small></div>
                    </div>
                    <div class="home-architecture-caption"><i class="fa-solid fa-lock" aria-hidden="true"></i> فایل‌های هر سرور در محدوده‌ی خودش</div>
                </div>
            </div>
        </section>

        <section class="home-section home-update-section" data-fc-reveal="up">
            <div class="home-update-visual" aria-hidden="true">
                <div class="home-update-orbit"></div>
                <div class="home-update-core"><i class="fa-solid fa-arrows-rotate"></i></div>
                <span class="home-update-node home-update-node-one"><i class="fa-brands fa-docker"></i> SERVER 01</span>
                <span class="home-update-node home-update-node-two"><i class="fa-brands fa-docker"></i> SERVER 02</span>
                <span class="home-update-node home-update-node-three"><i class="fa-brands fa-docker"></i> SERVER 03</span>
                <span class="home-update-time"><i class="fa-solid fa-bolt"></i> LESS THAN 1 MIN</span>
            </div>
            <div class="home-update-copy">
                <span class="home-kicker">02 / CENTRAL GAME UPDATES</span>
                <h2>آپدیت بازی، بدون معطلی برای هر سرور.</h2>
                <p>
                    فایل‌های مرکزی بازی به‌صورت مشترک مدیریت می‌شوند. وقتی نسخه‌ی بازی
                    به‌روزرسانی شود، سرورهای شما با فایل‌های مرکزی هماهنگ می‌شوند؛
                    هدف این ساختار، رساندن آپدیت به سرورها در کمتر از یک دقیقه است.
                </p>
                <div class="home-update-highlight"><strong>کمتر از ۶۰ ثانیه</strong><span>زمان هدف برای هماهنگ‌سازی فایل‌های مرکزی بازی</span></div>
            </div>
        </section>

        <section class="home-section home-modes-section" data-fc-reveal="up">
            <div class="home-modes-heading">
                <span class="home-kicker">03 / GAME MODES & CONTENT</span>
                <h2>یک سرور؛ بیشتر از یک سبک بازی.</h2>
                <p>
                    به بیش از ۲۵ گیم‌مود و مجموعه‌ای از کانتنت‌های مختلف دسترسی داشته باش.
                    فضای سرورت را مطابق کامیونیتی خودت انتخاب و مدیریت کن.
                </p>
            </div>
            <div class="home-modes-showcase">
                <div class="home-mode-feature"><span class="home-mode-number">25<span>+</span></span><strong>گیم‌مود و محتوای قابل انتخاب</strong><small>MORE WAYS TO PLAY</small></div>
                <div class="home-mode-catalog">
                    <div class="home-mode-tile"><i class="fa-solid fa-crosshairs" aria-hidden="true"></i><span>گیم‌مودهای متنوع</span></div>
                    <div class="home-mode-tile"><i class="fa-solid fa-map" aria-hidden="true"></i><span>نقشه‌ها و مپ‌ها</span></div>
                    <div class="home-mode-tile"><i class="fa-solid fa-puzzle-piece" aria-hidden="true"></i><span>پلاگین‌ها</span></div>
                    <div class="home-mode-tile"><i class="fa-solid fa-gears" aria-hidden="true"></i><span>تنظیمات اختصاصی</span></div>
                    <div class="home-mode-tile"><i class="fa-solid fa-layer-group" aria-hidden="true"></i><span>کانتنت‌های جانبی</span></div>
                    <div class="home-mode-tile"><i class="fa-solid fa-rotate" aria-hidden="true"></i><span>تعویض و مدیریت مود</span></div>
                </div>
            </div>
        </section>

        <section class="home-section home-tick-section" data-fc-reveal="up">
            <div class="home-tick-panel">
                <div class="home-tick-copy">
                    <span class="home-kicker">04 / SUB-TICK INPUT TIMING</span>
                    <h2>زمان‌بندی دقیق ورودی‌ها با Sub-tick.</h2>
                    <p>
                        CS2 با چرخه‌ی ۶۴ تیک در ثانیه و معماری Sub-tick کار می‌کند.
                        زمان دقیق شلیک، حرکت و پرتاب درون هر بازه‌ی تیک ثبت می‌شود؛
                        بنابراین ورودی بازیکن فقط به مرز تیک بعدی وابسته نیست.
                    </p>
                    <div class="home-tick-tags">
                        <span><i class="fa-solid fa-gauge-high" aria-hidden="true"></i> 64 Tick / sec</span>
                        <span><i class="fa-solid fa-crosshairs" aria-hidden="true"></i> Sub-tick timing</span>
                        <span><i class="fa-solid fa-gamepad" aria-hidden="true"></i> ثبت دقیق زمان ورودی</span>
                    </div>
                </div>
                <div class="home-tick-visual" aria-label="128 Tick">
                    <span class="home-tick-ring home-tick-ring-one"></span>
                    <span class="home-tick-ring home-tick-ring-two"></span>
                    <div class="home-tick-value"><strong>64</strong><span>TICK + SUB-TICK</span></div>
                    <span class="home-tick-side-label">SERVER RATE</span>
                </div>
            </div>
        </section>

        <section class="home-section home-resources-section" data-fc-reveal="up">
            <div class="home-section-heading">
                <span class="home-kicker">05 / DEDICATED RESOURCES</span>
                <h2>منابع سرورت مشخص است؛ سهم خودت را مدیریت کن.</h2>
                <p>برای هر سرویس، منابع اختصاصی تعریف می‌شود تا ظرفیت سرور بر اساس نیاز و پلن انتخابی‌ات در دسترس باشد.</p>
            </div>
            <div class="home-resource-grid">
                <article class="home-resource-card">
                    <span class="home-resource-icon"><i class="fa-solid fa-microchip" aria-hidden="true"></i></span>
                    <span class="home-resource-label">PROCESSING</span>
                    <h3>CPU اختصاصی</h3>
                    <p>ظرفیت پردازشی مشخص برای اجرای سرور و پردازش‌های مربوط به آن.</p>
                    <span class="home-resource-line"><i></i></span>
                </article>
                <article class="home-resource-card">
                    <span class="home-resource-icon"><i class="fa-solid fa-memory" aria-hidden="true"></i></span>
                    <span class="home-resource-label">MEMORY</span>
                    <h3>RAM اختصاصی</h3>
                    <p>مقدار حافظه‌ی مشخص برای اجرای بازی، مودها و پلاگین‌های سرور.</p>
                    <span class="home-resource-line"><i></i></span>
                </article>
                <article class="home-resource-card">
                    <span class="home-resource-icon"><i class="fa-solid fa-hard-drive" aria-hidden="true"></i></span>
                    <span class="home-resource-label">STORAGE</span>
                    <h3>فضای ذخیره‌سازی</h3>
                    <p>فضای مشخص برای فایل‌های سرور، تنظیمات و محتوای موردنیاز.</p>
                    <span class="home-resource-line"><i></i></span>
                </article>
            </div>
        </section>

        <section class="home-section home-control-section" data-fc-reveal="up">
            <div class="home-section-heading home-section-heading-split">
                <div><span class="home-kicker">06 / SERVER CONTROL</span><h2>پنل مدیریت، در اختیار خودت.</h2></div>
                <p>
                    مدیریت سرور نباید به ارسال تیکت برای هر تغییر کوچک وابسته باشد.
                    ابزارهای اصلی مدیریت را در پنل خودت در اختیار داشته باش.
                </p>
            </div>
            <div class="home-control-grid">
                <article class="home-control-card">
                    <div class="home-control-card-top"><span class="home-control-icon"><i class="fa-solid fa-folder-open" aria-hidden="true"></i></span><span class="home-control-index">01 / FILES</span></div>
                    <h3>مدیریت فایل‌های سرور</h3>
                    <p>فایل‌های سرور را از طریق پنل مدیریت کن؛ تنظیمات را ویرایش کن و ساختار فایل‌های موردنیازت را در دسترس داشته باش.</p>
                    <div class="home-file-preview" aria-hidden="true">
                        <div class="home-file-preview-bar"><span></span><span></span><span></span><small>SERVER FILES</small></div>
                        <div class="home-file-row"><i class="fa-solid fa-folder"></i><span>addons</span><small>FOLDER</small></div>
                        <div class="home-file-row"><i class="fa-solid fa-folder"></i><span>cfg</span><small>FOLDER</small></div>
                        <div class="home-file-row"><i class="fa-solid fa-file-code"></i><span>server.cfg</span><small>CONFIG</small></div>
                    </div>
                </article>
                <article class="home-control-card">
                    <div class="home-control-card-top"><span class="home-control-icon"><i class="fa-solid fa-users-gear" aria-hidden="true"></i></span><span class="home-control-index">02 / TEAM ACCESS</span></div>
                    <h3>دعوت از دوستان با Sub-user</h3>
                    <p>دوستان یا هم‌تیمی‌هایت را به مدیریت سرور دعوت کن و دسترسی‌های موردنیازشان را جداگانه تعریف کن؛ مدیریت سرور فقط دست یک نفر نیست.</p>
                    <div class="home-team-preview" aria-hidden="true">
                        <div class="home-team-avatar"><i class="fa-solid fa-user"></i></div>
                        <div class="home-team-avatar home-team-avatar-two"><i class="fa-solid fa-user"></i></div>
                        <div class="home-team-avatar home-team-avatar-three"><i class="fa-solid fa-user"></i></div>
                        <div class="home-team-add"><i class="fa-solid fa-plus"></i></div>
                        <span>دعوت و مدیریت اعضای تیم</span>
                    </div>
                </article>
            </div>
        </section>

        <section class="home-section home-backup-section" data-fc-reveal="up">
            <div class="home-backup-visual" aria-hidden="true">
                <div class="home-backup-cloud"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                <div class="home-backup-orbit"></div>
                <div class="home-backup-file home-backup-file-one"><i class="fa-solid fa-file-code"></i><span>server.cfg</span><small>CONFIG</small></div>
                <div class="home-backup-file home-backup-file-two"><i class="fa-solid fa-plug"></i><span>plugins</span><small>ADDONS</small></div>
                <div class="home-backup-check"><i class="fa-solid fa-check"></i> BACKUP READY</div>
            </div>
            <div class="home-backup-copy">
                <span class="home-kicker">07 / BACKUP & RESTORE</span>
                <h2>تنظیمات و پلاگین‌هایت را حفظ کن.</h2>
                <p>
                    از تنظیمات و پلاگین‌های سرور بکاپ بگیر تا نسخه‌ای از پیکربندی
                    موردعلاقه‌ات داشته باشی. برای تغییرات مهم، یک نقطه‌ی بازگشت مطمئن‌تر بساز.
                </p>
                <div class="home-backup-benefits">
                    <span><i class="fa-solid fa-check-circle" aria-hidden="true"></i> نگهداری نسخه از تنظیمات</span>
                    <span><i class="fa-solid fa-check-circle" aria-hidden="true"></i> بکاپ از پلاگین‌ها</span>
                    <span><i class="fa-solid fa-check-circle" aria-hidden="true"></i> آماده برای بازیابی پیکربندی</span>
                </div>
            </div>
        </section>

        <section class="home-final-cta" data-fc-reveal="up">
            <div class="home-final-cta-glow" aria-hidden="true"></div>
            <div class="home-final-cta-copy">
                <span class="home-kicker">YOUR SERVER. YOUR RULES.</span>
                <h2>کامیونیتی خودت را راه‌اندازی کن.</h2>
                <p>سرور اختصاصی‌ات را بساز و مدیریت آن را در دست بگیر.</p>
            </div>
            <a class="home-button home-button-primary" href="/index.php?page=buyserveropen" data-ajax-nav data-slug="buyserveropen">
                <span>شروع ساخت سرور</span>
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            </a>
        </section>
    </div>
</section>

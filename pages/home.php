<?php
/**
 * FragCity public landing page.
 * The public server directory is intentionally a separate destination.
 */
if (!defined('FRAGCITY_INDEX_RENDER')) {
    header('Location: /index.php', true, 303);
    exit;
}
?>

<section class="home-landing" dir="rtl">
    <div class="home-shell">
        <header class="home-hero" data-fc-reveal="up">
            <div class="home-hero-copy">
                <div class="home-eyebrow">
                    <span class="home-eyebrow-dot"></span>
                    FRAGCITY <span>/</span> CS2 COMMUNITY
                </div>

                <h1>سرور بعدی‌ات رو پیدا کن<span>.</span></h1>
                <p>
                    سرورهای کامیونیتی FragCity را ببین، وضعیت و نقشه را بررسی کن
                    و مستقیم وارد بازی شو.
                </p>

                <div class="home-hero-actions">
                    <a class="home-primary-btn" href="index.php?page=servers_vps">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        جستجوی سرورها
                        <i class="fa-solid fa-arrow-left home-btn-arrow" aria-hidden="true"></i>
                    </a>
                    <a class="home-secondary-btn" href="index.php?page=buyserveropen">
                        <i class="fa-solid fa-server" aria-hidden="true"></i>
                        ساخت سرور اختصاصی
                    </a>
                </div>

                <div class="home-hero-footnote">
                    <span><i class="fa-solid fa-gamepad" aria-hidden="true"></i> Counter-Strike 2</span>
                    <span class="home-footnote-separator"></span>
                    <span><i class="fa-solid fa-users" aria-hidden="true"></i> Community first</span>
                </div>
            </div>

            <div class="home-hero-art" aria-hidden="true">
                <div class="home-art-grid"></div>
                <div class="home-art-orbit home-art-orbit-one"></div>
                <div class="home-art-orbit home-art-orbit-two"></div>
                <div class="home-art-orbit home-art-orbit-three"></div>
                <div class="home-art-center">
                    <span class="home-art-crosshair"><i class="fa-solid fa-crosshairs"></i></span>
                    <strong>CS2</strong>
                    <small>FRAGCITY</small>
                </div>
                <span class="home-art-label home-art-label-top">PLAY TOGETHER</span>
                <span class="home-art-label home-art-label-bottom">BUILT FOR COMMUNITY</span>
                <span class="home-art-coordinate">35°41' N / 51°25' E</span>
            </div>
        </header>

        <section class="home-introduction" data-fc-reveal="up">
            <div class="home-section-heading">
                <span class="home-section-kicker">WELCOME TO FRAGCITY</span>
                <h2>همه‌چیز برای تجربه بهتر CS2، یک‌جا.</h2>
                <p>
                    FragCity فضایی برای دسترسی به سرورهای کامیونیتی، شخصی‌سازی تجربه بازی
                    و مدیریت سرویس‌های سرور Counter-Strike 2 است.
                </p>
            </div>

            <div class="home-feature-grid">
                <a class="home-feature-card home-feature-card-primary" href="index.php?page=servers_vps">
                    <span class="home-feature-icon"><i class="fa-solid fa-server" aria-hidden="true"></i></span>
                    <span class="home-feature-kicker">FIND A MATCH</span>
                    <h3>سرورهای کامیونیتی</h3>
                    <p>سرورها را بر اساس گیم‌مود، نقشه و وضعیت پیدا کن و با یک کلیک به بازی وصل شو.</p>
                    <span class="home-feature-link">مشاهده سرورها <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></span>
                    <span class="home-feature-watermark" aria-hidden="true"><i class="fa-solid fa-crosshairs"></i></span>
                </a>

                <a class="home-feature-card" href="index.php?page=skins">
                    <span class="home-feature-icon"><i class="fa-solid fa-palette" aria-hidden="true"></i></span>
                    <span class="home-feature-kicker">MAKE IT YOURS</span>
                    <h3>WeaponPaints</h3>
                    <p>اسکین‌ها و آیتم‌های دلخواهت را انتخاب کن و Loadout مخصوص خودت را بساز.</p>
                    <span class="home-feature-link">رفتن به WeaponPaints <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></span>
                    <span class="home-feature-watermark" aria-hidden="true"><i class="fa-solid fa-spray-can-sparkles"></i></span>
                </a>

                <a class="home-feature-card" href="index.php?page=buyserveropen">
                    <span class="home-feature-icon"><i class="fa-solid fa-cubes-stacked" aria-hidden="true"></i></span>
                    <span class="home-feature-kicker">YOUR OWN SPACE</span>
                    <h3>سرور اختصاصی</h3>
                    <p>برای کامیونیتی خودت سرور بساز و فضای بازی را مطابق نیازت مدیریت کن.</p>
                    <span class="home-feature-link">مشاهده سرویس‌ها <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></span>
                    <span class="home-feature-watermark" aria-hidden="true"><i class="fa-solid fa-network-wired"></i></span>
                </a>
            </div>
        </section>

        <section class="home-bottom-cta" data-fc-reveal="up">
            <div class="home-bottom-icon"><i class="fa-solid fa-fire-flame-curved" aria-hidden="true"></i></div>
            <div class="home-bottom-copy">
                <span class="home-section-kicker">READY WHEN YOU ARE</span>
                <h2>بازی بعدی از همین‌جا شروع می‌شه.</h2>
                <p>لیست سرورها را باز کن و کامیونیتی مناسب خودت را پیدا کن.</p>
            </div>
            <a href="index.php?page=servers_vps" class="home-bottom-link">
                ورود به Server Browser <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            </a>
        </section>
    </div>
</section>

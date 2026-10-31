<?php
/**
 * FragCity brand-first public landing page.
 * Product panel and Wiki implementation are intentionally out of scope here.
 */
if (!defined('FRAGCITY_INDEX_RENDER')) {
    header('Location: /index.php', true, 303);
    exit;
}
?>

<section class="home-landing" dir="rtl" aria-labelledby="homeHeroTitle">
    <div class="home-background-grid" aria-hidden="true"></div>
    <div class="home-glow home-glow-one" aria-hidden="true"></div>
    <div class="home-glow home-glow-two" aria-hidden="true"></div>

    <div class="home-shell">
        <!-- HERO -->
        <section class="home-hero" aria-labelledby="homeHeroTitle">
            <div class="home-hero-copy">
                <div class="home-eyebrow" data-fc-reveal="up">
                    <span class="home-eyebrow-mark"><i class="fa-solid fa-bolt" aria-hidden="true"></i></span>
                    <span>FRAGCITY</span>
                    <span class="home-eyebrow-divider">/</span>
                    <span>BUILT FOR GAME COMMUNITIES</span>
                </div>

                <h1 id="homeHeroTitle" data-fc-reveal="up" data-fc-reveal-delay="70">
                    سرور خودت را بساز؛
                    <span>کامیونیتی خودت را شکل بده.</span>
                </h1>

                <p class="home-hero-description" data-fc-reveal="up" data-fc-reveal-delay="140">
                    FragCity فضایی برای راه‌اندازی سرورهای بازی و رشد کامیونیتی‌های گیمینگ است؛
                    از اولین قدم برای ساخت سرور تا تجربه‌ای که بازیکن‌ها کنار هم می‌سازند.
                </p>

                <div class="home-hero-actions" data-fc-reveal="up" data-fc-reveal-delay="210">
                    <a class="home-button home-button-primary" href="/index.php?page=buyserveropen" data-ajax-nav data-slug="buyserveropen">
                        <span>شروع با FragCity</span>
                        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    </a>
                    <a class="home-button home-button-secondary" href="#home-ecosystem">
                        <span>آشنایی با سرویس‌ها</span>
                        <i class="fa-solid fa-arrow-down" aria-hidden="true"></i>
                    </a>
                </div>

                <div class="home-hero-signals" data-fc-reveal="up" data-fc-reveal-delay="270">
                    <span><i class="fa-solid fa-gamepad" aria-hidden="true"></i> برای دنیای گیمینگ</span>
                    <span><i class="fa-solid fa-users" aria-hidden="true"></i> با محوریت کامیونیتی</span>
                    <span><i class="fa-solid fa-layer-group" aria-hidden="true"></i> یک اکوسیستم یکپارچه</span>
                </div>
            </div>

            <div class="home-hero-visual" data-fc-reveal="left" aria-label="نمای گرافیکی اکوسیستم FragCity">
                <div class="home-visual-halo" aria-hidden="true"></div>
                <div class="home-visual-orbit home-visual-orbit-one" aria-hidden="true"></div>
                <div class="home-visual-orbit home-visual-orbit-two" aria-hidden="true"></div>

                <div class="home-visual-topline">
                    <span class="home-visual-brand">
                        <span class="home-visual-brand-mark">FC</span>
                        <span><strong>FRAGCITY</strong><small>YOUR GAMING SPACE</small></span>
                    </span>
                    <span class="home-visual-live"><i aria-hidden="true"></i> COMMUNITY PLATFORM</span>
                </div>

                <div class="home-visual-main-card fc-glass-surface">
                    <div class="home-visual-card-heading">
                        <span class="home-visual-card-icon"><i class="fa-solid fa-cubes-stacked" aria-hidden="true"></i></span>
                        <span><small>WELCOME TO</small><strong>YOUR NEXT GAME SPACE</strong></span>
                        <i class="fa-solid fa-ellipsis" aria-hidden="true"></i>
                    </div>

                    <div class="home-visual-feature">
                        <div class="home-visual-feature-art">
                            <span class="home-art-crosshair" aria-hidden="true"></span>
                            <i class="fa-solid fa-gamepad" aria-hidden="true"></i>
                            <span class="home-art-orbit" aria-hidden="true"></span>
                        </div>
                        <div class="home-visual-feature-copy">
                            <span class="home-visual-label">MAKE IT YOURS</span>
                            <strong>فضای بازی، به سبک تو</strong>
                            <small>از یک سرور شروع کن؛ یک جامعه بساز.</small>
                        </div>
                    </div>

                    <div class="home-visual-shortcuts">
                        <div class="home-visual-shortcut">
                            <span class="home-shortcut-icon"><i class="fa-solid fa-server" aria-hidden="true"></i></span>
                            <span><strong>Game Servers</strong><small>سرورهای اختصاصی</small></span>
                            <i class="fa-solid fa-arrow-up-left" aria-hidden="true"></i>
                        </div>
                        <div class="home-visual-shortcut">
                            <span class="home-shortcut-icon home-shortcut-icon-warm"><i class="fa-solid fa-users" aria-hidden="true"></i></span>
                            <span><strong>Community</strong><small>بازیکن‌ها و کامیونیتی</small></span>
                            <i class="fa-solid fa-arrow-up-left" aria-hidden="true"></i>
                        </div>
                    </div>
                </div>

                <div class="home-visual-float home-visual-float-top fc-glass-surface">
                    <span class="home-float-icon"><i class="fa-solid fa-bolt" aria-hidden="true"></i></span>
                    <span><strong>MAKE</strong><small>YOUR SPACE</small></span>
                </div>
                <div class="home-visual-float home-visual-float-bottom fc-glass-surface">
                    <span class="home-float-icon"><i class="fa-solid fa-users" aria-hidden="true"></i></span>
                    <span><strong>PLAY</strong><small>TOGETHER</small></span>
                </div>
            </div>
        </section>

        <div class="home-scroll-cue" aria-hidden="true">
            <span></span>
            <small>SCROLL TO EXPLORE</small>
        </div>

        <!-- WHAT IS FRAGCITY -->
        <section class="home-section home-intro-section" id="home-about" aria-labelledby="homeIntroTitle">
            <div class="home-intro-copy" data-fc-reveal="up">
                <span class="home-kicker"><span>01</span> / WHAT IS FRAGCITY?</span>
                <h2 id="homeIntroTitle">فراتر از یک سرور بازی.</h2>
                <p>
                    FragCity با یک ایده شکل گرفته: ساختن فضایی که در آن راه‌اندازی سرور،
                    مدیریت تجربه‌ی بازی و ارتباط بازیکن‌ها مسیر ساده‌تر و منسجم‌تری داشته باشد.
                    ما می‌خواهیم ابزارهای موردنیاز یک کامیونیتی، به‌جای پراکنده بودن،
                    در یک تجربه‌ی واحد کنار هم قرار بگیرند.
                </p>
                <a class="home-text-link" href="/index.php?page=about" data-ajax-nav data-slug="about">
                    بیشتر درباره FragCity
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                </a>
            </div>

            <div class="home-intro-side" data-fc-reveal="left" data-fc-reveal-delay="100">
                <div class="home-intro-orbit" aria-hidden="true"></div>
                <div class="home-intro-mark">FC<span>.</span></div>
                <div class="home-intro-caption">
                    <span>ONE PLACE</span>
                    <strong>FOR YOUR<br>GAME COMMUNITY</strong>
                </div>
                <div class="home-intro-side-note"><i class="fa-solid fa-circle-nodes" aria-hidden="true"></i> PLAY · CONNECT · BUILD</div>
            </div>
        </section>

        <!-- BUILT FOR COMMUNITIES -->
        <section class="home-section home-community-section" id="home-communities" aria-labelledby="homeCommunityTitle">
            <div class="home-section-heading" data-fc-reveal="up">
                <span class="home-kicker"><span>02</span> / BUILT FOR COMMUNITIES</span>
                <h2 id="homeCommunityTitle">برای آدم‌هایی که بازی را دور هم جمع می‌کنند.</h2>
                <p>چه یک سرور کوچک برای دوستانت بخواهی، چه در حال ساختن یک کامیونیتی باشی، FragCity برای شروع این مسیر طراحی شده است.</p>
            </div>

            <div class="home-community-grid">
                <article class="home-community-card fc-glass-surface" data-fc-reveal="up" data-fc-reveal-delay="0">
                    <div class="home-community-card-top"><span class="home-card-index">01 / SERVER OWNERS</span><span class="home-card-icon"><i class="fa-solid fa-server" aria-hidden="true"></i></span></div>
                    <h3>برای صاحب‌های سرور</h3>
                    <p>فضای بازی خودت را راه‌اندازی کن و تجربه‌ای بساز که با سبک و نیاز بازیکن‌هایت هماهنگ باشد.</p>
                    <span class="home-community-card-foot">BUILD YOUR SERVER <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></span>
                </article>

                <article class="home-community-card fc-glass-surface" data-fc-reveal="up" data-fc-reveal-delay="90">
                    <div class="home-community-card-top"><span class="home-card-index">02 / FRIEND GROUPS</span><span class="home-card-icon"><i class="fa-solid fa-gamepad" aria-hidden="true"></i></span></div>
                    <h3>برای گروه‌های دوستانه</h3>
                    <p>یک فضای اختصاصی برای دورهمی، بازی و ساختن خاطره‌های مشترک با دوستانت داشته باش.</p>
                    <span class="home-community-card-foot">PLAY TOGETHER <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></span>
                </article>

                <article class="home-community-card fc-glass-surface" data-fc-reveal="up" data-fc-reveal-delay="180">
                    <div class="home-community-card-top"><span class="home-card-index">03 / COMMUNITY BUILDERS</span><span class="home-card-icon"><i class="fa-solid fa-people-group" aria-hidden="true"></i></span></div>
                    <h3>برای مدیرهای کامیونیتی</h3>
                    <p>بازیکن‌ها را کنار هم نگه دار، سرویس‌های کامیونیتی را توسعه بده و برای رشد جمع خودت برنامه داشته باش.</p>
                    <span class="home-community-card-foot">GROW YOUR COMMUNITY <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></span>
                </article>
            </div>
        </section>

        <!-- OUR ECOSYSTEM -->
        <section class="home-section home-ecosystem-section" id="home-ecosystem" aria-labelledby="homeEcosystemTitle">
            <div class="home-section-heading home-section-heading-split" data-fc-reveal="up">
                <div>
                    <span class="home-kicker"><span>03</span> / OUR ECOSYSTEM</span>
                    <h2 id="homeEcosystemTitle">هر چیزی که کامیونیتی‌ات از FragCity می‌خواهد.</h2>
                </div>
                <p>از پیدا کردن یک سرور برای بازی تا راه‌اندازی فضای اختصاصی خودت؛ سرویس‌های FragCity در کنار هم یک مسیر کامل‌تر می‌سازند.</p>
            </div>

            <div class="home-ecosystem-grid">
                <a class="home-ecosystem-card home-ecosystem-card-featured fc-glass-surface" href="/index.php?page=buyserveropen" data-ajax-nav data-slug="buyserveropen" data-fc-reveal="up">
                    <div class="home-ecosystem-art home-ecosystem-art-server" aria-hidden="true">
                        <span class="home-ecosystem-art-orbit"></span>
                        <i class="fa-solid fa-server"></i>
                        <span class="home-art-node home-art-node-one"></span>
                        <span class="home-art-node home-art-node-two"></span>
                    </div>
                    <div class="home-ecosystem-content">
                        <span class="home-ecosystem-label">HOSTING</span>
                        <h3>سرورهای اختصاصی بازی</h3>
                        <p>فضای اختصاصی خودت را برای بازی و کامیونیتی راه‌اندازی کن.</p>
                        <span class="home-ecosystem-link">مشاهده سرویس <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></span>
                    </div>
                    <i class="fa-solid fa-arrow-up-left home-ecosystem-arrow" aria-hidden="true"></i>
                </a>

                <a class="home-ecosystem-card fc-glass-surface" href="/index.php?page=servers_vps" data-ajax-nav data-slug="servers_vps" data-fc-reveal="up" data-fc-reveal-delay="90">
                    <div class="home-ecosystem-art home-ecosystem-art-browser" aria-hidden="true">
                        <span class="home-browser-line"></span><span class="home-browser-line"></span><span class="home-browser-line"></span>
                        <i class="fa-solid fa-tower-broadcast"></i>
                    </div>
                    <div class="home-ecosystem-content">
                        <span class="home-ecosystem-label">DISCOVER</span>
                        <h3>سرورهای عمومی</h3>
                        <p>سرورهای عمومی را پیدا کن و به جمع بازیکن‌ها بپیوند.</p>
                        <span class="home-ecosystem-link">مشاهده سرورها <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></span>
                    </div>
                    <i class="fa-solid fa-arrow-up-left home-ecosystem-arrow" aria-hidden="true"></i>
                </a>

                <a class="home-ecosystem-card fc-glass-surface" href="/index.php?page=leaderboard" data-ajax-nav data-slug="leaderboard" data-fc-reveal="up" data-fc-reveal-delay="180">
                    <div class="home-ecosystem-art home-ecosystem-art-community" aria-hidden="true">
                        <span class="home-community-orbit"></span>
                        <i class="fa-solid fa-people-group"></i>
                        <span class="home-community-dot home-community-dot-one"></span>
                        <span class="home-community-dot home-community-dot-two"></span>
                        <span class="home-community-dot home-community-dot-three"></span>
                    </div>
                    <div class="home-ecosystem-content">
                        <span class="home-ecosystem-label">COMMUNITY</span>
                        <h3>تجربه‌ی کامیونیتی</h3>
                        <p>ابزارهای بازیکن‌محور FragCity را در کنار سرورها تجربه کن.</p>
                        <span class="home-ecosystem-link">کشف امکانات <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></span>
                    </div>
                    <i class="fa-solid fa-arrow-up-left home-ecosystem-arrow" aria-hidden="true"></i>
                </a>
            </div>
        </section>

        <!-- INSIDE FRAGCITY -->
        <section class="home-section home-inside-section" id="home-inside" aria-labelledby="homeInsideTitle">
            <div class="home-section-heading" data-fc-reveal="up">
                <span class="home-kicker"><span>04</span> / INSIDE FRAGCITY</span>
                <h2 id="homeInsideTitle">تجربه‌ی مدیریت، از یک نقطه.</h2>
                <p>می‌خواهیم مدیریت سرور و سرویس‌ها تجربه‌ای روشن و قابل‌فهم باشد. این تصویر، پیش‌نمایش مفهومی از جهتی است که برای تجربه‌ی FragCity در نظر گرفته‌ایم.</p>
            </div>

            <div class="home-dashboard-stage" data-fc-reveal="up" data-fc-reveal-delay="100">
                <div class="home-dashboard-window fc-glass-surface">
                    <div class="home-dashboard-sidebar">
                        <div class="home-dashboard-brand"><span>FC</span><strong>FRAGCITY</strong></div>
                        <div class="home-dashboard-nav">
                            <span class="is-active"><i class="fa-solid fa-grid-2" aria-hidden="true"></i> نمای کلی</span>
                            <span><i class="fa-solid fa-server" aria-hidden="true"></i> سرورهای من</span>
                            <span><i class="fa-solid fa-users" aria-hidden="true"></i> اعضای تیم</span>
                            <span><i class="fa-solid fa-gear" aria-hidden="true"></i> تنظیمات</span>
                        </div>
                        <div class="home-dashboard-sidebar-bottom"><i class="fa-solid fa-circle-question" aria-hidden="true"></i><span>مرکز راهنما</span></div>
                    </div>
                    <div class="home-dashboard-main">
                        <div class="home-dashboard-topbar">
                            <span><small>YOUR SPACE</small><strong>نمای کلی سرویس‌ها</strong></span>
                            <span class="home-dashboard-avatar">FC</span>
                        </div>
                        <div class="home-dashboard-welcome">
                            <span><small>WELCOME TO YOUR CONTROL SPACE</small><strong>همه‌چیز از همین‌جا شروع می‌شود.</strong></span>
                            <span class="home-dashboard-welcome-icon"><i class="fa-solid fa-shapes" aria-hidden="true"></i></span>
                        </div>
                        <div class="home-dashboard-placeholder-grid">
                            <div class="home-dashboard-placeholder"><span><i class="fa-solid fa-server" aria-hidden="true"></i> سرویس‌های تو</span><strong></strong><small></small></div>
                            <div class="home-dashboard-placeholder"><span><i class="fa-solid fa-users" aria-hidden="true"></i> اعضای کامیونیتی</span><strong></strong><small></small></div>
                            <div class="home-dashboard-placeholder home-dashboard-placeholder-wide"><span><i class="fa-solid fa-chart-line" aria-hidden="true"></i> نمای کلی فعالیت</span><div class="home-dashboard-bars"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div></div>
                        </div>
                        <div class="home-dashboard-bottom-note"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> این بخش، پیش‌نمایش طراحی است؛ جزئیات پنل در مرحله‌ی بعد معرفی می‌شود.</div>
                    </div>
                </div>
                <div class="home-dashboard-glow" aria-hidden="true"></div>
            </div>
        </section>

        <!-- HOW IT WORKS -->
        <section class="home-section home-steps-section" id="home-how-it-works" aria-labelledby="homeStepsTitle">
            <div class="home-section-heading" data-fc-reveal="up">
                <span class="home-kicker"><span>05</span> / HOW IT WORKS</span>
                <h2 id="homeStepsTitle">شروع مسیر، ساده‌تر از چیزی است که فکر می‌کنی.</h2>
                <p>برای شروع لازم نیست همه‌چیز را از قبل بدانی؛ قدم‌به‌قدم فضای مناسب خودت را انتخاب کن.</p>
            </div>

            <div class="home-steps-grid">
                <article class="home-step" data-fc-reveal="up" data-fc-reveal-delay="0">
                    <span class="home-step-number">01</span>
                    <span class="home-step-icon"><i class="fa-solid fa-compass" aria-hidden="true"></i></span>
                    <h3>سرویس مناسب را پیدا کن</h3>
                    <p>با توجه به سبک بازی و نیاز کامیونیتی‌ات، از بین سرویس‌های FragCity انتخاب کن.</p>
                </article>
                <article class="home-step" data-fc-reveal="up" data-fc-reveal-delay="100">
                    <span class="home-step-number">02</span>
                    <span class="home-step-icon"><i class="fa-solid fa-sliders" aria-hidden="true"></i></span>
                    <h3>فضای خودت را بساز</h3>
                    <p>انتخاب‌ها و تنظیمات موردنیازت را مشخص کن و تجربه‌ی بازی‌ات را شکل بده.</p>
                </article>
                <article class="home-step" data-fc-reveal="up" data-fc-reveal-delay="200">
                    <span class="home-step-number">03</span>
                    <span class="home-step-icon"><i class="fa-solid fa-rocket" aria-hidden="true"></i></span>
                    <h3>شروع کن و گسترش بده</h3>
                    <p>بازیکن‌ها را دعوت کن و با بزرگ‌تر شدن کامیونیتی، فضای خودت را توسعه بده.</p>
                </article>
            </div>
        </section>

        <!-- WHY FRAGCITY -->
        <section class="home-section home-why-section" id="home-why" aria-labelledby="homeWhyTitle">
            <div class="home-why-intro" data-fc-reveal="up">
                <span class="home-kicker"><span>06</span> / WHY FRAGCITY?</span>
                <h2 id="homeWhyTitle">تمرکز روی چیزی که واقعاً مهم است: تجربه‌ی بازی.</h2>
                <p>زیرساخت مهم است؛ اما در نهایت چیزی که می‌ماند، تجربه‌ای است که برای خودت و کامیونیتی‌ات می‌سازی.</p>
            </div>
            <div class="home-why-list">
                <div class="home-why-item fc-glass-surface" data-fc-reveal="up" data-fc-reveal-delay="0">
                    <span class="home-why-icon"><i class="fa-solid fa-fingerprint" aria-hidden="true"></i></span>
                    <span><strong>فضای مخصوص کامیونیتی تو</strong><small>سرویس و تجربه‌ای که می‌توانی مطابق نیاز جمع خودت شکل بدهی.</small></span>
                    <span class="home-why-index">01</span>
                </div>
                <div class="home-why-item fc-glass-surface" data-fc-reveal="up" data-fc-reveal-delay="80">
                    <span class="home-why-icon"><i class="fa-solid fa-shapes" aria-hidden="true"></i></span>
                    <span><strong>سرویس‌ها در کنار هم</strong><small>از سرورهای اختصاصی تا سرورهای عمومی و امکانات کامیونیتی.</small></span>
                    <span class="home-why-index">02</span>
                </div>
                <div class="home-why-item fc-glass-surface" data-fc-reveal="up" data-fc-reveal-delay="160">
                    <span class="home-why-icon"><i class="fa-solid fa-compass" aria-hidden="true"></i></span>
                    <span><strong>مسیر روشن برای شروع</strong><small>انتخاب سرویس، ساخت فضای خودت و توسعه‌ی کامیونیتی در یک مسیر مشخص.</small></span>
                    <span class="home-why-index">03</span>
                </div>
            </div>
        </section>

        <!-- FINAL CTA -->
        <section class="home-final-cta" aria-labelledby="homeFinalTitle" data-fc-reveal="up">
            <div class="home-final-cta-glow" aria-hidden="true"></div>
            <div class="home-final-cta-copy">
                <span class="home-kicker"><span>07</span> / READY WHEN YOU ARE</span>
                <h2 id="homeFinalTitle">جای کامیونیتی تو اینجاست.</h2>
                <p>از اولین سرور شروع کن و فضایی بساز که بازیکن‌ها دوست داشته باشند به آن برگردند.</p>
            </div>
            <div class="home-final-cta-actions">
                <a class="home-button home-button-primary" href="/index.php?page=buyserveropen" data-ajax-nav data-slug="buyserveropen">
                    <span>شروع با FragCity</span>
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                </a>
                <a class="home-final-link" href="/index.php?page=servers_vps" data-ajax-nav data-slug="servers_vps">یا سرورهای عمومی را ببین</a>
            </div>
        </section>
    </div>
</section>

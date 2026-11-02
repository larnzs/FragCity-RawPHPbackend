<?php
/**
 * Public FragCity service status page backed by a published Uptime Kuma page.
 */
if (!defined('FRAGCITY_INDEX_RENDER')) {
    header('Location: /index.php?page=status', true, 303);
    exit;
}
?>

<section class="fc-status-page" id="fragcityStatusPage" dir="rtl" data-status-endpoint="/api/public-status.php">
    <div class="fc-status-shell">
        <header class="fc-status-hero" data-fc-reveal="up">
            <div class="fc-status-hero-copy">
                <span class="fc-status-eyebrow">
                    <span class="fc-status-eyebrow-dot"></span>
                    FRAGCITY <span>/</span> SERVICE STATUS
                </span>
                <h1>وضعیت سرویس‌ها</h1>
                <p>سلامت سرویس‌های عمومی FragCity، وضعیت سرور بازی و دسترسی API را از این صفحه بررسی کن.</p>
                <div class="fc-status-overall" data-status-overall="unknown" aria-live="polite">
                    <span class="fc-status-overall-dot"></span>
                    <span class="fc-status-overall-copy">
                        <strong data-status-overall-label>در حال دریافت وضعیت...</strong>
                        <small data-status-overall-description>در حال ارتباط با سامانه مانیتورینگ</small>
                    </span>
                </div>
            </div>
            <div class="fc-status-hero-art" aria-hidden="true">
                <span class="fc-status-orbit fc-status-orbit-one"></span>
                <span class="fc-status-orbit fc-status-orbit-two"></span>
                <span class="fc-status-hero-core"><i class="fa-solid fa-heart-pulse"></i></span>
                <span class="fc-status-hero-tag">LIVE MONITORING</span>
            </div>
        </header>

        <section class="fc-status-summary" aria-label="خلاصه وضعیت">
            <article class="fc-status-summary-card" data-fc-reveal="up">
                <span class="fc-status-summary-icon fc-status-icon-green"><i class="fa-solid fa-circle-check"></i></span>
                <div>
                    <span>سرویس‌های عملیاتی</span>
                    <strong data-status-summary="operational">—</strong>
                </div>
            </article>
            <article class="fc-status-summary-card" data-fc-reveal="up" data-fc-reveal-delay="60">
                <span class="fc-status-summary-icon fc-status-icon-orange"><i class="fa-solid fa-server"></i></span>
                <div>
                    <span>سرویس‌های مانیتورشده</span>
                    <strong data-status-summary="total">—</strong>
                </div>
            </article>
            <article class="fc-status-summary-card" data-fc-reveal="up" data-fc-reveal-delay="120">
                <span class="fc-status-summary-icon fc-status-icon-warm"><i class="fa-solid fa-chart-line"></i></span>
                <div>
                    <span>میانگین Uptime / ۲۴ ساعت</span>
                    <strong data-status-summary="uptime">—</strong>
                </div>
            </article>
            <article class="fc-status-summary-card" data-fc-reveal="up" data-fc-reveal-delay="180">
                <span class="fc-status-summary-icon fc-status-icon-muted"><i class="fa-solid fa-triangle-exclamation"></i></span>
                <div>
                    <span>رخداد یا نگهداری فعال</span>
                    <strong data-status-summary="incidents">—</strong>
                </div>
            </article>
        </section>

        <div class="fc-status-toolbar" data-fc-reveal="up">
            <div class="fc-status-last-update">
                <span class="fc-status-live-dot"></span>
                <span data-status-updated>در انتظار اولین بررسی</span>
            </div>
            <button type="button" class="fc-status-refresh" data-status-refresh>
                <i class="fa-solid fa-rotate" aria-hidden="true"></i>
                بروزرسانی وضعیت
            </button>
        </div>

        <div class="fc-status-alert" data-status-alert role="status" hidden></div>

        <section class="fc-status-incidents" data-status-active-incidents hidden aria-labelledby="fcStatusActiveTitle">
            <div class="fc-status-section-heading">
                <div>
                    <span class="fc-status-kicker">ACTIVE EVENTS</span>
                    <h2 id="fcStatusActiveTitle">رخدادهای فعال</h2>
                </div>
            </div>
            <div class="fc-status-incident-list" data-status-active-list></div>
        </section>

        <section class="fc-status-services" aria-labelledby="fcStatusServicesTitle">
            <div class="fc-status-section-heading" data-fc-reveal="up">
                <div>
                    <span class="fc-status-kicker">SERVICE COMPONENTS</span>
                    <h2 id="fcStatusServicesTitle">وضعیت اجزای سرویس</h2>
                    <p>هر سرویس بر اساس آخرین بررسی ثبت‌شده در Uptime Kuma نمایش داده می‌شود.</p>
                </div>
            </div>

            <div class="fc-status-loading" data-status-loading>
                <span class="fc-status-spinner" aria-hidden="true"></span>
                <strong>در حال دریافت وضعیت سرویس‌ها...</strong>
            </div>
            <div class="fc-status-group-list" data-status-groups></div>
            <div class="fc-status-empty" data-status-empty hidden>
                <i class="fa-solid fa-satellite-dish" aria-hidden="true"></i>
                <strong>هنوز سرویسی برای نمایش ثبت نشده است</strong>
                <span>ابتدا سرویس‌ها را در Uptime Kuma به یک گروه عمومی در Status Page اضافه کن.</span>
            </div>
        </section>

        <section class="fc-status-history" aria-labelledby="fcStatusHistoryTitle">
            <div class="fc-status-section-heading" data-fc-reveal="up">
                <div>
                    <span class="fc-status-kicker">INCIDENT HISTORY</span>
                    <h2 id="fcStatusHistoryTitle">تغییرات و رخدادهای اخیر</h2>
                    <p>آخرین رخدادهایی که در Status Page ثبت شده‌اند.</p>
                </div>
            </div>
            <div class="fc-status-history-list" data-status-history></div>
            <div class="fc-status-history-empty" data-status-history-empty hidden>
                فعلاً رخداد ثبت‌شده‌ای وجود ندارد.
            </div>
        </section>

        <footer class="fc-status-footnote">
            <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
            <span>برای حفظ امنیت، این صفحه فقط وضعیت عمومی سرویس‌ها را نمایش می‌دهد؛ جزئیات مدیریتی و اطلاعات داخلی زیرساخت منتشر نمی‌شوند.</span>
        </footer>
    </div>
</section>

<?php
/**
 * FragCity CS2 server storefront.
 *
 * This is the presentation layer only. Order creation, pricing and provisioning
 * remain disabled until the server-order backend contract is implemented.
 */
?>
<main class="server-store-page" dir="rtl">
    <div class="server-store-container">
        <header class="server-store-hero" data-fc-reveal="up">
            <div class="server-store-hero-icon" aria-hidden="true">
                <i class="fa-solid fa-server"></i>
            </div>

            <div class="server-store-hero-copy">
                <span class="server-store-eyebrow" dir="ltr">FRAGCITY · SERVER STORE</span>
                <h1>سرور اختصاصی CS2 خودت رو بساز</h1>
                <p>ظرفیت، تنظیمات گیم‌مود و قابلیت‌های موردنیازت رو در یک مسیر یکپارچه انتخاب کن.</p>
            </div>

            <div class="server-store-hero-aside">
                <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                <span>پیکربندی اختصاصی</span>
            </div>
        </header>

        <ol class="server-store-progress" aria-label="مراحل خرید سرور" data-fc-reveal="up" data-fc-reveal-delay="60">
            <li class="is-current">
                <span class="server-store-progress-index">01</span>
                <span><strong>پیکربندی سرور</strong><small>انتخاب مشخصات و امکانات</small></span>
            </li>
            <li>
                <span class="server-store-progress-index">02</span>
                <span><strong>بررسی سفارش</strong><small>مشاهده جزئیات و مبلغ</small></span>
            </li>
            <li>
                <span class="server-store-progress-index">03</span>
                <span><strong>راه‌اندازی سرویس</strong><small>ساخت و تحویل سرور</small></span>
            </li>
        </ol>

        <div class="server-store-layout">
            <div class="server-store-main">
                <section class="server-store-section" aria-labelledby="serverDurationTitle" data-fc-reveal="up">
                    <div class="server-store-section-heading">
                        <span class="server-store-section-index">01</span>
                        <div>
                            <h2 id="serverDurationTitle">مدت اشتراک سرور</h2>
                            <p>مدت موردنظرت رو انتخاب کن؛ جزئیات قیمت پس از اتصال کاتالوگ فروش نمایش داده می‌شه.</p>
                        </div>
                        <span class="server-store-section-state">انتخاب پلن</span>
                    </div>

                    <div class="server-store-duration-grid" aria-label="گزینه‌های مدت اشتراک">
                        <article class="server-store-duration-card">
                            <span class="server-store-duration-code">01 DAY</span>
                            <strong>۱ روز</strong>
                            <small>اشتراک کوتاه‌مدت</small>
                        </article>
                        <article class="server-store-duration-card">
                            <span class="server-store-duration-code">03 DAYS</span>
                            <strong>۳ روز</strong>
                            <small>اشتراک آزمایشی</small>
                        </article>
                        <article class="server-store-duration-card">
                            <span class="server-store-duration-code">07 DAYS</span>
                            <strong>۷ روز</strong>
                            <small>یک هفته</small>
                        </article>
                        <article class="server-store-duration-card">
                            <span class="server-store-duration-code">15 DAYS</span>
                            <strong>۱۵ روز</strong>
                            <small>نیم‌ماه</small>
                        </article>
                        <article class="server-store-duration-card">
                            <span class="server-store-duration-code">30 DAYS</span>
                            <strong>۳۰ روز</strong>
                            <small>یک ماه</small>
                        </article>
                    </div>
                    <p class="server-store-inline-note">
                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                        کارت‌ها در این مرحله نمایشی هستند و هنوز به قیمت‌گذاری یا ثبت سفارش متصل نشده‌اند.
                    </p>
                </section>

                <section class="server-store-section" aria-labelledby="serverCapacityTitle" data-fc-reveal="up" data-fc-reveal-delay="50">
                    <div class="server-store-section-heading">
                        <span class="server-store-section-index">02</span>
                        <div>
                            <h2 id="serverCapacityTitle">ظرفیت و مشخصات سرور</h2>
                            <p>منابع اصلی سرور را متناسب با تعداد بازیکنان و نوع استفاده تنظیم کن.</p>
                        </div>
                    </div>

                    <div class="server-store-setting-grid">
                        <article class="server-store-setting-card">
                            <div class="server-store-setting-icon"><i class="fa-solid fa-users" aria-hidden="true"></i></div>
                            <div class="server-store-setting-copy">
                                <strong>حداکثر بازیکنان</strong>
                                <span>CS2 Maximum Players</span>
                            </div>
                            <div class="server-store-setting-value">در انتظار انتخاب</div>
                            <small>ظرفیت پایه و اسلات‌های اضافه در محاسبه نهایی لحاظ می‌شوند.</small>
                        </article>

                        <article class="server-store-setting-card">
                            <div class="server-store-setting-icon"><i class="fa-solid fa-network-wired" aria-hidden="true"></i></div>
                            <div class="server-store-setting-copy">
                                <strong>نود و پورت اتصال</strong>
                                <span>Node &amp; Allocation</span>
                            </div>
                            <div class="server-store-setting-value is-auto">
                                <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                                تخصیص خودکار
                            </div>
                            <small>نود و پورت مناسب توسط سیستم انتخاب می‌شود؛ نیازی به انتخاب دستی نیست.</small>
                        </article>
                    </div>
                </section>

                <section class="server-store-section" aria-labelledby="serverGameModeTitle" data-fc-reveal="up" data-fc-reveal-delay="90">
                    <div class="server-store-section-heading">
                        <span class="server-store-section-index">03</span>
                        <div>
                            <h2 id="serverGameModeTitle">تنظیمات GameModeManager</h2>
                            <p>گروه نقشه و CFG گیم‌مود را از گزینه‌های مجاز سرور انتخاب خواهی کرد.</p>
                        </div>
                    </div>

                    <div class="server-store-setting-grid">
                        <article class="server-store-setting-card server-store-setting-card--wide">
                            <div class="server-store-setting-icon"><i class="fa-solid fa-map" aria-hidden="true"></i></div>
                            <div class="server-store-setting-copy">
                                <strong>گروه نقشه</strong>
                                <span>mg_mapgroup</span>
                            </div>
                            <div class="server-store-setting-value">فهرست گزینه‌ها متصل می‌شود</div>
                            <small>فقط Map Groupهای تعریف‌شده و مجاز GameModeManager نمایش داده می‌شوند.</small>
                        </article>

                        <article class="server-store-setting-card server-store-setting-card--wide">
                            <div class="server-store-setting-icon"><i class="fa-solid fa-file-code" aria-hidden="true"></i></div>
                            <div class="server-store-setting-copy">
                                <strong>پیکربندی گیم‌مود</strong>
                                <span>GameMode CFG</span>
                            </div>
                            <div class="server-store-setting-value">فهرست گزینه‌ها متصل می‌شود</div>
                            <small>فقط CFGهای گیم‌مود قابل انتخاب هستند؛ فایل‌های تنظیمات عمومی در این فهرست قرار نمی‌گیرند.</small>
                        </article>
                    </div>
                </section>

                <section class="server-store-section" aria-labelledby="serverAddonTitle" data-fc-reveal="up" data-fc-reveal-delay="130">
                    <div class="server-store-section-heading">
                        <span class="server-store-section-index">04</span>
                        <div>
                            <h2 id="serverAddonTitle">افزونه‌های تکمیلی</h2>
                            <p>قابلیت‌های اضافه را در کنار سرویس اصلی فعال کن.</p>
                        </div>
                    </div>

                    <article class="server-store-addon-card">
                        <div class="server-store-addon-icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></div>
                        <div class="server-store-addon-copy">
                            <div class="server-store-addon-title">
                                <strong>Bot Guard</strong>
                                <span class="server-store-addon-badge">افزونه اختیاری</span>
                            </div>
                            <p>محافظت تکمیلی در برابر ورود و رفتار ربات‌های مزاحم در سرور.</p>
                            <small>مدت Ban و سایر گزینه‌های مجاز پس از تکمیل اتصال تنظیمات نمایش داده می‌شوند.</small>
                        </div>
                        <div class="server-store-addon-state">
                            <span class="server-store-addon-switch" aria-hidden="true"><span></span></span>
                            <small>در انتظار اتصال</small>
                        </div>
                    </article>
                </section>
            </div>

            <aside class="server-store-summary" aria-labelledby="serverSummaryTitle" data-fc-reveal="up" data-fc-reveal-delay="80">
                <div class="server-store-summary-topline">
                    <span><i class="fa-solid fa-receipt" aria-hidden="true"></i> ORDER SUMMARY</span>
                    <span class="server-store-summary-badge">پیش‌فاکتور</span>
                </div>

                <h2 id="serverSummaryTitle">خلاصه سفارش</h2>
                <p class="server-store-summary-description">با تکمیل پیکربندی، جزئیات سرویس و مبلغ نهایی در این بخش نمایش داده می‌شود.</p>

                <div class="server-store-summary-product">
                    <span class="server-store-summary-product-icon"><i class="fa-solid fa-server" aria-hidden="true"></i></span>
                    <span><strong>سرور اختصاصی CS2</strong><small>FragCity Game Hosting</small></span>
                </div>

                <div class="server-store-summary-lines">
                    <div><span>مدت اشتراک</span><strong>انتخاب نشده</strong></div>
                    <div><span>ظرفیت بازیکن</span><strong>انتخاب نشده</strong></div>
                    <div><span>GameMode CFG</span><strong>انتخاب نشده</strong></div>
                    <div><span>Bot Guard</span><strong>مشخص نشده</strong></div>
                    <div><span>نود و پورت</span><strong>تخصیص خودکار</strong></div>
                </div>

                <div class="server-store-summary-total">
                    <span>مبلغ نهایی</span>
                    <strong>پس از قیمت‌گذاری</strong>
                    <small>قیمت به‌صورت شفاف و پیش از ثبت سفارش محاسبه می‌شود.</small>
                </div>

                <button type="button" class="server-store-submit" disabled>
                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                    ثبت سفارش فعلاً غیرفعال است
                </button>

                <div class="server-store-summary-footnote">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    این صفحه در مرحله طراحی رابط کاربری است؛ سفارش و Provisioning تا آماده‌شدن Backend فعال نمی‌شوند.
                </div>
            </aside>
        </div>
    </div>
</main>

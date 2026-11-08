<?php
// includes/footer.php
require_once __DIR__ . '/routes.php';
?>
<footer class="site-footer" id="siteFooter" aria-label="FragCity footer">
    <div class="footer-content">
        <div class="footer-grid">
            <section class="footer-col footer-brand" aria-label="درباره FragCity">
                <a href="<?= htmlspecialchars(fragcity_page_url('home'), ENT_QUOTES, 'UTF-8') ?>" class="footer-brand-link" data-ajax-nav data-slug="home" aria-label="صفحه اصلی FragCity">
                    <img
                        src="/img/webp/newlogo.webp?v=20261102-2" onerror="this.onerror=null;this.src='/img/logoresponsive.png?v=20261102-2';"
                        alt="FragCity"
                        class="footer-logo"
                        loading="lazy"
                    >
                </a>

                <p class="footer-desc">
                    پلتفرمی برای سرورهای کانتر استرایک ۲، اسکین‌ها و ابزارهای اختصاصی جامعه‌ی بازیکنان.
                </p>
                <div class="footer-contact-columns" aria-label="راه‌های ارتباطی با FragCity">
                    <div class="footer-contact-column footer-contact-column--right" aria-label="راه‌های ارتباطی، ستون راست">
                <a
                    class="footer-ticket-button footer-contact-link"
                    href="/support/?tab=tickets"
                    aria-label="ثبت تیکت پشتیبانی FragCity"
                >
                    <i class="fa-solid fa-ticket" aria-hidden="true"></i>
                    <span>ثبت تیکت پشتیبانی</span>
                </a>
                <a
                    class="footer-telegram-support-button footer-contact-link"
                    href="https://t.me/fragcitysup"
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="ارتباط با پشتیبانی تلگرام FragCity"
                >
                    <i class="fa-brands fa-telegram" aria-hidden="true"></i>
                    <span>پشتیبانی تلگرام</span>
                    <strong dir="ltr">@fragcitysup</strong>
                </a>
                <a
                    class="footer-telegram-button footer-contact-link"
                    href="https://t.me/fragcitychannel"
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="ورود به کانال تلگرام FragCity"
                >
                    <i class="fa-brands fa-telegram" aria-hidden="true"></i>
                    <span>کانال تلگرام</span>
                    <strong dir="ltr">@fragcitychannel</strong>
                </a>
                <a
                    class="footer-discord-button footer-contact-link"
                    href="https://discord.gg/s6rFThCDdY"
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="ورود به سرور Discord FragCity"
                >
                    <i class="fa-brands fa-discord" aria-hidden="true"></i>
                    <span>سرور Discord</span>
                    <strong dir="ltr">FragCity</strong>
                </a>
                    </div>
                    <div class="footer-contact-column footer-contact-column--left" aria-label="راه‌های ارتباطی، ستون چپ">
                <a
                    class="footer-steam-button footer-contact-link"
                    href="https://steamcommunity.com/id/larnz/"
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="ارتباط با پشتیبانی FragCity در Steam"
                >
                    <i class="fa-brands fa-steam" aria-hidden="true"></i>
                    <span>پشتیبانی در Steam</span>
                    <strong dir="ltr">larnz</strong>
                </a>
                <a
                    class="footer-teamspeak-button footer-contact-link"
                    href="ts3server://217.18.90.7?port=2014"
                    aria-label="اتصال به سرور TeamSpeak FragCity"
                >
                    <i class="fa-solid fa-headset" aria-hidden="true"></i>
                    <span>سرور TeamSpeak</span>
                    <strong dir="ltr">217.18.90.7:2014</strong>
                </a>
                <a
                    class="footer-email-button footer-contact-link"
                    href="mailto:support@fragcity.ir"
                    aria-label="ارسال ایمیل به پشتیبانی FragCity"
                >
                    <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                    <span>ایمیل پشتیبانی</span>
                    <strong dir="ltr">support@fragcity.ir</strong>
                </a>
                    </div>
                </div>
            </section>

            <nav class="footer-col" aria-label="دسترسی سریع">
                <p class="footer-heading">دسترسی سریع</p>
                <ul class="footer-links">
                    <li><a href="<?= htmlspecialchars(fragcity_page_url('servers_vps'), ENT_QUOTES, 'UTF-8') ?>" data-ajax-nav data-slug="servers_vps">لیست سرورها</a></li>
                    <li><a href="<?= htmlspecialchars(fragcity_page_url('serverguide'), ENT_QUOTES, 'UTF-8') ?>" data-ajax-nav data-slug="serverguide">راهنمای سرور CS2</a></li>
                    <li><a href="<?= htmlspecialchars(fragcity_page_url('buyserveropen'), ENT_QUOTES, 'UTF-8') ?>" data-ajax-nav data-slug="buyserveropen">خرید سرور</a></li>
                    <li><a href="<?= htmlspecialchars(fragcity_page_url('buyserveropen') . '?game=minecraft', ENT_QUOTES, 'UTF-8') ?>" data-ajax-nav data-slug="buyserveropen">میزبانی Minecraft</a></li>
                    <li><a href="<?= htmlspecialchars(fragcity_page_url('vip'), ENT_QUOTES, 'UTF-8') ?>" data-ajax-nav data-slug="vip">عضویت VIP</a></li>
                    <li><a href="<?= htmlspecialchars(fragcity_page_url('status'), ENT_QUOTES, 'UTF-8') ?>" data-ajax-nav data-slug="status">وضعیت سرویس‌ها</a></li>
                    <li><a href="<?= htmlspecialchars(fragcity_page_url('about'), ENT_QUOTES, 'UTF-8') ?>" data-ajax-nav data-slug="about">درباره FragCity</a></li>
                    <li><a href="<?= htmlspecialchars(fragcity_page_url('support'), ENT_QUOTES, 'UTF-8') ?>" data-ajax-nav data-slug="support">پشتیبانی</a></li>
                </ul>
            </nav>

            <section class="footer-col footer-trust" aria-label="نشان‌های اعتماد">
                <p class="footer-heading">نشان‌های اعتماد</p>
                <div class="trust-badges">
                    <a
                        referrerpolicy="origin"
                        target="_blank"
                        rel="noopener noreferrer"
                        href="https://trustseal.enamad.ir/?id=733184&Code=LIGY7RCe6ngk9p2DzpzyW37m4vJArgsa"
                        class="enamad-badge"
                        aria-label="مشاهده نماد اعتماد الکترونیکی"
                    >
                        <img
                            referrerpolicy="origin"
                            src="https://trustseal.enamad.ir/logo.aspx?id=733184&Code=LIGY7RCe6ngk9p2DzpzyW37m4vJArgsa"
                            alt="نماد اعتماد الکترونیکی"
                            code="LIGY7RCe6ngk9p2DzpzyW37m4vJArgsa"
                            loading="lazy"
                        >
                    </a>

                    <div id="zarinpal" class="zarinpal-box">
                        <script src="https://www.zarinpal.com/webservice/TrustCode" type="text/javascript"></script>
                    </div>
                </div>
            </section>
        </div>

        <div class="footer-bottom">
            <p>Fragcity 2026</p>
        </div>
    </div>
</footer>

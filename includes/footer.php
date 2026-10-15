<?php
// includes/footer.php
?>
<footer class="site-footer" id="siteFooter" aria-label="FragCity footer">
    <div class="footer-content">
        <div class="footer-grid">
            <div class="footer-col footer-brand">
                <div class="footer-brand-row">
                    <img src="/assets/images/logo/main.png" alt="FragCity Logo" class="footer-logo">
                </div>

                <p class="footer-desc">
                    سرورهای حرفه‌ای کانتر-استرایک ۲<br>
                    تجربه‌ای سریع، پایدار و تمیز برای پلیرهای جدی
                </p>

                <div class="footer-socials" aria-label="Social links">
                    <a href="#" title="دیسکورد" aria-label="Discord"><i class="fab fa-discord"></i></a>
                    <a href="#" title="اینستاگرام" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" title="تلگرام" aria-label="Telegram"><i class="fab fa-telegram-plane"></i></a>
                </div>
            </div>

            <div class="footer-col">
                <h5>دسترسی سریع</h5>
                <ul class="footer-links">
                    <li><a href="?page=servers" data-ajax-nav data-slug="servers">لیست سرورها</a></li>
                    <li><a href="?page=skins" data-ajax-nav data-slug="skins">اسکین‌ها</a></li>
                    <li><a href="?page=launcher" data-ajax-nav data-slug="launcher">دانلود لانچر</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h5>پشتیبانی</h5>
                <ul class="footer-links">
                    <li><a href="?page=support" data-ajax-nav data-slug="support">ارتباط با ما</a></li>
                    <li><a href="?page=usermanual" data-ajax-nav data-slug="usermanual">راهنمای کاربر</a></li>
                    <li><a href="?page=rules" data-ajax-nav data-slug="rules">قوانین و مقررات</a></li>
                    <li><a href="?page=privacy" data-ajax-nav data-slug="privacy">حریم خصوصی</a></li>
                </ul>
            </div>

            <div class="footer-col footer-trust">
                <h5>نمادهای اعتماد</h5>
                <div class="trust-badges">
                    <a
                        referrerpolicy="origin"
                        target="_blank"
                        rel="noopener noreferrer"
                        href="https://trustseal.enamad.ir/?id=733184&Code=LIGY7RCe6ngk9p2DzpzyW37m4vJArgsa"
                        class="enamad-badge"
                    >
                        <img
                            referrerpolicy="origin"
                            src="https://trustseal.enamad.ir/logo.aspx?id=733184&Code=LIGY7RCe6ngk9p2DzpzyW37m4vJArgsa"
                            alt="نماد اینماد"
                            code="LIGY7RCe6ngk9p2DzpzyW37m4vJArgsa"
                        >
                    </a>

                    <div id="zarinpal" class="zarinpal-box">
                        <script src="https://www.zarinpal.com/webservice/TrustCode" type="text/javascript"></script>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <p>طراحی و ساخت وبسایت : عرفان و الیاس</p>
            <p>© <?= date('Y') ?> FragCity</p>
        </div>
    </div>
</footer>

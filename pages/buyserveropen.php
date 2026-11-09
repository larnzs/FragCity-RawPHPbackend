<?php
declare(strict_types=1);

/**
 * FragCity CS2 storefront entry point.
 *
 * Configuration, pricing, native Config Options, Cart, Orders, Invoices and
 * server provisioning are owned by Paymenter. FragCity only creates a
 * short-lived SSO link after verifying the customer's own website session.
 */

$serverStoreCheckoutError = '';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && (string) ($_POST['server_store_action'] ?? '') === 'start_paymenter_checkout'
) {
    $submittedCsrf = $_POST['csrf_token'] ?? '';
    $expectedCsrf = $_SESSION['auth_csrf_token'] ?? '';

    if (
        !is_string($submittedCsrf)
        || !is_string($expectedCsrf)
        || $expectedCsrf === ''
        || !hash_equals($expectedCsrf, $submittedCsrf)
    ) {
        $_SESSION['paymenter_checkout_error'] = 'درخواست معتبر نیست. صفحه را تازه‌سازی کنید و دوباره تلاش کنید.';
        header('Location: /index.php?page=buyserveropen', true, 303);
        exit;
    }

    $isLoggedIn = ($_SESSION['user_logged_in'] ?? false) === true;
    $steamId64 = (string) ($_SESSION['steamid'] ?? '');

    if (!$isLoggedIn || !preg_match('/^[0-9]{17}$/D', $steamId64)) {
        header('Location: /index.php?page=login', true, 303);
        exit;
    }

    try {
        require_once dirname(__DIR__) . '/class/PaymenterApiClient.php';

        $checkoutUrl = (new PaymenterApiClient())->createPaymenterCheckoutUrl($steamId64);

        header('Cache-Control: no-store, private');
        header('Referrer-Policy: no-referrer');
        header('Location: ' . $checkoutUrl, true, 303);
        exit;
    } catch (Throwable $e) {
        error_log('[FragCity] Native Paymenter checkout redirect failed; exception=' . get_class($e) . '.');
        $_SESSION['paymenter_checkout_error'] =
            'اتصال به Checkout برقرار نشد. یک‌بار از حساب FragCity خارج شوید و دوباره وارد شوید؛ اگر مشکل ادامه داشت با پشتیبانی تماس بگیرید.';

        header('Location: /index.php?page=buyserveropen', true, 303);
        exit;
    }
}

$serverStoreCheckoutError = is_string($_SESSION['paymenter_checkout_error'] ?? null)
    ? (string) $_SESSION['paymenter_checkout_error']
    : '';
unset($_SESSION['paymenter_checkout_error']);

$serverStoreLoggedIn = ($_SESSION['user_logged_in'] ?? false) === true;
$serverStoreCsrf = is_string($_SESSION['auth_csrf_token'] ?? null)
    ? (string) $_SESSION['auth_csrf_token']
    : '';
?>
<main class="server-store-page" dir="rtl">
    <div class="server-store-container">
        <header class="server-store-hero">
            <div class="server-store-hero-main">
                <div class="server-store-hero-icon" aria-hidden="true">
                    <i class="fa-solid fa-server"></i>
                </div>
                <div class="server-store-hero-copy">
                    <span class="server-store-eyebrow" dir="ltr">FRAGCITY / GAME HOSTING</span>
                    <h1>سرور بازی خودت رو بساز</h1>
                    <p>یک سرور CS2 بساز، منابع و تنظیماتش را انتخاب کن و کامیونیتی خودت را راه بینداز.</p>
                    <div class="server-store-hero-tags">
                        <span><i class="fa-solid fa-users" aria-hidden="true"></i> ظرفیت قابل انتخاب</span>
                        <span><i class="fa-solid fa-gamepad" aria-hidden="true"></i> مودهای متنوع</span>
                        <span><i class="fa-solid fa-sliders" aria-hidden="true"></i> منابع قابل تنظیم</span>
                    </div>
                </div>
            </div>

            <div class="server-store-hero-product">
                <span class="server-store-product-kicker">YOUR GAME SERVER</span>
                <strong>Counter-Strike 2</strong>
                <span class="server-store-product-status"><i class="fa-solid fa-circle" aria-hidden="true"></i> CS2 Server Hosting</span>
                <div class="server-store-product-mark" aria-hidden="true">
                    <i class="fa-solid fa-crosshairs"></i>
                </div>
            </div>
        </header>

        <section class="server-store-native-card" aria-labelledby="nativeCheckoutTitle">
            <div class="server-store-native-card__eyebrow" dir="ltr">NATIVE PAYMENTER CHECKOUT</div>
            <h2 id="nativeCheckoutTitle">پیکربندی و خرید سرور</h2>
            <p>
                انتخاب پلن، ظرفیت بازیکن، Tickrate، منابع سرور و قیمت نهایی در Checkout خود Paymenter انجام می‌شود.
                Paymenter انتخاب‌ها را با Config Optionهای واقعی ذخیره می‌کند و همان مقادیر را برای ساخت سرور به Pterodactyl می‌فرستد.
            </p>

            <div class="server-store-native-features">
                <div><i class="fa-solid fa-memory" aria-hidden="true"></i><span><strong>RAM</strong><small>انتخاب حافظه</small></span></div>
                <div><i class="fa-solid fa-microchip" aria-hidden="true"></i><span><strong>CPU</strong><small>سقف پردازنده</small></span></div>
                <div><i class="fa-solid fa-hard-drive" aria-hidden="true"></i><span><strong>SSD</strong><small>فضای ذخیره‌سازی</small></span></div>
                <div><i class="fa-solid fa-users" aria-hidden="true"></i><span><strong>Player Slots</strong><small>ظرفیت بازیکن</small></span></div>
                <div><i class="fa-solid fa-gauge-high" aria-hidden="true"></i><span><strong>Tickrate</strong><small>64 یا 128 Tick</small></span></div>
                <div><i class="fa-solid fa-gamepad" aria-hidden="true"></i><span><strong>Game Mode</strong><small>تنظیمات گیم‌سرور</small></span></div>
            </div>

            <?php if ($serverStoreCheckoutError !== ''): ?>
                <div class="server-store-native-error" role="alert">
                    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                    <?= htmlspecialchars($serverStoreCheckoutError, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if ($serverStoreLoggedIn): ?>
                <form method="post" action="/index.php?page=buyserveropen" class="server-store-native-action">
                    <input type="hidden" name="server_store_action" value="start_paymenter_checkout">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($serverStoreCsrf, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="server-store-submit">
                        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                        ادامه و انتخاب پلن و منابع
                    </button>
                </form>
            <?php else: ?>
                <a class="server-store-submit" href="/index.php?page=login">
                    <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
                    ورود برای خرید سرور
                </a>
            <?php endif; ?>

            <div class="server-store-native-footnote">
                <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                پرداخت از طریق درگاه امن انجام می‌شود. پس از تأیید پرداخت، Paymenter ساخت سرویس را به‌صورت خودکار آغاز می‌کند.
            </div>
        </section>

        <section class="server-store-coming-soon" aria-labelledby="cs16ComingSoonTitle">
            <div class="server-store-coming-soon__icon"><i class="fa-solid fa-gamepad" aria-hidden="true"></i></div>
            <span class="server-store-coming-soon__eyebrow">FRAGCITY / CLASSIC SERVERS</span>
            <h2 id="cs16ComingSoonTitle">سرور Counter-Strike 1.6</h2>
            <p>بخش سرورهای CS 1.6 در حال آماده‌سازی است و پس از تکمیل پلن‌ها و تنظیمات به فروشگاه اضافه خواهد شد.</p>
            <span class="server-store-coming-soon__status"><i class="fa-solid fa-circle" aria-hidden="true"></i> به‌زودی</span>
        </section>
    </div>
</main>

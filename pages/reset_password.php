<?php
declare(strict_types=1);

/**
 * SECURITY HOLD
 *
 * Password-reset tokens issued by the legacy SteamID-only flow were not
 * delivered to a verified email address. Do not validate or consume any
 * legacy token here. This page must remain fail-closed until email ownership
 * verification and the shared FragCity/Paymenter password-sync flow exist.
 */
?>
<div class="auth-page">
    <div class="auth-container">
        <div class="auth-logo">
            <img
                src="/img/webp/newlogo.webp?v=20261102-2"
                onerror="this.onerror=null;this.src='/img/logoresponsive.png?v=20261102-2';"
                alt="FragCity Logo"
            >
        </div>

        <h2>بازنشانی رمز عبور موقتاً غیرفعال است</h2>
        <p class="auth-subtitle">
            برای حفظ امنیت حساب‌ها، لینک‌های بازیابی قبلی دیگر قابل استفاده نیستند.
            سرویس بازنشانی پس از راه‌اندازی تأیید مالکیت ایمیل دوباره فعال می‌شود.
        </p>

        <div class="auth-message error" role="alert" aria-live="polite">
            <i class="fas fa-shield-halved" aria-hidden="true"></i>
            <span>
                این لینک نمی‌تواند رمز FragCity یا Paymenter را تغییر دهد.
                لطفاً برای ورود به حساب از روش‌های ورود موجود استفاده کنید.
            </span>
        </div>

        <a href="/login/" class="btn-auth-submit">
            بازگشت به صفحه ورود
        </a>
    </div>
</div>

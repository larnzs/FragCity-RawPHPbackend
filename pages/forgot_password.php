<?php
declare(strict_types=1);

/**
 * SECURITY HOLD
 *
 * The legacy recovery flow accepted a SteamID64 and exposed the reset URL
 * directly in the response. That does not prove ownership of the account's
 * email address. Do not create reset tokens or access the password_resets
 * table here until a verified-email delivery and confirmation flow is ready.
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

        <h2>بازیابی رمز عبور موقتاً غیرفعال است</h2>
        <p class="auth-subtitle">
            برای حفظ امنیت حساب‌ها، درخواست بازیابی رمز عبور تا زمان راه‌اندازی
            تأیید مالکیت ایمیل غیرفعال شده است.
        </p>

        <div class="auth-message error" role="alert" aria-live="polite">
            <i class="fas fa-shield-halved" aria-hidden="true"></i>
            <span>
                در این صفحه هیچ لینک بازیابی ساخته یا نمایش داده نمی‌شود.
                لطفاً برای ورود به حساب از روش‌های ورود موجود استفاده کنید.
                اگر به حساب خود دسترسی ندارید، از پشتیبانی FragCity کمک بگیرید.
            </span>
        </div>

        <a href="?page=login" class="btn-auth-submit">
            بازگشت به صفحه ورود
        </a>
    </div>
</div>

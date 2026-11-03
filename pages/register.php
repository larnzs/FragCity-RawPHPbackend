<?php
// pages/register.php

$error = $_SESSION['auth_error'] ?? '';
unset($_SESSION['auth_error']);

$isLoggedIn = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
$isAccountCompletion = $isLoggedIn
    && (string) ($_GET['complete'] ?? '') === '1';

$isSteamRegister = !$isAccountCompletion
    && isset($_GET['steam'])
    && (string) $_GET['steam'] === '1'
    && isset($_SESSION['steam_temp']);

$steamData = $isSteamRegister ? $_SESSION['steam_temp'] : null;
$completionUser = null;

if ($isAccountCompletion && $pdo instanceof PDO) {
    try {
        $lookup = $pdo->prepare(
            'SELECT username, first_name, last_name, email, phone, paymenter_sync_status, paymenter_sync_error, paymenter_synced_at FROM users WHERE steamid64 = ? LIMIT 1'
        );
        $lookup->execute([(string) ($_SESSION['steamid'] ?? '')]);
        $completionUser = $lookup->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        error_log('[FragCity] Account completion form lookup failed (' . get_class($e) . ').');
    }
}

$formUsername = (string) (
    $completionUser['username']
    ?? $steamData['personaname']
    ?? ''
);
$formFirstName = (string) ($completionUser['first_name'] ?? '');
$formLastName = (string) ($completionUser['last_name'] ?? '');
$formEmail = (string) ($completionUser['email'] ?? '');
$formPhone = (string) ($completionUser['phone'] ?? '');
$completionSyncStatus = (string) ($completionUser['paymenter_sync_status'] ?? 'pending');
$completionSyncError = (string) ($completionUser['paymenter_sync_error'] ?? '');

$syncErrorDescriptions = [
    'username_required' => 'نام کاربری معتبر برای حساب الزامی است.',
    'username_already_in_use' => 'این نام کاربری قبلاً استفاده شده است؛ نام دیگری انتخاب کنید.',
    'first_name_required' => 'نام کوچک برای ساخت حساب کاربری الزامی است.',
    'last_name_required' => 'نام خانوادگی برای ساخت حساب کاربری الزامی است.',
    'email_required' => 'ایمیل معتبر برای همگام‌سازی ثبت نشده است.',
    'phone_required' => 'شماره موبایل معتبر برای همگام‌سازی ثبت نشده است.',
    'password_policy' => 'رمز عبور با سیاست امنیتی موردنیاز مطابقت ندارد.',
    'paymenter_unreachable' => 'سایت نتوانسته به سرویس Paymenter متصل شود.',
    'paymenter_invalid_response' => 'پاسخ دریافتی از Paymenter معتبر یا قابل‌خواندن نیست.',
    'bridge_not_configured' => 'تنظیمات افزونه FragCity API در Paymenter کامل نیست.',
    'source_ip_not_allowed' => 'IP هاست سایت در فهرست مجاز افزونه Paymenter قرار ندارد.',
    'invalid_signature_headers' => 'هدرهای احراز هویت درخواست کامل یا معتبر نیستند.',
    'invalid_signature' => 'امضای امنیتی درخواست توسط Paymenter تأیید نشد.',
    'request_replayed' => 'درخواست تکراری تشخیص داده شد؛ صفحه را تازه‌سازی و دوباره تلاش کنید.',
    'invalid_json_body' => 'ساختار اطلاعات ارسالی به Paymenter معتبر نیست.',
    'validation_failed' => 'یکی از اطلاعات ارسالی با قوانین حساب Paymenter مطابقت ندارد.',
    'linked_paymenter_user_missing' => 'حساب Paymenter متصل‌شده دیگر وجود ندارد.',
    'email_already_linked_to_another_paymenter_user' => 'این ایمیل به حساب Paymenter دیگری متصل شده است.',
    'paymenter_user_already_linked_to_another_steam_account' => 'حساب Paymenter به یک حساب Steam دیگر متصل است.',
    'email_already_in_use' => 'این ایمیل در Paymenter ثبت شده است و رمز واردشده با حساب موجود مطابقت ندارد.',
    'account_sync_failed' => 'Paymenter هنگام ایجاد یا به‌روزرسانی حساب با خطای داخلی مواجه شد.',
    'local_paymenter_link_not_saved' => 'حساب Paymenter پاسخ موفق داده، اما ثبت شناسه اتصال در دیتابیس FragCity تأیید نشده است.',
    'paymenter_sync_failed' => 'همگام‌سازی حساب انجام نشد؛ کد فنی زیر را برای بررسی استفاده می‌کنیم.',
];

$safeSyncErrorCode = preg_match('/^[a-z0-9_]{1,64}$/D', $completionSyncError)
    ? $completionSyncError
    : '';
?>

<div class="auth-page">
    <div class="auth-container">

        <div class="auth-logo">
            <img src="/img/webp/newlogo.webp?v=20261102-2" onerror="this.onerror=null;this.src='/img/logoresponsive.png?v=20261102-2';" alt="FragCity Logo">
        </div>

        <h2>
            <?php if ($isAccountCompletion): ?>
                تکمیل اتصال حساب به پنل سرور
            <?php elseif ($isSteamRegister): ?>
                تکمیل ثبت‌نام با Steam
            <?php else: ?>
                ساخت حساب جدید
            <?php endif; ?>
        </h2>

        <p class="auth-subtitle">
            <?php if ($isAccountCompletion): ?>
                برای فعال‌سازی خرید و مدیریت سرور، نام، نام خانوادگی و اطلاعات حساب را تکمیل کنید.
            <?php elseif ($isSteamRegister): ?>
                حساب Steam شما شناسایی شد؛ نام و نام خانوادگی، ایمیل، موبایل و رمز مشترک حساب را وارد کنید.
            <?php else: ?>
                حساب FragCity خود را بسازید؛ حساب متناظر Paymenter هم خودکار ایجاد می‌شود.
            <?php endif; ?>
        </p>

        <?php if ($error): ?>
            <div class="auth-error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if (
            $isAccountCompletion
            && ($completionSyncStatus === 'failed' || $safeSyncErrorCode !== '')
        ): ?>
            <div class="auth-diagnostic" role="status" aria-live="polite">
                <div class="auth-diagnostic__heading">
                    <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                    <strong>وضعیت اتصال Paymenter: ناموفق</strong>
                </div>
                <p>
                    <?= htmlspecialchars(
                        $syncErrorDescriptions[$safeSyncErrorCode] ?? 'اتصال حساب کامل نشده است. کد فنی زیر برای تشخیص علت ثبت شده است.',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </p>
                <?php if ($safeSyncErrorCode !== ''): ?>
                    <div class="auth-diagnostic__code">
                        کد فنی:
                        <code><?= htmlspecialchars($safeSyncErrorCode, ENT_QUOTES, 'UTF-8') ?></code>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!$isSteamRegister && !$isAccountCompletion): ?>
            <a href="steam/steam_login.php" class="auth-steam-button">
                <i class="fab fa-steam"></i>
                ثبت‌نام سریع با Steam
            </a>

            <div class="auth-separator">
                <span>یا به صورت دستی</span>
            </div>
        <?php endif; ?>

        <?php if ($isSteamRegister || $isAccountCompletion): ?>
        <form id="registerForm" method="post" action="">
            <input type="hidden" name="auth_action" value="register">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($authCsrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">

            <?php if ($isAccountCompletion): ?>
                <input type="hidden" name="account_completion" value="1">
            <?php elseif ($isSteamRegister): ?>
                <input type="hidden" name="steam_register" value="1">
                <input type="hidden" name="steamid64" value="<?= htmlspecialchars((string) ($steamData['steamid64'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

                <?php if (!empty($steamData['avatar'])): ?>
                    <div class="auth-steam-avatar">
                        <img src="<?= htmlspecialchars((string) $steamData['avatar'], ENT_QUOTES, 'UTF-8') ?>" alt="Steam Avatar">
                        <div class="auth-steam-avatar-label">حساب Steam شما شناسایی شد</div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="form-group">
                    <label for="steamid64">SteamID64</label>
                    <input
                        id="steamid64"
                        type="text"
                        name="steamid64"
                        placeholder="7656119xxxxxxxxx"
                        autocomplete="off"
                        required
                        pattern="[0-9]{17}"
                        maxlength="17"
                    >
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="username">
                    نام کاربری (Username) <span class="auth-required">*</span>
                </label>
                <input
                    id="username"
                    type="text"
                    name="username"
                    value="<?= htmlspecialchars(strtolower($formUsername), ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="مثلاً elias_123"
                    autocomplete="username"
                    minlength="3"
                    maxlength="50"
                    pattern="[A-Za-z0-9][A-Za-z0-9_-]{1,48}[A-Za-z0-9]"
                    autocapitalize="none"
                    spellcheck="false"
                    required
                >
                <small class="auth-hint">۳ تا ۵۰ کاراکتر؛ فقط حروف انگلیسی، عدد، خط زیر (_) و خط تیره (-). نام کاربری نباید با _ یا - شروع یا تمام شود.</small>
            </div>

            <div class="form-group">
                <label for="first_name">
                    نام کوچک <span class="auth-required">*</span>
                    <span class="auth-optional">Client First Name</span>
                </label>
                <input
                    id="first_name"
                    type="text"
                    name="first_name"
                    value="<?= htmlspecialchars($formFirstName, ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="نام کوچک"
                    autocomplete="given-name"
                    maxlength="191"
                    required
                >
            </div>

            <div class="form-group">
                <label for="last_name">
                    نام خانوادگی <span class="auth-required">*</span>
                    <span class="auth-optional">Client Last Name</span>
                </label>
                <input
                    id="last_name"
                    type="text"
                    name="last_name"
                    value="<?= htmlspecialchars($formLastName, ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="نام خانوادگی"
                    autocomplete="family-name"
                    maxlength="191"
                    required
                >
            </div>

            <div class="form-group">
                <label for="phone">
                    شماره موبایل <span class="auth-required">*</span>
                </label>
                <input
                    id="phone"
                    type="tel"
                    name="phone"
                    value="<?= htmlspecialchars($formPhone, ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="09xxxxxxxxx"
                    autocomplete="tel"
                    inputmode="numeric"
                    maxlength="11"
                    pattern="09[0-9]{9}"
                    required
                >
            </div>

            <div class="form-group">
                <label for="email">
                    ایمیل <span class="auth-required">*</span>
                </label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="<?= htmlspecialchars($formEmail, ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="example@mail.com"
                    autocomplete="email"
                    maxlength="100"
                    required
                >
            </div>

            <div class="form-group">
                <label for="registerPassword">
                    <?= $isAccountCompletion ? 'رمز عبور مشترک جدید' : 'رمز عبور' ?>
                    <span class="auth-required">*</span>
                </label>
                <div class="password-box">
                    <input
                        id="registerPassword"
                        type="password"
                        name="password"
                        autocomplete="new-password"
                        minlength="8"
                        required
                    >
                    <button
                        type="button"
                        class="toggle-password"
                        data-password-toggle
                        data-password-target="registerPassword"
                        aria-label="نمایش رمز عبور"
                    >
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <small class="auth-hint">حداقل ۸ کاراکتر، شامل حروف و عدد. همین رمز برای حساب FragCity و Paymenter استفاده می‌شود.</small>
            </div>

            <div class="form-group">
                <label for="registerPasswordConfirm">تکرار رمز عبور <span class="auth-required">*</span></label>
                <div class="password-box">
                    <input
                        id="registerPasswordConfirm"
                        type="password"
                        name="password_confirm"
                        autocomplete="new-password"
                        minlength="8"
                        required
                    >
                    <button
                        type="button"
                        class="toggle-password"
                        data-password-toggle
                        data-password-target="registerPasswordConfirm"
                        aria-label="نمایش رمز عبور"
                    >
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-auth-submit">
                <i class="fas fa-user-check"></i>
                <?php if ($isAccountCompletion): ?>
                    ذخیره و اتصال حساب
                <?php elseif ($isSteamRegister): ?>
                    تکمیل ثبت‌نام
                <?php else: ?>
                    ایجاد حساب
                <?php endif; ?>
            </button>
        </form>
        <?php else: ?>
            <div class="auth-separator">
                <span>برای امنیت حساب، ابتدا مالکیت Steam خود را تأیید کنید.</span>
            </div>
        <?php endif; ?>

        <p class="auth-switch">
            <?php if ($isAccountCompletion): ?>
                <a href="index.php">بازگشت به سایت</a>
            <?php elseif ($isSteamRegister): ?>
                <a href="?page=login" data-auth-modal-open aria-haspopup="dialog" aria-controls="fcLoginModal" aria-expanded="false">ورود به حساب دیگر</a>
            <?php else: ?>
                قبلاً ثبت‌نام کرده‌اید؟
                <a href="?page=login" data-auth-modal-open aria-haspopup="dialog" aria-controls="fcLoginModal" aria-expanded="false">ورود</a>
            <?php endif; ?>
        </p>

    </div>
</div>

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
            'SELECT username, email, phone FROM users WHERE steamid64 = ? LIMIT 1'
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
$formEmail = (string) ($completionUser['email'] ?? '');
$formPhone = (string) ($completionUser['phone'] ?? '');
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
                برای فعال‌سازی خرید و مدیریت سرور، اطلاعات حساب Paymenter را تکمیل کنید.
            <?php elseif ($isSteamRegister): ?>
                حساب Steam شما شناسایی شد؛ ایمیل، موبایل و رمز مشترک حساب را تعیین کنید.
            <?php else: ?>
                حساب FragCity خود را بسازید؛ حساب متناظر Paymenter هم خودکار ایجاد می‌شود.
            <?php endif; ?>
        </p>

        <?php if ($error): ?>
            <div class="auth-error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
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
                    نام نمایشی <span class="auth-optional">اختیاری</span>
                </label>
                <input
                    id="username"
                    type="text"
                    name="username"
                    value="<?= htmlspecialchars($formUsername, ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="نام شما"
                    autocomplete="nickname"
                    maxlength="50"
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
                <a href="?page=register" data-ajax-nav data-slug="register">ثبت‌نام معمولی</a>
                <span class="auth-switch-divider">|</span>
                <a href="?page=login" data-auth-modal-open aria-haspopup="dialog" aria-controls="fcLoginModal" aria-expanded="false">ورود</a>
            <?php else: ?>
                قبلاً ثبت‌نام کرده‌اید؟
                <a href="?page=login" data-auth-modal-open aria-haspopup="dialog" aria-controls="fcLoginModal" aria-expanded="false">ورود</a>
            <?php endif; ?>
        </p>

    </div>
</div>

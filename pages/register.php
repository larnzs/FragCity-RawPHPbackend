<?php
// pages/register.php

$error = $_SESSION['auth_error'] ?? '';
unset($_SESSION['auth_error']);

$isSteamRegister =
    isset($_GET['steam']) &&
    $_GET['steam'] == 1 &&
    isset($_SESSION['steam_temp']);

$steamData = $isSteamRegister
    ? $_SESSION['steam_temp']
    : null;
?>

<div class="auth-page">
    <div class="auth-container">

        <!-- لوگوی FragCity -->
        <div class="auth-logo">
            <img src="assets/images/logo/main.png" alt="FragCity Logo">
        </div>

        <!-- عنوان -->
        <h2>
            <?= $isSteamRegister ? 'تکمیل ثبت‌نام با Steam' : 'ساخت حساب جدید' ?>
        </h2>

        <p class="auth-subtitle">
            <?= $isSteamRegister ? 'اطلاعات حساب خود را تکمیل کنید' : 'حساب FragCity خود را بسازید' ?>
        </p>

        <!-- خطای احراز هویت -->
        <?php if ($error): ?>
            <div class="auth-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- ثبت‌نام با Steam -->
        <?php if (!$isSteamRegister): ?>
            <a href="steam/steam_login.php" class="auth-steam-button">
                <i class="fab fa-steam"></i>
                ثبت‌نام سریع با Steam
            </a>

            <div class="auth-separator">
                <span>یا به صورت دستی</span>
            </div>
        <?php endif; ?>

        <!-- فرم ثبت‌نام -->
        <form id="registerForm" method="post" action="">
            <input type="hidden" name="auth_action" value="register">

            <?php if ($isSteamRegister): ?>
                <input type="hidden" name="steam_register" value="1">
                <input type="hidden" name="steamid64" value="<?= htmlspecialchars($steamData['steamid64'] ?? '') ?>">

                <?php if (!empty($steamData['avatar'])): ?>
                    <div class="auth-steam-avatar">
                        <img src="<?= htmlspecialchars($steamData['avatar']) ?>" alt="Steam Avatar">
                        <div class="auth-steam-avatar-label">حساب Steam شما شناسایی شد</div>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="username">نام نمایشی</label>
                    <input
                        id="username"
                        type="text"
                        name="username"
                        value="<?= htmlspecialchars($steamData['personaname'] ?? '') ?>"
                        placeholder="نام نمایشی شما"
                        autocomplete="nickname"
                        required
                    >
                </div>
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
                    >
                </div>

                <div class="form-group">
                    <label for="username">
                        نام کاربری <span class="auth-optional">اختیاری</span>
                    </label>
                    <input
                        id="username"
                        type="text"
                        name="username"
                        placeholder="نام شما"
                        autocomplete="username"
                    >
                </div>

                <div class="form-group">
                    <label for="registerPassword">رمز عبور</label>
                    <div class="password-box">
                        <input
                            id="registerPassword"
                            type="password"
                            name="password"
                            autocomplete="new-password"
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
                </div>

                <div class="form-group">
                    <label for="registerPasswordConfirm">تکرار رمز عبور</label>
                    <div class="password-box">
                        <input
                            id="registerPasswordConfirm"
                            type="password"
                            name="password_confirm"
                            autocomplete="new-password"
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
            <?php endif; ?>

            <div class="form-group">
                <label for="phone">
                    شماره موبایل <span class="auth-required">*</span>
                </label>
                <input
                    id="phone"
                    type="text"
                    name="phone"
                    placeholder="09xxxxxxxxx"
                    autocomplete="tel"
                    inputmode="numeric"
                    maxlength="11"
                    required
                >
            </div>

            <div class="form-group">
                <label for="email">
                    ایمیل <span class="auth-optional">اختیاری</span>
                </label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    placeholder="example@mail.com"
                    autocomplete="email"
                >
            </div>

            <button type="submit" class="btn-auth-submit">
                <i class="fas fa-user-check"></i>
                <?= $isSteamRegister ? 'تکمیل ثبت‌نام' : 'ایجاد حساب' ?>
            </button>
        </form>

        <p class="auth-switch">
            <?php if ($isSteamRegister): ?>
                <a href="?page=register" data-ajax-nav data-slug="register">ثبت‌نام معمولی</a>
                <span class="auth-switch-divider">|</span>
                <a href="?page=login" data-ajax-nav data-slug="login">ورود</a>
            <?php else: ?>
                قبلاً ثبت‌نام کرده‌اید؟
                <a href="?page=login" data-ajax-nav data-slug="login">ورود</a>
            <?php endif; ?>
        </p>

    </div>
</div>
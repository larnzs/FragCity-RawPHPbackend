<?php
// pages/login.php

$error = $_SESSION['auth_error'] ?? '';
unset($_SESSION['auth_error']);
?>

<div class="auth-page auth-page--modal">
    <div class="auth-container">

        <!-- FragCity Logo -->
        <div class="auth-logo">
            <img src="assets/images/logo/main.png" alt="FragCity Logo">
        </div>

        <!-- Page Title -->
        <h2>ورود به حساب کاربری</h2>
        <p class="auth-subtitle">به حساب FragCity خود وارد شوید</p>

        <!-- Authentication Error -->
        <?php if ($error): ?>
            <div class="auth-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form id="loginForm" method="post" action="?page=login&amp;auth_modal=1">
            <input type="hidden" name="auth_action" value="login">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($authCsrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">

            <!-- SteamID / Username / Phone -->
            <div class="form-group">
                <label for="loginId">SteamID64، نام کاربری یا شماره تلفن</label>
                <input
                    id="loginId"
                    type="text"
                    name="login_id"
                    placeholder="7656119xxxxxxxxx / username / 09xxxxxxxxx"
                    autocomplete="username"
                    required
                >
            </div>

            <!-- Password -->
            <div class="form-group">
                <label for="password">رمز عبور</label>
                <div class="password-box">
                    <input
                        id="password"
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        required
                    >
                    <button
                        type="button"
                        class="toggle-password"
                        data-password-toggle
                        data-password-target="password"
                        aria-label="نمایش رمز عبور"
                    >
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <!-- Forgot Password -->
            <div class="form-group auth-links">
                <a
                    href="?page=forgot_password"
                    class="forgot-link"
                    data-ajax-nav
                    data-slug="forgot_password"
                >
                    فراموشی رمز عبور
                </a>
            </div>

            <!-- Submit -->
            <button type="submit" class="btn-auth-submit">
                <i class="fas fa-sign-in-alt"></i>
                ورود
            </button>
        </form>

        <!-- Separator -->
        <div class="auth-separator">
            <span>یا</span>
        </div>

        <!-- Steam Login (Fixed: Removed target="_blank" for proper OpenID redirect flow) -->
        <a href="steam/steam_login.php" class="btn-steam-login">
            <i class="fab fa-steam"></i>
            ورود با Steam
        </a>

        <!-- Register Link -->
        <p class="auth-switch">
            حساب کاربری نداری؟
            <a href="?page=register" data-ajax-nav data-slug="register">
                ثبت‌نام کن
            </a>
        </p>

    </div>
</div>
<?php
// pages/login.php

$error = $_SESSION['auth_error'] ?? '';
unset($_SESSION['auth_error']);
?>

<div class="auth-page">
    <div class="auth-container">

        <!-- FragCity Logo -->
        <div class="auth-logo">
            <img src="/assets/images/logo/main.png" alt="FragCity Logo">
        </div>

        <!-- Page Title -->
        <h2>ورود به حساب کاربری</h2>
        <p class="auth-subtitle">به حساب FragCity خود وارد شوید</p>

        <!-- Warning Box -->
        <div class="auth-warning-box">
            <i class="fas fa-exclamation-triangle"></i>
            <span>در حال حاضر ورود و ثبت نام در سایت فقط از طریق استیم امکان پذیر است</span>
        </div>

        <!-- Authentication Error -->
        <?php if ($error): ?>
            <div class="auth-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Steam Login -->
        <a href="/steam/steam_login.php" class="btn-steam-login">
            <i class="fab fa-steam"></i>
            ورود با Steam
        </a>

        <!-- Register Link (Fixed: now correctly points to register.php via router) -->
        <p class="auth-switch">
            حساب کاربری نداری؟
            <a href="?page=register" data-ajax-nav data-slug="register" class="steam-reg-link">
                ثبت‌نام با استیم
            </a>
        </p>

    </div>
</div>
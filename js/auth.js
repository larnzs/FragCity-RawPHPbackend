(function () {
    'use strict';

    function initSubmitProtection() {
        const forms = document.querySelectorAll(
            '#loginForm, #registerForm, #resetPasswordForm, #forgotPasswordForm'
        );

        forms.forEach(function (form) {
            if (form.dataset.initialized === '1') return;
            form.dataset.initialized = '1';

            const submitBtn = form.querySelector('.btn-auth-submit');
            form.addEventListener('submit', function () {
                if (!submitBtn) return;
                submitBtn.disabled = true;
                submitBtn.classList.add('is-loading');
            });
        });
    }

    function initPasswordToggles() {
        document.querySelectorAll('[data-password-toggle]').forEach(function (toggle) {
            if (toggle.dataset.initialized === '1') return;

            const targetId = toggle.dataset.passwordTarget;
            const passwordInput = document.getElementById(targetId);
            if (!passwordInput) return;

            toggle.dataset.initialized = '1';
            toggle.addEventListener('click', function () {
                const icon = toggle.querySelector('i');
                const showPassword = passwordInput.type === 'password';
                passwordInput.type = showPassword ? 'text' : 'password';
                toggle.setAttribute(
                    'aria-label',
                    showPassword ? 'مخفی کردن رمز عبور' : 'نمایش رمز عبور'
                );

                if (icon) {
                    icon.classList.toggle('fa-eye', !showPassword);
                    icon.classList.toggle('fa-eye-slash', showPassword);
                }
            });
        });
    }

    function initPhoneInput() {
        document.querySelectorAll('input[name="phone"]').forEach(function (input) {
            if (input.dataset.phoneInitialized === '1') return;
            input.dataset.phoneInitialized = '1';

            input.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);
            });
        });
    }

    function initRememberMeSteamLink() {
        const steamLink = document.querySelector('.btn-steam-login');
        if (!steamLink || steamLink.dataset.rememberBound === '1') return;

        steamLink.dataset.rememberBound = '1';
        const baseHref = steamLink.dataset.steamLoginBase || steamLink.getAttribute('href');

        function syncRememberChoice() {
            const toggle = document.getElementById('rememberMe');
            const target = new URL(baseHref, window.location.href);

            if (toggle && toggle.checked) {
                target.searchParams.set('remember_me', '1');
            } else {
                target.searchParams.delete('remember_me');
            }

            steamLink.href = target.href;
        }

        const toggle = document.getElementById('rememberMe');
        if (toggle) {
            toggle.addEventListener('change', syncRememberChoice);
        }

        // Build the final URL at navigation time too; this avoids depending on
        // an earlier click or on stale href state in the dynamically loaded modal.
        steamLink.addEventListener('click', function (event) {
            event.preventDefault();
            syncRememberChoice();
            window.location.assign(steamLink.href);
        });

        syncRememberChoice();
    }

    function initAuthPages() {
        initSubmitProtection();
        initPasswordToggles();
        initPhoneInput();
        initRememberMeSteamLink();
    }

    window.initAuthPages = initAuthPages;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAuthPages, { once: true });
    } else {
        initAuthPages();
    }

    // Reinitialize controls when the login modal is inserted through AJAX navigation.
    window.addEventListener('content-loaded', initAuthPages);
})();
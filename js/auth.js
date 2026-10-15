(function () {

    'use strict';


    function initSubmitProtection() {

        const forms = document.querySelectorAll(
            '#loginForm, #registerForm, #resetPasswordForm, #forgotPasswordForm'
        );

        forms.forEach(function (form) {

            if (
                form.dataset.initialized === '1'
            ) {
                return;
            }

            form.dataset.initialized = '1';

            const submitBtn =
                form.querySelector(
                    '.btn-auth-submit'
                );

            form.addEventListener(
                'submit',
                function () {

                    if (!submitBtn) {
                        return;
                    }

                    submitBtn.disabled = true;

                    submitBtn.classList.add(
                        'is-loading'
                    );

                }
            );

        });

    }


    function initPasswordToggles() {

        const toggles =
            document.querySelectorAll(
                '[data-password-toggle]'
            );

        toggles.forEach(function (toggle) {

            if (
                toggle.dataset.initialized === '1'
            ) {
                return;
            }

            const targetId =
                toggle.dataset.passwordTarget;

            const passwordInput =
                document.getElementById(
                    targetId
                );

            if (!passwordInput) {
                return;
            }

            toggle.dataset.initialized = '1';

            toggle.addEventListener(
                'click',
                function () {

                    const icon =
                        toggle.querySelector(
                            'i'
                        );

                    if (
                        passwordInput.type ===
                        'password'
                    ) {

                        passwordInput.type =
                            'text';

                        toggle.setAttribute(
                            'aria-label',
                            'مخفی کردن رمز عبور'
                        );

                        if (icon) {

                            icon.classList.remove(
                                'fa-eye'
                            );

                            icon.classList.add(
                                'fa-eye-slash'
                            );

                        }

                    } else {

                        passwordInput.type =
                            'password';

                        toggle.setAttribute(
                            'aria-label',
                            'نمایش رمز عبور'
                        );

                        if (icon) {

                            icon.classList.remove(
                                'fa-eye-slash'
                            );

                            icon.classList.add(
                                'fa-eye'
                            );

                        }

                    }

                }
            );

        });

    }


    function initPhoneInput() {

        const phoneInputs =
            document.querySelectorAll(
                'input[name="phone"]'
            );

        phoneInputs.forEach(function (input) {

            if (
                input.dataset.phoneInitialized ===
                '1'
            ) {
                return;
            }

            input.dataset.phoneInitialized =
                '1';

            input.addEventListener(
                'input',
                function () {

                    this.value =
                        this.value
                            .replace(
                                /[^0-9]/g,
                                ''
                            )
                            .slice(
                                0,
                                11
                            );

                }
            );

        });

    }


    function initAuthPages() {

        initSubmitProtection();

        initPasswordToggles();

        initPhoneInput();

    }


    window.initAuthPages =
        initAuthPages;


    if (
        document.readyState ===
        'loading'
    ) {

        document.addEventListener(
            'DOMContentLoaded',
            initAuthPages,
            {
                once: true
            }
        );

    } else {

        initAuthPages();

    }


    /*
     * پشتیبانی از AJAX Navigation
     *
     * بعد از تغییر صفحه توسط سیستم
     * content-loaded اجرا می‌شود.
     */

    window.addEventListener(
        'content-loaded',
        function () {

            initAuthPages();

        }
    );

})();
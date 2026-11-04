(function () {
    'use strict';

    if (window.FragCityMyServersInitialized) return;
    window.FragCityMyServersInitialized = true;

    const errorMessages = {
        authentication_required: 'نشست شما منقضی شده است؛ دوباره وارد FragCity شوید.',
        csrf_failed: 'درخواست امنیتی منقضی شده است؛ صفحه را تازه‌سازی کنید.',
        current_password_incorrect: 'رمز فعلی FragCity صحیح نیست.',
        password_confirmation_mismatch: 'رمز جدید و تکرار آن مطابقت ندارند.',
        panel_password_policy: 'رمز پنل باید حداقل ۱۲ کاراکتر، شامل حروف و عدد و بدون فاصله باشد.',
        panel_password_must_be_different: 'رمز پنل باید با رمز FragCity متفاوت باشد.',
        panel_password_rate_limited: 'تعداد تلاش‌های ناموفق زیاد بوده است. ۱۵ دقیقه بعد دوباره تلاش کنید.',
        required_fields_missing: 'تمام فیلدهای لازم را تکمیل کنید.',
        paymenter_account_not_linked: 'اتصال حساب سرور کامل نیست؛ ابتدا همگام‌سازی حساب را تکمیل کنید.',
        service_not_found: 'این سرویس برای حساب شما پیدا نشد.',
        panel_password_update_failed: 'تغییر رمز پنل انجام نشد. تنظیمات اتصال پنل را بررسی کنید.',
        paymenter_unreachable: 'ارتباط با سرویس مدیریت سرورها برقرار نشد.',
        paymenter_invalid_response: 'پاسخ سرویس مدیریت سرورها معتبر نیست.'
    };

    function showMessage(form, message, type) {
        const target = form.querySelector('[data-password-message]');
        if (!target) return;

        target.textContent = message;
        target.classList.remove('is-error', 'is-success');
        if (type) target.classList.add(type === 'success' ? 'is-success' : 'is-error');
    }

    async function submitPasswordForm(form) {
        const root = document.getElementById('fcMyServers');
        if (!root) return;

        const serviceId = Number(form.querySelector('[name="service_id"]')?.value || 0);
        const currentPassword = form.querySelector('[name="current_password"]')?.value || '';
        const password = form.querySelector('[name="password"]')?.value || '';
        const passwordConfirm = form.querySelector('[name="password_confirm"]')?.value || '';
        const submit = form.querySelector('button[type="submit"]');

        if (!serviceId || !currentPassword || !password || !passwordConfirm) {
            showMessage(form, 'تمام فیلدها را تکمیل کنید.', 'error');
            return;
        }

        if (password !== passwordConfirm) {
            showMessage(form, errorMessages.password_confirmation_mismatch, 'error');
            return;
        }

        if (password === currentPassword) {
            showMessage(form, errorMessages.panel_password_must_be_different, 'error');
            return;
        }

        if (password.length < 12 || password.length > 128 || /\s/u.test(password) || !/\p{L}/u.test(password) || !/[0-9]/.test(password)) {
            showMessage(form, errorMessages.panel_password_policy, 'error');
            return;
        }

        const originalLabel = submit?.querySelector('span')?.textContent || 'ذخیره رمز پنل';
        if (submit) {
            submit.disabled = true;
            const label = submit.querySelector('span');
            if (label) label.textContent = 'در حال ذخیره...';
        }
        showMessage(form, 'در حال بررسی اطلاعات و تغییر رمز پنل...', '');

        try {
            const response = await fetch('/api/myservers.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-FragCity-CSRF': root.dataset.csrf || ''
                },
                body: JSON.stringify({
                    action: 'set_panel_password',
                    service_id: serviceId,
                    current_password: currentPassword,
                    password: password,
                    password_confirm: passwordConfirm
                })
            });

            let result = {};
            try {
                result = await response.json();
            } catch (error) {
                throw new Error('paymenter_invalid_response');
            }

            if (!response.ok || result.success !== true || result.data?.password_updated !== true) {
                const errorCode = typeof result.error === 'string' ? result.error : 'panel_password_update_failed';
                throw new Error(errorCode);
            }

            form.reset();
            showMessage(form, 'رمز اختصاصی پنل با موفقیت تنظیم شد. رمز جدید را در جای امن نگه دارید.', 'success');

            const dashboard = document.getElementById('fcMyServers');
            const status = dashboard?.querySelector('[data-password-status]');
            if (status) {
                status.classList.remove('is-pending');
                status.classList.add('is-set');
                status.innerHTML = '<i class="fa-solid fa-circle-check" aria-hidden="true"></i> رمز تنظیم شده';
            }

            const heading = form.closest('.fc-password-card')?.querySelector('.fc-dashboard-card__heading h3');
            if (heading) heading.textContent = 'تغییر رمز عبور پنل';

            const label = submit?.querySelector('span');
            if (label) label.textContent = 'ذخیره رمز جدید';
        } catch (error) {
            const code = error instanceof Error ? error.message : 'panel_password_update_failed';
            showMessage(form, errorMessages[code] || errorMessages.panel_password_update_failed, 'error');
        } finally {
            if (submit) {
                submit.disabled = false;
                const label = submit.querySelector('span');
                if (label && label.textContent === 'در حال ذخیره...') {
                    label.textContent = originalLabel;
                }
            }
        }
    }

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-panel-password-form]')) return;
        event.preventDefault();
        submitPasswordForm(form);
    });

    document.addEventListener('click', async function (event) {
        const button = event.target.closest('[data-copy-value]');
        if (!button) return;

        const value = button.getAttribute('data-copy-value') || '';
        if (!value) return;

        try {
            await navigator.clipboard.writeText(value);
            const original = button.innerHTML;
            button.innerHTML = '<i class="fa-solid fa-check" aria-hidden="true"></i>';
            button.setAttribute('aria-label', 'کپی شد');
            window.setTimeout(function () {
                button.innerHTML = original;
                button.setAttribute('aria-label', 'کپی');
            }, 1200);
        } catch (error) {
            const field = button.parentElement?.querySelector('strong');
            if (field) {
                const selection = window.getSelection();
                const range = document.createRange();
                range.selectNodeContents(field);
                selection.removeAllRanges();
                selection.addRange(range);
            }
        }
    });
})();

(function () {
    'use strict';
    if (window.FragCityLifecycleActionsBound) return;
    window.FragCityLifecycleActionsBound = true;

    const errorMessages = {
        admin_access_required: 'این عملیات فقط برای مدیران مجاز است.',
        csrf_failed: 'درخواست امنیتی منقضی شده است؛ صفحه را تازه‌سازی کنید.',
        validation_failed: 'اطلاعات ارسالی معتبر نیست.',
        admin_lifecycle_record_not_found: 'رکورد Lifecycle پیدا نشد.',
        admin_lifecycle_action_not_allowed: 'این عملیات برای وضعیت فعلی سرویس مجاز نیست.',
        admin_lifecycle_reconcile_failed: 'بررسی مجدد انجام نشد؛ ارتباط Paymenter/Pterodactyl را بررسی کنید.',
        admin_lifecycle_unavailable: 'سرویس مدیریت Lifecycle فعلاً در دسترس نیست.',
        paymenter_unreachable: 'اتصال به Paymenter برقرار نشد.',
        paymenter_invalid_response: 'پاسخ Paymenter معتبر نیست.'
    };

    function feedback(root, message, isError) {
        const target = root.querySelector('[data-lifecycle-feedback]');
        if (!target) return;
        target.textContent = message;
        target.classList.toggle('is-error', !!isError);
    }

    async function request(root, payload) {
        const response = await fetch(root.dataset.api || '/api/admin-lifecycle.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-FragCity-CSRF': root.dataset.csrf || ''
            },
            body: JSON.stringify(payload)
        });

        let result;
        try {
            result = await response.json();
        } catch (error) {
            throw new Error('admin_lifecycle_unavailable');
        }
        if (!response.ok || result.success !== true) {
            throw new Error(typeof result.error === 'string' ? result.error : 'admin_lifecycle_unavailable');
        }
        return result.data || {};
    }

    document.addEventListener('click', async function (event) {
        const button = event.target.closest('[data-lifecycle-reconcile]');
        if (!button) return;
        const root = button.closest('#fcLifecycleDashboard');
        if (!root) return;
        const serviceId = Number(button.dataset.serviceId || 0);
        if (!serviceId) return;

        const original = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><span>در حال بررسی...</span>';
        feedback(root, 'در حال بررسی وضعیت Paymenter و Pterodactyl...', false);

        try {
            await request(root, { action: 'reconcile', service_id: serviceId });
            feedback(root, 'وضعیت دوباره بررسی شد؛ داشبورد در حال به‌روزرسانی است.', false);
            window.location.reload();
        } catch (error) {
            const code = error instanceof Error ? error.message : 'admin_lifecycle_unavailable';
            feedback(root, errorMessages[code] || errorMessages.admin_lifecycle_unavailable, true);
            button.disabled = false;
            button.innerHTML = original;
        }
    });

    document.addEventListener('submit', async function (event) {
        const form = event.target.closest('[data-lifecycle-authorize]');
        if (!form) return;
        event.preventDefault();
        const root = form.closest('#fcLifecycleDashboard');
        if (!root) return;

        const serviceId = Number(form.dataset.serviceId || 0);
        const reason = (form.querySelector('[name="reason"]')?.value || '').trim();
        const submit = form.querySelector('button[type="submit"]');
        if (!serviceId || reason.length < 8 || reason.length > 500) {
            feedback(root, 'دلیل تأیید باید حداقل ۸ و حداکثر ۵۰۰ کاراکتر باشد.', true);
            return;
        }
        if (!window.confirm('این اقدام مجوز ساخت مجدد همین سرویس را ثبت می‌کند. پیش از ادامه از وضعیت Backup و احتمال از دست رفتن داده مطمئن شده‌اید؟')) {
            return;
        }

        const original = submit?.innerHTML || '';
        if (submit) {
            submit.disabled = true;
            submit.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><span>در حال ثبت مجوز...</span>';
        }
        feedback(root, 'در حال ثبت مجوز و Audit Event...', false);

        try {
            const result = await request(root, {
                action: 'authorize_recreation',
                service_id: serviceId,
                reason: reason
            });
            feedback(root, result.already_authorized ? 'این سرویس قبلاً مجوز ساخت مجدد گرفته است.' : 'مجوز ثبت شد. اجرای CreateJob همچنان باید جداگانه توسط ادمین انجام شود.', false);
            window.location.reload();
        } catch (error) {
            const code = error instanceof Error ? error.message : 'admin_lifecycle_unavailable';
            feedback(root, errorMessages[code] || errorMessages.admin_lifecycle_unavailable, true);
            if (submit) {
                submit.disabled = false;
                submit.innerHTML = original;
            }
        }
    });
})();

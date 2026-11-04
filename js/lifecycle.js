(function () {
    'use strict';
    if (window.FragCityLifecycleActionsBound) return;
    window.FragCityLifecycleActionsBound = true;

    const errorMessages = {
        admin_access_required: 'این عملیات فقط برای مدیران مجاز است.',
        csrf_failed: 'درخواست امنیتی منقضی شده است؛ صفحه را تازه‌سازی کنید.',
        validation_failed: 'اطلاعات ارسالی معتبر نیست.',
        admin_lifecycle_record_not_found: 'رکورد Lifecycle پیدا نشد.',
        admin_pterodactyl_client_not_found: 'شناسه Client واردشده در Pterodactyl پیدا نشد.',
        admin_lifecycle_identity_conflict: 'هویت یا ایمیل این Client با مشتری Paymenter تطبیق ندارد؛ هیچ تغییری اعمال نشد.',
        admin_lifecycle_identity_verification_failed: 'پس از تغییر external_id، تأیید مجدد Client موفق نبود.',
        admin_lifecycle_action_not_allowed: 'این عملیات برای وضعیت فعلی سرویس مجاز نیست.',
        admin_lifecycle_reconcile_failed: 'همگام‌سازی انجام نشد؛ ارتباط Paymenter/Pterodactyl را بررسی کنید.',
        lifecycle_storage_not_ready: 'ذخیره‌سازی Lifecycle کامل نیست؛ Migrationهای افزونه Paymenter بررسی شوند.',
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

        if (!window.confirm('وضعیت واقعی Pterodactyl با وضعیت سرویس Paymenter همگام می‌شود. در صورت اختلاف، ممکن است سرور Suspend یا Unsuspend شود. ادامه می‌دهید؟')) {
            return;
        }

        const original = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><span>در حال همگام‌سازی...</span>';
        feedback(root, 'در حال بررسی مالکیت و همگام‌سازی وضعیت Paymenter/Pterodactyl...', false);

        try {
            await request(root, { action: 'reconcile', service_id: serviceId });
            feedback(root, 'وضعیت واقعی سرور با وضعیت سرویس بررسی و همگام شد؛ داشبورد در حال به‌روزرسانی است.', false);
            window.location.reload();
        } catch (error) {
            const code = error instanceof Error ? error.message : 'admin_lifecycle_unavailable';
            feedback(root, errorMessages[code] || errorMessages.admin_lifecycle_unavailable, true);
            button.disabled = false;
            button.innerHTML = original;
        }
    });

    document.addEventListener('submit', async function (event) {
        const form = event.target.closest('[data-lifecycle-authorize], [data-lifecycle-revoke], [data-lifecycle-orphan-review], [data-lifecycle-client-verify]');
        if (!form) return;
        event.preventDefault();
        const root = form.closest('#fcLifecycleDashboard');
        if (!root) return;

        const isRevocation = form.matches('[data-lifecycle-revoke]');
        const isOrphanReview = form.matches('[data-lifecycle-orphan-review]');
        const isClientVerification = form.matches('[data-lifecycle-client-verify]');
        const serviceId = Number(form.dataset.serviceId || 0);
        const reason = (form.querySelector('[name="reason"]')?.value || '').trim();
        const submit = form.querySelector('button[type="submit"]');
        const pterodactylUserId = Number(form.querySelector('[name="pterodactyl_user_id"]')?.value || 0);
        if (!serviceId || reason.length < 8 || reason.length > 500 || (isClientVerification && (!Number.isInteger(pterodactylUserId) || pterodactylUserId < 1))) {
            feedback(root, isClientVerification
                ? 'شناسه Client باید عدد صحیح مثبت و دلیل حداقل ۸ و حداکثر ۵۰۰ کاراکتر باشد.'
                : 'دلیل باید حداقل ۸ و حداکثر ۵۰۰ کاراکتر باشد.', true);
            return;
        }
        const confirmation = isRevocation
            ? 'مجوز ساخت مجدد این سرویس لغو می‌شود. ادامه می‌دهید؟'
            : (isOrphanReview
                ? 'فقط نتیجه بررسی ثبت می‌شود و تصمیم حفظ منبع خارجی ثبت خواهد شد. هیچ سروری حذف یا تغییر نمی‌کند. ادامه می‌دهید؟'
                : (isClientVerification
                    ? 'سیستم وجود Client و تطابق دقیق ایمیل آن با Paymenter را بررسی می‌کند. فقط external_id خالی یا عددی قدیمی با Customer UUID جایگزین می‌شود؛ رمز و سایر مشخصات تغییر نمی‌کنند. ادامه می‌دهید؟'
                    : 'این اقدام مجوز ساخت مجدد همین سرویس را ثبت می‌کند. پیش از ادامه از وضعیت Backup و احتمال از دست رفتن داده مطمئن شده‌اید؟'));
        if (!window.confirm(confirmation)) return;

        const original = submit?.innerHTML || '';
        if (submit) {
            submit.disabled = true;
            submit.innerHTML = isRevocation
                ? '<i class="fa-solid fa-spinner fa-spin"></i><span>در حال لغو مجوز...</span>'
                : (isOrphanReview
                    ? '<i class="fa-solid fa-spinner fa-spin"></i><span>در حال ثبت بررسی...</span>'
                    : (isClientVerification
                        ? '<i class="fa-solid fa-spinner fa-spin"></i><span>در حال تأیید هویت...</span>'
                        : '<i class="fa-solid fa-spinner fa-spin"></i><span>در حال ثبت مجوز...</span>'));
        }
        feedback(root, isRevocation
            ? 'در حال لغو مجوز و ثبت Audit Event...'
            : (isOrphanReview
                ? 'در حال ثبت نتیجه بررسی و Audit Event...'
                : (isClientVerification
                    ? 'در حال بررسی Client در Pterodactyl و ثبت نگاشت تأییدشده...'
                    : 'در حال ثبت مجوز و Audit Event...')), false);

        try {
            const action = isRevocation
                ? 'revoke_recreation'
                : (isOrphanReview
                    ? 'acknowledge_paymenter_missing'
                    : (isClientVerification ? 'verify_client_identity' : 'authorize_recreation'));
            const requestPayload = {
                action: action,
                service_id: serviceId,
                reason: reason
            };
            if (isClientVerification) {
                requestPayload.pterodactyl_user_id = pterodactylUserId;
            }
            const result = await request(root, requestPayload);
            const message = isRevocation
                ? 'مجوز ساخت مجدد با موفقیت لغو شد.'
                : (isOrphanReview
                    ? (result.already_reviewed
                        ? 'این رکورد قبلاً بررسی و برای حفظ منبع خارجی ثبت شده است.'
                        : 'نتیجه بررسی ثبت شد؛ هیچ منبع خارجی حذف یا تغییر نکرد.')
                    : (isClientVerification
                        ? 'هویت Client تأیید و نگاشت مشتری به‌روزرسانی شد. حالا Reconcile را اجرا کنید تا مالکیت و وضعیت Server بررسی شود.'
                        : (result.already_authorized
                            ? 'این سرویس قبلاً مجوز ساخت مجدد گرفته است.'
                            : 'مجوز ثبت شد. اجرای CreateJob همچنان باید جداگانه توسط ادمین انجام شود.')));
            feedback(root, message, false);
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

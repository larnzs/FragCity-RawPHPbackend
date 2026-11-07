(function () {
    'use strict';

    function initOrdersPage() {
        const root = document.getElementById('fcOrders');
        if (!root || root.dataset.ordersControllerReady === '1') return;
        root.dataset.ordersControllerReady = '1';

        const statusCard = root.querySelector('#fcOrdersVipStatus');
        const openButton = root.querySelector('[data-vip-revoke-open]');
        const dialog = document.getElementById('fcVipRevokeDialog');
        const confirmButton = dialog ? dialog.querySelector('[data-vip-revoke-confirm]') : null;
        const cancelButton = dialog ? dialog.querySelector('[data-vip-revoke-cancel]') : null;
        const modalFeedback = dialog ? dialog.querySelector('[data-vip-revoke-modal-feedback]') : null;
        const pageFeedback = statusCard ? statusCard.querySelector('[data-vip-revoke-feedback]') : null;

        if (!statusCard || !openButton || !dialog || !confirmButton) return;

        // A transformed AJAX page wrapper would otherwise become the containing
        // block for fixed UI. Native dialogs live in the browser top layer, and
        // moving it to body also keeps its viewport centering consistent.
        if (dialog.parentElement !== document.body) {
            document.body.appendChild(dialog);
        }

        const messages = {
            authentication_required: 'نشست شما معتبر نیست؛ دوباره وارد حساب FragCity شوید.',
            csrf_failed: 'درخواست امنیتی منقضی شده است؛ صفحه را تازه‌سازی کنید.',
            membership_not_active: 'در حال حاضر اشتراک VIP فعالی برای ابطال وجود ندارد.',
            vipcore_disabled: 'اتصال VIPCore روی سرور فعال نیست.',
            vipcore_not_configured: 'تنظیمات اتصال VIPCore کامل نیست.',
            vipcore_unavailable: 'دیتابیس VIPCore یا جدول تاریخچه ابطال در دسترس نیست.',
            vip_revoke_unavailable: 'ابطال وضعیت VIP انجام نشد. ارتباط سرویس را بررسی کنید و دوباره تلاش کنید.'
        };

        function showFeedback(target, message, isError) {
            if (!target) return;
            target.textContent = message || '';
            target.classList.toggle('is-error', Boolean(isError));
            target.classList.toggle('is-success', Boolean(message) && !isError);
        }

        function setBusy(busy) {
            confirmButton.disabled = busy;
            if (cancelButton) cancelButton.disabled = busy;
            confirmButton.innerHTML = busy
                ? '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> در حال ابطال...'
                : '<i class="fa-solid fa-shield-halved" aria-hidden="true"></i> بله، باطل کن';
            dialog.setAttribute('aria-busy', busy ? 'true' : 'false');
        }

        function updateOrderCard(orderRef) {
            if (!orderRef) return;
            const orderCard = Array.from(root.querySelectorAll('[data-order-ref]'))
                .find((card) => card.dataset.orderRef === orderRef);
            if (!orderCard) return;

            const status = orderCard.querySelector('.fc-orders__status');
            if (status) {
                status.className = 'fc-orders__status fc-orders__status--muted';
                status.innerHTML = '<i class="fa-solid fa-shield-halved" aria-hidden="true"></i> باطل‌شده توسط کاربر';
            }

            const description = orderCard.querySelector('.fc-orders__order-description');
            if (description) {
                description.textContent = 'دسترسی این اشتراک به درخواست شما از VIPCore حذف شده است. سابقه پرداخت برای پیگیری نگهداری می‌شود.';
            }

            orderCard.querySelector('.fc-orders__retry')?.remove();
            orderCard.querySelector('.fc-orders__verified')?.remove();
        }

        function updateMembershipCard(data) {
            statusCard.classList.remove('is-active');
            statusCard.classList.add('is-revoked');

            const icon = statusCard.querySelector('.fc-orders__vip-status-icon i');
            if (icon) {
                icon.className = 'fa-solid fa-shield-check';
            }

            const description = statusCard.querySelector('.fc-orders__vip-status-description');
            if (description) {
                description.textContent = 'سطح VIP فعلی با موفقیت باطل شد؛ اکنون می‌توانید اشتراک جدیدی تهیه کنید.';
            }

            statusCard.querySelector('.fc-orders__vip-status-meta')?.remove();
            statusCard.querySelector('[data-vip-revoke-open]')?.remove();

            const actions = statusCard.querySelector('.fc-orders__vip-status-actions');
            if (actions) {
                const buyLink = document.createElement('a');
                buyLink.className = 'fc-orders__buy-link';
                buyLink.href = '/vip/';
                buyLink.setAttribute('data-ajax-nav', '');
                buyLink.setAttribute('data-slug', 'vip');
                buyLink.innerHTML = '<i class="fa-solid fa-crown" aria-hidden="true"></i> خرید VIP';
                actions.replaceChildren(buyLink);
            }

            const level = Number(data.level || 0);
            if (level > 0) {
                statusCard.dataset.revokedVipLevel = String(level);
            }
        }

        function openDialog() {
            showFeedback(modalFeedback, '', false);
            setBusy(false);
            if (typeof dialog.showModal === 'function') {
                dialog.showModal();
            } else {
                dialog.setAttribute('open', '');
            }
        }

        function closeDialog() {
            if (typeof dialog.close === 'function' && dialog.open) {
                dialog.close();
            } else {
                dialog.removeAttribute('open');
            }
        }

        openButton.addEventListener('click', openDialog);
        if (cancelButton) cancelButton.addEventListener('click', closeDialog);

        dialog.addEventListener('click', function (event) {
            if (event.target === dialog && !confirmButton.disabled) {
                closeDialog();
            }
        });

        confirmButton.addEventListener('click', async function () {
            if (confirmButton.disabled) return;

            setBusy(true);
            showFeedback(modalFeedback, 'در حال بررسی وضعیت اشتراک و ثبت ابطال...', false);

            try {
                const response = await fetch('/api/vip-revoke.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-FragCity-CSRF': root.dataset.vipRevokeCsrf || ''
                    },
                    body: '{}'
                });

                let result = {};
                try {
                    result = await response.json();
                } catch (error) {
                    throw new Error('invalid_response');
                }

                if (!response.ok || result.success !== true || result.data?.revoked !== true) {
                    const code = typeof result.error === 'string' ? result.error : 'vip_revoke_unavailable';
                    throw new Error(code);
                }

                updateMembershipCard(result.data);
                updateOrderCard(typeof result.data.order_ref === 'string' ? result.data.order_ref : '');
                showFeedback(
                    pageFeedback,
                    'وضعیت VIP با موفقیت باطل شد. اکنون می‌توانید اشتراک جدیدی تهیه کنید.',
                    false
                );
                closeDialog();
            } catch (error) {
                const code = error && typeof error.message === 'string' ? error.message : 'vip_revoke_unavailable';
                const message = code === 'invalid_response'
                    ? 'پاسخ سرور معتبر نیست؛ وضعیت VIP را دوباره بررسی کنید.'
                    : (messages[code] || messages.vip_revoke_unavailable);
                showFeedback(modalFeedback, message, true);
                setBusy(false);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initOrdersPage, { once: true });
    } else {
        initOrdersPage();
    }
})();

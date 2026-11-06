(function () {
    'use strict';

    function formatExpiry(value) {
        if (value === null || value === undefined || value === '') return '';
        const timestamp = Number(value);
        if (Number.isFinite(timestamp) && timestamp > 0) {
            try {
                return new Intl.DateTimeFormat('fa-IR', { dateStyle: 'medium', timeStyle: 'short' })
                    .format(new Date(timestamp * 1000));
            } catch (error) {
                return new Date(timestamp * 1000).toLocaleString();
            }
        }
        return String(value);
    }

    function initSuccessModal(root) {
        if (!root) return;

        // The page fader has a transform, which changes the containing block of
        // position:fixed descendants. Mount the overlay directly under body.
        if (root.parentElement !== document.body) {
            document.body.appendChild(root);
        }

        if (root.dataset.fcSuccessReady === '1') return;
        root.dataset.fcSuccessReady = '1';

        const dialog = root.querySelector('.fc-success-dialog');
        const closeButton = root.querySelector('[data-fc-success-close]');
        const backdrop = root.querySelector('[data-fc-success-backdrop]');
        const actionButton = root.querySelector('[data-fc-success-action]');
        const actionLabel = root.querySelector('[data-fc-success-action-label]');
        const titleNode = root.querySelector('[data-fc-success-title]');
        const messageNode = root.querySelector('[data-fc-success-message]');
        const eyebrowNode = root.querySelector('[data-fc-success-eyebrow]');
        const factsBox = root.querySelector('[data-fc-success-facts]');
        let previousFocus = null;
        let activeConfig = {};

        function setFact(key, value, label) {
            const fact = root.querySelector('[data-fc-success-fact="' + key + '"]');
            const valueNode = root.querySelector('[data-fc-success-value="' + key + '"]');
            if (!fact || !valueNode) return false;

            const cleanValue = value === null || value === undefined ? '' : String(value).trim();
            fact.hidden = cleanValue === '';
            if (cleanValue !== '') valueNode.textContent = cleanValue;

            if (key === 'amount' && label) {
                const labelNode = root.querySelector('[data-fc-success-label="amount"]');
                if (labelNode) labelNode.textContent = String(label);
            }
            return cleanValue !== '';
        }

        function hide() {
            root.classList.remove('is-visible');
            root.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('fc-success-modal-open');
            window.setTimeout(function () { root.hidden = true; }, 300);
            if (previousFocus && typeof previousFocus.focus === 'function') previousFocus.focus();
        }

        function leaveSuccess() {
            const returnUrl = String(activeConfig.returnUrl || '').trim();
            if (returnUrl !== '') {
                window.location.assign(returnUrl);
            } else if (activeConfig.reloadOnClose) {
                window.location.reload();
            } else {
                hide();
            }
        }

        function open(config) {
            activeConfig = Object.assign({}, config || {});
            const kind = String(activeConfig.kind || root.dataset.kind || 'success').toLowerCase();
            const isPayment = kind === 'payment';

            root.classList.toggle('is-payment', isPayment);
            root.classList.toggle('is-gift', kind === 'gift');

            if (titleNode) {
                titleNode.textContent = String(activeConfig.title ||
                    (isPayment ? 'پرداخت با موفقیت انجام شد' : 'هدیه ۳ روزه Silver را با موفقیت دریافت کردید!'));
            }
            if (messageNode) {
                messageNode.textContent = String(activeConfig.message ||
                    (isPayment
                        ? 'پرداخت شما توسط درگاه تأیید و با موفقیت ثبت شد.'
                        : 'عضویت Silver برای ۳ روز روی حساب Steam شما فعال شد. از بازی در سرورهای FragCity لذت ببرید.'));
            }
            if (eyebrowNode) {
                eyebrowNode.textContent = String(activeConfig.eyebrow ||
                    (isPayment ? 'FRAGCITY · PAYMENT VERIFIED' : 'FRAGCITY · WELCOME GIFT ACTIVATED'));
            }

            const visibleFacts = [
                setFact('tier', activeConfig.tier),
                setFact('duration', activeConfig.duration),
                setFact('expires', formatExpiry(activeConfig.expires)),
                setFact('amount', activeConfig.amount, activeConfig.amountLabel),
                setFact('reference', activeConfig.reference)
            ];
            if (factsBox) factsBox.hidden = !visibleFacts.some(Boolean);

            const returnUrl = String(activeConfig.returnUrl || root.dataset.returnUrl || '').trim();
            const reloadOnClose = activeConfig.reloadOnClose === true || root.dataset.reloadOnClose === '1';
            const dismissible = activeConfig.dismissible !== false && root.dataset.dismissible !== '0';

            if (actionLabel) {
                actionLabel.textContent = String(activeConfig.actionLabel ||
                    (returnUrl ? (root.dataset.returnLabel || 'بازگشت به FragCity') : 'مشاهده وضعیت اشتراک'));
            }
            if (closeButton) closeButton.hidden = !dismissible;

            activeConfig.returnUrl = returnUrl;
            activeConfig.reloadOnClose = reloadOnClose;
            activeConfig.dismissible = dismissible;

            previousFocus = document.activeElement;
            root.hidden = false;
            root.setAttribute('aria-hidden', 'false');
            document.body.classList.add('fc-success-modal-open');
            root.classList.remove('is-visible');
            void root.offsetWidth;
            root.classList.add('is-visible');

            window.requestAnimationFrame(function () {
                if (actionButton && typeof actionButton.focus === 'function') actionButton.focus();
                else if (dialog && typeof dialog.focus === 'function') dialog.focus();
            });
        }

        function dismiss() {
            if (!activeConfig.dismissible) return;
            leaveSuccess();
        }

        if (closeButton) closeButton.addEventListener('click', dismiss);
        if (backdrop) backdrop.addEventListener('click', dismiss);
        if (actionButton) actionButton.addEventListener('click', leaveSuccess);

        if (dialog) {
            dialog.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    dismiss();
                    return;
                }
                if (event.key !== 'Tab') return;

                const focusable = Array.from(dialog.querySelectorAll(
                    'button:not([disabled]):not([hidden]), a[href], [tabindex]:not([tabindex="-1"])'
                )).filter(function (item) { return item.offsetParent !== null; });

                if (!focusable.length) {
                    event.preventDefault();
                    dialog.focus();
                    return;
                }

                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            });
        }

        window.FragCitySuccessModal = { open: open, close: dismiss };

        if (root.dataset.autoOpen === '1') {
            open({
                kind: root.dataset.kind,
                title: root.dataset.title,
                message: root.dataset.message,
                tier: root.dataset.tier,
                duration: root.dataset.duration,
                expires: root.dataset.expires,
                amount: root.dataset.amount,
                amountLabel: root.dataset.amountLabel,
                reference: root.dataset.reference,
                returnUrl: root.dataset.returnUrl,
                actionLabel: root.dataset.returnLabel,
                dismissible: root.dataset.dismissible !== '0',
                reloadOnClose: root.dataset.reloadOnClose === '1'
            });
        }
    }

    function boot() {
        document.querySelectorAll('[data-fc-success-modal]').forEach(initSuccessModal);
    }

    function handleContentLoaded(event) {
        const page = event && event.detail ? String(event.detail.page || '') : '';

        if (page !== 'vip') {
            document.querySelectorAll('[data-fc-success-modal]').forEach(function (root) {
                root.remove();
            });
            document.body.classList.remove('fc-success-modal-open');
            return;
        }

        // A fresh VIP fragment may have been inserted while the previous modal
        // was mounted under body. Remove that old instance, then mount the new one.
        document.querySelectorAll('[data-fc-success-modal]').forEach(function (root) {
            if (root.parentElement === document.body) root.remove();
        });
        boot();
    }

    window.addEventListener('content-loaded', handleContentLoaded);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();

(function () {
    'use strict';

    const modal = document.getElementById('fcWalletChargeModal');
    if (!modal || modal.dataset.initialized === '1') {
        return;
    }

    modal.dataset.initialized = '1';

    const dialog = modal.querySelector('.fc-wallet-charge-modal__dialog');
    const form = document.getElementById('fcWalletChargeForm');
    const amountInput = document.getElementById('fcWalletChargeAmount');
    const hint = modal.querySelector('.fc-wallet-charge-modal__hint');
    const csrfInput = form ? form.querySelector('input[name="csrf_token"]') : null;
    const balanceOutputs = Array.from(document.querySelectorAll('[data-fc-wallet-balance]'));
    const chargeTriggers = Array.from(document.querySelectorAll('[data-fc-wallet-charge-open]'));

    let lastTrigger = null;
    let closeTimer = 0;
    let walletState = 'loading';
    let lastBalanceCheck = 0;

    function setHint(message, isError) {
        if (!hint) return;
        hint.textContent = message;
        hint.dataset.state = isError ? 'error' : 'info';
    }

    function setBalanceLabel(message) {
        balanceOutputs.forEach(function (output) {
            output.textContent = message;
        });
    }

    function setChargeEnabled(enabled) {
        chargeTriggers.forEach(function (trigger) {
            trigger.disabled = !enabled;
            trigger.setAttribute('aria-disabled', enabled ? 'false' : 'true');
        });
        if (form) {
            const submit = form.querySelector('button[type="submit"]');
            if (submit) submit.disabled = !enabled;
        }
    }

    function formatTomanFromRial(value) {
        const rial = Number(value);
        if (!Number.isFinite(rial) || rial < 0) return null;
        return new Intl.NumberFormat('fa-IR', {
            maximumFractionDigits: 8
        }).format(rial / 10) + ' تومان';
    }

    function formatInteger(value) {
        return new Intl.NumberFormat('fa-IR', {
            maximumFractionDigits: 0
        }).format(value);
    }

    async function refreshWalletBalance(force) {
        if (!force && lastBalanceCheck > 0 && Date.now() - lastBalanceCheck < 60000) {
            return walletState === 'enabled';
        }

        walletState = 'loading';
        setChargeEnabled(false);
        setBalanceLabel('در حال دریافت موجودی...');
        setHint('در حال دریافت موجودی و محدودیت‌های شارژ از Paymenter...', false);

        const csrf = csrfInput && typeof csrfInput.value === 'string'
            ? csrfInput.value.trim()
            : '';

        if (!/^[a-f0-9]{64}$/i.test(csrf)) {
            walletState = 'unavailable';
            setBalanceLabel('موجودی در دسترس نیست');
            setHint('توکن امنیتی صفحه معتبر نیست. صفحه را تازه‌سازی کنید.', true);
            lastBalanceCheck = Date.now();
            return false;
        }

        try {
            const response = await fetch('/api/server-store.php', {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-FragCity-CSRF': csrf
                },
                body: JSON.stringify({ action: 'wallet_balance' })
            });

            const payload = await response.json();
            const data = payload && payload.data && typeof payload.data === 'object'
                ? payload.data
                : null;

            if (!response.ok || payload.success !== true || !data) {
                throw new Error('wallet_balance_unavailable');
            }

            const balanceLabel = formatTomanFromRial(data.balance);
            if (balanceLabel === null) {
                throw new Error('wallet_balance_invalid');
            }

            if (data.wallet_enabled !== true) {
                walletState = 'disabled';
                setBalanceLabel('کیف پول غیرفعال');
                setHint('کیف پول در تنظیمات Paymenter غیرفعال است؛ امکان شارژ وجود ندارد.', true);
                lastBalanceCheck = Date.now();
                return false;
            }

            const minimumRial = Number(data.limits && data.limits.minimum_deposit);
            const maximumRial = Number(data.limits && data.limits.maximum_deposit);
            const minimumToman = Math.max(1000, Math.ceil(minimumRial / 10));
            const maximumToman = Math.floor(maximumRial / 10);

            if (
                !Number.isFinite(minimumRial)
                || !Number.isFinite(maximumRial)
                || minimumRial <= 0
                || maximumRial < minimumRial
                || maximumToman < minimumToman
            ) {
                walletState = 'misconfigured';
                setBalanceLabel(balanceLabel);
                setHint('محدودیت حداقل و حداکثر شارژ در تنظیمات Paymenter کامل یا معتبر نیست.', true);
                lastBalanceCheck = Date.now();
                return false;
            }

            if (amountInput) {
                amountInput.min = String(minimumToman);
                amountInput.max = String(maximumToman);
                amountInput.step = '1';
                amountInput.setAttribute('placeholder', 'حداقل ' + formatInteger(minimumToman) + ' تومان');
                const currentAmount = Number(amountInput.value);
                if (amountInput.value !== '' && (
                    !Number.isInteger(currentAmount)
                    || currentAmount < minimumToman
                    || currentAmount > maximumToman
                )) {
                    amountInput.value = '';
                }
            }

            walletState = 'enabled';
            setBalanceLabel(balanceLabel);
            setHint(
                'محدوده شارژ: حداقل ' + formatInteger(minimumToman)
                + ' و حداکثر ' + formatInteger(maximumToman) + ' تومان. قوانین نهایی توسط Paymenter بررسی می‌شود.',
                false
            );
            setChargeEnabled(true);
            lastBalanceCheck = Date.now();
            return true;
        } catch (error) {
            walletState = 'unavailable';
            setBalanceLabel('موجودی در دسترس نیست');
            setHint('ارتباط با Paymenter برقرار نشد. تا زمان بازیابی ارتباط، شارژ غیرفعال است.', true);
            lastBalanceCheck = Date.now();
            return false;
        }
    }

    function openModal(trigger) {
        if (trigger instanceof HTMLElement) {
            lastTrigger = trigger;
        }

        window.clearTimeout(closeTimer);
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('fc-wallet-charge-open');

        if (Date.now() - lastBalanceCheck >= 60000) {
            refreshWalletBalance(true);
        }

        window.requestAnimationFrame(function () {
            modal.classList.add('is-open');
            if (amountInput) {
                amountInput.focus({ preventScroll: true });
            } else if (dialog) {
                dialog.focus({ preventScroll: true });
            }
        });
    }

    function closeModal() {
        if (modal.hidden) {
            return;
        }

        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('fc-wallet-charge-open');

        closeTimer = window.setTimeout(function () {
            if (!modal.classList.contains('is-open')) {
                modal.hidden = true;
                if (lastTrigger && document.contains(lastTrigger)) {
                    lastTrigger.focus({ preventScroll: true });
                }
            }
        }, 190);
    }

    document.addEventListener('click', function (event) {
        const target = event.target instanceof Element ? event.target : null;
        if (!target) return;

        const openTrigger = target.closest('[data-fc-wallet-charge-open]');
        if (openTrigger) {
            event.preventDefault();
            if (walletState === 'enabled') {
                openModal(openTrigger);
            } else {
                refreshWalletBalance(true).then(function (enabled) {
                    if (enabled) openModal(openTrigger);
                });
            }
            return;
        }

        const closeTrigger = target.closest('[data-fc-wallet-charge-close]');
        if (closeTrigger && modal.contains(closeTrigger)) {
            event.preventDefault();
            closeModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    });

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    if (form) {
        form.addEventListener('submit', function (event) {
            if (walletState !== 'enabled') {
                event.preventDefault();
                setHint('ابتدا باید اتصال و محدودیت‌های کیف پول از Paymenter تأیید شود.', true);
                refreshWalletBalance(true);
                return;
            }

            const amount = Number(amountInput && amountInput.value);
            const minimum = Number(amountInput && amountInput.min);
            const maximum = Number(amountInput && amountInput.max);
            if (
                !Number.isInteger(amount)
                || amount < minimum
                || amount > maximum
            ) {
                event.preventDefault();
                setHint(
                    'مبلغ باید بین ' + formatInteger(minimum)
                    + ' تا ' + formatInteger(maximum) + ' تومان باشد.',
                    true
                );
                if (amountInput) amountInput.focus();
            }
        });
    }

    window.openFragCityWalletChargeModal = openModal;
    window.closeFragCityWalletChargeModal = closeModal;

    setChargeEnabled(false);
    refreshWalletBalance(true);
})();

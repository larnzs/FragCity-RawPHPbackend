(function () {
    'use strict';

    const modal = document.getElementById('fcWalletChargeModal');
    if (!modal || modal.dataset.initialized === '1') {
        return;
    }

    modal.dataset.initialized = '1';

    const dialog = modal.querySelector('.fc-wallet-charge-modal__dialog');
    const amountInput = document.getElementById('fcWalletChargeAmount');
    let lastTrigger = null;
    let closeTimer = 0;

    function openModal(trigger) {
        if (trigger instanceof HTMLElement) {
            lastTrigger = trigger;
        }

        window.clearTimeout(closeTimer);
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('fc-wallet-charge-open');

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
        const openTrigger = event.target.closest('[data-fc-wallet-charge-open]');
        if (openTrigger) {
            event.preventDefault();
            openModal(openTrigger);
            return;
        }

        const closeTrigger = event.target.closest('[data-fc-wallet-charge-close]');
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

    window.openFragCityWalletChargeModal = openModal;
    window.closeFragCityWalletChargeModal = closeModal;
})();
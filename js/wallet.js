(function () {
    'use strict';

    function initWalletPage() {
        const root = document.querySelector('.wallet-page');
        const modal = document.getElementById('withdrawModal');

        if (!root || !modal) {
            return;
        }

        if (root.dataset.walletInitialized === '1') {
            return;
        }

        root.dataset.walletInitialized = '1';

        const openBtn = document.getElementById('openWithdrawBtn');
        const closeBtn = document.getElementById('closeWithdrawBtn');
        const cancelBtn = document.getElementById('cancelWithdrawBtn');
        const backdrop = modal.querySelector('[data-close-withdraw]');
        const form = document.getElementById('withdrawForm');
        const amountInput = document.getElementById('withdrawAmount');
        const cardInput = document.getElementById('withdrawCard');
        const result = document.getElementById('withdrawResult');
        const submitBtn = form
            ? form.querySelector('.withdraw-submit-btn')
            : null;

        function setResult(message, type) {
            if (!result) {
                return;
            }

            result.textContent = message || '';
            result.className = 'withdraw-result';

            if (type) {
                result.classList.add('is-' + type);
            }
        }

        function openModal() {
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('withdraw-modal-open');

            setResult('', '');

            window.setTimeout(function () {
                if (amountInput) {
                    amountInput.focus();
                }
            }, 50);
        }

        function closeModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('withdraw-modal-open');
        }

        async function submitWithdraw(event) {
            if (event) {
                event.preventDefault();
            }

            if (!amountInput || !cardInput) {
                return;
            }

            const amount = amountInput.value.trim();
            const card = cardInput.value.trim();
            const amountNumber = Number.parseInt(amount, 10);

            if (
                !amount ||
                Number.isNaN(amountNumber) ||
                amountNumber < 10000
            ) {
                setResult(
                    'حداقل مبلغ ۱۰,۰۰۰ تومان است.',
                    'error'
                );

                amountInput.focus();
                return;
            }

            if (!card) {
                setResult(
                    'شماره کارت / شبا را وارد کنید.',
                    'error'
                );

                cardInput.focus();
                return;
            }

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add('is-loading');

                const label =
                    submitBtn.querySelector('span');

                if (label) {
                    label.textContent =
                        'در حال ثبت درخواست...';
                }
            }

            setResult(
                'در حال ثبت درخواست...',
                'loading'
            );

            try {
                const formData = new FormData();

                formData.append(
                    'amount',
                    amount
                );

                formData.append(
                    'card',
                    card
                );

                const response = await fetch(
                    'api/wallet_api.php?action=request_withdraw',
                    {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With':
                                'XMLHttpRequest'
                        }
                    }
                );

                if (!response.ok) {
                    throw new Error(
                        'HTTP ' +
                        response.status
                    );
                }

                const data =
                    await response.json();

                if (data.success) {
                    setResult(
                        'درخواست شما ثبت شد و پس از بررسی انجام خواهد شد.',
                        'success'
                    );

                    window.setTimeout(
                        function () {
                            closeModal();
                            window.location.reload();
                        },
                        2000
                    );
                } else {
                    setResult(
                        data.error ||
                            'خطا در ثبت درخواست',
                        'error'
                    );
                }
            } catch (error) {
                setResult(
                    'خطا در ارتباط با سرور',
                    'error'
                );
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove(
                        'is-loading'
                    );

                    const label =
                        submitBtn.querySelector(
                            'span'
                        );

                    if (label) {
                        label.textContent =
                            'ثبت درخواست';
                    }
                }
            }
        }

        if (openBtn) {
            openBtn.addEventListener(
                'click',
                openModal
            );
        }

        if (closeBtn) {
            closeBtn.addEventListener(
                'click',
                closeModal
            );
        }

        if (cancelBtn) {
            cancelBtn.addEventListener(
                'click',
                closeModal
            );
        }

        if (backdrop) {
            backdrop.addEventListener(
                'click',
                closeModal
            );
        }

        if (form) {
            form.addEventListener(
                'submit',
                submitWithdraw
            );
        }

        document.addEventListener(
            'keydown',
            function (event) {
                if (
                    event.key === 'Escape' &&
                    modal.classList.contains(
                        'is-open'
                    )
                ) {
                    closeModal();
                }
            }
        );

        window.openWithdrawModal =
            openModal;

        window.closeWithdrawModal =
            closeModal;

        window.submitWithdraw =
            submitWithdraw;
    }

    window.initWalletPage =
        initWalletPage;

    if (
        document.readyState ===
        'loading'
    ) {
        document.addEventListener(
            'DOMContentLoaded',
            initWalletPage,
            {
                once: true
            }
        );
    } else {
        initWalletPage();
    }

    window.addEventListener(
        'content-loaded',
        function (event) {
            if (
                !event.detail ||
                event.detail.page === 'wallet'
            ) {
                initWalletPage();
            }
        }
    );
})();
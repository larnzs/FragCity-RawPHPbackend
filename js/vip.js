(function () {
    'use strict';

    function initVipStorefront() {
        const root = document.getElementById('vipStorefront');
        if (!root || root.dataset.vipStoreReady === '1') return;
        root.dataset.vipStoreReady = '1';
        window.fragCityVipActiveRoot = root;

        const plans = Array.from(root.querySelectorAll('[data-vip-plan]'));
        const inactiveToggle = root.querySelector('#vipLockedToggle');
        const inactivePlans = root.querySelector('#vipInactivePlans');
        let inactiveExpanded = Boolean(inactiveToggle && inactiveToggle.getAttribute('aria-expanded') === 'true');
        const testPurchaseBox = root.querySelector('[data-vip-test-purchase-box]');
        const testPurchaseButton = root.querySelector('[data-vip-test-purchase]');
        const testFeedback = root.querySelector('[data-vip-test-feedback]');
        const freeTrialButton = root.querySelector('[data-vip-free-claim]');
        const freeTrialFeedback = root.querySelector('[data-vip-free-feedback]');
        const durationSelector = root.querySelector('[data-vip-duration-selector]');
        const durationOptions = durationSelector
            ? Array.from(durationSelector.querySelectorAll('[data-vip-duration-option]'))
            : [];
        const durationSelectionStatus = durationSelector
            ? durationSelector.querySelector('[data-vip-duration-selection-status]')
            : null;
        const invoice = root.querySelector('[data-vip-invoice]');
        const invoiceTier = invoice ? invoice.querySelector('[data-invoice-tier]') : null;
        const invoiceLevel = invoice ? invoice.querySelector('[data-invoice-level]') : null;
        const invoiceDuration = invoice ? invoice.querySelector('[data-invoice-duration]') : null;
        const invoiceTotal = invoice ? invoice.querySelector('[data-invoice-total]') : null;
        const invoiceStatus = invoice ? invoice.querySelector('[data-invoice-status]') : null;
        const checkoutButton = invoice ? invoice.querySelector('[data-vip-checkout]') : null;
        const comparisonModal = document.getElementById('vipComparisonModal');
        const comparisonDialog = comparisonModal
            ? comparisonModal.querySelector('#vipComparisonDialog')
            : null;
        const comparisonCloseButton = comparisonModal
            ? comparisonModal.querySelector('.vip-comparison-close')
            : null;
        let activePlan = null;
        let hasUserSelectedPlan = false;
        let selectedDurationDays = '';
        let previousModalFocus = null;
        let previousBodyOverflow = '';
        let comparisonCloseTimer = null;
        let comparisonIsClosing = false;
        let priceMap = {};
        try {
            priceMap = JSON.parse(root.dataset.vipPrices || '{}');
            if (!priceMap || typeof priceMap !== 'object' || Array.isArray(priceMap)) priceMap = {};
        } catch (error) {
            priceMap = {};
        }

        if (!plans.length) return;

        if (invoice && invoice.parentElement !== document.body) {
            document.body.appendChild(invoice);
        }

        function syncInvoiceFooterDock() {
            // Resolve the current page's invoice every time; AJAX navigation replaces it.
            const currentInvoice = document.querySelector('body > [data-vip-invoice]');
            if (!currentInvoice || !currentInvoice.isConnected) return;

            const footer = document.getElementById('siteFooter');
            if (!footer) {
                currentInvoice.classList.remove('is-footer-docked');
                currentInvoice.style.removeProperty('--vip-invoice-footer-top');
                return;
            }

            const invoiceRect = currentInvoice.getBoundingClientRect();
            const footerRect = footer.getBoundingClientRect();
            const invoiceHeight = invoiceRect.height;
            if (!invoiceHeight) return;

            let fixedBottom = Number(currentInvoice.dataset.vipFixedBottomGap);
            if (!Number.isFinite(fixedBottom)) {
                fixedBottom = Number.parseFloat(window.getComputedStyle(currentInvoice).bottom);
                if (!Number.isFinite(fixedBottom)) fixedBottom = 12;
                currentInvoice.dataset.vipFixedBottomGap = String(fixedBottom);
            }

            // Dock the fixed bar to the footer's top edge as soon as the two would overlap.
            const fixedTop = window.innerHeight - invoiceHeight - fixedBottom;
            if (footerRect.top <= fixedTop) {
                const dockTop = footerRect.top - invoiceHeight - fixedBottom;
                currentInvoice.style.setProperty('--vip-invoice-footer-top', dockTop + 'px');
                currentInvoice.classList.add('is-footer-docked');
            } else {
                currentInvoice.classList.remove('is-footer-docked');
                currentInvoice.style.removeProperty('--vip-invoice-footer-top');
            }
        }

        function bindInvoiceFooterDocking() {
            if (window.fragCityVipInvoiceFooterDockBound) {
                if (typeof window.fragCitySyncVipInvoiceFooterDock === 'function') {
                    window.fragCitySyncVipInvoiceFooterDock();
                }
                return;
            }

            window.fragCityVipInvoiceFooterDockBound = true;
            let framePending = false;
            const scheduleSync = function () {
                if (framePending) return;
                framePending = true;
                window.requestAnimationFrame(function () {
                    framePending = false;
                    const currentInvoice = document.querySelector('body > [data-vip-invoice]');
                    if (!currentInvoice || !currentInvoice.isConnected) return;
                    syncInvoiceFooterDock();
                });
            };

            window.fragCitySyncVipInvoiceFooterDock = scheduleSync;
            window.addEventListener('scroll', scheduleSync, { passive: true });
            window.addEventListener('resize', scheduleSync, { passive: true });
            window.addEventListener('load', scheduleSync);
            window.addEventListener('content-loaded', scheduleSync);

            if (typeof ResizeObserver === 'function') {
                const pageContent = document.getElementById('mainContent');
                if (pageContent) {
                    const pageResizeObserver = new ResizeObserver(scheduleSync);
                    pageResizeObserver.observe(pageContent);
                    window.fragCityVipInvoiceResizeObserver = pageResizeObserver;
                }
            }

            scheduleSync();
        }

        bindInvoiceFooterDocking();

        if (comparisonModal && comparisonDialog) {
            if (comparisonModal.parentElement !== document.body) {
                document.body.appendChild(comparisonModal);
            }

            if (comparisonModal.dataset.vipComparisonBound !== '1') {
                comparisonModal.dataset.vipComparisonBound = '1';

                comparisonModal.addEventListener('click', function (event) {
                    if (event.target.closest('[data-vip-comparison-close]')) {
                        closeComparison();
                    }
                });

                comparisonDialog.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape') {
                        event.preventDefault();
                        closeComparison();
                        return;
                    }
                    if (event.key !== 'Tab') return;

                    const focusable = Array.from(comparisonDialog.querySelectorAll(
                        'button:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])'
                    )).filter(function (element) {
                        return !element.hidden && element.getAttribute('aria-hidden') !== 'true';
                    });
                    if (!focusable.length) {
                        event.preventDefault();
                        comparisonDialog.focus();
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
        }

        function finishComparisonClose() {
            if (!comparisonModal || comparisonModal.hidden) return;
            if (comparisonCloseTimer !== null) {
                window.clearTimeout(comparisonCloseTimer);
                comparisonCloseTimer = null;
            }
            comparisonIsClosing = false;
            comparisonModal.classList.remove('is-closing');
            comparisonModal.hidden = true;
            comparisonModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('vip-comparison-open');
            document.body.style.overflow = previousBodyOverflow;
            const focusTarget = previousModalFocus;
            previousModalFocus = null;
            if (focusTarget && focusTarget.isConnected && typeof focusTarget.focus === 'function') {
                focusTarget.focus();
            }
        }

        function closeComparison() {
            if (!comparisonModal || comparisonModal.hidden || comparisonIsClosing) return;

            const reducedMotion = (
                document.documentElement.classList.contains('fc-no-animation')
                || (typeof window.matchMedia === 'function'
                    && window.matchMedia('(prefers-reduced-motion: reduce)').matches)
            );

            if (reducedMotion) {
                finishComparisonClose();
                return;
            }

            comparisonIsClosing = true;
            comparisonModal.classList.add('is-closing');
            comparisonCloseTimer = window.setTimeout(finishComparisonClose, 220);
        }

        function openComparison(plan, trigger) {
            if (!comparisonModal || !comparisonDialog) return;
            const name = plan ? (plan.dataset.vipName || 'VIP') : 'VIP';
            const level = plan ? (plan.dataset.level || '') : '';
            const summary = plan ? (plan.dataset.vipSummaryFa || '') : '';
            const selectedLabel = comparisonModal.querySelector('[data-vip-comparison-selected-name]');
            const selectedContainer = comparisonModal.querySelector('[data-vip-comparison-selected]');
            const selectedDot = comparisonModal.querySelector('.vip-comparison-selected-dot');

            if (selectedLabel) {
                selectedLabel.textContent = plan
                    ? 'سطح انتخاب‌شده برای بررسی: ' + name + (summary ? ' · ' + summary : '')
                    : 'مقایسه همه سطح‌ها';
            }
            if (selectedContainer && plan) {
                selectedContainer.style.setProperty('--vip-tier-color', plan.dataset.vipColor || '#F97316');
            } else if (selectedContainer) {
                selectedContainer.style.removeProperty('--vip-tier-color');
            }
            if (selectedDot) {
                selectedDot.style.background = plan ? (plan.dataset.vipColor || '#F97316') : '#F97316';
            }

            comparisonModal.querySelectorAll('[data-comparison-tier]').forEach(function (heading) {
                const active = Boolean(plan && heading.dataset.comparisonTier === level);
                heading.classList.toggle('is-active-tier', active);
            });

            if (comparisonCloseTimer !== null) {
                window.clearTimeout(comparisonCloseTimer);
                comparisonCloseTimer = null;
            }
            comparisonIsClosing = false;
            comparisonModal.classList.remove('is-closing');
            previousModalFocus = trigger || document.activeElement;
            previousBodyOverflow = document.body.style.overflow;
            comparisonModal.hidden = false;
            comparisonModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('vip-comparison-open');
            document.body.style.overflow = 'hidden';

            if (comparisonCloseButton) {
                comparisonCloseButton.focus();
            } else {
                comparisonDialog.focus();
            }
        }

        function getConfiguredPrice(level, days) {
            const tierPrices = priceMap[String(level)];
            if (!tierPrices || typeof tierPrices !== 'object') return null;
            const raw = tierPrices[String(days)];
            const price = Number(raw);
            return Number.isSafeInteger(price) && price > 0 ? price : null;
        }

        function formatPrice(price) {
            return new Intl.NumberFormat('fa-IR').format(price) + ' ' + (root.dataset.vipCurrency || 'تومان');
        }

        function renderDurationPrices(level) {
            durationOptions.forEach(function (button) {
                const days = Number(button.dataset.durationDays || 0);
                const label = button.querySelector('[data-duration-price]');
                if (!label) return;
                const price = getConfiguredPrice(level, days);
                label.textContent = price === null ? 'قیمت تعیین نشده' : formatPrice(price);
            });
        }

        function renderInvoice() {
            if (!invoice) return;

            if (!activePlan) {
                if (invoiceTier) invoiceTier.textContent = 'هنوز پلنی انتخاب نشده';
                if (invoiceLevel) invoiceLevel.textContent = '—';
                if (invoiceDuration) invoiceDuration.textContent = '—';
                if (invoiceTotal) invoiceTotal.textContent = '— تومان';
                if (invoiceStatus) invoiceStatus.textContent = 'برای ساخت فاکتور، یک سطح قابل خرید را انتخاب کن.';
                if (checkoutButton) checkoutButton.disabled = true;
                invoice.style.removeProperty('--vip-invoice-accent');
                return;
            }

            const level = Number(activePlan.dataset.level || 0);
            const name = activePlan.dataset.vipName || 'VIP';
            const mode = activePlan.dataset.vipPurchaseMode || 'none';
            const available = activePlan.dataset.vipAvailable === '1';
            const current = activePlan.dataset.vipCurrent === '1';
            const days = Number(selectedDurationDays || 0);
            const durationButton = durationOptions.find(function (button) {
                return Number(button.dataset.durationDays || 0) === days;
            });
            const durationLabel = durationButton ? (durationButton.dataset.durationLabel || (days + ' روز')) : '';
            const price = days > 0 ? getConfiguredPrice(level, days) : null;

            if (invoiceTier) invoiceTier.textContent = name;
            if (invoiceLevel) invoiceLevel.textContent = name + ' · VIP ' + String(level).padStart(2, '0');
            if (invoiceDuration) invoiceDuration.textContent = mode === 'trial'
                ? '۳ روز · هدیه خوش‌آمدگویی'
                : (durationLabel || 'انتخاب نشده');
            if (invoiceTotal) {
                invoiceTotal.textContent = mode === 'trial'
                    ? 'رایگان'
                    : (price !== null ? formatPrice(price) : '— تومان');
            }
            invoice.style.setProperty('--vip-invoice-accent', activePlan.dataset.vipColor || '#F97316');

            let message = 'برای ساخت فاکتور، یک سطح قابل خرید را انتخاب کن.';
            if (current) {
                message = 'این سطح هم‌اکنون روی حساب شما فعال است؛ انتخاب مدت خرید برای آن نمایش داده نمی‌شود.';
            } else if (!available) {
                message = 'این سطح در حال حاضر برای خرید فعال نیست.';
            } else if (mode === 'trial') {
                message = 'Silver هدیه سه‌روزه است؛ آن را از کارت هدیه خوش‌آمدگویی دریافت کن. این سفارش وارد Checkout نمی‌شود.';
            } else if (mode === 'paid' && root.dataset.vipStatusUnavailable === '1') {
                message = 'وضعیت VIPCore در دسترس نیست؛ برای جلوگیری از سفارش تکراری، انتخاب مدت موقتاً غیرفعال است.';
            } else if (mode === 'paid' && !days) {
                message = 'یکی از مدت‌های اشتراک را انتخاب کن تا جزئیات فاکتور تکمیل شود.';
            } else if (mode === 'paid' && price === null) {
                message = 'قیمت این مدت هنوز در تنظیمات فروش تعریف نشده است؛ مبلغی از مرورگر یا حدس محاسبه نمی‌شود.';
            } else if (mode === 'paid') {
                message = 'مبلغ از تنظیمات قیمت‌گذاری خوانده شد؛ پرداخت واقعی تا تکمیل سرویس سفارش و تأیید VIPCore غیرفعال است.';
            }

            if (invoiceStatus) invoiceStatus.textContent = message;
            if (checkoutButton) {
                checkoutButton.disabled = true;
                checkoutButton.title = 'Checkout مستقیم هنوز در سمت سرور پیاده‌سازی نشده است.';
            }
        }

        function resetDurationSelection() {
            selectedDurationDays = '';
            delete root.dataset.vipSelectedDurationDays;
            delete root.dataset.vipSelectedTierLevel;

            durationOptions.forEach(function (button) {
                button.classList.remove('is-selected');
                button.setAttribute('aria-pressed', 'false');
            });

            if (durationSelector) {
                durationSelector.classList.remove('has-selection');
            }
            if (durationSelectionStatus) {
                durationSelectionStatus.textContent = 'برای ادامه، یکی از مدت‌ها را انتخاب کن.';
            }
            renderInvoice();
        }

        function syncDurationSelector(plan, isCurrent) {
            if (!durationSelector) return;

            const canChooseDuration = (
                hasUserSelectedPlan
                && plan.dataset.vipAvailable === '1'
                && plan.dataset.vipPurchaseMode === 'paid'
                && !isCurrent
                && root.dataset.vipStatusUnavailable !== '1'
            );

            durationSelector.hidden = !canChooseDuration;
            durationSelector.setAttribute('aria-hidden', canChooseDuration ? 'false' : 'true');

            renderDurationPrices(Number(plan.dataset.level || 0));
            if (!canChooseDuration) {
                resetDurationSelection();
            }
        }

        function selectPlan(plan, userInitiated) {
            if (!plan) return;

            const level = Number(plan.dataset.level || 0);
            const isAdminTestTier = root.dataset.vipTestAdmin === '1' && [1, 2, 3].includes(level);
            if (plan.dataset.vipAvailable !== '1' && !isAdminTestTier) return;
            if (plan.dataset.vipCurrent === '1' && userInitiated) return;

            if (userInitiated) {
                hasUserSelectedPlan = true;
            }
            if (activePlan !== plan) {
                resetDurationSelection();
            }
            activePlan = plan;

            plans.forEach(function (item) {
                const selected = item === plan;
                item.classList.toggle('is-selected', selected);
                const selectButton = item.querySelector('[data-vip-select-plan]');
                if (selectButton) selectButton.setAttribute('aria-pressed', selected ? 'true' : 'false');
            });

            const isCurrent = plan.dataset.vipCurrent === '1';
            syncDurationSelector(plan, isCurrent);

            if (testPurchaseBox) {
                testPurchaseBox.hidden = !isAdminTestTier;
            }
            if (testPurchaseButton) {
                const hasActiveMembership = root.dataset.vipHasActive === '1';
                testPurchaseButton.dataset.level = String(level);
                testPurchaseButton.hidden = false;
                testPurchaseButton.disabled = hasActiveMembership;
                testPurchaseButton.textContent = hasActiveMembership
                    ? 'برای این حساب VIP فعال ثبت شده است'
                    : 'ثبت آزمایشی VIP بدون پرداخت';
                if (testFeedback) {
                    testFeedback.hidden = true;
                    testFeedback.textContent = '';
                    testFeedback.classList.remove('is-error', 'is-success');
                }
            }

            renderInvoice();
        }

        function setInactiveExpanded(expanded) {
            if (!inactiveToggle || !inactivePlans) return;
            inactiveExpanded = Boolean(expanded);
            inactiveToggle.setAttribute('aria-expanded', inactiveExpanded ? 'true' : 'false');
            inactivePlans.setAttribute('aria-hidden', inactiveExpanded ? 'false' : 'true');
            inactivePlans.hidden = !inactiveExpanded;
        }

        if (inactiveToggle && inactivePlans) {
            inactiveToggle.addEventListener('click', function () {
                const nextExpanded = !inactiveExpanded;

                if (!nextExpanded && activePlan && inactivePlans.contains(activePlan)) {
                    const firstPublicPlan = plans.find(function (plan) {
                        return plan.dataset.vipAvailable === '1' && plan.dataset.vipCurrent !== '1';
                    });
                    if (firstPublicPlan) {
                        selectPlan(firstPublicPlan, false);
                    } else {
                        activePlan = null;
                        renderInvoice();
                    }
                }

                setInactiveExpanded(nextExpanded);
            });
        }

        if (testPurchaseButton) {
            testPurchaseButton.addEventListener('click', async function () {
                const level = Number(testPurchaseButton.dataset.level || 0);
                if (testPurchaseButton.disabled || ![1, 2, 3].includes(level)) return;

                const previousLabel = testPurchaseButton.textContent;
                testPurchaseButton.disabled = true;
                testPurchaseButton.textContent = 'در حال ثبت در VIPCore...';
                if (testFeedback) {
                    testFeedback.hidden = false;
                    testFeedback.classList.remove('is-error', 'is-success');
                    testFeedback.textContent = 'درخواست ثبت عضویت آزمایشی ارسال شد.';
                }

                try {
                    const response = await fetch('/api/vip-test-purchase.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-FragCity-CSRF': testPurchaseButton.dataset.csrfToken || ''
                        },
                        body: JSON.stringify({ level: level })
                    });

                    let payload = null;
                    try {
                        payload = await response.json();
                    } catch (parseError) {
                        throw new Error('پاسخ سرور قابل خواندن نیست.');
                    }

                    if (!response.ok || !payload || payload.success !== true) {
                        const message = payload && typeof payload.message === 'string'
                            ? payload.message
                            : 'ثبت آزمایشی VIP ناموفق بود.';
                        throw new Error(message);
                    }

                    if (testFeedback) {
                        testFeedback.hidden = false;
                        testFeedback.classList.add('is-success');
                        testFeedback.textContent = payload.data && payload.data.message
                            ? payload.data.message + ' صفحه برای نمایش وضعیت جدید تازه‌سازی می‌شود.'
                            : 'VIP با موفقیت در دیتابیس ثبت شد.';
                    }
                    root.dataset.vipHasActive = '1';
                    testPurchaseButton.textContent = 'VIP ثبت شد';
                    window.setTimeout(function () {
                        window.location.reload();
                    }, 1300);
                } catch (error) {
                    if (testFeedback) {
                        testFeedback.hidden = false;
                        testFeedback.classList.add('is-error');
                        testFeedback.textContent = error instanceof Error
                            ? error.message
                            : 'ارتباط با سرویس VIPCore برقرار نشد.';
                    }
                    testPurchaseButton.disabled = root.dataset.vipHasActive === '1';
                    testPurchaseButton.textContent = root.dataset.vipHasActive === '1'
                        ? 'برای این حساب VIP فعال ثبت شده است'
                        : previousLabel;
                }
            });
        }

        if (freeTrialButton) {
            freeTrialButton.addEventListener('click', async function () {
                if (freeTrialButton.disabled) return;

                freeTrialButton.disabled = true;
                freeTrialButton.textContent = 'در حال ثبت هدیه Silver در VIPCore...';
                if (freeTrialFeedback) {
                    freeTrialFeedback.hidden = false;
                    freeTrialFeedback.classList.remove('is-error', 'is-success');
                    freeTrialFeedback.textContent = 'درخواست دریافت هدیه ارسال شد.';
                }

                try {
                    const response = await fetch('/api/vip-free-claim.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-FragCity-CSRF': freeTrialButton.dataset.csrfToken || ''
                        },
                        body: JSON.stringify({})
                    });

                    let payload = null;
                    try {
                        payload = await response.json();
                    } catch (parseError) {
                        throw new Error('پاسخ سرور قابل خواندن نیست.');
                    }

                    if (!response.ok || !payload || payload.success !== true) {
                        const message = payload && typeof payload.message === 'string'
                            ? payload.message
                            : 'دریافت هدیه Silver ناموفق بود.';
                        throw new Error(message);
                    }

                    root.dataset.vipHasActive = '1';
                    freeTrialButton.textContent = 'هدیه Silver ثبت شد';

                    if (window.FragCitySuccessModal && typeof window.FragCitySuccessModal.open === 'function') {
                        window.FragCitySuccessModal.open({
                            kind: 'gift',
                            title: 'هدیه ۳ روزه Silver را با موفقیت دریافت کردید!',
                            message: 'عضویت Silver با موفقیت روی حساب Steam شما فعال شد. از قابلیت‌های VIP در سرورهای FragCity لذت ببرید.',
                            tier: 'Silver',
                            duration: '۳ روز',
                            expires: payload.data && payload.data.expires ? payload.data.expires : '',
                            actionLabel: 'مشاهده وضعیت اشتراک',
                            reloadOnClose: true,
                            dismissible: true
                        });
                    } else {
                        if (freeTrialFeedback) {
                            freeTrialFeedback.hidden = false;
                            freeTrialFeedback.classList.add('is-success');
                            freeTrialFeedback.textContent = 'هدیه Silver با موفقیت ثبت شد. صفحه برای نمایش وضعیت جدید تازه‌سازی می‌شود.';
                        }
                        window.location.reload();
                    }
                } catch (error) {
                    if (freeTrialFeedback) {
                        freeTrialFeedback.hidden = false;
                        freeTrialFeedback.classList.add('is-error');
                        freeTrialFeedback.textContent = error instanceof Error
                            ? error.message
                            : 'ارتباط با سرویس VIPCore برقرار نشد.';
                    }
                    freeTrialButton.disabled = false;
                    freeTrialButton.textContent = 'دریافت Silver رایگان · ۳ روز';
                }
            });
        }

        durationOptions.forEach(function (button) {
            button.addEventListener('click', function () {
                if (
                    !durationSelector
                    || durationSelector.hidden
                    || !activePlan
                    || activePlan.dataset.vipPurchaseMode !== 'paid'
                    || activePlan.classList.contains('is-current')
                ) {
                    return;
                }

                const days = Number(button.dataset.durationDays || 0);
                if (![7, 15, 30, 60, 90].includes(days)) return;

                selectedDurationDays = String(days);
                root.dataset.vipSelectedDurationDays = selectedDurationDays;
                root.dataset.vipSelectedTierLevel = String(activePlan.dataset.level || '');

                durationOptions.forEach(function (option) {
                    const selected = option === button;
                    option.classList.toggle('is-selected', selected);
                    option.setAttribute('aria-pressed', selected ? 'true' : 'false');
                });

                durationSelector.classList.add('has-selection');
                if (durationSelectionStatus) {
                    const label = button.dataset.durationLabel || (days + ' روز');
                    durationSelectionStatus.textContent = 'مدت انتخاب‌شده: ' + label + ' (' + days + ' روز)';
                }
                renderInvoice();

                window.dispatchEvent(new CustomEvent('fragcity:vip-duration-selected', {
                    detail: {
                        tierLevel: Number(activePlan.dataset.level || 0),
                        durationDays: days
                    }
                }));
            });
        });

        plans.forEach(function (plan) {
            const selectButton = plan.querySelector('[data-vip-select-plan]');
            const featureButton = plan.querySelector('[data-vip-open-comparison]');
            const level = Number(plan.dataset.level || 0);
            const isAdminTestTier = root.dataset.vipTestAdmin === '1' && [1, 2, 3].includes(level);
            const canSelectCard = (
                (plan.dataset.vipAvailable === '1' || isAdminTestTier)
                && plan.dataset.vipCurrent !== '1'
            );

            // The card body is a selection target; nested action buttons keep their own behavior.
            plan.dataset.cardSelectable = canSelectCard ? '1' : '0';
            plan.tabIndex = canSelectCard ? 0 : -1;
            if (canSelectCard) {
                plan.addEventListener('click', function (event) {
                    if (event.defaultPrevented) return;
                    if (event.target.closest('button, a, input, select, textarea, [data-vip-open-comparison]')) return;
                    selectPlan(plan, true);
                });

                plan.addEventListener('keydown', function (event) {
                    if (event.target !== plan || (event.key !== 'Enter' && event.key !== ' ')) return;
                    event.preventDefault();
                    selectPlan(plan, true);
                });
            }

            if (selectButton) {
                selectButton.addEventListener('click', function () {
                    selectPlan(plan, true);
                });
            }

            if (featureButton) {
                featureButton.addEventListener('click', function () {
                    openComparison(plan, featureButton);
                });
            }
        });

        const initialCurrentPlan = plans.find(function (plan) {
            return plan.dataset.vipCurrent === '1';
        });
        if (initialCurrentPlan) {
            selectPlan(initialCurrentPlan, false);
        } else {
            renderInvoice();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initVipStorefront, { once: true });
    } else {
        initVipStorefront();
    }

    window.addEventListener('content-loaded', function () {
        const nextRoot = document.getElementById('vipStorefront');
        const previousRoot = window.fragCityVipActiveRoot || null;

        if (previousRoot !== nextRoot) {
            const staleModal = document.body.querySelector('#vipComparisonModal');
            const staleInvoice = document.body.querySelector('[data-vip-invoice]');
            if (staleModal) staleModal.remove();
            if (staleInvoice) staleInvoice.remove();
            document.body.classList.remove('vip-comparison-open');
            document.body.style.overflow = '';
        }

        window.fragCityVipActiveRoot = nextRoot;
        initVipStorefront();
    });
})();

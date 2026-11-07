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
        const inactiveSection = inactiveToggle ? inactiveToggle.closest('.vip-locked-section') : null;
        let inactiveAnimation = null;
        const faqAnimations = new WeakMap();
        if (inactiveExpanded && inactiveSection) inactiveSection.classList.add('is-expanded');
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
        const checkoutForm = invoice ? invoice.querySelector('[data-vip-checkout-form]') : null;
        const checkoutLevelInput = checkoutForm ? checkoutForm.querySelector('[data-vip-checkout-level]') : null;
        const checkoutDurationInput = checkoutForm ? checkoutForm.querySelector('[data-vip-checkout-duration]') : null;
        const walletCheckoutForm = invoice ? invoice.querySelector('[data-vip-wallet-form]') : null;
        const walletCheckoutButton = walletCheckoutForm
            ? walletCheckoutForm.querySelector('[data-vip-wallet-checkout]')
            : null;
        const walletLevelInput = walletCheckoutForm
            ? walletCheckoutForm.querySelector('[data-vip-wallet-level]')
            : null;
        const walletDurationInput = walletCheckoutForm
            ? walletCheckoutForm.querySelector('[data-vip-wallet-duration]')
            : null;
        const walletStatus = invoice ? invoice.querySelector('[data-vip-wallet-status]') : null;
        const walletRechargeButton = invoice ? invoice.querySelector('[data-vip-wallet-recharge]') : null;
        const walletBalanceToman = Math.max(0, Number(root.dataset.vipWalletBalance || 0));
        const walletBalanceAvailable = root.dataset.vipWalletBalanceAvailable === '1';
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

        let upgradePriceMap = {};
        try {
            upgradePriceMap = JSON.parse(root.dataset.vipUpgradePrices || '{}');
            if (!upgradePriceMap || typeof upgradePriceMap !== 'object' || Array.isArray(upgradePriceMap)) {
                upgradePriceMap = {};
            }
        } catch (error) {
            upgradePriceMap = {};
        }

        if (!plans.length) return;

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

        function getUpgradePrice(level) {
            const price = Number(upgradePriceMap[String(level)]);
            return Number.isSafeInteger(price) && price >= 100 ? price : null;
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
                if (checkoutButton) {
                    checkoutButton.disabled = true;
                    checkoutButton.title = 'ابتدا یک سطح و مدت اشتراک را انتخاب کنید.';
                }
                if (checkoutLevelInput) checkoutLevelInput.value = '';
                if (checkoutDurationInput) checkoutDurationInput.value = '';
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
            const basePrice = getConfiguredPrice(level, 30);
            const loggedIn = root.dataset.vipLoggedIn === '1';
            const hasAnyActiveMembership = root.dataset.vipHasActive === '1';
            const currentLevel = Number(root.dataset.vipCurrentLevel || 0);
            const paidUpgradeEligible = root.dataset.vipPaidUpgradeEligible === '1';
            const currentMembershipPaid = root.dataset.vipCurrentPaid === '1';
            const higherThanCurrent = level > currentLevel;
            const atOrAboveCurrent = level >= currentLevel;
            const paidUpgradeCandidate = hasAnyActiveMembership && paidUpgradeEligible && higherThanCurrent;
            const upgradePrice = paidUpgradeCandidate ? getUpgradePrice(level) : null;
            const isPaidUpgrade = paidUpgradeCandidate && upgradePrice !== null;
            // A non-paid active membership (trial, test grant or legacy/manual
            // grant) can be converted into a paid membership at the same tier
            // or replaced by a higher tier, always at the full catalogue price.
            const isFullPriceReplacement = hasAnyActiveMembership
                && !currentMembershipPaid
                && atOrAboveCurrent;
            const blockedByLevel = hasAnyActiveMembership && level < currentLevel;
            const checkoutReady = root.dataset.vipCheckoutReady === '1';
            const statusAvailable = root.dataset.vipStatusUnavailable !== '1';
            const paidLedgerReady = root.dataset.vipPaidLedgerAvailable === '1';
            const upgradeDurationDays = Number(root.dataset.vipUpgradeDurationDays || 0);

            if (invoiceTier) invoiceTier.textContent = name;
            if (invoiceLevel) invoiceLevel.textContent = name + ' · VIP ' + String(level).padStart(2, '0');
            if (invoiceDuration) {
                invoiceDuration.textContent = mode === 'trial'
                    ? '۳ روز · هدیه خوش‌آمدگویی'
                    : (paidUpgradeCandidate
                        ? 'تا پایان اعتبار اشتراک فعلی'
                        : (durationLabel || 'انتخاب نشده'));
            }
            if (invoiceTotal) {
                invoiceTotal.textContent = mode === 'trial'
                    ? 'رایگان'
                    : (paidUpgradeCandidate
                        ? (upgradePrice !== null ? formatPrice(upgradePrice) : 'قیمت ارتقا در دسترس نیست')
                        : (price !== null
                            ? formatPrice(price)
                            : (!days && basePrice !== null
                                ? 'قیمت پایهٔ ۳۰ روزه: ' + formatPrice(basePrice)
                                : '— تومان')));
            }
            invoice.style.setProperty('--vip-invoice-accent', activePlan.dataset.vipColor || '#F97316');

            const canBuyFullTerm = !hasAnyActiveMembership
                ? days > 0 && price !== null
                : (isFullPriceReplacement && days > 0 && price !== null);
            const canCreatePaidOrder = loggedIn
                && statusAvailable
                && paidLedgerReady
                && (!current || isFullPriceReplacement)
                && available
                && mode === 'paid'
                && (isPaidUpgrade || canBuyFullTerm);
            const payableToman = isPaidUpgrade ? upgradePrice : (canBuyFullTerm ? price : null);
            const canCheckout = canCreatePaidOrder && checkoutReady;
            const canWalletCheckout = canCreatePaidOrder
                && walletBalanceAvailable
                && Number.isSafeInteger(payableToman)
                && payableToman > 0
                && walletBalanceToman >= payableToman;

            const replacementNotice = 'عضویت فعلی شما هدیه، آزمایشی یا بدون سابقه خرید پولی است؛ برای تبدیل آن به اشتراک پولی، قیمت کامل سطح و مدت انتخاب‌شده پرداخت می‌شود. هدیه Silver اعتبار ارتقا ندارد.';
            let message = 'برای ساخت فاکتور، یک سطح قابل خرید را انتخاب کن.';
            if (current && !isFullPriceReplacement) {
                message = 'این سطح هم‌اکنون به‌صورت اشتراک پولی روی حساب شما فعال است.';
            } else if (!available) {
                message = 'این سطح در حال حاضر برای خرید فعال نیست.';
            } else if (blockedByLevel) {
                message = 'فقط می‌توانی به سطح بالاتر ارتقا بدهی؛ خرید مجدد یا تنزل سطح امکان‌پذیر نیست.';
            } else if (root.dataset.vipStatusUnavailable === '1') {
                message = 'وضعیت VIPCore قابل بررسی نیست؛ پرداخت تا بازیابی سرویس متوقف می‌ماند.';
            } else if (!loggedIn) {
                message = 'برای خرید VIP ابتدا با حساب Steam وارد شوید.';
            } else if (!paidLedgerReady) {
                message = 'جدول ثبت سفارش‌های پولی VIP آماده نیست؛ تا تکمیل Migration و مجوزهای دیتابیس، پرداخت فعال نمی‌شود.';
            } else if (mode === 'trial') {
                message = 'Silver هدیه سه‌روزه است؛ آن را از کارت هدیه خوش‌آمدگویی دریافت کن. این سفارش وارد Checkout نمی‌شود.';
            } else if (mode === 'paid' && !days) {
                message = 'یکی از مدت‌های اشتراک را انتخاب کن تا فاکتور و مبلغ نهایی تکمیل شود.';
            } else if (mode === 'paid' && !isPaidUpgrade && price === null) {
                message = 'قیمت این مدت در تنظیمات سرور معتبر نیست؛ مبلغی از مرورگر دریافت نمی‌شود.';
            } else if (paidUpgradeCandidate && upgradePrice === null) {
                message = 'قیمت ارتقا یا اعتبار باقی‌مانده قابل محاسبه نیست؛ لطفاً پس از پایان اشتراک، پلن جدید را با قیمت کامل بخر.';
            } else if (isPaidUpgrade) {
                message = 'اعتبار باقی‌مانده از اشتراک پولی کسر می‌شود و سطح جدید تا همان تاریخ انقضای فعلی فعال خواهد بود.';
            } else if (!canCreatePaidOrder) {
                message = 'شرایط ثبت سفارش VIP کامل نیست؛ انتخاب سطح، مدت، قیمت و وضعیت عضویت را بررسی کن.';
            } else if (!checkoutReady) {
                if (canWalletCheckout) {
                    message = 'تنظیمات امن زرین‌پال یا Checkout آماده نیست؛ پرداخت آنلاین غیرفعال است، اما موجودی کیف پول برای خرید کافی است.';
                } else if (walletSelectionValid && !walletBalanceAvailable) {
                    message = 'تنظیمات امن زرین‌پال آماده نیست و موجودی کیف پول نیز قابل بررسی نیست؛ پرداخت تا رفع مشکل غیرفعال می‌ماند.';
                } else if (walletSelectionValid && walletBalanceAvailable) {
                    message = 'تنظیمات امن زرین‌پال آماده نیست؛ موجودی کیف پول برای این سفارش کافی نیست.';
                } else {
                    message = 'تنظیمات امن زرین‌پال یا Checkout آماده نیست؛ پرداخت آنلاین تا تکمیل تنظیمات غیرفعال می‌ماند.';
                }
            } else if (canCheckout) {
                message = 'مبلغ نهایی آماده است. پس از تأیید زرین‌پال، VIPCore فعال می‌شود.';
            }

            if (isFullPriceReplacement && canCreatePaidOrder) {
                message += ' ' + replacementNotice;
            }

            if (invoiceStatus) invoiceStatus.textContent = message;
            if (checkoutLevelInput) checkoutLevelInput.value = canCheckout ? String(level) : '';
            if (checkoutDurationInput) {
                checkoutDurationInput.value = canCheckout
                    ? String(isPaidUpgrade ? upgradeDurationDays : days)
                    : '';
            }
            if (checkoutButton) {
                checkoutButton.disabled = !canCheckout;
                checkoutButton.title = canCheckout
                    ? (isPaidUpgrade ? 'پرداخت مابه‌التفاوت و ارتقای VIP' : 'ساخت سفارش امن و انتقال به درگاه زرین‌پال')
                    : message;
            }

            const walletSelectionValid = canCreatePaidOrder
                && Number.isSafeInteger(payableToman)
                && payableToman > 0;
            if (walletLevelInput) walletLevelInput.value = walletSelectionValid ? String(level) : '';
            if (walletDurationInput) {
                walletDurationInput.value = walletSelectionValid
                    ? String(isPaidUpgrade ? upgradeDurationDays : days)
                    : '';
            }
            if (walletCheckoutButton) {
                walletCheckoutButton.disabled = !canWalletCheckout;
                if (!walletSelectionValid) {
                    walletCheckoutButton.textContent = 'انتخاب پلن و مدت اشتراک';
                } else if (!walletBalanceAvailable) {
                    walletCheckoutButton.textContent = 'موجودی کیف پول در دسترس نیست';
                } else if (!canWalletCheckout) {
                    walletCheckoutButton.textContent = 'موجودی کیف پول ناکافی';
                } else {
                    walletCheckoutButton.textContent = 'پرداخت از کیف پول';
                }
                walletCheckoutButton.title = canWalletCheckout
                    ? 'کسر مبلغ از کیف پول و فعال‌سازی VIP'
                    : (walletBalanceAvailable && walletSelectionValid
                        ? 'موجودی کیف پول برای این سفارش کافی نیست.'
                        : 'ابتدا پلن و مدت اشتراک را انتخاب کنید.');
            }
            if (walletStatus) {
                walletStatus.textContent = walletBalanceAvailable
                    ? 'موجودی کیف پول: ' + formatPrice(walletBalanceToman)
                    : 'موجودی کیف پول فعلاً قابل بررسی نیست.';
                if (walletSelectionValid && walletBalanceAvailable && !canWalletCheckout) {
                    walletStatus.textContent += ' · مبلغ سفارش: ' + formatPrice(payableToman) + ' · موجودی ناکافی';
                } else if (walletSelectionValid && canWalletCheckout) {
                    walletStatus.textContent += ' · موجودی برای خرید کافی است.';
                }
            }
            if (walletRechargeButton) {
                walletRechargeButton.hidden = !loggedIn
                    || (walletSelectionValid && canWalletCheckout);
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

            const planLevel = Number(plan.dataset.level || 0);
            const hasActiveMembership = root.dataset.vipHasActive === '1';
            const currentLevel = Number(root.dataset.vipCurrentLevel || 0);
            const higherThanCurrent = planLevel > currentLevel;
            const atOrAboveCurrent = planLevel >= currentLevel;
            const paidUpgradeEligible = root.dataset.vipPaidUpgradeEligible === '1';
            const currentMembershipPaid = root.dataset.vipCurrentPaid === '1';
            const isPaidUpgrade = hasActiveMembership && paidUpgradeEligible && higherThanCurrent;
            const canReplaceNonPaidMembership = hasActiveMembership
                && !currentMembershipPaid
                && atOrAboveCurrent;
            const canChooseDuration = (
                hasUserSelectedPlan
                && plan.dataset.vipAvailable === '1'
                && plan.dataset.vipPurchaseMode === 'paid'
                && (!hasActiveMembership || canReplaceNonPaidMembership)
            );

            durationSelector.hidden = !canChooseDuration;
            durationSelector.setAttribute('aria-hidden', canChooseDuration ? 'false' : 'true');

            renderDurationPrices(planLevel);
            if (durationSelectionStatus) {
                if (isPaidUpgrade) {
                    durationSelectionStatus.textContent = 'با ارتقای پلن پولی، تاریخ انقضای فعلی حفظ می‌شود و فقط مابه‌التفاوت زمان باقی‌مانده پرداخت خواهد شد.';
                } else if (canReplaceNonPaidMembership) {
                    durationSelectionStatus.textContent = 'عضویت فعلی هدیه یا آزمایشی است؛ با پرداخت قیمت کامل می‌توانی آن را به اشتراک پولی تبدیل کنی.';
                } else if (hasActiveMembership && !higherThanCurrent) {
                    durationSelectionStatus.textContent = 'اشتراک پولی فعلی فقط امکان ارتقا به سطح بالاتر را دارد.';
                } else if (!canChooseDuration) {
                    durationSelectionStatus.textContent = 'برای ادامه، یک سطح قابل خرید را انتخاب کن.';
                } else {
                    durationSelectionStatus.textContent = 'مدت اشتراک موردنظرت را انتخاب کن.';
                }
            }
            if (!canChooseDuration) {
                resetDurationSelection();
            }
        }

        function selectPlan(plan, userInitiated) {
            if (!plan) return;

            const level = Number(plan.dataset.level || 0);
            const isAdminTestTier = root.dataset.vipTestAdmin === '1' && [1, 2, 3].includes(level);
            if (plan.dataset.vipSelectable !== '1' && !isAdminTestTier) return;

            if (userInitiated) {
                hasUserSelectedPlan = true;
            }
            if (activePlan !== plan) {
                resetDurationSelection();
            }
            activePlan = plan;
            if (userInitiated) playSelectionFeedback(plan);

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

        function playSelectionFeedback(element) {
            if (!element || prefersReducedMotion() || typeof element.animate !== 'function') return;
            element.animate(
                [
                    { transform: 'scale(1)', filter: 'brightness(1)' },
                    { transform: 'scale(.985)', filter: 'brightness(1.12)' },
                    { transform: 'scale(1)', filter: 'brightness(1)' }
                ],
                {
                    duration: 230,
                    easing: 'cubic-bezier(.2, .75, .25, 1)'
                }
            );
        }

        function prefersReducedMotion() {
            return (
                document.documentElement.classList.contains('fc-no-animation')
                || (typeof window.matchMedia === 'function'
                    && window.matchMedia('(prefers-reduced-motion: reduce)').matches)
            );
        }

        function setInactiveExpanded(expanded) {
            if (!inactiveToggle || !inactivePlans) return;

            const shouldExpand = Boolean(expanded);
            const currentHeight = inactivePlans.hidden
                ? 0
                : inactivePlans.getBoundingClientRect().height;

            if (inactiveAnimation) {
                inactiveAnimation.cancel();
                inactiveAnimation = null;
            }

            inactiveExpanded = shouldExpand;
            inactiveToggle.setAttribute('aria-expanded', shouldExpand ? 'true' : 'false');
            inactivePlans.setAttribute('aria-hidden', shouldExpand ? 'false' : 'true');
            if (inactiveSection) inactiveSection.classList.toggle('is-expanded', shouldExpand);

            if (prefersReducedMotion() || typeof inactivePlans.animate !== 'function') {
                inactivePlans.hidden = !shouldExpand;
                inactivePlans.style.removeProperty('height');
                inactivePlans.style.removeProperty('overflow');
                return;
            }

            if (shouldExpand) inactivePlans.hidden = false;

            const targetHeight = shouldExpand ? inactivePlans.scrollHeight : 0;
            inactivePlans.style.height = currentHeight + 'px';
            inactivePlans.style.overflow = 'hidden';

            const animation = inactivePlans.animate(
                [
                    { height: currentHeight + 'px', opacity: shouldExpand ? 0 : 1 },
                    { height: targetHeight + 'px', opacity: shouldExpand ? 1 : 0 }
                ],
                {
                    duration: 260,
                    easing: 'cubic-bezier(.22, .61, .36, 1)',
                    fill: 'forwards'
                }
            );
            inactiveAnimation = animation;

            animation.onfinish = function () {
                if (inactiveAnimation !== animation) return;
                inactivePlans.hidden = !shouldExpand;
                inactivePlans.style.removeProperty('height');
                inactivePlans.style.removeProperty('overflow');
                inactiveAnimation = null;
                animation.cancel();
            };
            animation.oncancel = function () {
                if (inactiveAnimation === animation) inactiveAnimation = null;
            };
        }

        function toggleFaqItem(details) {
            if (!details || !details.classList.contains('vip-faq-item')) return;

            const answer = details.querySelector('.vip-faq-answer');
            if (!answer) {
                details.open = !details.open;
                return;
            }

            const shouldOpen = !details.open;
            const previousAnimation = faqAnimations.get(details);
            const currentHeight = previousAnimation
                ? answer.getBoundingClientRect().height
                : (details.open ? answer.getBoundingClientRect().height : 0);

            if (previousAnimation) {
                previousAnimation.cancel();
                faqAnimations.delete(details);
            }

            if (shouldOpen) details.open = true;

            if (prefersReducedMotion() || typeof answer.animate !== 'function') {
                details.open = shouldOpen;
                answer.style.removeProperty('height');
                answer.style.removeProperty('overflow');
                return;
            }

            const targetHeight = shouldOpen ? answer.scrollHeight : 0;
            answer.style.height = currentHeight + 'px';
            answer.style.overflow = 'hidden';

            const animation = answer.animate(
                [
                    {
                        height: currentHeight + 'px',
                        opacity: shouldOpen ? 0 : 1,
                        transform: shouldOpen ? 'translateY(-5px)' : 'translateY(0)'
                    },
                    {
                        height: targetHeight + 'px',
                        opacity: shouldOpen ? 1 : 0,
                        transform: shouldOpen ? 'translateY(0)' : 'translateY(-5px)'
                    }
                ],
                {
                    duration: 230,
                    easing: 'cubic-bezier(.22, .61, .36, 1)',
                    fill: 'forwards'
                }
            );
            faqAnimations.set(details, animation);
            details.dataset.animating = '1';

            animation.onfinish = function () {
                if (faqAnimations.get(details) !== animation) return;
                details.open = shouldOpen;
                answer.style.removeProperty('height');
                answer.style.removeProperty('overflow');
                delete details.dataset.animating;
                faqAnimations.delete(details);
                animation.cancel();
            };
            animation.oncancel = function () {
                if (faqAnimations.get(details) === animation) {
                    delete details.dataset.animating;
                    faqAnimations.delete(details);
                }
            };
        }

        root.querySelectorAll('.vip-faq-item > summary').forEach(function (summary) {
            summary.addEventListener('click', function (event) {
                event.preventDefault();
                toggleFaqItem(summary.parentElement);
            });
        });

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

        if (checkoutForm && checkoutButton) {
            checkoutForm.addEventListener('submit', function (event) {
                if (checkoutButton.disabled
                    || !checkoutLevelInput
                    || !checkoutDurationInput
                    || !checkoutLevelInput.value
                    || !checkoutDurationInput.value) {
                    event.preventDefault();
                    renderInvoice();
                    return;
                }

                checkoutButton.disabled = true;
                checkoutButton.classList.add('is-submitting');
                checkoutButton.textContent = 'در حال انتقال به زرین‌پال...';
            });
        }

        if (walletCheckoutForm && walletCheckoutButton) {
            walletCheckoutForm.addEventListener('submit', function (event) {
                if (walletCheckoutButton.disabled
                    || !walletLevelInput
                    || !walletDurationInput
                    || !walletLevelInput.value
                    || !walletDurationInput.value) {
                    event.preventDefault();
                    renderInvoice();
                    return;
                }

                walletCheckoutButton.disabled = true;
                walletCheckoutButton.classList.add('is-submitting');
                walletCheckoutButton.textContent = 'در حال بررسی موجودی و فعال‌سازی...';
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
                ) {
                    return;
                }

                const days = Number(button.dataset.durationDays || 0);
                if (![7, 15, 30, 60, 90].includes(days)) return;

                selectedDurationDays = String(days);
                playSelectionFeedback(button);
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
                plan.dataset.vipSelectable === '1'
                || isAdminTestTier
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
            return plan.dataset.vipCurrent === '1'
                && (plan.dataset.vipSelectable === '1' || root.dataset.vipTestAdmin === '1');
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
            if (staleModal) staleModal.remove();
            document.body.classList.remove('vip-comparison-open');
            document.body.style.overflow = '';
        }

        window.fragCityVipActiveRoot = nextRoot;
        initVipStorefront();
    });
})();

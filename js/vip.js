(function () {
    'use strict';

    function initVipStorefront() {
        const root = document.getElementById('vipStorefront');
        if (!root || root.dataset.vipStoreReady === '1') return;
        root.dataset.vipStoreReady = '1';

        const plans = Array.from(root.querySelectorAll('[data-vip-plan]'));
        const inactiveToggle = root.querySelector('#vipLockedToggle');
        const inactivePlans = root.querySelector('#vipInactivePlans');
        let inactiveExpanded = Boolean(inactiveToggle && inactiveToggle.getAttribute('aria-expanded') === 'true');
        const detailName = root.querySelector('#vipDetailTierName');
        const detailSummaryFa = root.querySelector('#vipDetailTierSummaryFa');
        const detailSummaryEn = root.querySelector('#vipDetailTierSummaryEn');
        const detailState = root.querySelector('#vipDetailTierState');
        const detailFeatures = root.querySelector('#vipDetailFeatures');
        const detailPanel = root.querySelector('.vip-feature-panel');
        const detailHomeMarker = root.querySelector('#vipDetailPanelHome');
        const testPurchaseButton = root.querySelector('[data-vip-test-purchase]');
        const testLoginLink = root.querySelector('[data-vip-test-login]');
        const testFeedback = root.querySelector('[data-vip-test-feedback]');
        const freeTrialButton = root.querySelector('[data-vip-free-claim]');
        const freeTrialFeedback = root.querySelector('[data-vip-free-feedback]');
        const mobileQuery = window.matchMedia('(max-width: 700px)');
        let activePlan = null;
        let mobileDetailsOpen = false;

        if (!plans.length) return;

        function syncDetailPanelPosition() {
            if (!detailPanel || !detailHomeMarker) return;

            const inlineOpen = mobileQuery.matches && activePlan && mobileDetailsOpen;

            if (inlineOpen) {
                const planItem = activePlan.parentElement;
                if (
                    planItem &&
                    planItem.classList.contains('vip-plan-item') &&
                    detailPanel.parentElement !== planItem
                ) {
                    planItem.appendChild(detailPanel);
                }
                detailPanel.hidden = false;
            } else {
                const home = detailHomeMarker.parentElement;
                if (home && detailPanel.parentElement !== home) {
                    home.insertBefore(detailPanel, detailHomeMarker.nextSibling);
                }
                detailPanel.hidden = mobileQuery.matches;
            }

            plans.forEach(function (item) {
                const selected = item === activePlan;
                const expanded = selected && (!mobileQuery.matches || mobileDetailsOpen);
                item.setAttribute('aria-expanded', expanded ? 'true' : 'false');

                const indicator = item.querySelector('[data-vip-select]');
                if (indicator) {
                    indicator.innerHTML = expanded
                        ? 'قابلیت‌های این سطح <i class="fas fa-check" aria-hidden="true"></i>'
                        : 'مشاهده قابلیت‌ها <i class="fas fa-arrow-left" aria-hidden="true"></i>';
                }
            });
        }

        function selectPlan(plan, userInitiated) {
            if (!plan) return;
            const wasActivePlan = activePlan === plan;
            activePlan = plan;

            if (mobileQuery.matches) {
                mobileDetailsOpen = userInitiated
                    ? (wasActivePlan ? !mobileDetailsOpen : true)
                    : false;
            } else {
                mobileDetailsOpen = true;
            }

            plans.forEach(function (item) {
                const selected = item === plan;
                item.classList.toggle('is-selected', selected);
                item.setAttribute('aria-pressed', selected ? 'true' : 'false');
            });

            const level = plan.dataset.level || '';
            const name = plan.dataset.vipName || 'VIP';
            const isAvailable = plan.dataset.vipAvailable === '1';
            const isCurrent = plan.classList.contains('is-current');
            const summaryFa = plan.dataset.vipSummaryFa || '';
            const summaryEn = plan.dataset.vipSummary || '';

            if (detailName) detailName.textContent = name;
            if (detailSummaryFa) detailSummaryFa.textContent = summaryFa;
            if (detailSummaryEn) detailSummaryEn.textContent = summaryEn;

            if (detailState) {
                detailState.classList.toggle('is-locked', !isAvailable);
                detailState.textContent = isAvailable
                    ? (isCurrent ? 'CURRENT MEMBERSHIP · اشتراک فعلی' : 'PUBLIC TIER · پلن قابل خرید')
                    : (isCurrent ? 'CURRENT MEMBER · LOCKED FOR NEW PURCHASES' : 'LOCKED · NOT AVAILABLE');
            }

            if (detailFeatures) {
                const template = root.querySelector(
                    'template[data-vip-feature-template][data-level="' + level + '"]'
                );
                if (template) {
                    detailFeatures.replaceChildren(document.importNode(template.content, true));
                } else {
                    detailFeatures.replaceChildren();
                }
            }

            if (testPurchaseButton) {
                const hasActiveMembership = root.dataset.vipHasActive === '1';
                testPurchaseButton.dataset.level = level;
                testPurchaseButton.hidden = ![1, 2, 3].includes(Number(level));
                testPurchaseButton.disabled = !isAvailable || hasActiveMembership;
                testPurchaseButton.textContent = hasActiveMembership
                    ? 'برای این حساب VIP فعال ثبت شده است'
                    : 'ثبت آزمایشی VIP بدون پرداخت';
                if (testFeedback) {
                    testFeedback.hidden = true;
                    testFeedback.textContent = '';
                    testFeedback.classList.remove('is-error', 'is-success');
                }
            }
            if (testLoginLink) {
                testLoginLink.hidden = !isAvailable;
            }

            syncDetailPanelPosition();

        }

        function setInactiveExpanded(expanded) {
            if (!inactiveToggle || !inactivePlans) return;
            inactiveExpanded = Boolean(expanded);
            inactiveToggle.setAttribute('aria-expanded', inactiveExpanded ? 'true' : 'false');
            inactivePlans.setAttribute('aria-hidden', inactiveExpanded ? 'false' : 'true');
            inactivePlans.hidden = !inactiveExpanded;
        }

        function handleViewportChange() {
            mobileDetailsOpen = false;
            syncDetailPanelPosition();
        }

        if (typeof mobileQuery.addEventListener === 'function') {
            mobileQuery.addEventListener('change', handleViewportChange);
        } else if (typeof mobileQuery.addListener === 'function') {
            mobileQuery.addListener(handleViewportChange);
        }

        if (inactiveToggle && inactivePlans) {
            inactiveToggle.addEventListener('click', function () {
                const nextExpanded = !inactiveExpanded;

                // Never leave the detail panel inside a grid that is being hidden.
                if (
                    !nextExpanded &&
                    activePlan &&
                    activePlan.dataset.vipAvailable !== '1'
                ) {
                    const firstPublicPlan = plans.find(function (plan) {
                        return plan.dataset.vipAvailable === '1';
                    });
                    if (firstPublicPlan) {
                        selectPlan(firstPublicPlan, false);
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

                    if (freeTrialFeedback) {
                        freeTrialFeedback.hidden = false;
                        freeTrialFeedback.classList.add('is-success');
                        freeTrialFeedback.textContent = payload.data && payload.data.message
                            ? payload.data.message + ' صفحه برای نمایش وضعیت جدید تازه‌سازی می‌شود.'
                            : 'اشتراک Silver با موفقیت ثبت شد.';
                    }
                    root.dataset.vipHasActive = '1';
                    freeTrialButton.textContent = 'هدیه Silver ثبت شد';
                    window.setTimeout(function () {
                        window.location.reload();
                    }, 1400);
                } catch (error) {
                    if (freeTrialFeedback) {
                        freeTrialFeedback.hidden = false;
                        freeTrialFeedback.classList.add('is-error');
                        freeTrialFeedback.textContent = error instanceof Error
                            ? error.message
                            : 'ارتباط با سرویس VIPCore برقرار نشد.';
                    }
                    freeTrialButton.disabled = false;
                    freeTrialButton.textContent = 'دریافت Silver رایگان · ۷ روز';
                }
            });
        }

        plans.forEach(function (plan) {
            // The whole card is one accessible control (Enter / Space / pointer).
            plan.addEventListener('click', function () {
                selectPlan(plan, true);
            });

            plan.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    selectPlan(plan, true);
                }
            });
        });

        const initialPlan = plans.find(function (plan) {
            return plan.classList.contains('is-current');
        }) || plans.find(function (plan) {
            return plan.dataset.vipAvailable === '1';
        }) || plans[0];

        selectPlan(initialPlan, false);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initVipStorefront, { once: true });
    } else {
        initVipStorefront();
    }

    window.addEventListener('content-loaded', initVipStorefront);
})();

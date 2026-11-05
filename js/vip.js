(function () {
    'use strict';

    function initVipStorefront() {
        const root = document.getElementById('vipStorefront');
        if (!root || root.dataset.vipStoreReady === '1') return;
        root.dataset.vipStoreReady = '1';

        const plans = Array.from(root.querySelectorAll('[data-vip-plan]'));
        const selectedName = root.querySelector('#vipSelectedName');
        const selectedMeta = root.querySelector('#vipSelectedMeta');
        const detailName = root.querySelector('#vipDetailTierName');
        const detailSummaryFa = root.querySelector('#vipDetailTierSummaryFa');
        const detailSummaryEn = root.querySelector('#vipDetailTierSummaryEn');
        const detailState = root.querySelector('#vipDetailTierState');
        const detailFeatures = root.querySelector('#vipDetailFeatures');
        const detailPanel = root.querySelector('.vip-feature-panel');
        const detailHomeMarker = root.querySelector('#vipDetailPanelHome');
        const mobileQuery = window.matchMedia('(max-width: 700px)');
        let activePlan = null;
        let mobileDetailsOpen = false;
        const checkoutButton = root.querySelector('#vipCheckoutButton');

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

            if (selectedName) selectedName.textContent = name;
            if (selectedMeta) {
                selectedMeta.textContent =
                    (plan.dataset.priceLabel || 'قیمت تعیین نشده') +
                    ' · ' +
                    (plan.dataset.durationLabel || 'مدت اشتراک مشخص نشده');
            }
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

            syncDetailPanelPosition();

            if (checkoutButton) {
                checkoutButton.textContent = isAvailable
                    ? 'خرید VIP فعلاً غیرفعال است'
                    : 'این سطح در حال حاضر قابل خرید نیست';
                checkoutButton.disabled = true;
            }
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

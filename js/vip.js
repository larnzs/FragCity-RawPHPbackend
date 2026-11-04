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
        const detailSummary = root.querySelector('#vipDetailTierSummary');
        const detailState = root.querySelector('#vipDetailTierState');
        const detailFeatures = root.querySelector('#vipDetailFeatures');
        const checkoutButton = root.querySelector('#vipCheckoutButton');

        if (!plans.length) return;

        function selectPlan(plan) {
            if (!plan) return;

            plans.forEach(function (item) {
                const selected = item === plan;
                item.classList.toggle('is-selected', selected);

                const button = item.querySelector('[data-vip-select]');
                if (button) {
                    button.setAttribute('aria-pressed', selected ? 'true' : 'false');
                    button.innerHTML = selected
                        ? 'قابلیت‌های این سطح <i class="fas fa-check" aria-hidden="true"></i>'
                        : 'مشاهده قابلیت‌ها <i class="fas fa-arrow-left" aria-hidden="true"></i>';
                }
            });

            const level = plan.dataset.level || '';
            const name = plan.dataset.vipName || 'VIP';
            const isAvailable = plan.dataset.vipAvailable === '1';
            const isCurrent = plan.classList.contains('is-current');
            const summary = plan.dataset.vipSummary || '';

            if (selectedName) selectedName.textContent = name;
            if (selectedMeta) {
                selectedMeta.textContent =
                    (plan.dataset.priceLabel || 'قیمت تعیین نشده') +
                    ' · ' +
                    (plan.dataset.durationLabel || 'مدت اشتراک مشخص نشده');
            }
            if (detailName) detailName.textContent = name;
            if (detailSummary) detailSummary.textContent = summary;

            if (detailState) {
                detailState.classList.toggle('is-locked', !isAvailable);
                detailState.textContent = isAvailable
                    ? (isCurrent ? 'CURRENT MEMBERSHIP' : 'PUBLIC CATALOG TIER')
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

            if (checkoutButton) {
                checkoutButton.textContent = isAvailable
                    ? 'خرید VIP فعلاً غیرفعال است'
                    : 'این سطح در حال حاضر قابل خرید نیست';
                checkoutButton.disabled = true;
            }
        }

        plans.forEach(function (plan) {
            const button = plan.querySelector('[data-vip-select]');
            if (button) {
                button.addEventListener('click', function () {
                    selectPlan(plan);
                });
            }
        });

        const initialPlan = plans.find(function (plan) {
            return plan.classList.contains('is-current');
        }) || plans[0];

        selectPlan(initialPlan);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initVipStorefront, { once: true });
    } else {
        initVipStorefront();
    }

    window.addEventListener('content-loaded', initVipStorefront);
})();
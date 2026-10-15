(function () {
    'use strict';

    function initBuyServerPage() {
        const root = document.querySelector('.buy-server-page');

        if (!root || root.dataset.buyServerInitialized === '1') {
            return;
        }

        root.dataset.buyServerInitialized = '1';

        const form = root.querySelector('#buyServerForm');
        const slider = root.querySelector('#slotSlider');
        const slotCount = root.querySelector('#slotCount');
        const durationSlider = root.querySelector('#durationSlider');
        const durationCount = root.querySelector('#durationCount');
        const priceDisplay = root.querySelector('#priceDisplay');
        const priceBreakdown = root.querySelector('#priceBreakdown');
        const tickrateSelect = root.querySelector('#tickrateSelect');
        const nodeListContainer = root.querySelector('#nodeListContainer');
        const checkoutBtn = root.querySelector('.checkout-btn:not(.disabled)');

        const baseSlots = Number(root.dataset.baseSlots || 10);
        const basePricePerHour = Number(root.dataset.basePricePerHour || 11000);
        const pricePerExtraSlotHourly = Number(root.dataset.pricePerExtraSlotHourly || 1200);

        let tickratePrices = {};
        let tickrateLabels = {};

        try {
            tickratePrices = JSON.parse(
                root.dataset.tickratePrices || '{}'
            );

            tickrateLabels = JSON.parse(
                root.dataset.tickrateLabels || '{}'
            );
        } catch (error) {
            tickratePrices = {};
            tickrateLabels = {};
        }

        function formatPrice(price) {
            return Number(price || 0).toLocaleString('en-US');
        }

        function updatePrice() {
            if (!slider || !durationSlider || !tickrateSelect) {
                return;
            }

            const slots =
                Number.parseInt(slider.value, 10) ||
                baseSlots;

            const tickrate =
                tickrateSelect.value;

            const hours =
                Number.parseInt(
                    durationSlider.value,
                    10
                ) || 1;

            const slotExtraHour =
                Math.max(
                    0,
                    slots - baseSlots
                ) * pricePerExtraSlotHourly;

            const tickrateExtraHour =
                Number(
                    tickratePrices[tickrate] || 0
                );

            const hourlyTotal =
                basePricePerHour +
                slotExtraHour +
                tickrateExtraHour;

            const total =
                hourlyTotal * hours;

            if (slotCount) {
                slotCount.textContent =
                    slots + ' نفر';
            }

            if (durationCount) {
                durationCount.textContent =
                    hours + ' ساعت';
            }

            if (priceDisplay) {
                priceDisplay.textContent =
                    formatPrice(total) +
                    ' تومان';
            }

            if (priceBreakdown) {
                priceBreakdown.innerHTML = `
                    <div class="price-line">
                        <span>قیمت پایه ساعتی</span>
                        <strong>
                            ${formatPrice(basePricePerHour)}
                            تومان
                        </strong>
                    </div>

                    <div class="price-line">
                        <span>هزینه اسلات اضافه</span>
                        <strong>
                            ${formatPrice(slotExtraHour)}
                            تومان / ساعت
                        </strong>
                    </div>

                    <div class="price-line">
                        <span>هزینه Tickrate</span>
                        <strong>
                            ${formatPrice(tickrateExtraHour)}
                            تومان / ساعت
                        </strong>
                    </div>

                    <div class="price-line">
                        <span>مدت زمان</span>
                        <strong>
                            ${hours} ساعت
                        </strong>
                    </div>

                    <div class="price-line">
                        <span>نرخ نهایی ساعتی</span>
                        <strong>
                            ${formatPrice(hourlyTotal)}
                            تومان
                        </strong>
                    </div>

                    <div class="price-divider"></div>

                    <div class="price-line total">
                        <span>جمع کل فاکتور</span>
                        <strong>
                            ${formatPrice(total)}
                            تومان
                        </strong>
                    </div>
                `;
            }
        }

        function selectNodeCard(card) {
            if (
                !card ||
                card.classList.contains('disabled')
            ) {
                return;
            }

            root
                .querySelectorAll('.node-card')
                .forEach(function (item) {
                    item.classList.remove(
                        'selected'
                    );
                });

            card.classList.add(
                'selected'
            );

            const radio =
                card.querySelector(
                    'input[type="radio"]'
                );

            if (
                radio &&
                !radio.disabled
            ) {
                radio.checked = true;
            }
        }

        function measurePing(
            ip,
            element,
            refreshButton
        ) {
            if (!ip || !element) {
                return;
            }

            if (refreshButton) {
                refreshButton.classList.add(
                    'is-loading'
                );
            }

            element.textContent =
                '...';

            element.style.color =
                '#94a3b8';

            const img =
                new Image();

            const start =
                performance.now();

            let completed = false;

            const finish =
                function (
                    ping,
                    timedOut
                ) {
                    if (completed) {
                        return;
                    }

                    completed = true;

                    if (refreshButton) {
                        refreshButton.classList.remove(
                            'is-loading'
                        );
                    }

                    if (timedOut) {
                        element.textContent =
                            'تایم‌اوت';

                        element.style.color =
                            '#fca5a5';

                        return;
                    }

                    element.textContent =
                        ping + ' ms';

                    if (ping < 70) {

                        element.style.color =
                            '#86efac';

                    } else if (ping < 130) {

                        element.style.color =
                            '#fde68a';

                    } else {

                        element.style.color =
                            '#fca5a5';

                    }
                };

            img.onload =
                function () {
                    finish(
                        Math.round(
                            performance.now() -
                            start
                        ),
                        false
                    );
                };

            img.onerror =
                function () {
                    finish(
                        Math.round(
                            performance.now() -
                            start
                        ),
                        false
                    );
                };

            img.src =
                'http://' +
                ip +
                ':80/ping_test_fake.png?t=' +
                Date.now();

            window.setTimeout(
                function () {
                    finish(
                        0,
                        true
                    );
                },
                3000
            );
        }

        function refreshSinglePing(
            ip,
            spanElement,
            refreshButton
        ) {
            measurePing(
                ip,
                spanElement,
                refreshButton
            );
        }

        function updateAllPings() {
            root
                .querySelectorAll(
                    '.node-ping'
                )
                .forEach(
                    function (
                        pingContainer
                    ) {
                        const ip =
                            pingContainer.getAttribute(
                                'data-ip'
                            );

                        const span =
                            pingContainer.querySelector(
                                '.ping-val'
                            );

                        if (
                            !ip ||
                            !span
                        ) {
                            return;
                        }

                        if (
                            span.textContent ===
                                '-- ms' ||
                            span.textContent ===
                                'تایم‌اوت'
                        ) {
                            measurePing(
                                ip,
                                span,
                                pingContainer.querySelector(
                                    '[data-ping-refresh]'
                                )
                            );
                        }
                    }
                );
        }

        function escapeHtml(
            value
        ) {
            return String(
                value ?? ''
            )
                .replace(
                    /&/g,
                    '&amp;'
                )
                .replace(
                    /</g,
                    '&lt;'
                )
                .replace(
                    />/g,
                    '&gt;'
                )
                .replace(
                    /"/g,
                    '&quot;'
                )
                .replace(
                    /'/g,
                    '&#039;'
                );
        }

        function getCurrentSelectedNode() {
            const selectedRadio =
                root.querySelector(
                    'input[name="node_name"]:checked'
                );

            return selectedRadio
                ? selectedRadio.value
                : '';
        }

        function renderNodes(
            nodes
        ) {
            if (!nodeListContainer) {
                return;
            }

            if (
                !Array.isArray(nodes) ||
                nodes.length === 0
            ) {
                nodeListContainer.innerHTML = `
                    <div class="status-message error">
                        <i class="fas fa-triangle-exclamation"></i>
                        <span>
                            هیچ نود فعالی با هارت‌بیت معتبر یافت نشد.
                            لطفا ایجنت را روشن کنید.
                        </span>
                    </div>
                `;

                return;
            }

            const currentSelectedNode =
                getCurrentSelectedNode();

            const html =
                nodes
                    .map(
                        function (
                            node
                        ) {
                            const nName =
                                node.node_name ||
                                'Node';

                            const nIp =
                                node.ip_address ||
                                '';

                            const nRam =
                                node.ram_usage
                                    ? Math.round(
                                          Number.parseFloat(
                                              node.ram_usage
                                          ) *
                                              10
                                      ) / 10
                                    : 0;

                            const nCpuUsage =
                                node.cpu_usage
                                    ? Math.round(
                                          Number.parseFloat(
                                              node.cpu_usage
                                          ) *
                                              10
                                      ) / 10
                                    : 0;

                            const isRamHigh =
                                nRam > 80;

                            const isChecked =
                                !isRamHigh &&
                                currentSelectedNode ===
                                    nName;

                            const cardClass =
                                isRamHigh
                                    ? 'node-card disabled'
                                    : currentSelectedNode ===
                                        nName
                                    ? 'node-card selected'
                                    : 'node-card';

                            return `
                                <label
                                    class="${cardClass}"
                                    data-node-card
                                >

                                    <div class="node-main">

                                        <input
                                            type="radio"
                                            name="node_name"
                                            value="${escapeHtml(nName)}"
                                            ${isChecked ? 'checked' : ''}
                                            ${isRamHigh ? 'disabled' : 'required'}
                                        >

                                        <div class="node-copy">

                                            <div class="node-title-row">

                                                <strong>
                                                    ${escapeHtml(nName)}
                                                </strong>

                                                <span class="node-ip">
                                                    IP:
                                                    ${escapeHtml(nIp)}
                                                </span>

                                            </div>

                                            <div class="node-metrics">

                                                <span>
                                                    <i class="fas fa-microchip"></i>
                                                    CPU:
                                                    ${nCpuUsage}%
                                                </span>

                                                <span class="metric-separator">
                                                    |
                                                </span>

                                                <span class="${isRamHigh ? 'metric-high' : ''}">

                                                    <i class="fas fa-memory"></i>

                                                    RAM:
                                                    ${nRam}%

                                                    ${isRamHigh ? '(پر)' : ''}

                                                </span>

                                            </div>

                                        </div>

                                    </div>


                                    <div class="node-side">

                                        ${
                                            isRamHigh
                                                ? `
                                                    <div class="node-warning">

                                                        <i class="fas fa-triangle-exclamation"></i>

                                                        رم تکمیل

                                                    </div>
                                                `
                                                : `
                                                    <div
                                                        class="node-ping"
                                                        data-ip="${escapeHtml(nIp)}"
                                                    >

                                                        <span class="ping-text">

                                                            <i class="fas fa-wifi"></i>

                                                            <span class="ping-val">
                                                                -- ms
                                                            </span>

                                                        </span>

                                                        <button
                                                            type="button"
                                                            class="ping-refresh-btn"
                                                            data-ping-refresh
                                                            title="بروزرسانی پینگ"
                                                        >

                                                            <i class="fas fa-sync-alt"></i>

                                                        </button>

                                                    </div>


                                                    <div class="node-online">

                                                        <i class="fas fa-circle"></i>

                                                        Online

                                                    </div>
                                                `
                                        }

                                    </div>

                                </label>
                            `;
                        }
                    )
                    .join('');

            nodeListContainer.innerHTML = `
                <div class="node-list-scroll">
                    ${html}
                </div>
            `;

            updateAllPings();
        }

        async function checkAgentNodes() {
            if (!nodeListContainer) {
                return;
            }

            try {

                const response =
                    await fetch(
                        'index.php?page=buyserveropen&ajax_check_nodes=1',
                        {
                            credentials:
                                'same-origin',

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

                if (
                    data.status ===
                    'success'
                ) {
                    renderNodes(
                        data.nodes ||
                            []
                    );
                }

            } catch (error) {

                console.error(
                    'Error checking nodes:',
                    error
                );

            }
        }

        function initPaymentOptions() {

            root
                .querySelectorAll(
                    '.payment-option'
                )
                .forEach(
                    function (
                        option
                    ) {

                        const radio =
                            option.querySelector(
                                'input[type="radio"]'
                            );

                        if (!radio) {
                            return;
                        }

                        option.classList.toggle(
                            'active',
                            radio.checked
                        );

                    }
                );
        }

        if (slider) {
            slider.addEventListener(
                'input',
                updatePrice
            );
        }

        if (durationSlider) {
            durationSlider.addEventListener(
                'input',
                updatePrice
            );
        }

        if (tickrateSelect) {
            tickrateSelect.addEventListener(
                'change',
                updatePrice
            );
        }

        root.addEventListener(
            'change',
            function (
                event
            ) {

                if (
                    event.target.matches(
                        'input[name="payment_method"]'
                    )
                ) {

                    root
                        .querySelectorAll(
                            '.payment-option'
                        )
                        .forEach(
                            function (
                                option
                            ) {

                                const radio =
                                    option.querySelector(
                                        'input[type="radio"]'
                                    );

                                option.classList.toggle(
                                    'active',
                                    Boolean(
                                        radio &&
                                        radio.checked
                                    )
                                );

                            }
                        );
                }

                if (
                    event.target.matches(
                        'input[name="node_name"]'
                    )
                ) {

                    const card =
                        event.target.closest(
                            '.node-card'
                        );

                    if (card) {
                        selectNodeCard(
                            card
                        );
                    }

                }

            }
        );

        root.addEventListener(
            'click',
            function (
                event
            ) {

                const nodeCard =
                    event.target.closest(
                        '[data-node-card]'
                    );

                const pingRefresh =
                    event.target.closest(
                        '[data-ping-refresh]'
                    );

                if (pingRefresh) {

                    event.preventDefault();

                    event.stopPropagation();

                    const pingContainer =
                        pingRefresh.closest(
                            '.node-ping'
                        );

                    const ip =
                        pingContainer
                            ? pingContainer.getAttribute(
                                  'data-ip'
                              )
                            : '';

                    const span =
                        pingContainer
                            ? pingContainer.querySelector(
                                  '.ping-val'
                              )
                            : null;

                    refreshSinglePing(
                        ip,
                        span,
                        pingRefresh
                    );

                    return;
                }

                if (
                    nodeCard &&
                    !nodeCard.classList.contains(
                        'disabled'
                    )
                ) {
                    selectNodeCard(
                        nodeCard
                    );
                }

            }
        );

        if (form) {

            form.addEventListener(
                'submit',
                function () {

                    if (!checkoutBtn) {
                        return;
                    }

                    checkoutBtn.disabled =
                        true;

                    checkoutBtn.classList.add(
                        'is-loading'
                    );

                }
            );

        }

        updatePrice();

        initPaymentOptions();

        updateAllPings();

        const successRedirect =
            root.querySelector(
                '.buy-success-redirect[data-redirect-url]'
            );

        if (successRedirect) {

            window.setTimeout(
                function () {

                    window.location.href =
                        successRedirect.dataset.redirectUrl;

                },
                3000
            );

        }

        window.setTimeout(
            function () {

                checkAgentNodes();

            },
            1500
        );

        const refreshInterval =
            window.setInterval(
                function () {

                    if (
                        !document.body.contains(
                            root
                        )
                    ) {

                        window.clearInterval(
                            refreshInterval
                        );

                        return;
                    }

                    checkAgentNodes();

                },
                15000
            );

        root.dataset.buyServerRefreshInterval =
            String(
                refreshInterval
            );
    }

    window.initBuyServerPage =
        initBuyServerPage;

    if (
        document.readyState ===
        'loading'
    ) {

        document.addEventListener(
            'DOMContentLoaded',
            initBuyServerPage,
            {
                once: true
            }
        );

    } else {

        initBuyServerPage();

    }

    window.addEventListener(
        'content-loaded',
        function (
            event
        ) {

            if (
                !event.detail ||
                event.detail.page ===
                    'buyserveropen'
            ) {

                initBuyServerPage();

            }

        }
    );

})();
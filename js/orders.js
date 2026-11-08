(function () {
    'use strict';

    function initOrderTabs(root) {
        const tabs = Array.from(root.querySelectorAll('[data-orders-tab]'));
        const panels = Array.from(root.querySelectorAll('[data-orders-panel]'));
        const vipOnly = Array.from(root.querySelectorAll('[data-orders-vip-only]'));
        const serverTab = root.querySelector('[data-orders-tab="servers"]');
        const serverPanel = root.querySelector('[data-orders-panel="servers"]');
        const refreshButton = root.querySelector('[data-server-invoices-refresh]');
        const feedback = root.querySelector('[data-server-invoices-feedback]');
        const invoiceList = root.querySelector('[data-server-invoices-list]');
        const emptyState = root.querySelector('[data-server-invoices-empty]');
        const csrf = root.dataset.ordersCsrf || '';
        let loaded = false;
        let loading = false;

        if (!tabs.length || !panels.length || !serverPanel || !invoiceList) return;

        function setFeedback(message, isError) {
            if (!feedback) return;
            feedback.textContent = message || '';
            feedback.classList.toggle('is-error', Boolean(isError));
            feedback.classList.toggle('is-success', Boolean(message) && !isError);
        }

        function makeNode(tag, className, content) {
            const node = document.createElement(tag);
            if (className) node.className = className;
            if (content !== undefined && content !== null) node.textContent = String(content);
            return node;
        }

        function formatDate(value) {
            if (!value) return '—';
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) return '—';
            return new Intl.DateTimeFormat('fa-IR', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            }).format(date);
        }

        function formatAmount(value) {
            const number = Number(value);
            if (!Number.isFinite(number)) return '—';
            return new Intl.NumberFormat('fa-IR', {
                maximumFractionDigits: 2
            }).format(number);
        }

        function statusDetails(status) {
            const values = {
                paid: { label: 'پرداخت‌شده', className: 'success', icon: 'fa-circle-check' },
                pending: { label: 'در انتظار پرداخت', className: 'pending', icon: 'fa-clock' },
                cancelled: { label: 'لغوشده', className: 'muted', icon: 'fa-ban' }
            };
            return values[String(status || '').toLowerCase()] || {
                label: 'وضعیت نامشخص',
                className: 'muted',
                icon: 'fa-circle-question'
            };
        }

        function serviceStatusLabel(server) {
            const state = String(server?.state || '').toLowerCase();
            const status = String(server?.service_status || '').toLowerCase();
            if (state === 'provisioning' || status === 'pending') return 'در حال آماده‌سازی';
            if (state === 'unavailable') return 'جزئیات سرور در دسترس نیست';
            if (status === 'suspended' || server?.server_status === 'suspended') return 'معلق';
            if (status === 'active') return 'فعال';
            if (status === 'cancelled') return 'لغوشده';
            return 'وضعیت نامشخص';
        }

        function renderItemServerContext(item) {
            const context = makeNode('div', 'fc-orders__invoice-service');
            const server = item.server && typeof item.server === 'object' ? item.server : null;
            const kind = String(item.reference_kind || 'manual');
            const association = String(item.association_status || '');

            if (server) {
                const heading = makeNode('div', 'fc-orders__invoice-service-heading');
                const serviceName = server.server_name || server.product_name || 'سرویس سرور';
                heading.append(
                    makeNode('strong', '', serviceName),
                    makeNode('span', 'fc-orders__invoice-service-id', 'سرویس #' + server.service_id)
                );
                context.appendChild(heading);

                const productName = String(server.product_name || '').trim();
                if (productName && productName !== serviceName) {
                    context.appendChild(makeNode('p', 'fc-orders__invoice-service-meta', 'محصول: ' + productName));
                }

                const status = serviceStatusLabel(server);
                context.appendChild(makeNode('p', 'fc-orders__invoice-service-meta', 'وضعیت سرویس: ' + status));

                const host = String(server.host || '').trim();
                const port = Number(server.port);
                if (host) {
                    const address = host + (Number.isInteger(port) && port > 0 ? ':' + port : '');
                    context.appendChild(makeNode('p', 'fc-orders__invoice-service-meta', 'آدرس اتصال: ' + address));
                }

                const locationParts = [server.node_name, server.location].filter((value) => String(value || '').trim());
                if (locationParts.length) {
                    context.appendChild(makeNode('p', 'fc-orders__invoice-service-meta', 'موقعیت: ' + locationParts.join(' · ')));
                }

                if (server.expires_at) {
                    context.appendChild(makeNode('p', 'fc-orders__invoice-service-meta', 'انقضای سرویس: ' + formatDate(server.expires_at)));
                }
                return context;
            }

            if (kind === 'manual' || association === 'manual_unlinked') {
                context.appendChild(makeNode(
                    'p',
                    'fc-orders__invoice-service-warning',
                    'این ردیف به سرویس مشخصی در Paymenter متصل نشده است؛ بنابراین از اطلاعات موجود نمی‌توان فهمید برای کدام سرور صادر شده.'
                ));
            } else if (association === 'lookup_unavailable') {
                context.appendChild(makeNode(
                    'p',
                    'fc-orders__invoice-service-warning',
                    'شناسه سرویس در فاکتور ثبت شده، اما دریافت جزئیات سرور از سرویس مدیریت سرورها موقتاً ممکن نبود.'
                ));
            } else {
                const serviceId = Number(item.related_service_id);
                const prefix = kind === 'service_upgrade' ? 'ردیف ارتقای سرویس' : 'سرویس';
                const suffix = Number.isInteger(serviceId) && serviceId > 0 ? ' #' + serviceId : '';
                context.appendChild(makeNode(
                    'p',
                    'fc-orders__invoice-service-warning',
                    prefix + suffix + ' به این ردیف متصل است، اما در فهرست سرورهای فعلی شما پیدا نشد.'
                ));
            }

            return context;
        }

        function renderInvoice(invoice) {
            const card = makeNode('article', 'fc-orders__order-card fc-orders__invoice-card');
            const main = makeNode('div', 'fc-orders__order-main');
            const icon = makeNode('span', 'fc-orders__order-icon');
            const iconGlyph = makeNode('i');
            iconGlyph.className = 'fa-solid fa-file-invoice';
            iconGlyph.setAttribute('aria-hidden', 'true');
            icon.appendChild(iconGlyph);

            const identity = makeNode('div', 'fc-orders__order-identity');
            const titleRow = makeNode('div', 'fc-orders__order-title-row');
            titleRow.appendChild(makeNode('h3', '', 'فاکتور سرور'));
            titleRow.appendChild(makeNode('span', 'fc-orders__order-id', 'شناسه فاکتور #' + invoice.invoice_id));
            identity.appendChild(titleRow);

            const meta = makeNode('div', 'fc-orders__order-meta');
            const created = makeNode('span');
            const createdIcon = makeNode('i');
            createdIcon.className = 'fa-regular fa-calendar';
            createdIcon.setAttribute('aria-hidden', 'true');
            created.append(createdIcon, document.createTextNode(formatDate(invoice.created_at)));
            meta.appendChild(created);
            identity.appendChild(meta);

            const status = statusDetails(invoice.status);
            const statusNode = makeNode('span', 'fc-orders__status fc-orders__status--' + status.className);
            const statusIcon = makeNode('i');
            statusIcon.className = 'fa-solid ' + status.icon;
            statusIcon.setAttribute('aria-hidden', 'true');
            statusNode.append(statusIcon, document.createTextNode(status.label));

            main.append(icon, identity, statusNode);
            card.appendChild(main);

            const details = makeNode('div', 'fc-orders__order-details fc-orders__invoice-details');
            const currency = String(invoice.currency_code || '').trim();
            const currencyLabel = currency || 'واحد پول نامشخص';
            const detailValues = [
                ['تاریخ صدور', formatDate(invoice.created_at)],
                ['سررسید', formatDate(invoice.due_at)],
                ['آخرین تغییر', formatDate(invoice.updated_at)],
                ['جمع ردیف‌های نمایش‌داده‌شده', invoice.subtotal === null ? '—' : formatAmount(invoice.subtotal) + ' ' + currencyLabel],
                ['تعداد ردیف‌ها', new Intl.NumberFormat('fa-IR').format(Array.isArray(invoice.items) ? invoice.items.length : 0)]
            ];
            detailValues.forEach(([label, value]) => {
                const detail = makeNode('div', 'fc-orders__detail');
                detail.append(makeNode('span', '', label), makeNode('strong', '', value));
                details.appendChild(detail);
            });
            card.appendChild(details);

            const itemList = makeNode('div', 'fc-orders__invoice-items');
            if (Array.isArray(invoice.items) && invoice.items.length) {
                invoice.items.forEach((item) => {
                    const line = makeNode('div', 'fc-orders__invoice-line');
                    const description = makeNode('span', 'fc-orders__invoice-description', item.description || 'آیتم فاکتور');
                    const quantity = Number(item.quantity);
                    const quantityLabel = Number.isFinite(quantity)
                        ? new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 3 }).format(quantity)
                        : '۱';
                    const quantityNode = makeNode('small', 'fc-orders__invoice-quantity', '× ' + quantityLabel);

                    const amounts = makeNode('div', 'fc-orders__invoice-amounts');
                    const unitPrice = item.unit_price === null
                        ? 'قیمت واحد: —'
                        : 'قیمت واحد: ' + formatAmount(item.unit_price) + ' ' + currencyLabel;
                    amounts.appendChild(makeNode('small', 'fc-orders__invoice-unit-price', unitPrice));
                    amounts.appendChild(makeNode(
                        'strong',
                        'fc-orders__invoice-line-amount',
                        item.line_total === null ? 'جمع: —' : 'جمع: ' + formatAmount(item.line_total) + ' ' + currencyLabel
                    ));

                    line.append(description, quantityNode, amounts);
                    itemList.append(line, renderItemServerContext(item));
                });
            } else {
                itemList.appendChild(makeNode('p', 'fc-orders__invoice-no-items', 'جزئیات اقلام این فاکتور در دسترس نیست.'));
            }
            card.appendChild(itemList);

            return card;
        }

        function renderInvoices(invoices) {
            invoiceList.replaceChildren();
            if (emptyState) emptyState.hidden = invoices.length !== 0;
            invoiceList.hidden = invoices.length === 0;

            if (serverTab) {
                const count = serverTab.querySelector('[data-server-invoice-count]');
                if (count) count.textContent = new Intl.NumberFormat('fa-IR').format(invoices.length);
            }

            invoices.forEach((invoice) => invoiceList.appendChild(renderInvoice(invoice)));
        }

        const rememberedInvoices = window.fragCityServerInvoiceCache;
        if (Array.isArray(rememberedInvoices)) {
            renderInvoices(rememberedInvoices);
        }

        async function loadInvoices(force) {
            if (loading || (!force && loaded)) return;
            loading = true;
            if (refreshButton) refreshButton.disabled = true;
            if (emptyState) emptyState.hidden = true;
            const hasExistingInvoices = invoiceList.childElementCount > 0;
            if (!hasExistingInvoices) invoiceList.hidden = true;
            setFeedback(
                hasExistingInvoices
                    ? 'در حال به‌روزرسانی اطلاعات فاکتورها...'
                    : 'در حال دریافت فاکتورهای حساب شما از Paymenter...',
                false
            );

            try {
                const response = await fetch('/api/server-invoices.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-FragCity-CSRF': csrf
                    },
                    body: JSON.stringify({ action: 'list' })
                });

                let result = {};
                try {
                    result = await response.json();
                } catch (error) {
                    throw new Error('invalid_response');
                }

                if (!response.ok || result.success !== true || !Array.isArray(result.data?.invoices)) {
                    throw new Error(typeof result.error === 'string' ? result.error : 'paymenter_invoice_request_failed');
                }

                window.fragCityServerInvoiceCache = result.data.invoices;
                window.fragCityServerInvoiceCacheAt = Date.now();
                renderInvoices(result.data.invoices);
                loaded = true;
                setFeedback('', false);
            } catch (error) {
                loaded = false;
                const messages = {
                    authentication_required: 'برای مشاهده فاکتورها دوباره وارد حساب FragCity شوید.',
                    csrf_failed: 'درخواست امنیتی منقضی شده است؛ صفحه را تازه‌سازی کنید.',
                    paymenter_invoice_identity_not_synced: 'حساب Paymenter شما هنوز با ایمیل حساب FragCity همگام نشده است.',
                    paymenter_invoices_not_configured: 'اتصال مستقل فاکتورهای Paymenter هنوز تنظیم نشده است.',
                    paymenter_api_permission_denied: 'دسترسی خواندن فاکتور، اقلام یا اطلاعات مالک در API Key وجود ندارد.',
                    paymenter_invoice_identity_mismatch: 'اطلاعات مالک فاکتور با حساب FragCity شما مطابقت ندارد؛ برای امنیت، فاکتور نمایش داده نشد.',
                    paymenter_invoice_owner_unverified: 'مالک فاکتور از پاسخ Paymenter قابل تأیید نیست؛ برای امنیت، فاکتور نمایش داده نشد.',
                    paymenter_invoice_items_unavailable: 'جزئیات اقلام فاکتور از Paymenter دریافت نشد.',
                    paymenter_invoice_unreachable: 'ارتباط با Paymenter برقرار نشد. کمی بعد دوباره تلاش کنید.',
                    paymenter_invoice_curl_required: 'ماژول ارتباط امن سرور در دسترس نیست.',
                    invalid_response: 'پاسخ دریافتی از سرور معتبر نیست.',
                    paymenter_invoice_request_failed: 'دریافت فاکتورها با خطا مواجه شد.'
                };
                setFeedback(
                    messages[error && error.message] || messages.paymenter_invoice_request_failed,
                    true
                );
            } finally {
                loading = false;
                if (refreshButton) refreshButton.disabled = false;
            }
        }

        function selectTab(name) {
            tabs.forEach((tab) => {
                const active = tab.dataset.ordersTab === name;
                tab.classList.toggle('fc-orders__category--active', active);
                tab.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            panels.forEach((panel) => {
                panel.hidden = panel.dataset.ordersPanel !== name;
            });
            vipOnly.forEach((node) => {
                node.hidden = name !== 'vip';
            });

            window.fragCityOrdersActiveTab = name;
            if (name === 'servers') loadInvoices(false);
        }

        tabs.forEach((tab) => {
            tab.addEventListener('click', () => selectTab(tab.dataset.ordersTab || 'vip'));
        });
        if (refreshButton) refreshButton.addEventListener('click', () => loadInvoices(true));
        const rememberedTab = window.fragCityOrdersActiveTab === 'servers' ? 'servers' : 'vip';
        selectTab(rememberedTab);
    }

    function initOrdersPage() {
        const root = document.getElementById('fcOrders');
        if (!root || root.dataset.ordersControllerReady === '1') return;
        root.dataset.ordersControllerReady = '1';

        initOrderTabs(root);

        const statusCard = root.querySelector('#fcOrdersVipStatus');
        const openButton = root.querySelector('[data-vip-revoke-open]');
        const dialog = document.getElementById('fcVipRevokeDialog');
        const confirmButton = dialog ? dialog.querySelector('[data-vip-revoke-confirm]') : null;
        const cancelButton = dialog ? dialog.querySelector('[data-vip-revoke-cancel]') : null;
        const modalFeedback = dialog ? dialog.querySelector('[data-vip-revoke-modal-feedback]') : null;
        const pageFeedback = statusCard ? statusCard.querySelector('[data-vip-revoke-feedback]') : null;

        if (!statusCard || !openButton || !dialog || !confirmButton) return;

        // A transformed AJAX page wrapper would otherwise become the containing
        // block for fixed UI. Native dialogs live in the browser top layer, and
        // moving it to body also keeps its viewport centering consistent.
        if (dialog.parentElement !== document.body) {
            document.body.appendChild(dialog);
        }

        const messages = {
            authentication_required: 'نشست شما معتبر نیست؛ دوباره وارد حساب FragCity شوید.',
            csrf_failed: 'درخواست امنیتی منقضی شده است؛ صفحه را تازه‌سازی کنید.',
            membership_not_active: 'در حال حاضر اشتراک VIP فعالی برای ابطال وجود ندارد.',
            vipcore_disabled: 'اتصال VIPCore روی سرور فعال نیست.',
            vipcore_not_configured: 'تنظیمات اتصال VIPCore کامل نیست.',
            vipcore_unavailable: 'دیتابیس VIPCore یا جدول تاریخچه ابطال در دسترس نیست.',
            vip_refund_migration_required: 'پیش از ابطال، Migration بازپرداخت کیف پول را روی دیتابیس اصلی سایت اجرا کنید.',
            vip_revoke_cooldown: 'ابطال VIP فقط یک ساعت پس از پرداخت سفارش فعال می‌شود.',
            vip_revoke_cooldown_status_unavailable: 'زمان پرداخت سفارش برای بررسی محدودیت ابطال در دسترس نیست؛ فعلاً امکان ابطال وجود ندارد.',
            vip_revoke_unavailable: 'ابطال وضعیت VIP انجام نشد. ارتباط سرویس را بررسی کنید و دوباره تلاش کنید.'
        };

        function showFeedback(target, message, isError) {
            if (!target) return;
            target.textContent = message || '';
            target.classList.toggle('is-error', Boolean(isError));
            target.classList.toggle('is-success', Boolean(message) && !isError);
        }

        function setBusy(busy) {
            confirmButton.disabled = busy;
            if (cancelButton) cancelButton.disabled = busy;
            confirmButton.innerHTML = busy
                ? '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> در حال ابطال...'
                : '<i class="fa-solid fa-shield-halved" aria-hidden="true"></i> بله، باطل کن';
            dialog.setAttribute('aria-busy', busy ? 'true' : 'false');
        }

        function updateOrderCards(orderRef) {
            const cards = Array.from(root.querySelectorAll('[data-order-ref]'));
            cards.forEach((orderCard) => {
                const currentStatus = String(orderCard.dataset.orderStatus || '').toLowerCase();
                const isCurrentMembershipOrder = Boolean(orderRef)
                    && orderCard.dataset.orderRef === orderRef;
                const isStalePaidOrder = currentStatus === 'paid';

                if (!isCurrentMembershipOrder && !isStalePaidOrder) return;

                const status = orderCard.querySelector('.fc-orders__status');
                if (status) {
                    status.className = 'fc-orders__status fc-orders__status--muted';
                    status.innerHTML = '<i class="fa-solid fa-shield-halved" aria-hidden="true"></i> باطل‌شده توسط کاربر';
                }

                const description = orderCard.querySelector('.fc-orders__order-description');
                if (description) {
                    description.textContent = 'این سفارش به درخواست شما از صف فعال‌سازی خارج شده است. سابقه پرداخت برای پیگیری نگهداری می‌شود.';
                }

                orderCard.querySelector('.fc-orders__retry')?.remove();
                orderCard.querySelector('.fc-orders__verified')?.remove();
                orderCard.dataset.orderStatus = 'revoked';
            });
        }

        function updateMembershipCard(data) {
            statusCard.classList.remove('is-active');
            statusCard.classList.add('is-revoked');

            const icon = statusCard.querySelector('.fc-orders__vip-status-icon i');
            if (icon) {
                icon.className = 'fa-solid fa-shield-check';
            }

            const description = statusCard.querySelector('.fc-orders__vip-status-description');
            if (description) {
                description.textContent = 'سطح VIP فعلی با موفقیت باطل شد؛ اکنون می‌توانید اشتراک جدیدی تهیه کنید.';
            }

            statusCard.querySelector('.fc-orders__vip-status-meta')?.remove();
            statusCard.querySelector('[data-vip-revoke-open]')?.remove();

            const actions = statusCard.querySelector('.fc-orders__vip-status-actions');
            if (actions) {
                const buyLink = document.createElement('a');
                buyLink.className = 'fc-orders__buy-link';
                buyLink.href = '/vip/';
                buyLink.setAttribute('data-ajax-nav', '');
                buyLink.setAttribute('data-slug', 'vip');
                buyLink.innerHTML = '<i class="fa-solid fa-crown" aria-hidden="true"></i> خرید VIP';
                actions.replaceChildren(buyLink);
            }

            const level = Number(data.level || 0);
            if (level > 0) {
                statusCard.dataset.revokedVipLevel = String(level);
            }
        }

        const cooldownLabel = statusCard.querySelector('[data-vip-revoke-countdown]');
        let cooldownTimer = null;
        const renderedServerTime = Number(openButton.dataset.vipRevokeServerNow || 0);
        const renderedClientTime = Date.now();

        function formatCooldown(seconds) {
            const safeSeconds = Math.max(0, Math.floor(seconds));
            const hours = Math.floor(safeSeconds / 3600);
            const minutes = Math.floor((safeSeconds % 3600) / 60);
            const remaining = safeSeconds % 60;
            const number = (value) => new Intl.NumberFormat('fa-IR', {
                minimumIntegerDigits: 2,
                maximumFractionDigits: 0
            }).format(value);

            return hours > 0
                ? number(hours) + ':' + number(minutes) + ':' + number(remaining)
                : number(minutes) + ':' + number(remaining);
        }

        function updateRevokeCooldown() {
            const availableAt = Number(openButton.dataset.vipRevokeAvailableAt || 0);
            if (!cooldownLabel || availableAt < 1) return;

            // Advance the server-rendered timestamp using elapsed browser time;
            // this avoids depending on the visitor's local wall-clock setting.
            const estimatedServerNow = renderedServerTime > 0
                ? renderedServerTime + Math.floor((Date.now() - renderedClientTime) / 1000)
                : Math.floor(Date.now() / 1000);
            const remaining = Math.max(0, availableAt - estimatedServerNow);

            if (remaining > 0) {
                openButton.disabled = true;
                cooldownLabel.textContent = 'امکان ابطال یک ساعت پس از پرداخت فعال می‌شود · زمان باقی‌مانده: ' + formatCooldown(remaining);
                return;
            }

            openButton.disabled = false;
            cooldownLabel.textContent = 'محدودیت یک‌ساعته به پایان رسیده است؛ امکان ابطال فعال است.';
            if (cooldownTimer !== null) {
                window.clearInterval(cooldownTimer);
                cooldownTimer = null;
            }
        }

        if (cooldownLabel && Number(openButton.dataset.vipRevokeAvailableAt || 0) > 0) {
            updateRevokeCooldown();
            cooldownTimer = window.setInterval(function () {
                if (!document.contains(statusCard)) {
                    window.clearInterval(cooldownTimer);
                    cooldownTimer = null;
                    return;
                }
                updateRevokeCooldown();
            }, 1000);
        }

        function openDialog() {
            if (openButton.disabled) return;
            showFeedback(modalFeedback, '', false);
            setBusy(false);
            if (typeof dialog.showModal === 'function') {
                dialog.showModal();
            } else {
                dialog.setAttribute('open', '');
            }
        }

        function closeDialog() {
            if (typeof dialog.close === 'function' && dialog.open) {
                dialog.close();
            } else {
                dialog.removeAttribute('open');
            }
        }

        openButton.addEventListener('click', openDialog);
        if (cancelButton) cancelButton.addEventListener('click', closeDialog);

        dialog.addEventListener('click', function (event) {
            if (event.target === dialog && !confirmButton.disabled) {
                closeDialog();
            }
        });

        confirmButton.addEventListener('click', async function () {
            if (confirmButton.disabled) return;

            setBusy(true);
            showFeedback(modalFeedback, 'در حال بررسی وضعیت اشتراک و ثبت ابطال...', false);

            try {
                const response = await fetch('/api/vip-revoke.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-FragCity-CSRF': root.dataset.vipRevokeCsrf || ''
                    },
                    body: '{}'
                });

                let result = {};
                try {
                    result = await response.json();
                } catch (error) {
                    throw new Error('invalid_response');
                }

                if (!response.ok || result.success !== true || result.data?.revoked !== true) {
                    const code = typeof result.error === 'string' ? result.error : 'vip_revoke_unavailable';
                    const requestError = new Error(code);
                    requestError.retryAfterSeconds = Number(result.retry_after_seconds || 0);
                    requestError.availableAt = Number(result.available_at || 0);
                    throw requestError;
                }

                updateMembershipCard(result.data);
                updateOrderCards(typeof result.data.order_ref === 'string' ? result.data.order_ref : '');
                const refundedToman = Math.max(0, Number(result.data.refund_amount_toman || 0));
                const refundedLabel = new Intl.NumberFormat('fa-IR', {
                    maximumFractionDigits: 8
                }).format(refundedToman);
                const successMessage = refundedToman > 0
                    ? 'وضعیت VIP باطل شد و ' + refundedLabel + ' تومان به کیف پول شما بازگشت داده شد. اکنون می‌توانید اشتراک جدیدی تهیه کنید.'
                    : 'وضعیت VIP با موفقیت باطل شد؛ اعتبار قابل‌بازگشتی باقی نمانده بود. اکنون می‌توانید اشتراک جدیدی تهیه کنید.';
                showFeedback(pageFeedback, successMessage, false);

                const walletBalanceAfter = Number(result.data.wallet_balance_toman);
                if (Number.isFinite(walletBalanceAfter) && walletBalanceAfter >= 0) {
                    const walletBalanceLabel = new Intl.NumberFormat('fa-IR', {
                        maximumFractionDigits: 8
                    }).format(walletBalanceAfter) + ' تومان';
                    document.querySelectorAll('[data-fc-wallet-balance]').forEach((balanceNode) => {
                        balanceNode.textContent = walletBalanceLabel;
                    });
                }
                closeDialog();
            } catch (error) {
                const code = error && typeof error.message === 'string' ? error.message : 'vip_revoke_unavailable';
                let message = code === 'invalid_response'
                    ? 'پاسخ سرور معتبر نیست؛ وضعیت VIP را دوباره بررسی کنید.'
                    : (messages[code] || messages.vip_revoke_unavailable);

                if (code === 'vip_revoke_cooldown' && Number(error.retryAfterSeconds) > 0) {
                    const retryAfter = Math.floor(Number(error.retryAfterSeconds));
                    message += ' زمان باقی‌مانده: ' + formatCooldown(retryAfter) + '.';
                    if (Number(error.availableAt) > 0) {
                        openButton.dataset.vipRevokeAvailableAt = String(Math.floor(Number(error.availableAt)));
                        if (cooldownLabel) {
                            if (cooldownTimer === null) {
                                cooldownTimer = window.setInterval(function () {
                                    if (!document.contains(statusCard)) {
                                        window.clearInterval(cooldownTimer);
                                        cooldownTimer = null;
                                        return;
                                    }
                                    updateRevokeCooldown();
                                }, 1000);
                            }
                            updateRevokeCooldown();
                        } else {
                            openButton.disabled = true;
                        }
                    }
                }

                showFeedback(modalFeedback, message, true);
                setBusy(false);
            }
        });
    }

    window.addEventListener('content-loaded', function (event) {
        if (event.detail && event.detail.page === 'orders') {
            initOrdersPage();
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initOrdersPage, { once: true });
    } else {
        initOrdersPage();
    }
})();

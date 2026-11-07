(function () {
    'use strict';

    function initSupport() {
        const page = document.querySelector('.support-page');
        if (!page) return;

        page.querySelectorAll('.support-faq-item').forEach(function (item) {
            const summary = item.querySelector('summary');
            if (!summary || summary.dataset.transitionBound === '1') return;
            summary.dataset.transitionBound = '1';
            summary.addEventListener('click', function (event) {
                event.preventDefault();
                if (item.open) {
                    item.classList.add('is-closing');
                    window.setTimeout(function () {
                        if (item.classList.contains('is-closing')) {
                            item.open = false;
                            item.classList.remove('is-closing');
                        }
                    }, 280);
                } else {
                    item.classList.remove('is-closing');
                    item.open = true;
                }
            });
        });

        const copyBtn = page.querySelector('#copyTsAddress');
        if (copyBtn && !copyBtn.dataset.bound) {
            copyBtn.dataset.bound = '1';
            copyBtn.addEventListener('click', async function () {
                const tsAddress = 'ts.fragcity.ir:9987';
                const original = copyBtn.innerHTML;
                try {
                    await navigator.clipboard.writeText(tsAddress);
                    copyBtn.innerHTML = '<i class="fas fa-check"></i> کپی شد!';
                    copyBtn.classList.add('copied');
                    window.setTimeout(function () {
                        copyBtn.innerHTML = original;
                        copyBtn.classList.remove('copied');
                    }, 1800);
                } catch (error) {
                    window.prompt('آدرس TeamSpeak را کپی کنید:', tsAddress);
                }
            });
        }

        const app = page.querySelector('#fragcitySupportApp');
        if (!app || app.dataset.bound === '1') return;
        app.dataset.bound = '1';

        const endpoint = app.dataset.endpoint || '/api/support.php';
        const csrf = app.dataset.csrf || '';
        const listElement = app.querySelector('#supportTicketList');
        const listCard = app.querySelector('#supportListCard');
        const createCard = app.querySelector('#supportCreateCard');
        const detailCard = app.querySelector('#supportTicketDetail');
        const createForm = app.querySelector('#supportCreateForm');
        const createButton = app.querySelector('#supportCreateButton');
        const refreshButton = app.querySelector('#supportRefreshButton');
        const backButton = app.querySelector('#supportBackButton');
        const detailSubject = app.querySelector('#supportDetailSubject');
        const detailStatus = app.querySelector('#supportDetailStatus');
        const detailMeta = app.querySelector('#supportDetailMeta');
        const conversation = app.querySelector('#supportConversation');
        const detailActions = app.querySelector('#supportDetailActions');
        const replyForm = app.querySelector('#supportReplyForm');
        const replyButton = app.querySelector('#supportReplyButton');
        const toast = app.querySelector('#supportToast');
        const captchaQuestion = app.querySelector('#supportCaptchaQuestion');
        const captchaAnswer = app.querySelector('#supportCaptchaAnswer');
        const captchaToken = app.querySelector('#supportCaptchaToken');
        const captchaRefreshButton = app.querySelector('#supportCaptchaRefreshButton');
        const extraFieldsContainer = app.querySelector('#supportExtraFields');
        const addExtraButton = app.querySelector('#supportAddExtraButton');

        if (!listElement || !createForm || !detailCard) return;

        let activeTicketId = 0;
        let requestSequence = 0;

        function setPanelVisibility(panel, visible) {
            if (!panel) return;
            const transitionId = (Number(panel.dataset.transitionId) || 0) + 1;
            panel.dataset.transitionId = String(transitionId);

            if (visible) {
                if (!panel.hidden && !panel.classList.contains('is-exiting')) return;
                panel.hidden = false;
                panel.classList.remove('is-exiting', 'is-shown');
                panel.classList.add('is-entering');
                panel.getBoundingClientRect();
                window.requestAnimationFrame(function () {
                    if (panel.dataset.transitionId !== String(transitionId)) return;
                    panel.classList.remove('is-entering');
                    panel.classList.add('is-shown');
                    window.setTimeout(function () {
                        if (panel.dataset.transitionId === String(transitionId)) panel.classList.remove('is-shown');
                    }, 360);
                });
                return;
            }

            if (panel.hidden || panel.classList.contains('is-exiting')) return;
            panel.classList.remove('is-entering', 'is-shown');
            panel.classList.add('is-exiting');
            window.setTimeout(function () {
                if (panel.dataset.transitionId !== String(transitionId)) return;
                panel.hidden = true;
                panel.classList.remove('is-exiting');
            }, 260);
        }

        async function loadCaptcha() {
            if (!captchaQuestion || !captchaToken) return;
            captchaQuestion.textContent = 'در حال دریافت کد امنیتی...';
            captchaToken.value = '';
            if (captchaAnswer) captchaAnswer.value = '';
            try {
                const data = await callApi('captcha_challenge');
                captchaQuestion.textContent = data.question || 'کد امنیتی دریافت نشد';
                captchaToken.value = data.token || '';
            } catch (error) {
                captchaQuestion.textContent = 'دریافت کد امنیتی ناموفق بود؛ دوباره تلاش کن.';
                showToast(errorMessage(error), 'error');
            }
        }

        function addExtraField() {
            if (!extraFieldsContainer || extraFieldsContainer.children.length >= 3) {
                showToast('حداکثر سه مورد تکمیلی می‌توانی اضافه کنی.', 'error');
                return;
            }
            const row = document.createElement('div');
            row.className = 'support-extra-field-row';

            const labelWrap = document.createElement('label');
            labelWrap.className = 'support-extra-field-input';
            labelWrap.textContent = 'عنوان';
            const labelInput = document.createElement('input');
            labelInput.type = 'text';
            labelInput.maxLength = 50;
            labelInput.placeholder = 'مثلاً شماره سفارش';
            labelInput.className = 'support-extra-label';
            labelWrap.appendChild(labelInput);

            const valueWrap = document.createElement('label');
            valueWrap.className = 'support-extra-field-input';
            valueWrap.textContent = 'مقدار';
            const valueInput = document.createElement('input');
            valueInput.type = 'text';
            valueInput.maxLength = 200;
            valueInput.placeholder = 'مقدار موردنظر';
            valueInput.className = 'support-extra-value';
            valueWrap.appendChild(valueInput);

            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'support-extra-remove';
            removeButton.setAttribute('aria-label', 'حذف مورد تکمیلی');
            removeButton.innerHTML = '<i class="fas fa-trash" aria-hidden="true"></i>';
            removeButton.addEventListener('click', function () {
                row.classList.add('is-removing');
                window.setTimeout(function () { row.remove(); }, 180);
            });

            row.append(labelWrap, valueWrap, removeButton);
            extraFieldsContainer.appendChild(row);
            labelInput.focus();
        }

        function showToast(message, kind) {
            if (!toast) return;
            toast.textContent = message;
            toast.className = 'toast-message ' + (kind === 'error' ? 'error' : 'success');
            toast.hidden = false;
            window.clearTimeout(showToast.timer);
            window.clearTimeout(showToast.hideTimer);
            showToast.timer = window.setTimeout(function () {
                toast.classList.add('is-hidden');
                showToast.hideTimer = window.setTimeout(function () {
                    toast.hidden = true;
                }, 240);
            }, 4200);
        }

        function formatDate(value) {
            if (!value) return '—';
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) return String(value);
            try {
                return new Intl.DateTimeFormat('fa-IR', {
                    dateStyle: 'medium',
                    timeStyle: 'short'
                }).format(date);
            } catch (error) {
                return date.toLocaleString();
            }
        }

        function statusLabel(status) {
            return ({
                open: 'باز',
                replied: 'پاسخ داده شده',
                closed: 'بسته'
            })[status] || 'نامشخص';
        }

        function statusClass(status) {
            if (status === 'closed') return 'closed';
            if (status === 'replied') return 'answered';
            return 'open';
        }

        function setBusy(button, busy, busyText) {
            if (!button) return;
            if (busy) {
                button.dataset.originalHtml = button.innerHTML;
                button.disabled = true;
                button.classList.add('is-loading');
                button.textContent = busyText || 'در حال انجام...';
            } else {
                button.disabled = false;
                button.classList.remove('is-loading');
                if (button.dataset.originalHtml) {
                    button.innerHTML = button.dataset.originalHtml;
                    delete button.dataset.originalHtml;
                }
            }
        }

        async function callApi(action, data) {
            const response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-FragCity-CSRF': csrf
                },
                body: JSON.stringify(Object.assign({ action: action }, data || {}))
            });

            let payload;
            try {
                payload = await response.json();
            } catch (error) {
                throw new Error('پاسخ سرور قابل خواندن نیست.');
            }

            if (!response.ok || payload.success !== true) {
                const error = new Error(payload.error || 'درخواست ناموفق بود.');
                error.payload = payload;
                error.status = response.status;
                throw error;
            }

            return payload.data || {};
        }

        function makeEmpty(text) {
            const node = document.createElement('p');
            node.className = 'text-muted support-empty';
            node.textContent = text;
            return node;
        }

        function renderTickets(tickets) {
            listElement.replaceChildren();

            if (!Array.isArray(tickets) || tickets.length === 0) {
                listElement.appendChild(makeEmpty('هنوز تیکتی ثبت نکرده‌اید.'));
                return;
            }

            tickets.forEach(function (ticket) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'ticket-row support-ticket-row';
                button.dataset.ticketId = String(ticket.ticket_id || '');

                const info = document.createElement('span');
                info.className = 'ticket-info';

                const title = document.createElement('strong');
                title.textContent = '#' + String(ticket.ticket_id || '') + ' – ' + (ticket.subject || 'بدون موضوع');

                const date = document.createElement('span');
                date.className = 'ticket-date';
                date.textContent = formatDate(ticket.updated_at || ticket.created_at);

                const status = document.createElement('span');
                status.className = 'ticket-status ' + statusClass(ticket.status);
                status.textContent = statusLabel(ticket.status);

                info.append(title, date);
                button.append(info, status);
                button.addEventListener('click', function () {
                    openTicket(Number(ticket.ticket_id), true);
                });
                listElement.appendChild(button);
            });
        }

        async function loadTickets() {
            listElement.replaceChildren(makeEmpty('در حال دریافت تیکت‌ها...'));
            try {
                const data = await callApi('list_tickets', { page: 1 });
                renderTickets(data.tickets || []);
            } catch (error) {
                listElement.replaceChildren(makeEmpty(errorMessage(error)));
            }
        }

        function errorMessage(error) {
            const errors = {
                authentication_required: 'برای مشاهده تیکت‌ها دوباره وارد حساب خود شوید.',
                csrf_failed: 'نشست شما منقضی شده است. صفحه را تازه‌سازی کنید.',
                captcha_invalid: 'کد امنیتی اشتباه یا منقضی شده است؛ کد تازه‌ای دریافت کن و دوباره تلاش کن.',
                fragcity_account_not_linked: 'حساب شما هنوز به Paymenter متصل نشده است.',
                linked_paymenter_user_missing: 'حساب پشتیبانی شما در دسترس نیست.',
                paymenter_support_identity_not_synced: 'اطلاعات حساب شما هنوز با Paymenter همگام نشده است. لطفاً دوباره وارد حساب FragCity شوید یا با پشتیبانی تماس بگیرید.',
                paymenter_support_identity_mismatch: 'ایمیل یا شماره تلفن حساب FragCity با اطلاعات حساب Paymenter یکسان نیست. برای جلوگیری از نمایش تیکت اشتباه، دسترسی موقتاً متوقف شده است.',
                fragcity_account_unavailable: 'دریافت اطلاعات حساب شما موقتاً ممکن نیست. کمی بعد دوباره تلاش کنید.',
                paymenter_api_permission_denied: 'دسترسی API Paymenter برای خواندن تیکت یا پیام‌ها کامل نیست؛ مجوزهای View Tickets و View Ticket Messages باید فعال باشند.',
                paymenter_support_invalid_api_query: 'تنظیمات درخواست نمایش تیکت با API فعلی Paymenter سازگار نیست؛ لطفاً تنظیمات اتصال پشتیبانی را بررسی کنید.',
                paymenter_support_request_failed: 'Paymenter درخواست نمایش تیکت را با خطا پاسخ داد؛ لطفاً چند دقیقه بعد دوباره تلاش کنید.',
                paymenter_support_ticket_closed: 'این تیکت بسته شده است. ابتدا آن را دوباره باز کنید.',
                paymenter_support_ticket_not_found: 'تیکت پیدا نشد یا متعلق به حساب شما نیست.',
                paymenter_support_ticket_created_message_failed: 'تیکت ساخته شد اما پیام اول ثبت نشد. تیکت را باز کنید و پیام را دوباره ارسال کنید.',
                ticket_created_message_failed: 'تیکت ساخته شد اما پیام اول ثبت نشد. تیکت را باز کنید و پیام را دوباره ارسال کنید.',
                paymenter_support_unavailable: 'ارتباط با سامانه پشتیبانی موقتاً برقرار نشد.'
            };
            return errors[error.message] || 'در انجام درخواست مشکلی پیش آمد. دوباره تلاش کنید.';
        }

        function renderConversation(ticket) {
            conversation.replaceChildren();

            const messages = Array.isArray(ticket.messages) ? ticket.messages : [];
            if (messages.length === 0) {
                conversation.appendChild(makeEmpty('هنوز پیامی برای این تیکت ثبت نشده است.'));
                return;
            }

            messages.forEach(function (message) {
                const item = document.createElement('article');
                item.className = 'support-message';

                const header = document.createElement('div');
                header.className = 'support-message-meta';

                const sender = document.createElement('strong');
                sender.textContent = message.is_customer === true ? 'شما' :
                    (message.is_customer === false ? 'پشتیبانی' : 'پیام');

                const date = document.createElement('time');
                date.textContent = formatDate(message.created_at);
                header.append(sender, date);

                const body = document.createElement('p');
                body.className = 'support-message-body';
                body.textContent = message.message || '';

                item.append(header, body);
                conversation.appendChild(item);
            });
        }

        function renderDetailActions(ticket) {
            detailActions.replaceChildren();

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'support-state-btn';

            if (ticket.status === 'closed') {
                button.classList.add('support-reopen-btn');
                button.innerHTML = '<i class="fas fa-folder-open"></i> بازکردن مجدد تیکت';
                button.addEventListener('click', function () {
                    changeTicketStatus('reopen_ticket', ticket.ticket_id);
                });
                if (replyForm) setPanelVisibility(replyForm, false);
            } else {
                button.classList.add('support-close-btn');
                button.innerHTML = '<i class="fas fa-lock"></i> بستن تیکت';
                button.addEventListener('click', function () {
                    changeTicketStatus('close_ticket', ticket.ticket_id);
                });
                if (replyForm) setPanelVisibility(replyForm, true);
            }

            detailActions.appendChild(button);
        }

        async function openTicket(ticketId, pushHistory) {
            if (!Number.isInteger(ticketId) || ticketId < 1) return;

            const seq = ++requestSequence;
            activeTicketId = ticketId;
            setPanelVisibility(listCard, false);
            setPanelVisibility(createCard, false);
            setPanelVisibility(detailCard, true);
            conversation.replaceChildren(makeEmpty('در حال دریافت مکالمه...'));
            detailSubject.textContent = 'تیکت #' + ticketId;
            detailStatus.textContent = '...';
            detailStatus.className = 'ticket-status';
            detailMeta.textContent = '';

            if (pushHistory) {
                const url = new URL(window.location.href);
                url.searchParams.set('tab', 'tickets');
                url.searchParams.set('ticket_id', String(ticketId));
                window.history.pushState({ ticketId: ticketId }, '', url.pathname + url.search + url.hash);
            }

            try {
                const data = await callApi('show_ticket', { ticket_id: ticketId });
                if (seq !== requestSequence || activeTicketId !== ticketId) return;

                const ticket = data.ticket || {};
                detailSubject.textContent = '#' + ticket.ticket_id + ' – ' + (ticket.subject || 'بدون موضوع');
                detailStatus.textContent = statusLabel(ticket.status);
                detailStatus.className = 'ticket-status ' + statusClass(ticket.status);
                detailMeta.textContent = 'ایجاد: ' + formatDate(ticket.created_at) +
                    (ticket.updated_at ? ' | آخرین تغییر: ' + formatDate(ticket.updated_at) : '');
                renderConversation(ticket);
                renderDetailActions(ticket);
            } catch (error) {
                conversation.replaceChildren(makeEmpty(errorMessage(error)));
                showToast(errorMessage(error), 'error');
            }
        }

        async function changeTicketStatus(action, ticketId) {
            try {
                await callApi(action, { ticket_id: ticketId });
                showToast(action === 'close_ticket' ? 'تیکت بسته شد.' : 'تیکت دوباره باز شد.', 'success');
                await loadTickets();
                await openTicket(ticketId, false);
            } catch (error) {
                showToast(errorMessage(error), 'error');
            }
        }

        function showTicketList(updateHistory) {
            activeTicketId = 0;
            requestSequence++;
            setPanelVisibility(detailCard, false);
            setPanelVisibility(listCard, true);
            setPanelVisibility(createCard, true);

            if (updateHistory) {
                const url = new URL(window.location.href);
                url.searchParams.set('tab', 'tickets');
                url.searchParams.delete('ticket_id');
                window.history.pushState({ ticketId: 0 }, '', url.pathname + url.search + url.hash);
            }
        }

        createForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            const valueOf = function (name) {
                const field = createForm.querySelector('[name="' + name + '"]');
                return field ? String(field.value || '').trim() : '';
            };
            const subject = valueOf('subject');
            const message = valueOf('message');
            const captcha = valueOf('captcha_answer');
            const token = valueOf('captcha_token');
            const priorityField = createForm.querySelector('[name="priority"]:checked');
            const extraFields = extraFieldsContainer
                ? Array.from(extraFieldsContainer.querySelectorAll('.support-extra-field-row')).map(function (row) {
                    const label = row.querySelector('.support-extra-label');
                    const value = row.querySelector('.support-extra-value');
                    return { label: label ? label.value.trim() : '', value: value ? value.value.trim() : '' };
                })
                : [];

            if (!subject || !message || !captcha || !token) {
                showToast('موضوع، توضیحات و کد امنیتی را کامل کن.', 'error');
                return;
            }
            if (extraFields.some(function (item) { return !item.label || !item.value; })) {
                showToast('عنوان و مقدار تمام موارد تکمیلی را وارد کن.', 'error');
                return;
            }

            setBusy(createButton, true, 'در حال ثبت...');
            try {
                const data = await callApi('create_ticket', {
                    subject: subject,
                    message: message,
                    department: valueOf('department'),
                    priority: priorityField ? priorityField.value : 'medium',
                    service: valueOf('service'),
                    domain: valueOf('domain'),
                    extra_fields: extraFields,
                    captcha_answer: captcha,
                    captcha_token: token
                });
                createForm.reset();
                if (extraFieldsContainer) extraFieldsContainer.replaceChildren();
                await loadCaptcha();
                showToast('درخواست شما با موفقیت ثبت شد.', 'success');
                await loadTickets();
                await openTicket(Number(data.ticket_id), true);
            } catch (error) {
                const ticketId = Number(error.payload && error.payload.data && error.payload.data.ticket_id);
                if (error.message === 'ticket_created_message_failed' && ticketId > 0) {
                    showToast(errorMessage(error), 'error');
                    await loadTickets();
                    await openTicket(ticketId, true);
                } else {
                    showToast(errorMessage(error), 'error');
                    // CAPTCHA is consumed before API validation; issue a fresh one
                    // after every failed create attempt, not only an incorrect answer.
                    await loadCaptcha();
                }
            } finally {
                setBusy(createButton, false);
            }
        });

        if (replyForm) {
            replyForm.addEventListener('submit', async function (event) {
                event.preventDefault();
                const input = replyForm.querySelector('[name="message"]');
                const message = input ? input.value.trim() : '';
                if (!activeTicketId || !message) {
                    showToast('متن پاسخ را وارد کنید.', 'error');
                    return;
                }

                setBusy(replyButton, true, 'در حال ارسال...');
                try {
                    await callApi('reply_ticket', {
                        ticket_id: activeTicketId,
                        message: message
                    });
                    replyForm.reset();
                    showToast('پاسخ شما ثبت شد.', 'success');
                    await loadTickets();
                    await openTicket(activeTicketId, false);
                } catch (error) {
                    showToast(errorMessage(error), 'error');
                } finally {
                    setBusy(replyButton, false);
                }
            });
        }

        if (refreshButton) refreshButton.addEventListener('click', loadTickets);
        if (addExtraButton) addExtraButton.addEventListener('click', addExtraField);
        if (captchaRefreshButton) captchaRefreshButton.addEventListener('click', loadCaptcha);
        if (backButton) {
            backButton.addEventListener('click', function () {
                showTicketList(true);
            });
        }

        window.addEventListener('popstate', function () {
            const url = new URL(window.location.href);
            const ticketId = Number(url.searchParams.get('ticket_id') || 0);
            if (ticketId > 0) {
                openTicket(ticketId, false);
            } else {
                showTicketList(false);
            }
        });

        loadTickets();
        loadCaptcha();
        const initialTicketId = Number(app.dataset.initialTicketId || 0);
        if (initialTicketId > 0) {
            openTicket(initialTicketId, false);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSupport, { once: true });
    } else {
        initSupport();
    }
})();

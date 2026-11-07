(function () {
    'use strict';

    function initSupport() {
        const page = document.querySelector('.support-page');
        if (!page) return;

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

        if (!listElement || !createForm || !detailCard) return;

        let activeTicketId = 0;
        let requestSequence = 0;

        function showToast(message, kind) {
            if (!toast) return;
            toast.textContent = message;
            toast.className = 'toast-message ' + (kind === 'error' ? 'error' : 'success');
            toast.hidden = false;
            window.clearTimeout(showToast.timer);
            showToast.timer = window.setTimeout(function () {
                toast.classList.add('is-hidden');
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
                fragcity_account_not_linked: 'حساب شما هنوز به Paymenter متصل نشده است.',
                linked_paymenter_user_missing: 'حساب پشتیبانی شما در دسترس نیست.',
                paymenter_api_permission_denied: 'دسترسی API Paymenter کامل نیست؛ لطفاً با پشتیبانی تماس بگیرید.',
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
                if (replyForm) replyForm.hidden = true;
            } else {
                button.classList.add('support-close-btn');
                button.innerHTML = '<i class="fas fa-lock"></i> بستن تیکت';
                button.addEventListener('click', function () {
                    changeTicketStatus('close_ticket', ticket.ticket_id);
                });
                if (replyForm) replyForm.hidden = false;
            }

            detailActions.appendChild(button);
        }

        async function openTicket(ticketId, pushHistory) {
            if (!Number.isInteger(ticketId) || ticketId < 1) return;

            const seq = ++requestSequence;
            activeTicketId = ticketId;
            if (listCard) listCard.hidden = true;
            if (createCard) createCard.hidden = true;
            detailCard.hidden = false;
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
            detailCard.hidden = true;
            if (listCard) listCard.hidden = false;
            if (createCard) createCard.hidden = false;

            if (updateHistory) {
                const url = new URL(window.location.href);
                url.searchParams.set('tab', 'tickets');
                url.searchParams.delete('ticket_id');
                window.history.pushState({ ticketId: 0 }, '', url.pathname + url.search + url.hash);
            }
        }

        createForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            const subjectInput = createForm.querySelector('[name="subject"]');
            const messageInput = createForm.querySelector('[name="message"]');
            const subject = subjectInput ? subjectInput.value.trim() : '';
            const message = messageInput ? messageInput.value.trim() : '';

            if (!subject || !message) {
                showToast('موضوع و متن تیکت را وارد کنید.', 'error');
                return;
            }

            setBusy(createButton, true, 'در حال ثبت...');
            try {
                const data = await callApi('create_ticket', {
                    subject: subject,
                    message: message
                });
                createForm.reset();
                showToast('تیکت شما با موفقیت ثبت شد.', 'success');
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

        if (refreshButton) {
            refreshButton.addEventListener('click', loadTickets);
        }
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

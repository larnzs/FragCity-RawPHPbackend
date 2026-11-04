(function () {
    'use strict';

    if (window.FragCityMyServersInitialized) return;
    window.FragCityMyServersInitialized = true;

    const errorMessages = {
        authentication_required: 'نشست شما منقضی شده است؛ دوباره وارد FragCity شوید.',
        csrf_failed: 'درخواست امنیتی منقضی شده است؛ صفحه را تازه‌سازی کنید.',
        current_password_incorrect: 'رمز فعلی FragCity صحیح نیست.',
        password_confirmation_mismatch: 'رمز جدید و تکرار آن مطابقت ندارند.',
        panel_password_policy: 'رمز پنل باید حداقل ۱۲ کاراکتر، شامل حروف و عدد و بدون فاصله باشد.',
        panel_password_must_be_different: 'رمز پنل باید با رمز FragCity متفاوت باشد.',
        panel_password_rate_limited: 'تعداد تلاش‌های ناموفق زیاد بوده است. ۱۵ دقیقه بعد دوباره تلاش کنید.',
        required_fields_missing: 'تمام فیلدهای لازم را تکمیل کنید.',
        paymenter_account_not_linked: 'اتصال حساب سرور کامل نیست؛ ابتدا همگام‌سازی حساب را تکمیل کنید.',
        service_not_found: 'این سرویس برای حساب شما پیدا نشد.',
        panel_password_update_failed: 'تغییر رمز پنل انجام نشد. تنظیمات اتصال پنل را بررسی کنید.',
        panel_username_invalid: 'نام کاربری باید ۳ تا ۵۰ کاراکتر و شامل حروف کوچک انگلیسی، عدد، خط تیره یا زیرخط باشد.',
        panel_username_already_in_use: 'این نام کاربری قبلاً در Pterodactyl استفاده شده است.',
        panel_username_rate_limited: 'تعداد تلاش‌های تغییر نام کاربری زیاد بوده است. ۱۵ دقیقه بعد دوباره تلاش کنید.',
        panel_username_update_failed: 'ذخیره نام کاربری پنل انجام نشد. تنظیمات اتصال پنل را بررسی کنید.',
        paymenter_unreachable: 'ارتباط با سرویس مدیریت سرورها برقرار نشد.',
        paymenter_invalid_response: 'پاسخ سرویس مدیریت سرورها معتبر نیست.',
        client_api_key_invalid: 'کلید Client API معتبر نیست. باید از Account → API Credentials ساخته شده باشد، نه Application API.',
        client_api_key_must_belong_to_server_owner: 'این کلید متعلق به مالک همین سرور نیست. با حساب اصلی مالک وارد پنل شوید و کلید جدید بسازید.',
        client_api_server_verification_failed: 'مالکیت سرور با این کلید تأیید نشد.',
        client_api_connection_failed: 'اتصال Client API برقرار نشد. وضعیت کلید و حساب پنل را بررسی کنید.',
        client_api_connect_rate_limited: 'تعداد تلاش‌های اتصال زیاد بوده است. ۱۵ دقیقه بعد دوباره تلاش کنید.',
        client_api_not_connected: 'ابتدا Client API حساب Pterodactyl را از بخش دسترسی پنل متصل کنید.',
        client_api_storage_not_ready: 'ذخیره‌سازی Client API هنوز آماده نشده است. ابتدا افزونه FragCity API در Paymenter را به‌روزرسانی کنید.',
        client_api_permission_denied: 'حساب Pterodactyl برای این عملیات مجوز لازم را ندارد.',
        client_api_file_too_large_to_edit: 'این فایل برای ویرایش داخل مرورگر بیش از حد بزرگ است؛ آن را دانلود و با ابزار مناسب ویرایش کنید.',
        client_api_file_not_editable: 'این نوع فایل در ویرایشگر مرورگر پشتیبانی نمی‌شود. برای تغییر آن از فایل خام یا ابزار مناسب استفاده کنید.',
        client_api_invalid_file_content: 'محتوای فایل معتبر نیست یا از محدودیت اندازه بیشتر است.',
        client_api_invalid_file_name: 'نام فایل یا پوشه معتبر نیست.',
        client_api_invalid_file_path: 'مسیر فایل معتبر نیست.',
        client_api_invalid_file_list: 'فهرست فایل‌های درخواستی معتبر نیست.',
        client_api_invalid_file_action: 'عملیات فایل پشتیبانی نمی‌شود.',
        customer_console_unavailable: 'اتصال کنسول برقرار نشد. وضعیت سرور و ارتباط Wings را بررسی کنید.',
        customer_file_action_failed: 'عملیات فایل انجام نشد. مجوزهای فایل و ارتباط Wings را بررسی کنید.'
    };

    function showMessage(form, message, type) {
        const target = form.querySelector('[data-password-message]');
        if (!target) return;

        target.textContent = message;
        target.classList.remove('is-error', 'is-success');
        if (type) target.classList.add(type === 'success' ? 'is-success' : 'is-error');
    }

    function showUsernameMessage(form, message, type) {
        const target = form.querySelector('[data-username-message]');
        if (!target) return;

        target.textContent = message;
        target.classList.remove('is-error', 'is-success');
        if (type) target.classList.add(type === 'success' ? 'is-success' : 'is-error');
    }

    async function submitUsernameForm(form) {
        const root = document.getElementById('fcMyServers');
        if (!root) return;

        const serviceId = Number(form.querySelector('[name="service_id"]')?.value || 0);
        const usernameInput = form.querySelector('[name="username"]');
        const currentPasswordInput = form.querySelector('[name="current_password"]');
        const username = (usernameInput?.value || '').trim().toLowerCase();
        const currentPassword = currentPasswordInput?.value || '';
        const submit = form.querySelector('button[type="submit"]');

        if (!serviceId || !username || !currentPassword) {
            showUsernameMessage(form, 'تمام فیلدها را تکمیل کنید.', 'error');
            return;
        }

        if (!/^[a-z0-9][a-z0-9_-]{1,48}[a-z0-9]$/.test(username)) {
            showUsernameMessage(form, errorMessages.panel_username_invalid, 'error');
            return;
        }

        const originalLabel = submit?.querySelector('span')?.textContent || 'ذخیره نام کاربری';
        if (submit) {
            submit.disabled = true;
            const label = submit.querySelector('span');
            if (label) label.textContent = 'در حال ذخیره...';
        }
        showUsernameMessage(form, 'در حال بررسی و به‌روزرسانی نام کاربری پنل...', '');

        try {
            const response = await fetch('/api/myservers.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-FragCity-CSRF': root.dataset.csrf || ''
                },
                body: JSON.stringify({
                    action: 'set_panel_username',
                    service_id: serviceId,
                    current_password: currentPassword,
                    username: username
                })
            });

            let result = {};
            try {
                result = await response.json();
            } catch (error) {
                throw new Error('paymenter_invalid_response');
            }

            if (!response.ok || result.success !== true || result.data?.username_updated !== true) {
                const errorCode = typeof result.error === 'string'
                    ? result.error
                    : 'panel_username_update_failed';
                throw new Error(errorCode);
            }

            const savedUsername = typeof result.data.username === 'string'
                ? result.data.username
                : username;

            if (usernameInput) usernameInput.value = savedUsername;
            if (currentPasswordInput) currentPasswordInput.value = '';

            const usernameDisplay = document.querySelector('[data-panel-username-value]');
            if (usernameDisplay) usernameDisplay.textContent = savedUsername;

            const copyButton = document.querySelector('[data-panel-username-copy]');
            if (copyButton) copyButton.setAttribute('data-copy-value', savedUsername);

            showUsernameMessage(form, 'نام کاربری پنل با موفقیت ذخیره شد.', 'success');
        } catch (error) {
            const code = error instanceof Error ? error.message : 'panel_username_update_failed';
            showUsernameMessage(
                form,
                errorMessages[code] || errorMessages.panel_username_update_failed,
                'error'
            );
        } finally {
            if (submit) {
                submit.disabled = false;
                const label = submit.querySelector('span');
                if (label && label.textContent === 'در حال ذخیره...') {
                    label.textContent = originalLabel;
                }
            }
        }
    }

    async function submitPasswordForm(form) {
        const root = document.getElementById('fcMyServers');
        if (!root) return;

        const serviceId = Number(form.querySelector('[name="service_id"]')?.value || 0);
        const currentPassword = form.querySelector('[name="current_password"]')?.value || '';
        const password = form.querySelector('[name="password"]')?.value || '';
        const passwordConfirm = form.querySelector('[name="password_confirm"]')?.value || '';
        const submit = form.querySelector('button[type="submit"]');

        if (!serviceId || !currentPassword || !password || !passwordConfirm) {
            showMessage(form, 'تمام فیلدها را تکمیل کنید.', 'error');
            return;
        }

        if (password !== passwordConfirm) {
            showMessage(form, errorMessages.password_confirmation_mismatch, 'error');
            return;
        }

        if (password === currentPassword) {
            showMessage(form, errorMessages.panel_password_must_be_different, 'error');
            return;
        }

        if (password.length < 12 || password.length > 128 || /\s/u.test(password) || !/\p{L}/u.test(password) || !/[0-9]/.test(password)) {
            showMessage(form, errorMessages.panel_password_policy, 'error');
            return;
        }

        const originalLabel = submit?.querySelector('span')?.textContent || 'ذخیره رمز پنل';
        if (submit) {
            submit.disabled = true;
            const label = submit.querySelector('span');
            if (label) label.textContent = 'در حال ذخیره...';
        }
        showMessage(form, 'در حال بررسی اطلاعات و تغییر رمز پنل...', '');

        try {
            const response = await fetch('/api/myservers.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-FragCity-CSRF': root.dataset.csrf || ''
                },
                body: JSON.stringify({
                    action: 'set_panel_password',
                    service_id: serviceId,
                    current_password: currentPassword,
                    password: password,
                    password_confirm: passwordConfirm
                })
            });

            let result = {};
            try {
                result = await response.json();
            } catch (error) {
                throw new Error('paymenter_invalid_response');
            }

            if (!response.ok || result.success !== true || result.data?.password_updated !== true) {
                const errorCode = typeof result.error === 'string' ? result.error : 'panel_password_update_failed';
                throw new Error(errorCode);
            }

            form.reset();
            showMessage(form, 'رمز اختصاصی پنل با موفقیت تنظیم شد. رمز جدید را در جای امن نگه دارید.', 'success');

            const dashboard = document.getElementById('fcMyServers');
            const status = dashboard?.querySelector('[data-password-status]');
            if (status) {
                status.classList.remove('is-pending');
                status.classList.add('is-set');
                status.innerHTML = '<i class="fa-solid fa-circle-check" aria-hidden="true"></i> رمز تنظیم شده';
            }

            const heading = form.closest('.fc-password-card')?.querySelector('.fc-dashboard-card__heading h3');
            if (heading) heading.textContent = 'تغییر رمز عبور پنل';

            const label = submit?.querySelector('span');
            if (label) label.textContent = 'ذخیره رمز جدید';
        } catch (error) {
            const code = error instanceof Error ? error.message : 'panel_password_update_failed';
            showMessage(form, errorMessages[code] || errorMessages.panel_password_update_failed, 'error');
        } finally {
            if (submit) {
                submit.disabled = false;
                const label = submit.querySelector('span');
                if (label && label.textContent === 'در حال ذخیره...') {
                    label.textContent = originalLabel;
                }
            }
        }
    }

    async function postMyServers(payload) {
        const root = document.getElementById('fcMyServers');
        if (!root) throw new Error('authentication_required');

        const response = await fetch('/api/myservers.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-FragCity-CSRF': root.dataset.csrf || ''
            },
            body: JSON.stringify(payload)
        });

        let result = {};
        try {
            result = await response.json();
        } catch (error) {
            throw new Error('paymenter_invalid_response');
        }

        if (!response.ok || result.success !== true) {
            throw new Error(typeof result.error === 'string' ? result.error : 'paymenter_invalid_response');
        }

        return result.data || {};
    }

    function setClientApiMessage(form, message, type) {
        const target = form.querySelector('[data-client-api-message]');
        if (!target) return;
        target.textContent = message;
        target.classList.remove('is-error', 'is-success');
        if (type) target.classList.add(type === 'success' ? 'is-success' : 'is-error');
    }

    async function submitClientApiForm(form) {
        const serviceId = Number(form.querySelector('[name="service_id"]')?.value || 0);
        const tokenInput = form.querySelector('[name="api_token"]');
        const passwordInput = form.querySelector('[name="current_password"]');
        const apiToken = tokenInput?.value || '';
        const currentPassword = passwordInput?.value || '';
        const submit = form.querySelector('button[type="submit"]');

        if (!serviceId || !apiToken || !currentPassword) {
            setClientApiMessage(form, 'کلید API، رمز فعلی FragCity و سرویس را بررسی کنید.', 'error');
            return;
        }

        if (submit) submit.disabled = true;
        setClientApiMessage(form, 'در حال اعتبارسنجی مالکیت سرور و ذخیره امن کلید...', '');

        try {
            await postMyServers({
                action: 'connect_client_api',
                service_id: serviceId,
                api_token: apiToken,
                current_password: currentPassword
            });
            form.reset();
            setClientApiMessage(form, 'حساب پنل با موفقیت متصل شد. در حال تازه‌سازی داشبورد...', 'success');
            window.location.reload();
        } catch (error) {
            const code = error instanceof Error ? error.message : 'client_api_connection_failed';
            setClientApiMessage(form, errorMessages[code] || errorMessages.client_api_connection_failed, 'error');
        } finally {
            if (tokenInput) tokenInput.value = '';
            if (passwordInput) passwordInput.value = '';
            if (submit) submit.disabled = false;
        }
    }

    async function disconnectClientApi(button) {
        if (!window.confirm('اتصال Client API از تمام سرورهای CS2 این حساب در FragCity قطع شود؟ برای باطل‌کردن خود کلید باید آن را از Pterodactyl هم حذف کنید.')) {
            return;
        }

        button.disabled = true;
        try {
            await postMyServers({ action: 'disconnect_client_api' });
            window.location.reload();
        } catch (error) {
            const code = error instanceof Error ? error.message : 'paymenter_unreachable';
            window.alert(errorMessages[code] || 'قطع اتصال Client API انجام نشد.');
            button.disabled = false;
        }
    }

    let activeConsoleRoot = null;
    let activeConsoleSocket = null;
    let activeConsoleAuthenticated = false;
    let intentionalConsoleClose = false;

    function consoleStatus(root, label, state) {
        const status = root?.querySelector('[data-console-status]');
        if (!status) return;
        status.textContent = label;
        status.dataset.state = state || 'idle';
    }

    function appendConsoleOutput(root, value) {
        const output = root?.querySelector('[data-console-output]');
        if (!output) return;

        const safeText = String(value ?? '')
            .replace(/\u001b\[[0-?]*[ -/]*[@-~]/g, '')
            .replace(/\u0000/g, '');
        const existing = output.textContent ? output.textContent.split('\n') : [];
        const added = safeText.split(/\r?\n/);
        const lines = existing.concat(added).filter((line, index) => !(index === 0 && line === ''));
        output.textContent = lines.slice(-500).join('\n');
        output.scrollTop = output.scrollHeight;
    }

    function formatConsoleBytes(bytes) {
        const value = Number(bytes || 0);
        if (!Number.isFinite(value) || value < 0) return '—';
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        let amount = value;
        let index = 0;
        while (amount >= 1024 && index < units.length - 1) {
            amount /= 1024;
            index += 1;
        }
        return amount.toFixed(index === 0 ? 0 : 1) + ' ' + units[index];
    }

    function updateConsoleStats(root, stats) {
        if (!root || !stats || typeof stats !== 'object') return;
        const memoryUsed = Number(stats.memory_bytes || 0);
        const memoryLimit = Number(stats.memory_limit_bytes || 0);
        const diskUsed = Number(stats.disk_bytes || 0);
        const cpu = Number(stats.cpu_absolute || 0);

        const cpuTarget = root.querySelector('[data-console-cpu]');
        const memoryTarget = root.querySelector('[data-console-memory]');
        const diskTarget = root.querySelector('[data-console-disk]');
        const stateTarget = root.querySelector('[data-console-state]');

        if (cpuTarget) cpuTarget.textContent = Number.isFinite(cpu) ? cpu.toFixed(1) + '%' : '—';
        if (memoryTarget) {
            memoryTarget.textContent = memoryLimit > 0
                ? formatConsoleBytes(memoryUsed) + ' / ' + formatConsoleBytes(memoryLimit)
                : formatConsoleBytes(memoryUsed);
        }
        if (diskTarget) diskTarget.textContent = formatConsoleBytes(diskUsed);
        if (stateTarget) stateTarget.textContent = String(stats.state || 'unknown');
    }

    function closeConsoleConnection(updateUi = true) {
        intentionalConsoleClose = true;
        activeConsoleAuthenticated = false;

        const socket = activeConsoleSocket;
        activeConsoleSocket = null;
        if (socket) {
            // Detach callbacks before closing so a deliberate close cannot be
            // mistaken for a network failure after the navigation has changed.
            socket.onopen = null;
            socket.onmessage = null;
            socket.onerror = null;
            socket.onclose = null;
            try {
                socket.close(1000, 'FragCity console closed');
            } catch (error) {
                // Ignore already-closed sockets.
            }
        }

        if (updateUi && activeConsoleRoot?.isConnected) {
            consoleStatus(activeConsoleRoot, 'Disconnected', 'idle');
            const button = activeConsoleRoot.querySelector('[data-console-connect]');
            if (button) {
                button.disabled = false;
                button.innerHTML = '<i class="fa-solid fa-plug"></i><span>اتصال</span>';
            }
            const send = activeConsoleRoot.querySelector('[data-console-send]');
            if (send) send.disabled = true;
        }
        intentionalConsoleClose = false;
    }

    async function refreshConsoleToken(root, socket) {
        const serviceId = Number(root?.dataset.serviceId || 0);
        if (!serviceId || !socket || socket.readyState !== WebSocket.OPEN) return;

        try {
            const data = await postMyServers({ action: 'console_connect', service_id: serviceId });
            if (socket.readyState === WebSocket.OPEN && typeof data.token === 'string') {
                socket.send(JSON.stringify({ event: 'auth', args: [data.token] }));
            }
        } catch (error) {
            appendConsoleOutput(root, '[FragCity] توکن کنسول تمدید نشد؛ اتصال را دوباره برقرار کنید.');
        }
    }

    async function connectConsole(root) {
        if (!root || !root.isConnected) return;
        closeConsoleConnection(false);
        activeConsoleRoot = root;
        intentionalConsoleClose = false;
        activeConsoleAuthenticated = false;

        const button = root.querySelector('[data-console-connect]');
        const send = root.querySelector('[data-console-send]');
        if (button) {
            button.disabled = true;
            button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><span>در حال اتصال...</span>';
        }
        if (send) send.disabled = true;
        consoleStatus(root, 'Connecting', 'connecting');
        appendConsoleOutput(root, '[FragCity] در حال دریافت دسترسی کوتاه‌عمر کنسول...');

        try {
            const serviceId = Number(root.dataset.serviceId || 0);
            const data = await postMyServers({ action: 'console_connect', service_id: serviceId });

            if (!data.socket || !data.token || !String(data.socket).toLowerCase().startsWith('wss://')) {
                throw new Error('customer_console_unavailable');
            }

            const socket = new WebSocket(data.socket);
            activeConsoleSocket = socket;

            socket.onopen = () => {
                if (socket !== activeConsoleSocket) return;
                socket.send(JSON.stringify({ event: 'auth', args: [data.token] }));
            };

            socket.onmessage = async (event) => {
                if (socket !== activeConsoleSocket || !root.isConnected) return;

                let message;
                try {
                    message = JSON.parse(event.data);
                } catch (error) {
                    return;
                }

                const args = Array.isArray(message.args) ? message.args : [];
                switch (message.event) {
                    case 'auth success': {
                        activeConsoleAuthenticated = true;
                        consoleStatus(root, 'Connected', 'connected');
                        if (button) {
                            button.disabled = false;
                            button.innerHTML = '<i class="fa-solid fa-plug-circle-xmark"></i><span>قطع اتصال</span>';
                        }
                        if (send) send.disabled = false;
                        socket.send(JSON.stringify({ event: 'send logs', args: [null] }));
                        socket.send(JSON.stringify({ event: 'send stats', args: [null] }));
                        appendConsoleOutput(root, '[FragCity] اتصال کنسول برقرار شد.');
                        break;
                    }
                    case 'auth error':
                        appendConsoleOutput(root, '[Pterodactyl] ' + String(args[0] || 'Authentication failed'));
                        consoleStatus(root, 'Authentication failed', 'error');
                        if (button) {
                            button.disabled = false;
                            button.innerHTML = '<i class="fa-solid fa-plug"></i><span>تلاش مجدد</span>';
                        }
                        if (send) send.disabled = true;
                        activeConsoleAuthenticated = false;
                        break;
                    case 'console output':
                        appendConsoleOutput(root, args[0] || '');
                        break;
                    case 'stats': {
                        let stats = args[0];
                        if (typeof stats === 'string') {
                            try { stats = JSON.parse(stats); } catch (error) { stats = null; }
                        }
                        updateConsoleStats(root, stats);
                        break;
                    }
                    case 'status':
                        consoleStatus(root, String(args[0] || 'Unknown'), 'connected');
                        break;
                    case 'token expiring':
                        await refreshConsoleToken(root, socket);
                        break;
                    case 'token expired':
                        appendConsoleOutput(root, '[FragCity] توکن قبلی منقضی شد؛ در حال اتصال مجدد...');
                        window.setTimeout(() => {
                            if (root.isConnected && activeConsoleRoot === root) connectConsole(root);
                        }, 300);
                        break;
                    default:
                        break;
                }
            };

            socket.onerror = () => {
                if (root.isConnected) consoleStatus(root, 'Connection error', 'error');
            };

            socket.onclose = () => {
                if (socket !== activeConsoleSocket) return;
                activeConsoleSocket = null;
                activeConsoleAuthenticated = false;
                if (root.isConnected) {
                    consoleStatus(root, intentionalConsoleClose ? 'Disconnected' : 'Connection closed', 'idle');
                    if (button) {
                        button.disabled = false;
                        button.innerHTML = '<i class="fa-solid fa-plug"></i><span>اتصال مجدد</span>';
                    }
                    if (send) send.disabled = true;
                }
            };
        } catch (error) {
            const code = error instanceof Error ? error.message : 'customer_console_unavailable';
            appendConsoleOutput(root, '[FragCity] ' + (errorMessages[code] || errorMessages.customer_console_unavailable));
            consoleStatus(root, 'Unavailable', 'error');
            if (button) {
                button.disabled = false;
                button.innerHTML = '<i class="fa-solid fa-plug"></i><span>تلاش مجدد</span>';
            }
        }
    }

    function submitConsoleCommand(form) {
        const root = form.closest('[data-service-id]');
        const input = form.querySelector('[name="command"]');
        const command = (input?.value || '').trim();

        if (!command || command.length > 2000) return;
        if (!activeConsoleSocket || activeConsoleSocket.readyState !== WebSocket.OPEN || !activeConsoleAuthenticated || activeConsoleRoot !== root) {
            appendConsoleOutput(root, '[FragCity] ابتدا اتصال کنسول را برقرار کنید.');
            return;
        }

        activeConsoleSocket.send(JSON.stringify({ event: 'send command', args: [command] }));
        if (input) input.value = '';
    }

    async function fileManagerRequest(root, fileAction, extra = {}) {
        const serviceId = Number(root?.dataset.serviceId || 0);
        if (!serviceId) throw new Error('service_not_found');

        return postMyServers({
            action: 'files',
            service_id: serviceId,
            file_action: fileAction,
            directory: root.dataset.currentDirectory || '/',
            ...extra
        });
    }

    function fileManagerMessage(root, message, error = false) {
        const target = root.querySelector('[data-file-message]');
        if (!target) return;
        target.textContent = message;
        target.classList.toggle('is-error', Boolean(error));
    }

    function joinFilePath(directory, name) {
        const base = directory === '/' ? '' : directory.replace(/\/+$/, '');
        return base + '/' + name;
    }

    function formatFileSize(bytes) {
        const value = Number(bytes || 0);
        if (!Number.isFinite(value) || value < 0) return '—';
        if (value < 1024) return value + ' B';
        if (value < 1024 * 1024) return (value / 1024).toFixed(1) + ' KB';
        if (value < 1024 * 1024 * 1024) return (value / (1024 * 1024)).toFixed(1) + ' MB';
        return (value / (1024 * 1024 * 1024)).toFixed(2) + ' GB';
    }

    function renderFileBreadcrumbs(root, directory) {
        const target = root.querySelector('[data-file-breadcrumbs]');
        if (!target) return;
        target.replaceChildren();

        const addCrumb = (label, path) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = label;
            button.dataset.fileGoPath = path;
            target.appendChild(button);
        };

        addCrumb('container', '/');
        const parts = directory.split('/').filter(Boolean);
        let current = '';
        parts.forEach((part) => {
            current += '/' + part;
            addCrumb(part, current);
        });
    }

    async function loadFileDirectory(root, directory = '/') {
        if (!root || !root.isConnected) return;
        root.dataset.currentDirectory = directory || '/';
        renderFileBreadcrumbs(root, root.dataset.currentDirectory);

        const directoryLabel = root.querySelector('[data-file-directory]');
        if (directoryLabel) directoryLabel.textContent = root.dataset.currentDirectory;

        fileManagerMessage(root, 'در حال دریافت فهرست فایل‌ها...');

        try {
            const data = await fileManagerRequest(root, 'list', { directory: root.dataset.currentDirectory });
            const tbody = root.querySelector('[data-file-list]');
            if (!tbody) return;
            tbody.replaceChildren();

            const allItems = Array.isArray(data.items) ? data.items : [];
            const items = allItems.slice(0, 250);
            items.sort((a, b) => {
                if (Boolean(a.is_file) !== Boolean(b.is_file)) return a.is_file ? 1 : -1;
                return String(a.name || '').localeCompare(String(b.name || ''));
            });

            for (const item of items) {
                const row = document.createElement('tr');
                const nameCell = document.createElement('td');
                const openButton = document.createElement('button');
                openButton.type = 'button';
                openButton.className = 'fc-file-manager__name';
                openButton.dataset.fileOpen = item.name;
                openButton.dataset.fileIsFile = item.is_file ? '1' : '0';
                openButton.dataset.fileEditable = item.is_editable && !item.is_symlink ? '1' : '0';
                openButton.textContent = item.name;
                const icon = document.createElement('i');
                icon.className = item.is_file ? 'fa-regular fa-file' : 'fa-solid fa-folder';
                icon.setAttribute('aria-hidden', 'true');
                openButton.prepend(icon);
                nameCell.appendChild(openButton);

                const typeCell = document.createElement('td');
                typeCell.textContent = item.is_file ? (item.mimetype || 'File') : 'Folder';

                const sizeCell = document.createElement('td');
                sizeCell.textContent = item.is_file ? formatFileSize(item.size) : '—';

                const modifiedCell = document.createElement('td');
                modifiedCell.textContent = item.modified_at || '—';

                const actionsCell = document.createElement('td');
                actionsCell.className = 'fc-file-manager__row-actions';

                if (item.is_file) {
                    const download = document.createElement('button');
                    download.type = 'button';
                    download.title = 'Download';
                    download.dataset.fileDownload = item.name;
                    download.innerHTML = '<i class="fa-solid fa-download" aria-hidden="true"></i>';
                    actionsCell.appendChild(download);

                    if (item.is_editable && !item.is_symlink) {
                        const edit = document.createElement('button');
                        edit.type = 'button';
                        edit.title = 'Edit';
                        edit.dataset.fileEdit = item.name;
                        edit.innerHTML = '<i class="fa-solid fa-pen" aria-hidden="true"></i>';
                        actionsCell.appendChild(edit);
                    }
                }

                const rename = document.createElement('button');
                rename.type = 'button';
                rename.title = 'Rename';
                rename.dataset.fileRename = item.name;
                rename.innerHTML = '<i class="fa-solid fa-i-cursor" aria-hidden="true"></i>';
                actionsCell.appendChild(rename);

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.title = 'Delete';
                remove.dataset.fileDelete = item.name;
                remove.innerHTML = '<i class="fa-solid fa-trash" aria-hidden="true"></i>';
                actionsCell.appendChild(remove);

                row.append(nameCell, typeCell, sizeCell, modifiedCell, actionsCell);
                tbody.appendChild(row);
            }

            const listingSummary = items.length < allItems.length
                ? items.length + ' مورد از ' + allItems.length + ' مورد در ' + root.dataset.currentDirectory + ' نمایش داده شد؛ برای محدودیت رابط، فقط ۲۵۰ مورد اول نشان داده می‌شود.'
                : items.length + ' مورد در ' + root.dataset.currentDirectory + ' نمایش داده شد.';
            fileManagerMessage(root, listingSummary);
        } catch (error) {
            const code = error instanceof Error ? error.message : 'customer_file_action_failed';
            fileManagerMessage(root, errorMessages[code] || errorMessages.customer_file_action_failed, true);
        }
    }

    async function openFileForEdit(root, name) {
        const path = joinFilePath(root.dataset.currentDirectory || '/', name);
        const editor = root.querySelector('[data-file-editor]');
        const textarea = root.querySelector('[data-file-editor-content]');
        const title = root.querySelector('[data-file-editor-name]');
        const message = root.querySelector('[data-file-editor-message]');
        if (!editor || !textarea) return;

        editor.hidden = false;
        textarea.value = '';
        if (title) title.textContent = path;
        if (message) message.textContent = 'در حال خواندن فایل...';

        try {
            const data = await fileManagerRequest(root, 'read', { file: path });
            textarea.value = typeof data.content === 'string' ? data.content : '';
            root.dataset.editingFile = path;
            if (message) message.textContent = formatFileSize(new Blob([textarea.value]).size) + ' · Ready';
            editor.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } catch (error) {
            const code = error instanceof Error ? error.message : 'customer_file_action_failed';
            if (message) message.textContent = errorMessages[code] || errorMessages.customer_file_action_failed;
        }
    }

    async function downloadServerFile(root, name) {
        const path = joinFilePath(root.dataset.currentDirectory || '/', name);
        const newWindow = window.open('about:blank', '_blank');

        try {
            const data = await fileManagerRequest(root, 'download', { file: path });
            if (!data.url || !String(data.url).startsWith('https://')) {
                throw new Error('customer_file_action_failed');
            }
            if (newWindow) {
                newWindow.location = data.url;
            } else {
                window.location.assign(data.url);
            }
        } catch (error) {
            if (newWindow) newWindow.close();
            const code = error instanceof Error ? error.message : 'customer_file_action_failed';
            fileManagerMessage(root, errorMessages[code] || errorMessages.customer_file_action_failed, true);
        }
    }

    async function uploadServerFiles(root, fileList) {
        const files = Array.from(fileList || []);
        if (!files.length) return;
        const directory = root.dataset.currentDirectory || '/';

        try {
            fileManagerMessage(root, 'در حال آماده‌سازی بارگذاری ' + files.length + ' فایل...');
            const data = await fileManagerRequest(root, 'upload_url', { directory: directory });
            if (!data.url) throw new Error('customer_file_action_failed');

            const uploadUrl = new URL(data.url);
            uploadUrl.searchParams.set('directory', directory);

            for (const file of files) {
                const formData = new FormData();
                formData.append('files', file, file.name);
                fileManagerMessage(root, 'در حال بارگذاری ' + file.name + '...');

                const response = await fetch(uploadUrl.toString(), {
                    method: 'POST',
                    credentials: 'omit',
                    body: formData
                });
                if (!response.ok) throw new Error('customer_file_action_failed');
            }

            fileManagerMessage(root, 'بارگذاری با موفقیت انجام شد.');
            await loadFileDirectory(root, directory);
        } catch (error) {
            const code = error instanceof Error ? error.message : 'customer_file_action_failed';
            fileManagerMessage(
                root,
                (errorMessages[code] || errorMessages.customer_file_action_failed) + ' در صورت خطای CORS، Origin این سایت باید در allowed_origins تنظیمات Wings مجاز شود.',
                true
            );
        }
    }

    function initializeMyServersWidgets() {
        const currentConsole = document.getElementById('fcConsole');
        if (activeConsoleRoot && activeConsoleRoot !== currentConsole) {
            closeConsoleConnection(false);
            activeConsoleRoot = null;
        }

        if (currentConsole && currentConsole.dataset.initialized !== '1') {
            currentConsole.dataset.initialized = '1';
            const connectButton = currentConsole.querySelector('[data-console-connect]');
            if (connectButton) {
                connectButton.addEventListener('click', () => {
                    if (activeConsoleSocket && activeConsoleRoot === currentConsole) {
                        closeConsoleConnection(true);
                    } else {
                        connectConsole(currentConsole);
                    }
                });
            }

            currentConsole.querySelector('[data-console-clear]')?.addEventListener('click', () => {
                const output = currentConsole.querySelector('[data-console-output]');
                if (output) output.textContent = '';
            });
        }

        const fileManager = document.getElementById('fcFileManager');
        if (fileManager && fileManager.dataset.initialized !== '1') {
            fileManager.dataset.initialized = '1';
            fileManager.dataset.currentDirectory = '/';
            loadFileDirectory(fileManager, '/');
        }
    }

    function initializeMyServersObserver() {
        if (!document.body || window.FragCityMyServersObserver) return;
        window.FragCityMyServersObserver = new MutationObserver(() => initializeMyServersWidgets());
        window.FragCityMyServersObserver.observe(document.body, { childList: true, subtree: true });
        initializeMyServersWidgets();
    }

    if (document.body) {
        initializeMyServersObserver();
    } else {
        document.addEventListener('DOMContentLoaded', initializeMyServersObserver, { once: true });
    }

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;

        if (form.matches('[data-client-api-connect-form]')) {
            event.preventDefault();
            submitClientApiForm(form);
            return;
        }

        if (form.matches('[data-console-command-form]')) {
            event.preventDefault();
            submitConsoleCommand(form);
            return;
        }

        if (form.matches('[data-panel-password-form]')) {
            event.preventDefault();
            submitPasswordForm(form);
            return;
        }

        if (form.matches('[data-panel-username-form]')) {
            event.preventDefault();
            submitUsernameForm(form);
        }
    });

    document.addEventListener('click', async function (event) {
        const target = event.target instanceof Element
            ? event.target
            : event.target?.parentElement;
        if (!target) return;

        const disconnectButton = target.closest('[data-client-api-disconnect]');
        if (disconnectButton) {
            event.preventDefault();
            disconnectClientApi(disconnectButton);
            return;
        }

        const fileManager = target.closest('#fcFileManager');
        if (fileManager) {
            const goPath = target.closest('[data-file-go-path]');
            if (goPath) {
                event.preventDefault();
                loadFileDirectory(fileManager, goPath.dataset.fileGoPath || '/');
                return;
            }

            const openButton = target.closest('[data-file-open]');
            if (openButton) {
                event.preventDefault();
                const name = openButton.dataset.fileOpen || '';
                if (openButton.dataset.fileIsFile === '1') {
                    if (openButton.dataset.fileEditable === '1') {
                        await openFileForEdit(fileManager, name);
                    } else {
                        fileManagerMessage(
                            fileManager,
                            'این فایل در ویرایشگر مرورگر پشتیبانی نمی‌شود؛ برای دریافت آن از دکمه دانلود استفاده کنید.'
                        );
                    }
                } else {
                    loadFileDirectory(fileManager, joinFilePath(fileManager.dataset.currentDirectory || '/', name));
                }
                return;
            }

            const editButton = target.closest('[data-file-edit]');
            if (editButton) {
                event.preventDefault();
                await openFileForEdit(fileManager, editButton.dataset.fileEdit || '');
                return;
            }

            const downloadButton = target.closest('[data-file-download]');
            if (downloadButton) {
                event.preventDefault();
                let name = downloadButton.dataset.fileDownload || '';
                if (!name) {
                    const editingFile = fileManager.dataset.editingFile || '';
                    name = editingFile.split('/').filter(Boolean).pop() || '';
                    if (editingFile) {
                        await downloadServerFile(fileManager, name);
                    }
                } else {
                    await downloadServerFile(fileManager, name);
                }
                return;
            }

            const renameButton = target.closest('[data-file-rename]');
            if (renameButton) {
                event.preventDefault();
                const from = renameButton.dataset.fileRename || '';
                const to = window.prompt('نام جدید فایل یا پوشه را وارد کنید:', from);
                if (to && to.trim() && to.trim() !== from) {
                    try {
                        await fileManagerRequest(fileManager, 'rename', {
                            from: joinFilePath(fileManager.dataset.currentDirectory || '/', from),
                            to: to.trim()
                        });
                        await loadFileDirectory(fileManager, fileManager.dataset.currentDirectory || '/');
                    } catch (error) {
                        const code = error instanceof Error ? error.message : 'customer_file_action_failed';
                        fileManagerMessage(fileManager, errorMessages[code] || errorMessages.customer_file_action_failed, true);
                    }
                }
                return;
            }

            const deleteButton = target.closest('[data-file-delete]');
            if (deleteButton) {
                event.preventDefault();
                const name = deleteButton.dataset.fileDelete || '';
                if (window.confirm('فایل یا پوشه «' + name + '» حذف شود؟ این عملیات قابل بازگشت نیست.')) {
                    try {
                        await fileManagerRequest(fileManager, 'delete', { files: [name] });
                        const editor = fileManager.querySelector('[data-file-editor]');
                        const deletingPath = joinFilePath(fileManager.dataset.currentDirectory || '/', name);
                        if (fileManager.dataset.editingFile === deletingPath) {
                            if (editor) editor.hidden = true;
                            delete fileManager.dataset.editingFile;
                        }
                        await loadFileDirectory(fileManager, fileManager.dataset.currentDirectory || '/');
                    } catch (error) {
                        const code = error instanceof Error ? error.message : 'customer_file_action_failed';
                        fileManagerMessage(fileManager, errorMessages[code] || errorMessages.customer_file_action_failed, true);
                    }
                }
                return;
            }

            if (target.closest('[data-file-up]')) {
                event.preventDefault();
                const currentPath = fileManager.dataset.currentDirectory || '/';
                const parts = currentPath.split('/').filter(Boolean);
                parts.pop();
                loadFileDirectory(fileManager, parts.length ? '/' + parts.join('/') : '/');
                return;
            }

            if (target.closest('[data-file-refresh]')) {
                event.preventDefault();
                loadFileDirectory(fileManager, fileManager.dataset.currentDirectory || '/');
                return;
            }

            if (target.closest('[data-file-new-folder]')) {
                event.preventDefault();
                const name = window.prompt('نام پوشه جدید را وارد کنید:');
                if (name && name.trim()) {
                    try {
                        await fileManagerRequest(fileManager, 'create_folder', { name: name.trim() });
                        await loadFileDirectory(fileManager, fileManager.dataset.currentDirectory || '/');
                    } catch (error) {
                        const code = error instanceof Error ? error.message : 'customer_file_action_failed';
                        fileManagerMessage(fileManager, errorMessages[code] || errorMessages.customer_file_action_failed, true);
                    }
                }
                return;
            }

            if (target.closest('[data-file-upload-trigger]')) {
                event.preventDefault();
                fileManager.querySelector('[data-file-upload-input]')?.click();
                return;
            }

            if (target.closest('[data-file-save]')) {
                event.preventDefault();
                const file = fileManager.dataset.editingFile || '';
                const textarea = fileManager.querySelector('[data-file-editor-content]');
                const save = target.closest('[data-file-save]');
                if (!file || !textarea || !save) return;

                save.disabled = true;
                const message = fileManager.querySelector('[data-file-editor-message]');
                if (message) message.textContent = 'در حال ذخیره...';

                try {
                    await fileManagerRequest(fileManager, 'write', { file: file, content: textarea.value });
                    if (message) message.textContent = 'ذخیره شد.';
                    fileManagerMessage(fileManager, 'تغییرات فایل ذخیره شد.');
                } catch (error) {
                    const code = error instanceof Error ? error.message : 'customer_file_action_failed';
                    if (message) message.textContent = errorMessages[code] || errorMessages.customer_file_action_failed;
                } finally {
                    save.disabled = false;
                }
                return;
            }

            if (target.closest('[data-file-close]')) {
                event.preventDefault();
                const editor = fileManager.querySelector('[data-file-editor]');
                if (editor) editor.hidden = true;
                delete fileManager.dataset.editingFile;
                return;
            }
        }

        if (target.closest('[data-ajax-nav]')) {
            const currentConsole = document.getElementById('fcConsole');
            if (activeConsoleSocket && currentConsole && !target.closest('#fcConsole')) {
                closeConsoleConnection(false);
                activeConsoleRoot = null;
            }
        }

        const button = target.closest('[data-copy-value]');
        if (!button) return;

        const value = button.getAttribute('data-copy-value') || '';
        if (!value) return;

        try {
            await navigator.clipboard.writeText(value);
            const original = button.innerHTML;
            button.innerHTML = '<i class="fa-solid fa-check" aria-hidden="true"></i>';
            button.setAttribute('aria-label', 'کپی شد');
            window.setTimeout(function () {
                button.innerHTML = original;
                button.setAttribute('aria-label', 'کپی');
            }, 1200);
        } catch (error) {
            const field = button.parentElement?.querySelector('strong');
            if (field) {
                const selection = window.getSelection();
                const range = document.createRange();
                range.selectNodeContents(field);
                selection.removeAllRanges();
                selection.addRange(range);
            }
        }
    });

    document.addEventListener('change', function (event) {
        const input = event.target;
        if (!(input instanceof HTMLInputElement) || !input.matches('[data-file-upload-input]')) return;

        const root = input.closest('#fcFileManager');
        if (!root || !input.files || !input.files.length) return;

        const files = input.files;
        uploadServerFiles(root, files);
        input.value = '';
    });
})();

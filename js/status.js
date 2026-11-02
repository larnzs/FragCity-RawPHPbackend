/*
 * FragCity public service status.
 * Reads only the same-origin sanitized endpoint; no Uptime Kuma credentials
 * or private monitor configuration are sent to the browser.
 */
(function () {
    'use strict';

    if (window.FragCityStatusPage) {
        window.FragCityStatusPage.init();
        return;
    }

    const STATUS_LABELS = {
        up: { label: 'عملیاتی', tone: 'up' },
        down: { label: 'مختل', tone: 'down' },
        degraded: { label: 'اختلال جزئی', tone: 'degraded' },
        pending: { label: 'در انتظار بررسی', tone: 'pending' },
        maintenance: { label: 'در حال نگهداری', tone: 'maintenance' },
        unknown: { label: 'نامشخص', tone: 'unknown' }
    };

    const TYPE_LABELS = {
        http: 'HTTP / HTTPS',
        keyword: 'HTTP Keyword',
        jsonQuery: 'HTTP JSON',
        port: 'TCP Port',
        ping: 'Ping',
        steam: 'Steam Game Server',
        gamedig: 'GameDig',
        docker: 'Docker',
        push: 'Push Monitor'
    };

    function makeElement(tag, className, text) {
        const element = document.createElement(tag);
        if (className) element.className = className;
        if (text !== undefined && text !== null) element.textContent = String(text);
        return element;
    }

    function formatDate(value) {
        if (!value) return '—';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return '—';
        return new Intl.DateTimeFormat('fa-IR', {
            dateStyle: 'medium',
            timeStyle: 'short'
        }).format(date);
    }

    function formatUptime(value) {
        if (value === null || value === undefined || value === '' || !Number.isFinite(Number(value))) return '—';
        return Number(value).toLocaleString('fa-IR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 3
        }) + '٪';
    }

    function statusMeta(status) {
        return STATUS_LABELS[status] || STATUS_LABELS.unknown;
    }

    function createStatusPill(status) {
        const meta = statusMeta(status);
        return makeElement('span', 'fc-status-pill fc-status-pill--' + meta.tone, meta.label);
    }

    function createIncidentCard(incident, active) {
        const card = makeElement('article', 'fc-status-incident-card' + (active ? ' is-active' : ''));
        const top = makeElement('div', 'fc-status-incident-top');
        top.appendChild(makeElement('strong', '', incident.title || 'رخداد'));
        top.appendChild(makeElement('time', '', formatDate(incident.updated_at || incident.created_at)));
        card.appendChild(top);
        if (incident.content) card.appendChild(makeElement('p', '', incident.content));
        if (active) card.appendChild(makeElement('span', 'fc-status-incident-active', 'نیازمند پیگیری'));
        return card;
    }

    function renderHistoryBars(container, history) {
        const wrapper = makeElement('div', 'fc-status-beat-history');
        const rows = Array.isArray(history) ? history : [];
        if (rows.length === 0) {
            wrapper.appendChild(makeElement('span', 'fc-status-history-unavailable', 'تاریخچه هنوز جمع‌آوری نشده است'));
            container.appendChild(wrapper);
            return;
        }

        rows.forEach(function (beat, index) {
            const status = beat && typeof beat.status === 'string' ? beat.status : 'unknown';
            const meta = statusMeta(status);
            const bar = makeElement('span', 'fc-status-beat fc-status-beat--' + meta.tone);
            bar.title = meta.label + (beat.time ? ' · ' + formatDate(beat.time) : '');
            bar.setAttribute('aria-label', 'بررسی ' + (index + 1) + ': ' + meta.label);
            wrapper.appendChild(bar);
        });
        container.appendChild(wrapper);
    }

    function renderMonitor(monitor) {
        const card = makeElement('article', 'fc-status-monitor-card');
        const heading = makeElement('div', 'fc-status-monitor-heading');
        const titleWrap = makeElement('div', 'fc-status-monitor-title-wrap');
        titleWrap.appendChild(makeElement('span', 'fc-status-monitor-dot fc-status-monitor-dot--' + statusMeta(monitor.status).tone));
        const title = makeElement('div', 'fc-status-monitor-title');
        title.appendChild(makeElement('h3', '', monitor.name || 'Service'));
        title.appendChild(makeElement('small', '', TYPE_LABELS[monitor.type] || 'Service monitor'));
        titleWrap.appendChild(title);
        heading.appendChild(titleWrap);
        heading.appendChild(createStatusPill(monitor.status));
        card.appendChild(heading);

        const metrics = makeElement('div', 'fc-status-monitor-metrics');
        const uptime = makeElement('div', 'fc-status-monitor-metric');
        uptime.appendChild(makeElement('span', '', 'Uptime · ۲۴ ساعت'));
        uptime.appendChild(makeElement('strong', '', formatUptime(monitor.uptime_24h)));
        metrics.appendChild(uptime);

        const response = makeElement('div', 'fc-status-monitor-metric');
        response.appendChild(makeElement('span', '', 'زمان پاسخ'));
        const ping = Number(monitor.ping_ms);
        const pingAvailable = monitor.ping_ms !== null && monitor.ping_ms !== undefined && monitor.ping_ms !== '' && Number.isFinite(ping);
        response.appendChild(makeElement('strong', '', pingAvailable ? ping.toLocaleString('fa-IR', { maximumFractionDigits: 1 }) + ' ms' : '—'));
        metrics.appendChild(response);

        const checked = makeElement('div', 'fc-status-monitor-metric');
        checked.appendChild(makeElement('span', '', 'آخرین بررسی'));
        checked.appendChild(makeElement('strong', '', formatDate(monitor.last_checked)));
        metrics.appendChild(checked);
        card.appendChild(metrics);

        const history = makeElement('div', 'fc-status-monitor-history');
        history.appendChild(makeElement('span', 'fc-status-monitor-history-label', 'آخرین بررسی‌ها'));
        renderHistoryBars(history, monitor.history);
        card.appendChild(history);
        return card;
    }

    function renderGroups(container, groups) {
        container.replaceChildren();
        (Array.isArray(groups) ? groups : []).forEach(function (group) {
            const section = makeElement('section', 'fc-status-group');
            const heading = makeElement('div', 'fc-status-group-heading');
            heading.appendChild(makeElement('h3', '', group.name || 'سرویس‌ها'));
            const count = Array.isArray(group.monitors) ? group.monitors.length : 0;
            heading.appendChild(makeElement('span', '', count.toLocaleString('fa-IR') + ' سرویس'));
            section.appendChild(heading);

            const grid = makeElement('div', 'fc-status-monitor-grid');
            (Array.isArray(group.monitors) ? group.monitors : []).forEach(function (monitor) {
                grid.appendChild(renderMonitor(monitor));
            });
            section.appendChild(grid);
            container.appendChild(section);
        });
    }

    function renderIncidents(activeContainer, activeList, incidents, maintenance) {
        const activeRows = Array.isArray(incidents) ? incidents.slice() : [];
        (Array.isArray(maintenance) ? maintenance : []).forEach(function (item) {
            activeRows.push({
                title: item.title || 'نگهداری برنامه‌ریزی‌شده',
                content: item.description || '',
                created_at: item.start_at || null,
                updated_at: item.end_at || item.start_at || null
            });
        });

        activeList.replaceChildren();
        activeRows.forEach(function (incident) {
            activeList.appendChild(createIncidentCard(incident, true));
        });
        activeContainer.hidden = activeRows.length === 0;
    }

    function renderIncidentHistory(container, empty, incidents) {
        container.replaceChildren();
        const rows = Array.isArray(incidents) ? incidents : [];
        rows.forEach(function (incident) {
            const item = makeElement('article', 'fc-status-history-item');
            item.appendChild(makeElement('span', 'fc-status-history-marker'));
            const copy = makeElement('div', 'fc-status-history-copy');
            copy.appendChild(makeElement('strong', '', incident.title || 'تغییر وضعیت'));
            if (incident.content) copy.appendChild(makeElement('p', '', incident.content));
            item.appendChild(copy);
            item.appendChild(makeElement('time', '', formatDate(incident.updated_at || incident.created_at)));
            container.appendChild(item);
        });
        empty.hidden = rows.length !== 0;
    }

    function renderSummary(summary) {
        const operational = document.querySelector('#fragcityStatusPage [data-status-summary="operational"]');
        const total = document.querySelector('#fragcityStatusPage [data-status-summary="total"]');
        const uptime = document.querySelector('#fragcityStatusPage [data-status-summary="uptime"]');
        const incidents = document.querySelector('#fragcityStatusPage [data-status-summary="incidents"]');

        if (operational) operational.textContent = (Number(summary.operational) || 0).toLocaleString('fa-IR') + ' / ' + (Number(summary.total) || 0).toLocaleString('fa-IR');
        if (total) total.textContent = (Number(summary.total) || 0).toLocaleString('fa-IR');
        if (uptime) uptime.textContent = formatUptime(summary.uptime_24h_average);
        if (incidents) incidents.textContent = (Number(summary.active_incidents) || 0).toLocaleString('fa-IR');
    }

    function initStatusPage() {
        const root = document.getElementById('fragcityStatusPage');
        if (!root || root.dataset.statusReady === '1') return;
        root.dataset.statusReady = '1';

        const endpoint = String(root.dataset.statusEndpoint || '/api/public-status.php');
        const overall = root.querySelector('[data-status-overall]');
        const overallLabel = root.querySelector('[data-status-overall-label]');
        const overallDescription = root.querySelector('[data-status-overall-description]');
        const updated = root.querySelector('[data-status-updated]');
        const loading = root.querySelector('[data-status-loading]');
        const groups = root.querySelector('[data-status-groups]');
        const empty = root.querySelector('[data-status-empty]');
        const alert = root.querySelector('[data-status-alert]');
        const refreshButton = root.querySelector('[data-status-refresh]');
        const activeIncidents = root.querySelector('[data-status-active-incidents]');
        const activeList = root.querySelector('[data-status-active-list]');
        const historyList = root.querySelector('[data-status-history]');
        const historyEmpty = root.querySelector('[data-status-history-empty]');

        let refreshTimer = 0;
        let requestController = null;
        let lastSnapshot = null;

        function setOverall(status) {
            const meta = statusMeta(status);
            if (overall) {
                overall.dataset.statusOverall = meta.tone;
                overall.className = 'fc-status-overall fc-status-overall--' + meta.tone;
            }
            if (overallLabel) overallLabel.textContent = meta.label;
            if (overallDescription) {
                const messages = {
                    up: 'تمام سرویس‌های مانیتورشده در آخرین بررسی در دسترس بوده‌اند.',
                    down: 'در آخرین بررسی، سرویس‌های مانیتورشده پاسخ‌گو نبوده‌اند.',
                    degraded: 'حداقل یک سرویس با اختلال یا پاسخ غیرعادی روبه‌رو است.',
                    pending: 'وضعیت آخرین بررسی هنوز قطعی نیست.',
                    maintenance: 'حداقل یک سرویس در حال نگهداری برنامه‌ریزی‌شده است.',
                    unknown: 'هنوز داده‌ی معتبری از مانیتورینگ دریافت نشده است.'
                };
                overallDescription.textContent = messages[status] || messages.unknown;
            }
        }

        function showError(code) {
            if (!alert) return;
            alert.hidden = false;
            alert.textContent = code === 'status_not_configured'
                ? 'اتصال Uptime Kuma هنوز تنظیم نشده است. پس از استقرار مانیتورینگ و انتشار Status Page عمومی، اطلاعات این قسمت نمایش داده می‌شود.'
                : 'در دریافت وضعیت سرویس‌ها مشکلی پیش آمد. اطلاعات قبلی حفظ شده و در بروزرسانی بعدی دوباره تلاش می‌کنیم.';
            if (!lastSnapshot) setOverall('unknown');
        }

        async function loadStatus(silent) {
            if (requestController) requestController.abort();
            requestController = new AbortController();
            const controller = requestController;

            if (!silent && loading) loading.hidden = false;
            if (refreshButton) refreshButton.disabled = true;
            if (alert && lastSnapshot) alert.hidden = true;

            try {
                const response = await fetch(endpoint, {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { Accept: 'application/json' },
                    signal: controller.signal
                });
                const payload = await response.json();
                if (!response.ok || payload.success !== true || !payload.data) {
                    const code = payload && typeof payload.error === 'string' ? payload.error : 'status_temporarily_unavailable';
                    showError(code);
                    return;
                }

                const data = payload.data;
                lastSnapshot = data;
                setOverall(data.overall_status || 'unknown');
                renderSummary(data.summary || {});
                renderGroups(groups, data.groups || []);
                empty.hidden = Array.isArray(data.groups) && data.groups.some(function (group) {
                    return Array.isArray(group.monitors) && group.monitors.length > 0;
                });
                renderIncidents(activeIncidents, activeList, data.active_incidents || [], data.maintenance || []);
                renderIncidentHistory(historyList, historyEmpty, data.incident_history || []);
                if (updated) updated.textContent = 'آخرین دریافت اطلاعات: ' + formatDate(data.updated_at || new Date().toISOString());
                if (alert) alert.hidden = true;
            } catch (error) {
                if (error && error.name === 'AbortError') return;
                showError('status_temporarily_unavailable');
            } finally {
                if (controller === requestController) {
                    if (loading) loading.hidden = true;
                    if (refreshButton) refreshButton.disabled = false;
                }
            }
        }

        if (refreshButton) {
            refreshButton.addEventListener('click', function () {
                void loadStatus(false);
            });
        }

        void loadStatus(false);
        refreshTimer = window.setInterval(function () {
            if (document.visibilityState === 'visible') void loadStatus(true);
        }, 60000);

        function cleanupStatusPage() {
            if (root.isConnected) return;
            window.clearInterval(refreshTimer);
            if (requestController) requestController.abort();
            window.removeEventListener('content-loaded', cleanupStatusPage);
        }
        window.addEventListener('content-loaded', cleanupStatusPage);
    }

    window.FragCityStatusPage = Object.freeze({ init: initStatusPage });
    document.addEventListener('DOMContentLoaded', initStatusPage);
    window.addEventListener('content-loaded', initStatusPage);
    if (document.readyState !== 'loading') initStatusPage();
})();
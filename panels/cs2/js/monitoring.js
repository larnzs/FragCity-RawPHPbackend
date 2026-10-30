(() => {
    'use strict';

    const root = document.getElementById('fcPterodactylMonitoring');
    if (!root) return;

    const endpoint = root.dataset.endpoint || '/api/admin-pterodactyl-monitoring.php';
    const refreshButton = document.getElementById('fcMonitoringRefresh');
    const connection = document.getElementById('fcMonitoringConnection');
    const connectionText = document.getElementById('fcMonitoringConnectionText');
    const updated = document.getElementById('fcMonitoringUpdated');
    const errorBox = document.getElementById('fcMonitoringError');
    const nodesContainer = document.getElementById('fcMonitoringNodes');
    const serversContainer = document.getElementById('fcMonitoringServers');
    const locale = document.documentElement.lang === 'fa' ? 'fa-IR' : 'en-US';

    let requestInFlight = false;
    let timer = null;

    const bytes = (value) => {
        const number = Math.max(0, Number(value) || 0);
        if (number < 1024) return number.toFixed(0) + ' B';
        const units = ['KB', 'MB', 'GB', 'TB', 'PB'];
        let scaled = number / 1024;
        let index = 0;
        while (scaled >= 1024 && index < units.length - 1) {
            scaled /= 1024;
            index++;
        }
        return scaled.toFixed(1) + ' ' + units[index];
    };

    const number = (value, digits = 0) =>
        (Number(value) || 0).toLocaleString(locale, {
            minimumFractionDigits: digits,
            maximumFractionDigits: digits
        });

    const cpu = (value) => number(Math.max(0, Number(value) || 0), 1) + '%';

    const duration = (milliseconds) => {
        let seconds = Math.floor(Math.max(0, Number(milliseconds) || 0) / 1000);
        const days = Math.floor(seconds / 86400);
        seconds %= 86400;
        const hours = Math.floor(seconds / 3600);
        seconds %= 3600;
        const minutes = Math.floor(seconds / 60);
        if (days > 0) return days + 'd ' + hours + 'h';
        if (hours > 0) return hours + 'h ' + minutes + 'm';
        if (minutes > 0) return minutes + 'm';
        return seconds + 's';
    };

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    })[char]);

    const statusLabel = (status) => {
        const labels = {
            online: 'Online',
            offline: 'Offline',
            running: 'Running',
            starting: 'Starting',
            stopping: 'Stopping',
            suspended: 'Suspended',
            unknown: 'Unknown'
        };
        return labels[String(status || 'unknown').toLowerCase()] || 'Unknown';
    };

    const statusClass = (status) => {
        const value = String(status || 'unknown').toLowerCase();
        return ['online', 'offline', 'running', 'starting', 'stopping', 'suspended'].includes(value)
            ? value
            : 'unknown';
    };

    const progress = (used, capacity, tone = '') => {
        const total = Math.max(0, Number(capacity) || 0);
        const current = Math.max(0, Number(used) || 0);
        const percent = total > 0 ? Math.min(100, (current / total) * 100) : 0;
        return `<div class="fc-monitor-progress ${tone}"><span style="width:${percent.toFixed(1)}%"></span></div>`;
    };

    const renderSummary = (summary) => {
        document.getElementById('fcSummaryNodes').textContent =
            number(summary.nodes_online) + ' / ' + number(summary.nodes_total);
        document.getElementById('fcSummaryServers').textContent =
            number(summary.servers_running) + ' / ' + number(summary.servers_total);
        document.getElementById('fcSummaryCpu').textContent = cpu(summary.cpu_absolute);
        document.getElementById('fcSummaryMemory').textContent = bytes(summary.memory_bytes);
        document.getElementById('fcSummaryDisk').textContent = bytes(summary.disk_bytes);
    };

    const renderNode = (node) => {
        const system = node.system || {};
        const allocation = node.allocation || {};
        const cs2 = node.cs2 || {};
        const resources = cs2.resources || {};
        const memoryCapacity = Number(system.memory_total_bytes || allocation.memory_capacity_bytes || 0);
        const diskCapacity = Number(allocation.disk_capacity_bytes || 0);
        const memoryUsed = Number(resources.memory_bytes || 0);
        const diskUsed = Number(resources.disk_bytes || 0);
        const state = statusClass(node.status);

        const systemTags = [
            system.os || '',
            system.architecture || '',
            system.cpu_threads ? number(system.cpu_threads) + ' CPU threads' : '',
            node.wings_version ? 'Wings ' + node.wings_version : '',
            node.maintenance_mode ? 'Maintenance' : ''
        ].filter(Boolean).map((item) => `<span>${escapeHtml(item)}</span>`).join('');

        const docker = node.docker || {};
        const containers = number(docker.containers_running || 0) + ' / ' + number(docker.containers_total || 0);

        return `
            <article class="fc-monitor-node-card">
                <div class="fc-monitor-node-heading">
                    <div class="fc-monitor-node-title">
                        <h3>${escapeHtml(node.name || 'Node')}</h3>
                        <small>${escapeHtml(node.fqdn || '—')}</small>
                    </div>
                    <span class="fc-monitor-status ${state}">${statusLabel(state)}</span>
                </div>
                <div class="fc-monitor-node-system">${systemTags || '<span>System details unavailable</span>'}</div>
                <div class="fc-monitor-node-metrics">
                    <div>
                        <div class="fc-monitor-metric-line">
                            <span>CS2 CPU</span>
                            <strong>${cpu(resources.cpu_absolute)}</strong>
                        </div>
                        ${progress(resources.cpu_absolute, Math.max(100, Number(system.cpu_threads || 1) * 100), 'blue')}
                    </div>
                    <div>
                        <div class="fc-monitor-metric-line">
                            <span>CS2 RAM / Node RAM</span>
                            <strong>${bytes(memoryUsed)} / ${bytes(memoryCapacity)}</strong>
                        </div>
                        ${progress(memoryUsed, memoryCapacity, 'green')}
                    </div>
                    <div>
                        <div class="fc-monitor-metric-line">
                            <span>CS2 Disk / Node allocation</span>
                            <strong>${bytes(diskUsed)} / ${diskCapacity > 0 ? bytes(diskCapacity) : 'Unlimited'}</strong>
                        </div>
                        ${diskCapacity > 0 ? progress(diskUsed, diskCapacity) : progress(0, 0)}
                    </div>
                </div>
                <div class="fc-monitor-node-footer">
                    <span><i class="fa-brands fa-docker"></i> Docker: ${escapeHtml(containers)}</span>
                    <span><i class="fa-solid fa-gamepad"></i> CS2: ${number(cs2.servers_running || 0)} / ${number(cs2.servers_total || 0)}</span>
                    <span><i class="fa-solid fa-network-wired"></i> Daemon: ${number(node.daemon_port || 8080)}</span>
                </div>
                ${node.error ? `<div class="fc-monitor-node-error">${escapeHtml(node.error)}</div>` : ''}
            </article>
        `;
    };

    const renderServer = (server) => {
        const state = statusClass(server.status);
        const memoryLimit = Number(server.memory_limit_bytes || 0);
        const diskLimit = Number(server.disk_limit_bytes || 0);
        const usage = `${bytes(server.network_rx_bytes)} ↓ / ${bytes(server.network_tx_bytes)} ↑`;

        return `
            <tr>
                <td>
                    <span class="fc-monitor-server-name">${escapeHtml(server.name || 'CS2 Server')}</span>
                    <span class="fc-monitor-server-id">${escapeHtml(server.identifier || '')}</span>
                </td>
                <td>${escapeHtml(server.node_name || '—')}</td>
                <td><span class="fc-monitor-status ${state}">${statusLabel(state)}</span></td>
                <td class="mono">${cpu(server.cpu_absolute)}</td>
                <td class="mono">${bytes(server.memory_bytes)}${memoryLimit > 0 ? ' / ' + bytes(memoryLimit) : ''}</td>
                <td class="mono">${bytes(server.disk_bytes)}${diskLimit > 0 ? ' / ' + bytes(diskLimit) : ''}</td>
                <td class="mono">${escapeHtml(usage)}</td>
                <td class="mono">${duration(server.uptime_ms)}</td>
            </tr>
        `;
    };

    const setConnection = (state, text) => {
        connection.classList.remove('is-error', 'is-loading');
        if (state === 'error') connection.classList.add('is-error');
        if (state === 'loading') connection.classList.add('is-loading');
        connectionText.textContent = text;
    };

    const refresh = async (silent = false) => {
        if (requestInFlight) return;
        requestInFlight = true;
        refreshButton.disabled = true;

        if (!silent) setConnection('loading', 'Connecting to Pterodactyl and Wings...');

        try {
            const response = await fetch(endpoint, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'Accept': 'application/json' }
            });
            const payload = await response.json().catch(() => null);
            if (!response.ok || !payload || payload.success !== true || !payload.data) {
                const code = payload && payload.error ? payload.error : 'monitoring_request_failed';
                throw new Error(code);
            }

            const data = payload.data;
            const nodes = Array.isArray(data.nodes) ? data.nodes : [];
            const servers = Array.isArray(data.servers) ? data.servers : [];
            renderSummary(data.summary || {});

            nodesContainer.innerHTML = nodes.length
                ? nodes.map(renderNode).join('')
                : '<div class="fc-monitor-loading">No allowlisted Pterodactyl nodes configured.</div>';

            serversContainer.innerHTML = servers.length
                ? servers.map(renderServer).join('')
                : '<tr><td colspan="8" class="fc-monitor-empty">No allowlisted CS2 servers found.</td></tr>';

            document.getElementById('fcNodeCount').textContent = number(nodes.length) + ' node(s)';
            document.getElementById('fcServerCount').textContent = number(servers.length) + ' server(s)';

            const timestamp = data.generated_at ? new Date(data.generated_at) : new Date();
            updated.textContent = 'Updated ' + timestamp.toLocaleTimeString(locale);
            errorBox.hidden = true;
            setConnection('online', 'Monitoring API connected · auto refresh every 15 seconds');
        } catch (error) {
            setConnection('error', 'Monitoring API unavailable');
            errorBox.textContent = 'Could not retrieve live monitoring data. Check the Linux API configuration, Pterodactyl token permissions, and Wings connectivity. (' + String(error.message || 'request failed') + ')';
            errorBox.hidden = false;
        } finally {
            requestInFlight = false;
            refreshButton.disabled = false;
        }
    };

    refreshButton.addEventListener('click', () => refresh(false));
    refresh(false);
    timer = window.setInterval(() => {
        if (document.visibilityState === 'visible') refresh(true);
    }, 15000);

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') refresh(true);
    });

    window.addEventListener('beforeunload', () => {
        if (timer !== null) window.clearInterval(timer);
    });
})();

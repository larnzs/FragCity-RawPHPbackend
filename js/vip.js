(function () {
    'use strict';

    function applyVipColors(root) {
        root.querySelectorAll('.vip-level-tab').forEach(function (tab) {
            const color = tab.dataset.color || '#3b82f6';
            const gradient = tab.dataset.gradient || 'linear-gradient(135deg,#2563eb,#3b82f6)';
            const badge = tab.querySelector('.level-badge');
            if (badge) badge.style.background = gradient;
        });

        root.querySelectorAll('.feature-panel').forEach(function (panel) {
            const color = panel.dataset.color || '#60a5fa';
            panel.style.setProperty('--vip-color', color);
        });

        root.querySelectorAll('.feature-card, .feature-icon, .feature-title').forEach(function (element) {
            const color = element.closest('.feature-card')?.dataset.color || '#3b82f6';
            element.style.setProperty('--feature-color', color);
        });
    }

    function selectLevel(root, level) {
        root.querySelectorAll('.feature-panel').forEach(function (panel) {
            const active = panel.dataset.level === String(level);
            panel.hidden = !active;
            panel.classList.toggle('is-active', active);
        });

        root.querySelectorAll('.vip-level-tab').forEach(function (tab) {
            tab.classList.toggle('selected', tab.dataset.level === String(level));
        });
    }

    async function syncVip(root, button) {
        const result = root.querySelector('#syncResult');
        if (!result) return;

        result.className = 'vip-sync-result';
        result.textContent = 'در حال همگام‌سازی...';
        button.disabled = true;

        try {
            const resp = await fetch('api/vip_sync_proxy.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await resp.json();

            if (data.error) {
                result.classList.add('error');
                result.textContent = data.error;
                return;
            }

            result.classList.add('success');
            result.textContent = `همگام‌سازی موفق! سطح شما: ${data.vip_group} (${data.xp} XP)`;
            setTimeout(function () {
                window.location.reload();
            }, 1500);
        } catch (error) {
            result.classList.add('error');
            result.textContent = 'خطا در ارتباط با سرور';
        } finally {
            button.disabled = false;
        }
    }

    function initVip() {
        const root = document.querySelector('.vip-page');
        if (!root) return;

        const xpFill = root.querySelector('.vip-xp-fill');
        if (xpFill) {
            const percent = Math.max(0, Math.min(100, Number(xpFill.dataset.xpPercent || 0)));
            requestAnimationFrame(function () {
                xpFill.style.width = percent + '%';
            });
        }

        applyVipColors(root);

        root.querySelectorAll('.vip-level-tab').forEach(function (tab) {
            tab.addEventListener('click', function () {
                selectLevel(root, tab.dataset.level);
            });
        });

        const syncButton = root.querySelector('#syncVipButton');
        if (syncButton) {
            syncButton.addEventListener('click', function () {
                syncVip(root, syncButton);
            });
        }

        const initial = root.querySelector('.vip-level-tab.selected')?.dataset.level || '1';
        selectLevel(root, initial);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initVip, { once: true });
    } else {
        initVip();
    }
})();

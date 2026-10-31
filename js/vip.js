(function () {
    'use strict';

    function applyVipColors(root) {
        // Tier badges retain their bronze/silver/gold identity, while the
        // page controls and feature UI stay within FragCity orange/amber.
        root.querySelectorAll('.vip-level-tab').forEach(function (tab) {
            const gradient = tab.dataset.gradient || 'linear-gradient(135deg,#F97316,#FFB347)';
            const badge = tab.querySelector('.level-badge');
            if (badge) badge.style.background = gradient;
        });

        root.querySelectorAll('.feature-panel').forEach(function (panel) {
            panel.style.setProperty('--vip-color', '#FFB347');
        });

        root.querySelectorAll('.feature-card, .feature-icon, .feature-title').forEach(function (element) {
            element.style.setProperty('--feature-color', '#F97316');
        });
    }

    function selectLevel(root, level) {
        root.querySelectorAll('.feature-panel').forEach(function (panel) {
            const active = panel.dataset.level === String(level);
            panel.hidden = !active;
            panel.classList.toggle('is-active', active);
        });

        root.querySelectorAll('.vip-level-tab').forEach(function (tab) {
            const active = tab.dataset.level === String(level);
            tab.classList.toggle('selected', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            tab.tabIndex = active ? 0 : -1;
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

            if (!resp.ok) {
                throw new Error('VIP sync request failed with HTTP ' + resp.status);
            }

            const data = await resp.json();

            if (data.error) {
                result.classList.add('error');
                result.textContent = data.error;
                return;
            }

            result.classList.add('success');
            result.textContent = `همگام‌سازی موفق! سطح شما: ${data.vip_group} (${data.xp} XP)`;
            window.setTimeout(function () {
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
        if (!root || root.dataset.vipInitialized === '1') return;
        root.dataset.vipInitialized = '1';

        const xpFill = root.querySelector('.vip-xp-fill');
        if (xpFill) {
            const percent = Math.max(0, Math.min(100, Number(xpFill.dataset.xpPercent || 0)));
            window.requestAnimationFrame(function () {
                xpFill.style.width = percent + '%';
            });
        }

        applyVipColors(root);

        const tabs = Array.from(root.querySelectorAll('.vip-level-tab'));
        tabs.forEach(function (tab, index) {
            tab.addEventListener('click', function () {
                selectLevel(root, tab.dataset.level);
            });

            tab.addEventListener('keydown', function (event) {
                if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
                event.preventDefault();

                const step = event.key === 'ArrowLeft' ? 1 : -1;
                const nextIndex = (index + step + tabs.length) % tabs.length;
                tabs[nextIndex].focus();
                selectLevel(root, tabs[nextIndex].dataset.level);
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
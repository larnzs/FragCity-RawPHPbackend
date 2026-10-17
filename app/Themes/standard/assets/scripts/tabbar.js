(function () {
    const TABBAR_ID = 'tabbar';
    const MOBILE_QUERY = '(max-width: 768px)';
    const SCROLL_THRESHOLD = 8;
    const TOP_SAFE_ZONE = 80;

    class Tabbar {
        constructor(root) {
            this.root = root;
            this.indicator = root.querySelector('[data-tabbar-indicator]');
            this.content = root.querySelector('.tabbar__content');

            this.lastScrollY = window.scrollY;
            this.hidden = false;
            this.scrollTicking = false;
            this.indicatorPending = false;

            this.onScroll = this.onScroll.bind(this);
            this.onResize = this.onResize.bind(this);
            this.onLocationChange = this.onLocationChange.bind(this);

            this.bind();
            this.onLocationChange();
        }

        bind() {
            window.addEventListener('scroll', this.onScroll, { passive: true });
            window.addEventListener('resize', this.onResize, { passive: true });
            window.addEventListener('popstate', this.onLocationChange);

            document.body.addEventListener('htmx:afterSwap', this.onLocationChange);
            document.body.addEventListener('htmx:pushedIntoHistory', this.onLocationChange);
            document.body.addEventListener('htmx:historyRestore', this.onLocationChange);
            document.body.addEventListener('htmx:beforeSwap', closeOpenSheets);
        }

        isMobileViewport() {
            return window.matchMedia(MOBILE_QUERY).matches;
        }

        onScroll() {
            if (this.scrollTicking) return;
            this.scrollTicking = true;

            requestAnimationFrame(() => {
                this.scrollTicking = false;

                if (!this.isMobileViewport()) {
                    if (this.hidden) this.show();
                    return;
                }

                if (document.body.classList.contains('no-scroll')) {
                    this.show();
                    return;
                }

                const y = window.scrollY;
                const delta = y - this.lastScrollY;

                if (Math.abs(delta) < SCROLL_THRESHOLD) return;

                if (y < TOP_SAFE_ZONE) {
                    this.show();
                    this.lastScrollY = y;
                    return;
                }

                if (delta > 0) this.hide();
                else this.show();

                this.lastScrollY = y;
            });
        }

        onResize() {
            if (!this.isMobileViewport() && this.hidden) this.show();
            this.scheduleIndicator(false);
        }

        onLocationChange() {
            this.updateActive();
            this.scheduleIndicator(true);
        }

        show() {
            if (!this.hidden) return;
            this.hidden = false;
            this.root.classList.remove('is-hidden');
        }

        hide() {
            if (this.hidden) return;
            this.hidden = true;
            this.root.classList.add('is-hidden');
        }

        updateActive() {
            if (!this.content) return;
            const items = this.content.querySelectorAll('a.tabbar__item');
            const current = normalizePath(window.location.pathname);

            items.forEach((item) => {
                const href = item.getAttribute('href');
                if (!href) return;
                try {
                    const path = normalizePath(new URL(href, window.location.origin).pathname);
                    const match = path === current || (path !== '/' && current.startsWith(path + '/'));
                    item.classList.toggle('active', match);
                    if (match) item.setAttribute('aria-current', 'page');
                    else item.removeAttribute('aria-current');
                } catch (e) { }
            });
        }

        scheduleIndicator(animate) {
            if (this.indicatorPending) return;
            this.indicatorPending = true;
            requestAnimationFrame(() => {
                this.indicatorPending = false;
                this.moveIndicator(animate);
            });
        }

        moveIndicator(animate) {
            if (!this.indicator || !this.content) return;
            const active = this.content.querySelector('.tabbar__item.active, .tabbar__item[aria-current="page"]');

            if (!active) {
                this.indicator.classList.remove('is-visible');
                return;
            }

            const contentRect = this.content.getBoundingClientRect();
            const rect = active.getBoundingClientRect();
            const center = rect.left + rect.width / 2 - contentRect.left;
            const indicatorWidth = this.indicator.offsetWidth || 24;

            if (!animate) {
                const prevTransition = this.indicator.style.transition;
                this.indicator.style.transition = 'none';
                this.indicator.style.transform = `translateX(${center - indicatorWidth / 2}px)`;
                this.indicator.classList.add('is-visible');
                requestAnimationFrame(() => {
                    this.indicator.style.transition = prevTransition;
                });
                return;
            }

            this.indicator.style.transform = `translateX(${center - indicatorWidth / 2}px)`;
            this.indicator.classList.add('is-visible');
        }

        destroy() {
            window.removeEventListener('scroll', this.onScroll);
            window.removeEventListener('resize', this.onResize);
            window.removeEventListener('popstate', this.onLocationChange);
            document.body.removeEventListener('htmx:afterSwap', this.onLocationChange);
            document.body.removeEventListener('htmx:pushedIntoHistory', this.onLocationChange);
            document.body.removeEventListener('htmx:historyRestore', this.onLocationChange);
            document.body.removeEventListener('htmx:beforeSwap', closeOpenSheets);
        }
    }

    function normalizePath(path) {
        return (path || '/').replace(/\/+$/, '') || '/';
    }

    function closeOpenSheets() {
        try {
            document.querySelectorAll('.modal.is-open[id^="tabbar-"]').forEach((modal) => {
                if (modal.dialogInstance && typeof modal.dialogInstance.hide === 'function') {
                    modal.dialogInstance.hide();
                }
            });
        } catch (e) { }
    }

    function initTabbar() {
        const el = document.getElementById(TABBAR_ID);

        if (!el) {
            if (window.tabbarInstance) {
                window.tabbarInstance.destroy();
                window.tabbarInstance = null;
            }
            return;
        }

        if (window.tabbarInstance && window.tabbarInstance.root === el) {
            window.tabbarInstance.onLocationChange();
            return;
        }

        if (window.tabbarInstance) window.tabbarInstance.destroy();
        window.tabbarInstance = new Tabbar(el);

        if (window.htmx && typeof window.htmx.process === 'function') {
            window.htmx.process(el);
        }
    }

    document.addEventListener('click', (event) => {
        const themeBtn = event.target.closest('[data-tabbar-theme-toggle]');
        if (themeBtn) {
            event.preventDefault();
            const current = document.documentElement.getAttribute('data-theme') || 'dark';
            const next = current === 'light' ? 'dark' : 'light';
            window.dispatchEvent(new CustomEvent('switch-theme', { detail: { theme: next } }));
            return;
        }

        const groupTrigger = event.target.closest('.tabbar-sheet__group-trigger');
        if (groupTrigger) {
            event.preventDefault();
            const group = groupTrigger.closest('.tabbar-sheet__group');
            if (!group) return;
            const open = group.classList.toggle('is-open');
            groupTrigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    });

    window.initTabbar = initTabbar;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTabbar);
    } else {
        initTabbar();
    }

    document.body.addEventListener('htmx:load', (event) => {
        if (event.detail && event.detail.elt && event.detail.elt.id === TABBAR_ID) return;
        initTabbar();
    });
})();

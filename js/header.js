(function () {
    'use strict';

    if (window.fragCityHeaderReady) return;
    window.fragCityHeaderReady = true;

    const body = document.body;
    const siteHeader = document.getElementById('siteHeader');
    let scrollTicking = false;
    let lastScrollTop = Math.max(
        window.scrollY || 0,
        document.documentElement.scrollTop || 0
    );
    const HEADER_SCROLL_THRESHOLD = 4;
    const HEADER_DIRECTION_THRESHOLD = 2;

    function updateHeaderScrollState() {
        if (!siteHeader) {
            scrollTicking = false;
            return;
        }

        const scrollTop = Math.max(
            window.scrollY || 0,
            document.documentElement.scrollTop || 0
        );
        const delta = scrollTop - lastScrollTop;

        if (scrollTop <= HEADER_SCROLL_THRESHOLD) {
            siteHeader.classList.remove('is-scrolled', 'is-scroll-down', 'is-scroll-up');
        } else {
            siteHeader.classList.add('is-scrolled');

            if (delta > HEADER_DIRECTION_THRESHOLD) {
                siteHeader.classList.add('is-scroll-down');
                siteHeader.classList.remove('is-scroll-up');
            } else if (delta < -HEADER_DIRECTION_THRESHOLD) {
                siteHeader.classList.add('is-scroll-up');
                siteHeader.classList.remove('is-scroll-down');
            }
        }

        lastScrollTop = scrollTop;
        scrollTicking = false;
    }

    window.addEventListener('scroll', function () {
        if (document.documentElement.classList.contains('fc-no-animation')) {
            updateHeaderScrollState();
            return;
        }
        if (scrollTicking) return;
        scrollTicking = true;
        window.requestAnimationFrame(updateHeaderScrollState);
    }, { passive: true });

    updateHeaderScrollState();

    const dropdowns = Array.from(
        document.querySelectorAll('.frag-dropdown')
    );

    const mobileNav = document.getElementById('mobileNav');
    const mobileOverlay = document.getElementById('mobileOverlay');
    const mobileOpen = document.getElementById('mobileNavOpen');
    const mobileClose = document.getElementById('mobileNavClose');

    const pageAssets = window.FragCityPageAssets || {};
    const authLoginModal = document.getElementById('fcLoginModal');
    const authLoginModalContent = document.getElementById('fcLoginModalContent');
    let authLoginReturnFocus = null;
    let authLoginOpenPromise = null;

    function getDropdownTrigger(dropdown) {
        return dropdown.querySelector(':scope > button, :scope > a');
    }

    function setDropdownState(dropdown, button, open) {
        if (!dropdown) return;

        dropdown.classList.toggle('open', open);

        if (button) {
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    }

    function closeDropdowns(except) {
        dropdowns.forEach(function (dropdown) {
            if (dropdown === except) return;
            setDropdownState(dropdown, getDropdownTrigger(dropdown), false);
        });
    }

    function openMobileMenu() {
        if (!mobileNav || !mobileOverlay) return;

        closeDropdowns();

        mobileNav.classList.add('open');
        mobileOverlay.classList.add('show');

        mobileNav.setAttribute('aria-hidden', 'false');
        mobileOverlay.setAttribute('aria-hidden', 'false');

        if (mobileOpen) {
            mobileOpen.setAttribute('aria-expanded', 'true');
        }

        body.classList.add('mobile-menu-open');
    }

    function closeMobileMenu() {
        if (!mobileNav || !mobileOverlay) return;

        mobileNav.classList.remove('open');
        mobileOverlay.classList.remove('show');

        mobileNav.setAttribute('aria-hidden', 'true');
        mobileOverlay.setAttribute('aria-hidden', 'true');

        if (mobileOpen) {
            mobileOpen.setAttribute('aria-expanded', 'false');
        }

        body.classList.remove('mobile-menu-open');
    }

    window.openMobileMenu = openMobileMenu;
    window.closeMobileMenu = closeMobileMenu;

    dropdowns.forEach(function (dropdown) {
        const trigger = getDropdownTrigger(dropdown);
        if (!trigger) return;

        trigger.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();

            const shouldOpen = !dropdown.classList.contains('open');

            closeDropdowns(dropdown);
            setDropdownState(dropdown, trigger, shouldOpen);
        });
    });

    if (mobileOpen) {
        mobileOpen.addEventListener('click', openMobileMenu);
    }

    if (mobileClose) {
        mobileClose.addEventListener('click', closeMobileMenu);
    }

    if (mobileOverlay) {
        mobileOverlay.addEventListener('click', closeMobileMenu);
    }

    document.addEventListener('click', function (event) {
        dropdowns.forEach(function (dropdown) {
            if (!dropdown.contains(event.target)) {
                setDropdownState(dropdown, getDropdownTrigger(dropdown), false);
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeDropdowns();
            closeMobileMenu();
        }
    });

    function setActiveLink(slug) {
        document
            .querySelectorAll('[data-ajax-nav][data-slug]')
            .forEach(function (link) {
                link.classList.toggle('active', link.dataset.slug === slug);
            });

        dropdowns.forEach(function (dropdown) {
            const trigger = getDropdownTrigger(dropdown);
            if (!trigger) return;

            const hasActiveChild = Boolean(
                dropdown.querySelector('.frag-dropdown-menu .active')
            );
            trigger.classList.toggle('active', hasActiveChild);
        });
    }

    function slugFromUrl(url) {
        try {
            const parsed = new URL(url, window.location.href);
            return parsed.searchParams.get('page') || 'home';
        } catch (e) {
            return 'home';
        }
    }

    function pageAssetList(slug, type) {
        // Resolve older public route names to the canonical route bundle.
        const aliases = {
            servers: 'servers_vps',
            skins_vps: 'skins',
            leaderboard_vps: 'leaderboard'
        };
        const assetKey = aliases[slug] || slug;
        const assets = pageAssets[assetKey];
        if (!assets || !Array.isArray(assets[type])) return [];
        return assets[type];
    }

    function syncPageStyles(slug) {
        const desired = new Set(pageAssetList(slug, 'css'));

        document.querySelectorAll('link[data-page-style]').forEach(function (link) {
            const href = link.getAttribute('href');
            if (!desired.has(href)) {
                link.remove();
            }
        });

        desired.forEach(function (href) {
            const exists = Array.from(
                document.querySelectorAll('link[data-page-style]')
            ).some(function (link) {
                return link.getAttribute('href') === href;
            });

            if (!exists) {
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = href;
                link.setAttribute('data-page-style', '');
                document.head.appendChild(link);
            }
        });
    }

    function loadScript(src) {
        const existing = Array.from(
            document.querySelectorAll('script[data-page-script]')
        ).find(function (script) {
            return (
                script.getAttribute('data-page-script-src') === src ||
                script.getAttribute('src') === src
            );
        });

        if (existing) {
            return Promise.resolve();
        }

        return new Promise(function (resolve, reject) {
            const script = document.createElement('script');
            script.src = src;
            script.async = false;
            script.setAttribute('data-page-script', '');
            script.setAttribute('data-page-script-src', src);
            script.onload = resolve;
            script.onerror = reject;
            document.body.appendChild(script);
        });
    }

    async function ensureAuthLoginAssets() {
        const styles = pageAssetList('login', 'css');

        await Promise.all(styles.map(function (href) {
            const absoluteHref = new URL(href, window.location.href).href;
            const existing = Array.from(
                document.querySelectorAll('link[rel="stylesheet"]')
            ).find(function (link) {
                return link.href === absoluteHref;
            });

            if (existing) return Promise.resolve();

            return new Promise(function (resolve, reject) {
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = href;
                link.setAttribute('data-page-style', '');
                link.onload = resolve;
                link.onerror = reject;
                document.head.appendChild(link);
            });
        }));

        await loadPageScripts('login');
    }

    function releaseAuthLoginStyles() {
        const activeSlug = window.FragCityCurrentPage || 'home';
        const activeStyles = new Set(pageAssetList(activeSlug, 'css'));
        const loginStyles = new Set(pageAssetList('login', 'css'));

        document.querySelectorAll('link[data-page-style]').forEach(function (link) {
            const href = link.getAttribute('href');
            if (loginStyles.has(href) && !activeStyles.has(href)) {
                link.remove();
            }
        });
    }

    function renderAuthLoginFragment(html) {
        if (!authLoginModalContent) {
            throw new Error('Login modal content container not found');
        }

        const parsed = new DOMParser().parseFromString(html, 'text/html');
        const authPage = parsed.querySelector('.auth-page');
        if (!authPage) {
            throw new Error('Login form fragment was not returned');
        }

        authPage.classList.add('auth-page--modal');
        const form = authPage.querySelector('#loginForm');
        if (form) {
            form.setAttribute('action', '?page=login&auth_modal=1');
        }

        authLoginModalContent.replaceChildren(authPage);

        if (typeof window.initAuthPages === 'function') {
            window.initAuthPages();
        }

        window.dispatchEvent(new CustomEvent('content-loaded', {
            detail: { page: 'login-modal' }
        }));
    }

    function showAuthLoginLoadError() {
        if (!authLoginModalContent) return;

        const message = document.createElement('div');
        message.className = 'fc-auth-modal__loading';
        message.setAttribute('role', 'alert');
        message.textContent = 'بارگذاری فرم ورود انجام نشد. اتصال اینترنت را بررسی و دوباره تلاش کنید.';
        authLoginModalContent.replaceChildren(message);
    }

    async function fetchAuthLoginFragment() {
        const requestUrl = new URL('?page=login&auth_modal=1', window.location.href);
        const currentUrl = new URL(window.location.href);

        // A protected-page redirect already stored its intended destination in
        // the session. Otherwise, return the user to the page they opened login from.
        if (currentUrl.searchParams.get('page') !== 'login') {
            let returnTo = currentUrl.pathname + currentUrl.search;
            if (returnTo === '/' || returnTo === '') returnTo = '/index.php';
            requestUrl.searchParams.set('return_to', returnTo);
        }

        const response = await fetch(requestUrl.href, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        if (!response.ok) {
            throw new Error('Login form request failed: HTTP ' + response.status);
        }

        renderAuthLoginFragment(await response.text());
    }

    function updateAuthLoginTriggerState(open) {
        document.querySelectorAll('[data-auth-modal-open]').forEach(function (trigger) {
            trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    function closeAuthLoginModal(restoreFocus) {
        if (!authLoginModal || authLoginModal.hidden) return;

        authLoginModal.hidden = true;
        authLoginModal.setAttribute('aria-hidden', 'true');
        body.classList.remove('fc-auth-modal-open');
        updateAuthLoginTriggerState(false);
        releaseAuthLoginStyles();

        const currentUrl = new URL(window.location.href);
        if (currentUrl.searchParams.get('page') === 'login') {
            currentUrl.searchParams.delete('page');
            currentUrl.searchParams.delete('auth_modal');
            currentUrl.searchParams.delete('return_to');
            const cleanUrl = currentUrl.pathname
                + (currentUrl.searchParams.toString() ? '?' + currentUrl.searchParams.toString() : '')
                + currentUrl.hash;
            window.history.replaceState({}, '', cleanUrl);
            window.FragCityCurrentPage = 'home';
            setActiveLink('home');
        }

        if (restoreFocus !== false && authLoginReturnFocus instanceof HTMLElement
            && authLoginReturnFocus.isConnected) {
            if (mobileNav && mobileNav.contains(authLoginReturnFocus) && mobileOpen) {
                mobileOpen.focus();
            } else {
                authLoginReturnFocus.focus();
            }
        }
        authLoginReturnFocus = null;
    }

    async function openAuthLoginModal(trigger) {
        if (!authLoginModal || !authLoginModalContent) return;
        if (!authLoginModal.hidden) return authLoginOpenPromise;

        authLoginReturnFocus = trigger || document.activeElement;
        closeMobileMenu();

        authLoginModal.hidden = false;
        authLoginModal.setAttribute('aria-hidden', 'false');
        body.classList.add('fc-auth-modal-open');
        updateAuthLoginTriggerState(true);

        const dialog = authLoginModal.querySelector('.fc-auth-modal__dialog');
        if (dialog) dialog.focus();

        authLoginOpenPromise = (async function () {
            try {
                await ensureAuthLoginAssets();
                await fetchAuthLoginFragment();

                const firstInput = authLoginModalContent.querySelector(
                    '#loginId, input:not([type="hidden"]), button, a[href]'
                );
                if (firstInput) firstInput.focus();
            } catch (error) {
                showAuthLoginLoadError();
            } finally {
                authLoginOpenPromise = null;
            }
        })();

        return authLoginOpenPromise;
    }

    if (authLoginModal) {
        authLoginModal.addEventListener('click', function (event) {
            const closeTrigger = event.target.closest('[data-auth-modal-close]');
            if (closeTrigger) {
                event.preventDefault();
                closeAuthLoginModal(true);
                return;
            }

            const pageLink = event.target.closest('a[data-ajax-nav]');
            if (pageLink) {
                closeAuthLoginModal(false);
            }
        });

        authLoginModal.addEventListener('submit', async function (event) {
            const form = event.target.closest('#loginForm');
            if (!form) return;

            event.preventDefault();
            if (form.dataset.modalSubmitting === '1') return;
            form.dataset.modalSubmitting = '1';

            const submitButton = form.querySelector('.btn-auth-submit');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.classList.add('is-loading');
            }

            try {
                const actionUrl = new URL(
                    form.getAttribute('action') || '?page=login&auth_modal=1',
                    window.location.href
                );
                actionUrl.searchParams.set('page', 'login');
                actionUrl.searchParams.set('auth_modal', '1');

                const response = await fetch(actionUrl.href, {
                    method: 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin',
                    redirect: 'follow',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                const finalUrl = new URL(response.url || actionUrl.href, window.location.href);
                const isModalResponse = (
                    finalUrl.searchParams.get('page') === 'login'
                    && finalUrl.searchParams.get('auth_modal') === '1'
                );

                if (response.redirected && !isModalResponse) {
                    window.location.assign(finalUrl.href);
                    return;
                }

                if (!response.ok) {
                    throw new Error('Login submit failed: HTTP ' + response.status);
                }

                renderAuthLoginFragment(await response.text());
                const refreshedInput = authLoginModalContent.querySelector('#loginId');
                if (refreshedInput) refreshedInput.focus();
            } catch (error) {
                showAuthLoginLoadError();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (authLoginModal.hidden) return;

            if (event.key === 'Escape') {
                event.preventDefault();
                closeAuthLoginModal(true);
                return;
            }

            if (event.key !== 'Tab') return;

            const focusable = Array.from(authLoginModal.querySelectorAll(
                'a[href], button:not(:disabled), input:not(:disabled), [tabindex]:not([tabindex="-1"])'
            )).filter(function (element) {
                return element.offsetParent !== null;
            });

            if (!focusable.length) {
                event.preventDefault();
                return;
            }

            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });
    }

    document.addEventListener('click', function (event) {
        const trigger = event.target.closest('[data-auth-modal-open]');
        if (!trigger || !authLoginModal || !authLoginModalContent) return;

        if (
            event.defaultPrevented ||
            event.button !== 0 ||
            event.ctrlKey ||
            event.metaKey ||
            event.shiftKey ||
            event.altKey
        ) {
            return;
        }

        event.preventDefault();
        openAuthLoginModal(trigger);
    });

    function runInlineScripts(scripts) {
        scripts.forEach(function (oldScript) {
            const newScript = document.createElement('script');

            Array.from(oldScript.attributes).forEach(function (attribute) {
                newScript.setAttribute(attribute.name, attribute.value);
            });

            if (!oldScript.src) {
                newScript.textContent = oldScript.textContent;
            }

            document.body.appendChild(newScript);
        });
    }

    function replaceContent(target, html) {
        const template = document.createElement('template');
        template.innerHTML = html.trim();

        const scripts = Array.from(template.content.querySelectorAll('script'));
        scripts.forEach(function (script) {
            script.remove();
        });

        target.replaceChildren(template.content.cloneNode(true));
        runInlineScripts(scripts);
    }

    async function loadPage(url, targetSlug, pushState) {
        const fader = document.getElementById('contentFader');

        if (!fader) {
            window.location.href = url;
            return;
        }

        const slug = targetSlug || slugFromUrl(url);

        /*
         * Some pages (e.g. Weapon Paints) relocate their modals to be
         * direct children of <body> so they can render above
         * everything else. Those elements live outside #contentFader,
         * so swapping this fader's content never touches them. Left
         * behind, a modal that was open (or its backdrop) stays fixed
         * over the next page's content. Since we're leaving this page
         * entirely, clear any of them out first.
         */
        document
            .querySelectorAll('body > .wp-modal, body > .wp-modal-backdrop')
            .forEach(function (leftover) {
                leftover.remove();
            });

        document.body.classList.remove('wp-modal-open', 'modal-open');

        fader.classList.add('fade-out');

        try {
            const response = await fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            /*
             * fetch() silently follows server-side redirects (e.g. an
             * auth guard sending an unauthenticated request from
             * ?page=skins to ?page=login). response.url reflects the
             * FINAL destination in that case. Without this, the
             * address bar, active nav link, and page-specific CSS/JS
             * would all keep pointing at the originally requested
             * page even though different content was actually loaded.
             */
            const finalUrl = response.url || url;
            const requestedFinalSlug = response.redirected
                ? slugFromUrl(finalUrl)
                : slug;
            const shouldOpenLogin = requestedFinalSlug === 'login';
            const finalSlug = shouldOpenLogin ? 'home' : requestedFinalSlug;

            const html = await response.text();
            const documentCopy = new DOMParser().parseFromString(html, 'text/html');
            const incomingContent = documentCopy.querySelector('#contentFader');

            if (!incomingContent) {
                throw new Error('contentFader not found');
            }

            syncPageStyles(finalSlug);
            replaceContent(fader, incomingContent.innerHTML);

            // Home is a full-bleed route; keep its layout state in sync when
            // the page is entered through AJAX navigation as well as direct load.
            const isHomePage = finalSlug === 'home';
            body.classList.toggle('site-body--home', isHomePage);
            const mainContent = document.getElementById('mainContent');
            if (mainContent) {
                mainContent.classList.toggle('main-content--home', isHomePage);
            }
            fader.classList.toggle('content-fader--home', isHomePage);

            await loadPageScripts(finalSlug);

            if (pushState) {
                history.pushState({}, '', finalUrl);
            }

            window.FragCityCurrentPage = finalSlug;
            setActiveLink(finalSlug);

            closeDropdowns();
            closeMobileMenu();

            window.scrollTo({
                top: 0,
                behavior: 'auto'
            });

            window.dispatchEvent(
                new CustomEvent('content-loaded', {
                    detail: { page: finalSlug }
                })
            );

            if (shouldOpenLogin) {
                await openAuthLoginModal();
            }
        } catch (error) {
            window.location.href = url;
            return;
        } finally {
            fader.classList.remove('fade-out');
        }
    }

    document.addEventListener('click', function (event) {
        const link = event.target.closest('a[data-ajax-nav]');
        if (!link) return;

        if (
            event.defaultPrevented ||
            event.button !== 0 ||
            event.ctrlKey ||
            event.metaKey ||
            event.shiftKey ||
            event.altKey
        ) {
            return;
        }

        if (link.target && link.target !== '_self') return;
        if (link.hasAttribute('download')) return;

        const href = link.getAttribute('href');
        if (
            !href ||
            href.startsWith('#') ||
            href.startsWith('javascript:') ||
            href.startsWith('mailto:') ||
            href.startsWith('tel:')
        ) {
            return;
        }

        const url = new URL(href, window.location.href);
        if (url.origin !== window.location.origin) return;

        event.preventDefault();
        loadPage(
            url.href,
            link.dataset.slug || slugFromUrl(url.href),
            true
        );
    });

    window.addEventListener('popstate', function () {
        loadPage(
            window.location.href,
            slugFromUrl(window.location.href),
            false
        );
    });

    setActiveLink(window.FragCityCurrentPage || slugFromUrl(window.location.href));

    /* Persistent performance mode: remove non-essential visual motion/effects. */
    const motionPreferenceKey = 'fragcity:no-animation-mode';

    function syncMotionToggleButtons() {
        const enabled = document.documentElement.classList.contains('fc-no-animation');
        document.querySelectorAll('[data-motion-toggle]').forEach(function (button) {
            button.setAttribute('aria-pressed', enabled ? 'true' : 'false');
            button.classList.toggle('is-active', enabled);
            button.title = enabled
                ? 'Performance Mode is on — click to restore animations'
                : 'Enable Performance Mode';
            const label = button.querySelector('.fc-motion-toggle__label');
            if (label) {
                label.textContent = 'Performance Mode';
            }
            const state = button.querySelector('.fc-motion-toggle__state');
            if (state) state.textContent = enabled ? 'روشن' : 'خاموش';
            const icon = button.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-gauge-high', !enabled);
                icon.classList.toggle('fa-bolt', enabled);
            }
        });
    }

    function setNoAnimationMode(enabled) {
        const root = document.documentElement;
        root.classList.toggle('fc-no-animation', enabled);

        try {
            localStorage.setItem(motionPreferenceKey, enabled ? '1' : '0');
        } catch (error) {
            /* The visual mode still works for the current page when storage is unavailable. */
        }

        window.dispatchEvent(new CustomEvent('fc-no-animation-change', {
            detail: { enabled: enabled }
        }));
        syncMotionToggleButtons();
    }

    document.addEventListener('click', function (event) {
        const toggle = event.target.closest('[data-motion-toggle]');
        if (!toggle) return;
        event.preventDefault();
        setNoAnimationMode(!document.documentElement.classList.contains('fc-no-animation'));
    });

    window.addEventListener('fc-motion-mode-ready', syncMotionToggleButtons);
    syncMotionToggleButtons();

    if (window.FragCityOpenLoginModal) {
        openAuthLoginModal();
    }
})();
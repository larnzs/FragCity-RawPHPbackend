(function () {
    'use strict';

    if (window.fragCityHeaderReady) return;
    window.fragCityHeaderReady = true;

    const body = document.body;

    const moreDropdown = document.getElementById('shockMoreDropdown');
    const userDropdown = document.getElementById('shockUserDropdown');
    const moreButton = document.getElementById('shockMoreButton');
    const userButton = document.getElementById('shockUserButton');

    const mobileNav = document.getElementById('mobileNav');
    const mobileOverlay = document.getElementById('mobileOverlay');
    const mobileOpen = document.getElementById('mobileNavOpen');
    const mobileClose = document.getElementById('mobileNavClose');

    const pageAssets = window.FragCityPageAssets || {};

    function setDropdownState(dropdown, button, open) {
        if (!dropdown) return;

        dropdown.classList.toggle('open', open);

        if (button) {
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    }

    function closeDropdowns() {
        setDropdownState(moreDropdown, moreButton, false);
        setDropdownState(userDropdown, userButton, false);
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

    if (moreButton && moreDropdown) {
        moreButton.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();

            const shouldOpen = !moreDropdown.classList.contains('open');

            setDropdownState(userDropdown, userButton, false);
            setDropdownState(moreDropdown, moreButton, shouldOpen);
        });
    }

    if (userButton && userDropdown) {
        userButton.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();

            const shouldOpen = !userDropdown.classList.contains('open');

            setDropdownState(moreDropdown, moreButton, false);
            setDropdownState(userDropdown, userButton, shouldOpen);
        });
    }

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
        if (moreDropdown && !moreDropdown.contains(event.target)) {
            setDropdownState(moreDropdown, moreButton, false);
        }

        if (userDropdown && !userDropdown.contains(event.target)) {
            setDropdownState(userDropdown, userButton, false);
        }
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

        if (moreButton && moreDropdown) {
            const hasActiveChild = Boolean(
                moreDropdown.querySelector('.active')
            );
            moreButton.classList.toggle('active', hasActiveChild);
        }
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
        const assets = pageAssets[slug];
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

    async function loadPageScripts(slug) {
        const scripts = pageAssetList(slug, 'js');
        for (const src of scripts) {
            await loadScript(src);
        }
    }

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

            const html = await response.text();
            const documentCopy = new DOMParser().parseFromString(html, 'text/html');
            const incomingContent = documentCopy.querySelector('#contentFader');

            if (!incomingContent) {
                throw new Error('contentFader not found');
            }

            syncPageStyles(slug);
            replaceContent(fader, incomingContent.innerHTML);
            await loadPageScripts(slug);

            if (pushState) {
                history.pushState({}, '', url);
            }

            window.FragCityCurrentPage = slug;
            setActiveLink(slug);

            closeDropdowns();
            closeMobileMenu();

            window.scrollTo({
                top: 0,
                behavior: 'auto'
            });

            window.dispatchEvent(
                new CustomEvent('content-loaded', {
                    detail: { page: slug }
                })
            );
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
})();
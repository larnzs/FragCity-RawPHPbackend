/*
 * FragCity Motion Controller
 * Initializes [data-fc-reveal] elements and supports AJAX page changes.
 */
(function () {
    'use strict';

    if (window.FragCityMotion) return;

    const reducedMotion = Boolean(
        window.matchMedia &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
    );

    let observer = null;

    function getObserver() {
        if (observer || !('IntersectionObserver' in window)) {
            return observer;
        }

        observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;

                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            });
        }, {
            threshold: 0.12,
            rootMargin: '0px 0px -40px 0px'
        });

        return observer;
    }

    function prepareElement(element) {
        if (element.dataset.fcMotionReady === 'true') return;

        element.dataset.fcMotionReady = 'true';

        const rawDelay = Number.parseInt(
            element.getAttribute('data-fc-reveal-delay') || '0',
            10
        );
        const delay = Number.isFinite(rawDelay)
            ? Math.min(Math.max(rawDelay, 0), 1200)
            : 0;

        if (delay > 0) {
            element.style.setProperty('--fc-reveal-delay', delay + 'ms');
        }

        if (reducedMotion) {
            element.classList.add('is-visible');
            return;
        }

        const activeObserver = getObserver();

        if (activeObserver) {
            activeObserver.observe(element);
        } else {
            element.classList.add('is-visible');
        }
    }

    function refresh(root) {
        if (reducedMotion) return;

        const scope = root && typeof root.querySelectorAll === 'function'
            ? root
            : document;

        const elements = [];

        if (
            scope.nodeType === 1 &&
            typeof scope.matches === 'function' &&
            scope.matches('[data-fc-reveal]')
        ) {
            elements.push(scope);
        }

        scope.querySelectorAll('[data-fc-reveal]').forEach(function (element) {
            elements.push(element);
        });

        if (elements.length === 0) return;

        document.documentElement.classList.add('fc-motion-ready');
        elements.forEach(prepareElement);
    }

    const api = Object.freeze({
        refresh: refresh,
        reducedMotion: reducedMotion
    });

    window.FragCityMotion = api;

    refresh(document);

    window.addEventListener('content-loaded', function (event) {
        const pageRoot = document.getElementById('contentFader');
        refresh(pageRoot || document);
    });
})();

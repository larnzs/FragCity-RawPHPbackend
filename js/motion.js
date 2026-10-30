/*
 * FragCity Motion Controller v2
 * Automatically enhances page entrances, shared interactive cards, and
 * scroll reveals. Re-initializes safely after the site's AJAX navigation.
 */
(function () {
    'use strict';

    if (window.FragCityMotion && window.FragCityMotion.version === 2) return;

    const root = document.documentElement;
    const reducedMotionQuery = window.matchMedia
        ? window.matchMedia('(prefers-reduced-motion: reduce)')
        : null;
    const reducedMotion = Boolean(reducedMotionQuery && reducedMotionQuery.matches);

    root.classList.add('fc-motion-enabled', 'fc-motion-ready');

    const revealSelector = [
        '.profile-hero','.profile-identity-strip','.profile-progress-item',
        '.stat-card','.profile-inventory-card','.profile-loadout-summary','.profile-settings-card',
        '.wallet-balance-card','.wallet-empty-state','.transaction-item',
        '.serverlist-page .sl-page-head','.serverlist-page .sl-empty-state',
        '.sl-server-card','.sl-empty','.leaderboard-header','.leaderboard-stat-card',
        '.podium-card','.leaderboard-table-scroll','.banlist-header','.banlist-table-wrapper',
        '.banlist-state','.auth-container','.support-header','.support-card',
        '.ticket-create-card','.ticket-list-card','.social-item','.ticket-row',
        '.about-page .section-title','.about-page .about-text','.value-item',
        '.vip-xp-card','.vip-levels-card','.feature-card','.servers-card','.panel-card',
        '.console-card','.instance-item','.wp-section-head','.wp-card','.selection-card',
        '.wp-control-card','.wp-empty-state','[data-fc-reveal]'
    ].join(',');

    const interactiveSelector = [
        '.fc-card--interactive','.wp-card--clickable','.sl-server-card','[data-fc-interactive]'
    ].join(',');

    let intersectionObserver = null;
    let mutationObserver = null;
    let mutationFrame = 0;
    const pendingNodes = new Set();

    function getIntersectionObserver() {
        if (intersectionObserver) return intersectionObserver;
        if (!('IntersectionObserver' in window)) return null;
        intersectionObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                intersectionObserver.unobserve(entry.target);
            });
        }, { threshold: 0.08, rootMargin: '0px 0px -36px 0px' });
        return intersectionObserver;
    }

    function isExcluded(element) {
        if (!(element instanceof Element)) return true;
        return Boolean(
            element.matches(
                'script,style,template,noscript,[data-fc-no-motion],.wp-modal,' +
                '.wp-modal-backdrop,.modal-backdrop,[role="dialog"][aria-hidden="true"]'
            ) ||
            element.closest(
                '[data-fc-no-motion],.wp-modal,.wp-modal-backdrop,.modal-backdrop'
            ) ||
            element.hasAttribute('hidden') ||
            element.getAttribute('aria-hidden') === 'true'
        );
    }

    function siblingDelay(element) {
        const parent = element.parentElement;
        if (!parent) return 0;
        const siblings = Array.from(parent.children).filter(function (sibling) {
            return sibling.matches(revealSelector) && !isExcluded(sibling);
        });
        const index = siblings.indexOf(element);
        return index < 0 ? 0 : (index % 5) * 42;
    }

    function prepareReveal(element) {
        if (isExcluded(element)) return;
        if (!element.hasAttribute('data-fc-reveal')) element.setAttribute('data-fc-reveal', 'up');
        if (element.dataset.fcMotionReady === 'true') {
            if (reducedMotion) element.classList.add('is-visible');
            return;
        }

        element.dataset.fcMotionReady = 'true';
        const authoredDelay = element.getAttribute('data-fc-reveal-delay');
        const parsedDelay = authoredDelay === null ? siblingDelay(element) : Number.parseInt(authoredDelay, 10);
        const delay = Number.isFinite(parsedDelay) ? Math.min(Math.max(parsedDelay, 0), 420) : 0;
        if (delay > 0) element.style.setProperty('--fc-reveal-delay', delay + 'ms');

        if (reducedMotion) {
            element.classList.add('is-visible');
            return;
        }

        const observer = getIntersectionObserver();
        if (observer) observer.observe(element);
        else element.classList.add('is-visible');
    }

    function prepareInteractive(element) {
        if (!isExcluded(element) && element.matches(interactiveSelector)) {
            element.classList.add('fc-motion-interactive');
        }
    }

    function visitTree(rootNode) {
        if (!rootNode) return;
        const candidates = [];
        if (
            rootNode instanceof Element &&
            (rootNode.matches(revealSelector) || rootNode.matches(interactiveSelector))
        ) candidates.push(rootNode);

        if (typeof rootNode.querySelectorAll === 'function') {
            rootNode.querySelectorAll(revealSelector + ',' + interactiveSelector).forEach(function (element) {
                candidates.push(element);
            });
        }

        candidates.forEach(function (element) {
            if (element.matches(interactiveSelector)) prepareInteractive(element);
            if (element.matches(revealSelector)) prepareReveal(element);
        });
    }

    function animatePage(contentRoot) {
        if (!contentRoot || !contentRoot.children) return;
        Array.from(contentRoot.children).forEach(function (pageElement) {
            if (isExcluded(pageElement) || pageElement.tagName === 'SCRIPT' || pageElement.tagName === 'STYLE') return;
            pageElement.classList.remove('fc-motion-page-enter');
            void pageElement.offsetWidth;
            pageElement.classList.add('fc-motion-page-enter');
        });
    }

    function scan(container, animateCurrentPage) {
        const scope = container && typeof container.querySelectorAll === 'function'
            ? container
            : document;
        visitTree(scope);

        if (animateCurrentPage) {
            const contentRoot = scope.id === 'contentFader'
                ? scope
                : document.getElementById('contentFader');
            animatePage(contentRoot);
        }
    }

    function queueMutationNodes(nodes) {
        nodes.forEach(function (node) {
            if (node.nodeType === Node.ELEMENT_NODE) pendingNodes.add(node);
        });
        if (pendingNodes.size === 0 || mutationFrame) return;
        mutationFrame = window.requestAnimationFrame(function () {
            mutationFrame = 0;
            pendingNodes.forEach(visitTree);
            pendingNodes.clear();
        });
    }

    function watchContent(contentRoot) {
        if (!contentRoot || !('MutationObserver' in window)) return;
        if (mutationObserver) mutationObserver.disconnect();
        mutationObserver = new MutationObserver(function (records) {
            records.forEach(function (record) {
                queueMutationNodes(record.addedNodes);
            });
        });
        mutationObserver.observe(contentRoot, { childList: true, subtree: true });
    }

    window.FragCityMotion = Object.freeze({
        version: 2,
        refresh: function (container) { scan(container || document, false); },
        reducedMotion: reducedMotion
    });

    const initialContent = document.getElementById('contentFader');
    scan(document, true);
    watchContent(initialContent);

    window.addEventListener('content-loaded', function () {
        const nextContent = document.getElementById('contentFader');
        scan(nextContent || document, true);
        watchContent(nextContent);
    });
})();

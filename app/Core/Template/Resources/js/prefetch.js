(function () {
    if (window.FlutePrefetch && window.FlutePrefetch.__ready) {
        return;
    }

    var configs = [];
    var seen = new Map();
    var queue = [];
    var active = 0;
    var scanTimer = 0;
    var hoverTimer = 0;
    var pointerLink = null;
    var listenersBound = false;
    var defaults = {
        root: document,
        selector: 'a[href], [hx-get], [data-hx-get]',
        target: 'main',
        swap: 'outerHTML transition:true',
        maxEntries: 48,
        maxConcurrent: 2,
        hoverDelay: 30,
        ttl: 180000,
        visibleLimit: 0,
        visibleDelay: 350,
        visibleIdle: true,
        visibleIdleTimeout: 750,
        sameOriginOnly: true,
        requireBoost: true,
        pathPrefix: '',
        headers: {},
    };
    var blockedPath = /\/(?:logout|delete|destroy|remove|clear|reset|install|uninstall|enable|disable|activate|deactivate|download|export)(?:\/|$)/i;
    var blockedExt = /\.(?:7z|zip|rar|tar|gz|pdf|docx?|xlsx?|csv|xml|json|png|jpe?g|gif|webp|svg|ico|css|js|mjs|map|woff2?|ttf|eot|mp4|webm|mp3|wav)(?:[?#]|$)/i;

    function now() {
        return Date.now ? Date.now() : new Date().getTime();
    }

    function extend(base, extra) {
        var out = {};
        Object.keys(base).forEach(function (key) {
            out[key] = base[key];
        });
        Object.keys(extra || {}).forEach(function (key) {
            out[key] = extra[key];
        });
        return out;
    }

    function connectionAllowsIdle() {
        var connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
        if (!connection) return true;
        if (connection.saveData) return false;
        return !/^(slow-)?2g$/i.test(connection.effectiveType || '');
    }

    function getBoostValue(element) {
        var own = element.getAttribute('hx-boost');
        if (own !== null) return own;
        var parent = element.closest('[hx-boost]');
        return parent ? parent.getAttribute('hx-boost') : null;
    }

    function getHtmxGet(element) {
        return element.getAttribute('hx-get') || element.getAttribute('data-hx-get') || '';
    }

    function getRequestUrl(element) {
        return getHtmxGet(element) || element.getAttribute('href') || '';
    }

    function hasPassiveHtmxTrigger(element) {
        var trigger = (element.getAttribute('hx-trigger') || element.getAttribute('data-hx-trigger') || '').toLowerCase();
        return /\b(?:load|revealed|intersect|every|changed|input)\b/.test(trigger);
    }

    function getTarget(element, config) {
        return element.getAttribute('hx-target') ||
            (element.closest('[hx-target]') && element.closest('[hx-target]').getAttribute('hx-target')) ||
            ('#' + config.target);
    }

    function getSwap(element, config) {
        return element.getAttribute('hx-swap') ||
            (element.closest('[hx-swap]') && element.closest('[hx-swap]').getAttribute('hx-swap')) ||
            config.swap;
    }

    function isPrefetchableElement(element, config) {
        if (!element || element.nodeType !== 1) return false;
        if (element.dataset && element.dataset.noPrefetch !== undefined) return false;
        if (element.hasAttribute('data-modal-open') || element.hasAttribute('data-dropdown-open')) return false;
        if (element.closest('form')) return false;
        if (hasPassiveHtmxTrigger(element)) return false;

        var hxGet = getHtmxGet(element);
        var href = getRequestUrl(element);

        if (!hxGet) {
            if (element.hasAttribute('download')) return false;

            var target = (element.getAttribute('target') || '').toLowerCase();
            if (target && target !== '_self') return false;
        }

        if (!href || href.charAt(0) === '#' || /^javascript:/i.test(href) || /^mailto:|^tel:/i.test(href)) {
            return false;
        }

        if (!hxGet && config.requireBoost) {
            var boost = getBoostValue(element);
            if (!(boost === '' || boost === 'true')) return false;
        } else if (!hxGet && (getBoostValue(element) === 'false' || element.closest('[hx-boost=false]'))) {
            return false;
        }

        return true;
    }

    function normalizeUrl(element, config) {
        if (!isPrefetchableElement(element, config)) return null;

        var url;
        try {
            url = new URL(getRequestUrl(element), location.href);
        } catch (_) {
            return null;
        }

        if (config.sameOriginOnly && url.origin !== location.origin) return null;
        if (url.pathname === location.pathname && url.search === location.search) return null;
        if (url.pathname === '/api' || url.pathname.startsWith('/api/')) return null;
        if (config.pathPrefix && !url.pathname.startsWith(config.pathPrefix)) return null;
        if (blockedPath.test(url.pathname)) return null;
        if (blockedExt.test(url.pathname)) return null;
        if (url.search && /(?:^|[?&])(?:export|download|token|signature|key)=/i.test(url.search)) return null;

        return url;
    }

    function remember(key, state, limit) {
        if (seen.size >= (limit || defaults.maxEntries)) {
            var oldest = seen.keys().next();
            if (!oldest.done) seen.delete(oldest.value);
        }

        seen.set(key, state);
    }

    function shouldSkip(key, config) {
        var state = seen.get(key);
        if (!state) return false;
        if (now() - state.time > config.ttl) {
            seen.delete(key);
            return false;
        }
        if (state.status === 'loading' || state.status === 'done') return true;
        return state.status === 'failed' && now() - state.time < Math.min(config.ttl, 30000);
    }

    function clearState() {
        seen.clear();
        queue = [];
        pointerLink = null;
        clearTimeout(hoverTimer);
    }

    function runNext(config) {
        if (active >= config.maxConcurrent || queue.length === 0) return;

        var item = queue.shift();
        active += 1;

        var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var timeout = controller ? setTimeout(function () {
            controller.abort();
        }, 8000) : 0;

        var headers = extend({
            'Accept': 'text/html, */*;q=0.9',
            'HX-Request': 'true',
            'HX-Target': item.target || config.target,
            'HX-Current-URL': location.href,
            'X-Flute-Prefetch': 'true',
        }, config.headers);

        if (item.boosted) {
            headers['HX-Boosted'] = 'true';
        }

        remember(item.key, { status: 'loading', time: now() }, config.maxEntries);

        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';
        if (csrf) {
            headers['X-CSRF-Token'] = csrf;
        }

        var finish = function () {
            if (timeout) clearTimeout(timeout);
            active -= 1;
            runNext(config);
        };

        fetch(item.url, {
            method: 'GET',
            cache: 'default',
            credentials: 'same-origin',
            headers: headers,
            priority: 'low',
            signal: controller ? controller.signal : undefined,
        }).then(function (response) {
            if (!response.ok || response.redirected) {
                throw new Error('prefetch skipped');
            }

            return response.text();
        }).then(function () {
            remember(item.key, { status: 'done', time: now() }, config.maxEntries);
        }).catch(function () {
            remember(item.key, { status: 'failed', time: now() }, config.maxEntries);
        }).then(finish, finish);
    }

    function enqueue(element, config, reason) {
        var url = normalizeUrl(element, config);
        if (!url) return;

        var target = getTarget(element, config).replace(/^#/, '') || config.target;
        var swap = getSwap(element, config);
        var boosted = !getHtmxGet(element) && (getBoostValue(element) === '' || getBoostValue(element) === 'true');
        var key = target + '|' + (boosted ? 'boosted' : 'htmx-get') + '|' + swap + '|' + url.href;
        if (shouldSkip(key, config)) return;
        if (queue.some(function (item) { return item.key === key; })) return;

        queue.push({
            key: key,
            url: url.href,
            target: target,
            swap: swap,
            boosted: boosted,
            reason: reason || 'hover',
        });

        runNext(config);
    }

    function isVisible(element) {
        if (!element.offsetParent && getComputedStyle(element).position !== 'fixed') return false;
        var rect = element.getBoundingClientRect();
        return rect.width > 0 && rect.height > 0 && rect.bottom >= 0 && rect.top <= window.innerHeight;
    }

    function scanVisible(config) {
        if (!config.visibleLimit || !connectionAllowsIdle() || document.hidden) return;

        var root = typeof config.root === 'string' ? document.querySelector(config.root) : config.root;
        if (!root) return;

        var links = root.querySelectorAll(config.selector);
        var warmed = 0;

        for (var i = 0; i < links.length && warmed < config.visibleLimit; i += 1) {
            if (!isVisible(links[i])) continue;
            enqueue(links[i], config, 'visible');
            warmed += 1;
        }
    }

    function getVisibleDelay() {
        if (!configs.length) return defaults.visibleDelay;

        return Math.min.apply(null, configs.map(function (config) {
            var delay = Number(config.visibleDelay);
            return delay >= 0 ? delay : defaults.visibleDelay;
        }));
    }

    function scheduleVisibleScan() {
        clearTimeout(scanTimer);
        scanTimer = setTimeout(function () {
            configs.forEach(function (config) {
                var run = function () { scanVisible(config); };
                if (config.visibleIdle !== false && typeof requestIdleCallback === 'function') {
                    requestIdleCallback(run, { timeout: config.visibleIdleTimeout || defaults.visibleIdleTimeout });
                } else {
                    run();
                }
            });
        }, getVisibleDelay());
    }

    function findConfig(element) {
        for (var i = configs.length - 1; i >= 0; i -= 1) {
            var config = configs[i];
            var root = typeof config.root === 'string' ? document.querySelector(config.root) : config.root;
            if (!root) continue;
            if (root === document || root === document.body || root.contains(element)) {
                return config;
            }
        }

        return null;
    }

    function closestCandidate(target) {
        return target && target.closest && target.closest(defaults.selector);
    }

    function handlePointerOver(event) {
        var link = closestCandidate(event.target);
        if (!link) return;

        var config = findConfig(link);
        if (!config) return;

        pointerLink = link;
        clearTimeout(hoverTimer);
        if (config.hoverDelay <= 0) {
            enqueue(link, config, 'hover');
            return;
        }

        hoverTimer = setTimeout(function () {
            if (pointerLink === link) enqueue(link, config, 'hover');
        }, config.hoverDelay);
    }

    function handlePointerOut(event) {
        if (!pointerLink) return;
        if (event.relatedTarget && pointerLink.contains(event.relatedTarget)) return;
        pointerLink = null;
        clearTimeout(hoverTimer);
    }

    function handleImmediate(event) {
        var link = closestCandidate(event.target);
        if (!link) return;

        var config = findConfig(link);
        if (config) enqueue(link, config, 'intent');
    }

    function bindListeners() {
        if (listenersBound) return;
        listenersBound = true;

        if (window.PointerEvent) {
            document.addEventListener('pointerover', handlePointerOver, true);
            document.addEventListener('pointerout', handlePointerOut, true);
            document.addEventListener('pointerdown', handleImmediate, true);
        } else {
            document.addEventListener('mouseover', handlePointerOver, true);
            document.addEventListener('mouseout', handlePointerOut, true);
            document.addEventListener('mousedown', handleImmediate, true);
            document.addEventListener('touchstart', handleImmediate, { passive: true, capture: true });
        }
        document.addEventListener('focusin', handleImmediate, true);
        document.body.addEventListener('htmx:afterSettle', scheduleVisibleScan);
        document.body.addEventListener('htmx:afterRequest', function (event) {
            var method = event.detail &&
                event.detail.requestConfig &&
                event.detail.requestConfig.verb;

            if (method && String(method).toUpperCase() !== 'GET') {
                clearState();
            }
        });
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) scheduleVisibleScan();
        });

        if (navigator.serviceWorker) {
            navigator.serviceWorker.addEventListener('message', function (event) {
                if (event.data && event.data.type === 'flute-prefetch-cleared') {
                    clearState();
                    scheduleVisibleScan();
                }
            });
        }
    }

    function configure(options) {
        var config = extend(defaults, options || {});
        config.target = String(config.target || defaults.target).replace(/^#/, '');
        configs.push(config);
        bindListeners();
        scheduleVisibleScan();
    }

    window.FlutePrefetch = {
        __ready: true,
        configure: configure,
        prefetch: function (link) {
            var config = findConfig(link) || configs[0] || defaults;
            enqueue(link, config, 'manual');
        },
        invalidate: clearState,
        stats: function () {
            return { entries: seen.size, queued: queue.length, active: active };
        },
    };

    (window.FlutePrefetchConfig || []).forEach(configure);
    window.FlutePrefetchConfig = {
        push: configure,
    };
})();

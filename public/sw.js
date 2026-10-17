const CACHE_NAME = 'flute-cache-v3';
const OFFLINE_URL = '/offline';

const ASSET_CACHE = 'flute-assets-v1';
const FONT_CACHE = 'flute-fonts-v1';
const PAGE_PREFETCH_CACHE = 'flute-page-prefetch-v1';
const PAGE_PREFETCH_TTL = 300000;
const PAGE_PREFETCH_MAX_ENTRIES = 24;
const PAGE_PREFETCH_MAX_BYTES = 2 * 1024 * 1024;
const pagePrefetchCache = new Map();
const blockedPrefetchPath = /\/(?:logout|delete|destroy|remove|clear|reset|install|uninstall|enable|disable|activate|deactivate|download|export)(?:\/|$)/i;
const blockedPrefetchExt = /\.(?:7z|zip|rar|tar|gz|pdf|docx?|xlsx?|csv|xml|json|png|jpe?g|gif|webp|svg|ico|css|js|mjs|map|woff2?|ttf|eot|mp4|webm|mp3|wav)(?:[?#]|$)/i;
const mutationInvalidationSkip = new Set([
    '/api/analytics',
    '/chat/api/heartbeat',
]);
const mutationPathHint = /\/(?:save|store|create|update|edit|delete|destroy|remove|clear|reset|install|uninstall|enable|disable|activate|deactivate|upload|import|restore|backup|send|execute)(?:\/|$)/i;
const mutationActionHint = /(?:save|store|create|update|edit|delete|destroy|remove|clear|reset|install|uninstall|enable|disable|activate|deactivate|upload|import|restore|backup|send|execute)/i;
const readOnlyActionHint = /(?:handleFilters|clearFilters|setViewMode|switchChannel|handleCheckUpdates)$/i;

function normalizePrefetchTarget(target) {
    return String(target || '').trim().replace(/^#/, '');
}

function pagePrefetchKey(request, url) {
    const target = normalizePrefetchTarget(request.headers.get('HX-Target'));
    const boosted = request.headers.get('HX-Boosted') === 'true' ? '1' : '0';
    return `${self.location.origin}/__flute_prefetch__?u=${encodeURIComponent(url.href)}&t=${encodeURIComponent(target)}&b=${boosted}`;
}

function canCachePagePrefetch(request, url) {
    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return false;
    }

    if (request.headers.get('HX-Request') !== 'true') {
        return false;
    }

    if (url.pathname === '/api' || url.pathname.startsWith('/api/')) {
        return false;
    }

    if (blockedPrefetchPath.test(url.pathname) || blockedPrefetchExt.test(url.pathname)) {
        return false;
    }

    if (url.search && /(?:^|[?&])(?:export|download|token|signature|key)=/i.test(url.search)) {
        return false;
    }

    return true;
}

function makeSyntheticHeaders(headers) {
    const synthetic = new Headers(headers);
    synthetic.delete('content-encoding');
    synthetic.delete('content-length');
    synthetic.delete('transfer-encoding');
    synthetic.delete('connection');

    return synthetic;
}

async function prunePagePrefetchCache() {
    const now = Date.now();
    for (const [key, entry] of pagePrefetchCache.entries()) {
        if (now - entry.time > entry.ttl) {
            pagePrefetchCache.delete(key);
        }
    }

    while (pagePrefetchCache.size >= PAGE_PREFETCH_MAX_ENTRIES) {
        const oldest = pagePrefetchCache.keys().next();
        if (oldest.done) break;
        pagePrefetchCache.delete(oldest.value);
    }

    const cache = await caches.open(PAGE_PREFETCH_CACHE);
    const keys = await cache.keys();

    await Promise.all(keys.map(async (request, index) => {
        const response = await cache.match(request);
        const storedAt = Number(response?.headers.get('X-Flute-Prefetch-Stored-At') || 0);
        const ttl = Number(response?.headers.get('X-Flute-Prefetch-Ttl') || 0);

        if (!response || !storedAt || !ttl || now - storedAt > ttl || index >= PAGE_PREFETCH_MAX_ENTRIES) {
            await cache.delete(request);
        }
    }));
}

async function clearPagePrefetchCache() {
    pagePrefetchCache.clear();
    await caches.delete(PAGE_PREFETCH_CACHE);

    const clientsList = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    clientsList.forEach((client) => {
        client.postMessage({ type: 'flute-prefetch-cleared' });
    });
}

function hasMutationHint(value) {
    const hint = String(value || '');
    if (!hint || readOnlyActionHint.test(hint)) {
        return false;
    }

    return mutationActionHint.test(hint);
}

async function getMutationPayloadHint(request) {
    const contentType = request.headers.get('content-type') || '';

    try {
        if (/json/i.test(contentType)) {
            const data = await request.clone().json();
            return data?.component || data?.action || data?.method || data?.event || '';
        }

        if (/form|urlencoded|multipart/i.test(contentType)) {
            const formData = await request.clone().formData();
            return formData.get('component') ||
                formData.get('action') ||
                formData.get('method') ||
                formData.get('event') ||
                '';
        }

        const text = await request.clone().text();
        if (!text) {
            return '';
        }

        const params = new URLSearchParams(text);
        return params.get('component') ||
            params.get('action') ||
            params.get('method') ||
            params.get('event') ||
            text;
    } catch {
        return '';
    }
}

async function shouldClearPagePrefetchForMutation(request, url) {
    if (mutationInvalidationSkip.has(url.pathname)) {
        return false;
    }

    if (mutationPathHint.test(url.pathname)) {
        return true;
    }

    for (const value of url.searchParams.values()) {
        if (hasMutationHint(value)) {
            return true;
        }
    }

    if (request.headers.get('HX-Request') === 'true') {
        return hasMutationHint(await getMutationPayloadHint(request));
    }

    return true;
}

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE_NAME)
            .then((cache) =>
                cache.addAll([
                    OFFLINE_URL,
                    '/assets/fonts/manrope/Manrope-Regular.woff2',
                    '/assets/fonts/manrope/Manrope-Medium.woff2',
                    '/assets/js/htmx/core.js',
                    '/assets/js/app.js',
                    '/assets/js/libs/jquery.js',
                    '/assets/js/libs/floating.js',
                    '/assets/js/libs/a11y-dialog.js',
                ]),
            )
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    const keepCaches = [CACHE_NAME, ASSET_CACHE, FONT_CACHE];
    event.waitUntil(
        caches
            .keys()
            .then((cacheNames) => {
                return Promise.all(
                    cacheNames
                        .filter((name) => !keepCaches.includes(name))
                        .map((name) => caches.delete(name)),
                );
            })
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        event.waitUntil(self.skipWaiting());
    }
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    if (
        url.origin === self.location.origin &&
        !['GET', 'HEAD', 'OPTIONS'].includes(event.request.method)
    ) {
        event.waitUntil((async () => {
            if (await shouldClearPagePrefetchForMutation(event.request, url)) {
                await clearPagePrefetchCache();
            }
        })());
        return;
    }

    if (canCachePagePrefetch(event.request, url)) {
        const key = pagePrefetchKey(event.request, url);
        const isPrefetch = event.request.headers.get('X-Flute-Prefetch') === 'true';

        if (isPrefetch) {
            event.respondWith(
                fetch(event.request).then(async (response) => {
                    const contentType = response.headers.get('content-type') || '';
                    const cacheControl = response.headers.get('cache-control') || '';
                    const contentLength = Number(response.headers.get('content-length') || 0);
                    const isUncacheable = /(?:^|,)\s*(?:private|no-cache|no-store)\s*(?:,|$)/i.test(cacheControl);
                    const ttl = PAGE_PREFETCH_TTL;

                    if (
                        response.ok &&
                        !response.redirected &&
                        !isUncacheable &&
                        contentType.includes('text/html') &&
                        (!contentLength || contentLength <= PAGE_PREFETCH_MAX_BYTES)
                    ) {
                        const body = await response.clone().text();
                        if (body.length > PAGE_PREFETCH_MAX_BYTES) {
                            return response;
                        }

                        await prunePagePrefetchCache();

                        const headers = makeSyntheticHeaders(response.headers);
                        headers.set('X-Flute-Prefetch-Stored-At', String(Date.now()));
                        headers.set('X-Flute-Prefetch-Ttl', String(ttl));

                        const cache = await caches.open(PAGE_PREFETCH_CACHE);
                        await cache.put(key, new Response(body, {
                            status: response.status,
                            statusText: response.statusText,
                            headers,
                        }));

                        pagePrefetchCache.set(key, {
                            body,
                            headers: Array.from(headers.entries()),
                            status: response.status,
                            statusText: response.statusText,
                            time: Date.now(),
                            ttl,
                        });
                    }

                    return response;
                }),
            );
            return;
        }

        event.respondWith((async () => {
            await prunePagePrefetchCache();

            const memoryHit = pagePrefetchCache.get(key);
            if (memoryHit && Date.now() - memoryHit.time <= memoryHit.ttl) {
                const headers = makeSyntheticHeaders(memoryHit.headers);
                headers.set('X-Flute-Prefetch-Cache', 'HIT');

                return new Response(memoryHit.body, {
                    status: memoryHit.status,
                    statusText: memoryHit.statusText,
                    headers,
                });
            }

            if (memoryHit) {
                pagePrefetchCache.delete(key);
            }

            const cache = await caches.open(PAGE_PREFETCH_CACHE);
            const cached = await cache.match(key);

            if (cached) {
                const headers = makeSyntheticHeaders(cached.headers);
                headers.set('X-Flute-Prefetch-Cache', 'HIT');

                return new Response(await cached.text(), {
                    status: cached.status,
                    statusText: cached.statusText,
                    headers,
                });
            }

            return fetch(event.request);
        })());
        return;
    }

    // Font files — cache-first, long-lived
    if (url.pathname.match(/\.(woff2?|ttf|eot)(\?|$)/)) {
        event.respondWith(
            caches.open(FONT_CACHE).then((cache) =>
                cache.match(event.request).then(
                    (cached) =>
                        cached ||
                        fetch(event.request).then((response) => {
                            if (response.ok) {
                                cache.put(event.request, response.clone());
                            }
                            return response;
                        }),
                ),
            ),
        );
        return;
    }

    // Static assets (CSS/JS with ?v= cache-bust) — cache-first
    if (
        url.origin === self.location.origin &&
        url.pathname.match(/\.(css|js)(\?|$)/) &&
        url.pathname.startsWith('/assets/')
    ) {
        event.respondWith(
            caches.open(ASSET_CACHE).then((cache) =>
                cache.match(event.request).then(
                    (cached) =>
                        cached ||
                        fetch(event.request).then((response) => {
                            if (response.ok) {
                                cache.put(event.request, response.clone());
                            }
                            return response;
                        }),
                ),
            ),
        );
        return;
    }

    // Navigation — network-first with offline fallback
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(() => caches.match(OFFLINE_URL)),
        );
        return;
    }
});

self.addEventListener('push', (event) => {
    event.waitUntil(
        fetch(self.location.origin + '/api/push/pending', {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((r) => (r.ok ? r.json() : null))
            .then((data) => {
                if (!data || !data.title) return;

                let notificationUrl = '/';
                if (data.url) {
                    try {
                        const parsed = new URL(data.url, self.location.origin);
                        if (parsed.origin === self.location.origin) {
                            notificationUrl = parsed.pathname + parsed.search;
                        }
                    } catch {
                        notificationUrl = '/';
                    }
                }

                return self.registration.showNotification(
                    String(data.title).slice(0, 200),
                    {
                        body: String(data.body || '').slice(0, 500),
                        icon: data.icon || '/assets/img/logo.ico',
                        badge: '/assets/img/logo.ico',
                        data: { url: notificationUrl },
                        vibrate: [100, 50, 100],
                        tag: 'flute-' + (data.timestamp || Date.now()),
                        renotify: true,
                    },
                );
            })
            .catch(() => {}),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const rawUrl = event.notification.data?.url || '/';

    let targetUrl;
    try {
        const parsed = new URL(rawUrl, self.location.origin);
        targetUrl =
            parsed.origin === self.location.origin ? parsed.href : '/';
    } catch {
        targetUrl = self.location.origin + '/';
    }

    event.waitUntil(
        clients
            .matchAll({ type: 'window', includeUncontrolled: true })
            .then((windowClients) => {
                for (const client of windowClients) {
                    if (
                        client.url.startsWith(self.location.origin) &&
                        'focus' in client
                    ) {
                        client.navigate(targetUrl);
                        return client.focus();
                    }
                }
                return clients.openWindow(targetUrl);
            }),
    );
});

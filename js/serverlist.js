/*
 * FragCity Public Server Browser
 * Client-side filtering only. Server status/player data comes from the
 * server-side inventory/A2S integration, never from arbitrary browser queries.
 */
(function () {
    'use strict';

    if (window.FragCityServerBrowser) {
        window.FragCityServerBrowser.init();
        return;
    }

    function normalize(value) {
        return String(value || '').trim().toLocaleLowerCase();
    }

    function init() {
        const root = document.getElementById('fragcityServerBrowser');
        if (!root) return;

        const faceitIconBase = String(root.dataset.faceitIconBase || '/img/faceit/')
            .replace(/\/?$/, '/');
        const faceitIconVersion = String(root.dataset.faceitIconVersion || '1');

        if (root.dataset.slReady === '1') return;
        root.dataset.slReady = '1';

        const list = root.querySelector('#serverList');
        const search = root.querySelector('#slSearchInput');
        const sortSelect = root.querySelector('#slSortSelect');
        const noMatch = root.querySelector('#slNoMatch');
        const resultsLabel = root.querySelector('#slResultsLabel');
        const clearButton = root.querySelector('#slClearFilters');
        const resetButtons = Array.from(root.querySelectorAll('[data-sl-reset]'));
        const hideOffline = root.querySelector('[data-sl-hide-offline]');
        const hidePassword = root.querySelector('[data-sl-hide-password]');
        const favoritesOnly = root.querySelector('[data-sl-favorites-only]');
        const mapSearch = root.querySelector('#slMapSearch');
        const favoriteCount = root.querySelector('[data-sl-favorite-count]');
        const toast = root.querySelector('#slToast');
        const viewButtons = Array.from(root.querySelectorAll('[data-sl-view-option]'));
        const playerModal = root.querySelector('#slPlayerModal');
        const playerModalPanel = playerModal ? playerModal.querySelector('.sl-player-modal-panel') : null;
        const playerModalTitle = root.querySelector('#slPlayerModalTitle');
        const playerModalMeta = root.querySelector('#slPlayerModalMeta');
        const playerModalCount = root.querySelector('#slPlayerModalCount');
        const playerLoading = root.querySelector('#slPlayerLoading');
        const playerError = root.querySelector('#slPlayerError');
        const playerErrorMessage = root.querySelector('#slPlayerErrorMessage');
        const playerEmpty = root.querySelector('#slPlayerEmpty');
        const playerTableWrap = root.querySelector('#slPlayerTableWrap');
        const playerTableBody = root.querySelector('#slPlayerTableBody');
        const playerRetry = root.querySelector('#slPlayerRetry');

        if (!list) return;

        // A transformed #contentFader creates a containing block for fixed descendants.
        // Portal the dialog to body so it covers the viewport, not the page wrapper.
        if (playerModal && playerModal.parentElement !== document.body) {
            document.body.appendChild(playerModal);
        }

        let activeVac = 'all';
        let activeGame = 'all';
        let activeMode = 'all';
        let activePlayers = 'all';
        let activeMap = '';
        let toastTimer = 0;
        let modalCloseTimer = 0;
        let modalIsClosing = false;
        let activePlayerButton = null;
        let playerRequestSequence = 0;
        const PLAYER_CACHE_TTL_MS = 30000;
        const playerCache = new Map();
        const playerRequests = new Map();

        function setView(view) {
            const nextView = view === 'grid' ? 'grid' : 'list';
            list.dataset.slView = nextView;

            viewButtons.forEach(function (button) {
                const active = button.dataset.slViewOption === nextView;
                button.classList.toggle('active', active);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
        }

        const getCards = function () {
            return Array.from(list.querySelectorAll('[data-sl-card]'));
        };

        const FAVORITES_STORAGE_KEY = 'fragcity.serverlist.favorites.v1';
        const favoriteIds = new Set();

        try {
            const savedFavorites = JSON.parse(window.localStorage.getItem(FAVORITES_STORAGE_KEY) || '[]');
            if (Array.isArray(savedFavorites)) {
                savedFavorites.forEach(function (id) {
                    if (typeof id === 'string' && id.length > 0 && id.length <= 80) {
                        favoriteIds.add(id);
                    }
                });
            }
        } catch (error) {
            // Local storage can be unavailable in private browsing; favorites still work for this view.
        }

        function persistFavorites() {
            try {
                window.localStorage.setItem(FAVORITES_STORAGE_KEY, JSON.stringify(Array.from(favoriteIds)));
            } catch (error) {
                showToast('علاقه‌مندی‌ها فقط تا پایان این بازدید نگه داشته می‌شوند.');
            }
        }

        function updateFavoriteButtons() {
            getCards().forEach(function (card) {
                const id = String(card.dataset.serverId || '');
                const isFavorite = favoriteIds.has(id);
                card.dataset.favorite = isFavorite ? 'true' : 'false';
                const button = card.querySelector('[data-sl-favorite]');
                if (!button) return;
                button.classList.toggle('is-favorite', isFavorite);
                button.setAttribute('aria-pressed', isFavorite ? 'true' : 'false');
                button.setAttribute('title', isFavorite ? 'حذف از علاقه‌مندی‌ها' : 'افزودن به علاقه‌مندی‌ها');
                button.setAttribute('aria-label', isFavorite ? 'حذف از علاقه‌مندی‌ها' : 'افزودن به علاقه‌مندی‌ها');
                const icon = button.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-solid', isFavorite);
                    icon.classList.toggle('fa-regular', !isFavorite);
                }
            });

            if (favoriteCount) favoriteCount.textContent = String(favoriteIds.size);
        }

        function setFacetSelection(facet, value) {
            root.querySelectorAll('[data-sl-option="' + facet + '"]').forEach(function (button) {
                const active = button.dataset.slValue === value;
                button.classList.toggle('active', active);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
        }

        function cardMatchesFacet(card, excludedFacet) {
            const online = card.dataset.status === 'online';
            const players = Math.max(0, Number(card.dataset.players) || 0);
            const maxPlayers = Math.max(0, Number(card.dataset.maxPlayers) || 0);

            if (excludedFacet !== 'display') {
                if (hideOffline && hideOffline.checked && !online) return false;
                if (hidePassword && hidePassword.checked && card.dataset.password === 'true') return false;
                if (favoritesOnly && favoritesOnly.checked && !favoriteIds.has(String(card.dataset.serverId || ''))) return false;
            }

            if (excludedFacet !== 'vac' && activeVac !== 'all' && card.dataset.vac !== activeVac) return false;
            if (excludedFacet !== 'game' && activeGame !== 'all' && card.dataset.game !== activeGame) return false;
            if (excludedFacet !== 'mode' && activeMode !== 'all' && normalize(card.dataset.mode) !== activeMode) return false;

            if (excludedFacet !== 'players') {
                if (activePlayers === 'playing' && (!online || players <= 0)) return false;
                if (activePlayers === 'slots' && (!online || maxPlayers <= players)) return false;
                if (activePlayers === 'empty' && (!online || players !== 0)) return false;
            }

            if (excludedFacet !== 'map' && activeMap !== '' && normalize(card.dataset.map) !== activeMap) return false;

            const query = normalize(search ? search.value : '');
            if (query !== '') {
                const searchable = [
                    card.dataset.name,
                    card.dataset.map,
                    card.dataset.mode,
                    card.dataset.address
                ].map(normalize).join(' ');
                if (!searchable.includes(query)) return false;
            }

            return true;
        }

        function updateFacetCounts() {
            root.querySelectorAll('[data-sl-option]').forEach(function (button) {
                const facet = button.dataset.slOption || '';
                const value = button.dataset.slValue || 'all';
                const count = getCards().filter(function (card) {
                    if (!cardMatchesFacet(card, facet)) return false;
                    if (value === 'all') return true;
                    if (facet === 'vac') return card.dataset.vac === value;
                    if (facet === 'game') return card.dataset.game === value;
                    if (facet === 'mode') return normalize(card.dataset.mode) === value;
                    if (facet === 'players') {
                        const online = card.dataset.status === 'online';
                        const players = Math.max(0, Number(card.dataset.players) || 0);
                        const maxPlayers = Math.max(0, Number(card.dataset.maxPlayers) || 0);
                        if (value === 'playing') return online && players > 0;
                        if (value === 'slots') return online && maxPlayers > players;
                        if (value === 'empty') return online && players === 0;
                    }
                    return false;
                }).length;
                const output = button.querySelector('[data-sl-count]');
                if (output) output.textContent = count.toLocaleString('fa-IR');
                button.classList.toggle('is-zero', count === 0);
            });

            const mapQuery = normalize(mapSearch ? mapSearch.value : '');
            const mapButtons = Array.from(root.querySelectorAll('[data-sl-map-option]'));
            mapButtons.forEach(function (button) {
                const value = button.dataset.slMapOption || '';
                const matching = getCards().filter(function (card) {
                    return cardMatchesFacet(card, 'map') && normalize(card.dataset.map) === value;
                }).length;
                const output = button.querySelector('[data-sl-map-count]');
                if (output) output.textContent = matching.toLocaleString('fa-IR');
                const text = normalize(button.textContent);
                button.hidden = (mapQuery !== '' && !text.includes(mapQuery)) || matching === 0;
                const active = activeMap === value;
                button.classList.toggle('active', active);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
            });

            const visibleMapButtons = mapButtons.filter(function (button) {
                return !button.hidden;
            });
            const mapEmpty = root.querySelector('[data-sl-map-empty]');
            if (mapEmpty) mapEmpty.hidden = visibleMapButtons.length !== 0;
        }

        function setPlayerModalSection(section) {
            if (playerLoading) playerLoading.hidden = section !== 'loading';
            if (playerError) playerError.hidden = section !== 'error';
            if (playerEmpty) playerEmpty.hidden = section !== 'empty';
            if (playerTableWrap) playerTableWrap.hidden = section !== 'table';
        }

        function formatPlayerDuration(value) {
            const total = Math.max(0, Math.floor(Number(value) || 0));
            const hours = Math.floor(total / 3600);
            const minutes = Math.floor((total % 3600) / 60);
            const seconds = total % 60;

            if (hours > 0) return hours + ' ساعت و ' + minutes + ' دقیقه';
            if (minutes > 0) return minutes + ' دقیقه و ' + seconds + ' ثانیه';
            return seconds + ' ثانیه';
        }

        function appendPlayerCell(row, value, className, direction) {
            const cell = document.createElement('td');
            if (className) cell.className = className;
            if (direction) cell.dir = direction;
            cell.textContent = value;
            row.appendChild(cell);
            return cell;
        }

        function faceitIconUrl(level) {
            const safeLevel = Math.min(11, Math.max(0, Math.trunc(Number(level) || 0)));
            return faceitIconBase
                + encodeURIComponent(String(safeLevel))
                + '.svg?v='
                + encodeURIComponent(faceitIconVersion);
        }

        function createFaceitLevelIcon(level) {
            const icon = document.createElement('img');
            icon.className = 'sl-faceit-level-icon';
            icon.alt = 'FACEIT Level ' + level;
            icon.width = 30;
            icon.height = 30;
            icon.loading = 'eager';
            icon.decoding = 'async';
            icon.setAttribute('draggable', 'false');
            icon.src = faceitIconUrl(level);

            // Never replace a failed image with text or a badge.
            icon.addEventListener('error', function () {
                icon.remove();
            }, { once: true });

            return icon;
        }

        function updateCardFaceitAverage(button, payload) {
            const card = button ? button.closest('[data-sl-card]') : null;
            const output = card ? card.querySelector('[data-sl-faceit-average]') : null;
            const averageRow = card ? card.querySelector('[data-sl-faceit-average-row]') : null;
            if (!output) return;

            const players = Array.isArray(payload && payload.players) ? payload.players : [];
            const seenPlayers = new Set();
            const levels = [];

            players.forEach(function (player, index) {
                if (!player || typeof player !== 'object') return;

                const level = Number(player.faceit_level);
                if (!Number.isInteger(level) || level < 1 || level > 10) return;

                const steamId = String(player.steam_id || '').trim();
                const slot = player.index !== undefined && player.index !== null
                    ? String(player.index)
                    : '';
                const key = steamId
                    ? 'steam:' + steamId
                    : (slot !== '' ? 'slot:' + slot : 'row:' + index);

                if (seenPlayers.has(key)) return;
                seenPlayers.add(key);
                levels.push(level);
            });

            if (levels.length === 0) {
                output.removeAttribute('src');
                output.dataset.averageState = 'empty';
                output.dataset.averageValue = '';
                output.style.visibility = 'hidden';
                if (averageRow) averageRow.hidden = true;
                return;
            }

            const average = levels.reduce(function (total, level) {
                return total + level;
            }, 0) / levels.length;
            const iconLevel = Math.min(10, Math.max(1, Math.floor(average + 0.5)));

            output.src = faceitIconUrl(iconLevel);
            output.alt = 'Average FACEIT level icon';
            output.dataset.averageState = 'ready';
            output.dataset.averageValue = average.toFixed(2);
            output.dataset.averageLevel = String(iconLevel);
            output.setAttribute(
                'aria-label',
                'Average FACEIT level ' + average.toFixed(2)
                    + ', represented by level ' + iconLevel + ' icon'
            );
            output.style.visibility = 'visible';
            if (averageRow) averageRow.hidden = false;
        }

        function renderPlayers(payload) {
            const players = Array.isArray(payload.players) ? payload.players : [];
            if (playerTableBody) playerTableBody.replaceChildren();
            if (playerModalCount) playerModalCount.textContent = players.length + ' بازیکن';

            if (players.length === 0) {
                setPlayerModalSection('empty');
                return;
            }

            players.forEach(function (player) {
                const row = document.createElement('tr');

                appendPlayerCell(row, String(player.name || 'Unknown'), 'sl-player-name');
                const steamStatus = String(player.steam_id_status || 'not_found');
                const steamStatusLabels = {
                    not_found: 'SteamID پیدا نشد',
                    ambiguous: 'نام تکراری است',
                    simpleadmin_unavailable: 'SimpleAdmin در دسترس نیست'
                };
                const steamCell = appendPlayerCell(
                    row,
                    player.steam_id ? String(player.steam_id) : (steamStatusLabels[steamStatus] || 'نامشخص'),
                    'sl-player-steamid',
                    player.steam_id ? 'ltr' : null
                );
                if (!player.steam_id) {
                    steamCell.classList.add('sl-player-unavailable');
                    steamCell.title = steamStatus === 'ambiguous'
                        ? 'این نام در SimpleAdmin به بیش از یک SteamID تعلق دارد؛ برای جلوگیری از نمایش رتبهٔ اشتباه، Level نمایش داده نمی‌شود.'
                        : (steamStatus === 'simpleadmin_unavailable'
                            ? 'سرویس جست‌وجوی SimpleAdmin موقتاً در دسترس نیست.'
                            : 'برای این نام، SteamID یکتایی در سابقهٔ SimpleAdmin پیدا نشد.');
                }

                appendPlayerCell(row, formatPlayerDuration(player.connected_seconds), 'sl-player-duration');
                appendPlayerCell(row, Number.isFinite(Number(player.score)) ? String(Math.trunc(Number(player.score))) : '—', 'sl-player-score', 'ltr');

                const faceitCell = document.createElement('td');
                faceitCell.className = 'sl-player-faceit';
                const level = Number(player.faceit_level);

                if (Number.isInteger(level) && level >= 1 && level <= 10) {
                    faceitCell.appendChild(createFaceitLevelIcon(level));
                } else if (String(player.faceit_status || '') === 'unranked') {
                    faceitCell.appendChild(createFaceitLevelIcon(0));
                }
                // Missing/unavailable FACEIT profiles stay visually empty:
                // no status text, badge, background, or replacement label.
                row.appendChild(faceitCell);
                if (playerTableBody) playerTableBody.appendChild(row);
            });

            setPlayerModalSection('table');
        }

        function finishPlayerModalClose() {
            if (!playerModal) return;
            window.clearTimeout(modalCloseTimer);
            playerModal.hidden = true;
            playerModal.classList.remove('is-open', 'is-closing');
            playerModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('sl-player-modal-open');
            modalIsClosing = false;

            if (activePlayerButton && document.contains(activePlayerButton)) {
                activePlayerButton.focus();
            }
            activePlayerButton = null;
        }

        function closePlayerModal() {
            if (!playerModal || playerModal.hidden || modalIsClosing) return;

            // Keep in-flight prefetch requests alive so their responses can warm the cache.
            ++playerRequestSequence;

            playerModal.setAttribute('aria-hidden', 'true');
            if (document.documentElement.classList.contains('fc-no-animation')) {
                finishPlayerModalClose();
                return;
            }

            modalIsClosing = true;
            playerModal.classList.remove('is-open');
            playerModal.classList.add('is-closing');
            window.clearTimeout(modalCloseTimer);
            modalCloseTimer = window.setTimeout(finishPlayerModalClose, 270);
        }

        async function fetchPlayersData(serverId, forceRefresh) {
            const cacheEntry = playerCache.get(serverId);
            if (!forceRefresh && cacheEntry && Date.now() - cacheEntry.savedAt < PLAYER_CACHE_TTL_MS) {
                return cacheEntry.data;
            }

            const pendingRequest = playerRequests.get(serverId);
            if (pendingRequest && !forceRefresh) {
                return pendingRequest.promise;
            }
            if (pendingRequest && forceRefresh) {
                pendingRequest.controller.abort();
            }

            const controller = new AbortController();
            const requestEntry = { promise: null, controller: controller };
            const promise = (async function () {
                const response = await fetch(
                    '/api/public-server-players.php?server_id=' + encodeURIComponent(serverId),
                    {
                        method: 'GET',
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        signal: controller.signal
                    }
                );

                let result;
                try {
                    result = await response.json();
                } catch (parseError) {
                    throw new Error('پاسخ سرور قابل خواندن نیست.');
                }

                if (!response.ok || !result || result.success !== true || !result.data) {
                    throw new Error('سرور در حال حاضر اطلاعات بازیکنان را ارائه نمی‌کند.');
                }

                // Cache even when the modal has been closed while the request ran.
                playerCache.set(serverId, { data: result.data, savedAt: Date.now() });
                return result.data;
            })();
            requestEntry.promise = promise;
            playerRequests.set(serverId, requestEntry);

            try {
                return await promise;
            } finally {
                if (playerRequests.get(serverId) === requestEntry) {
                    playerRequests.delete(serverId);
                }
            }
        }

        async function loadPlayers(serverId, forceRefresh) {
            if (!playerModal || !/^[a-f0-9]{16}$/i.test(serverId)) return;

            const requestSequence = ++playerRequestSequence;
            const cacheEntry = playerCache.get(serverId);
            if (!forceRefresh && cacheEntry && Date.now() - cacheEntry.savedAt < PLAYER_CACHE_TTL_MS) {
                renderPlayers(cacheEntry.data);
                return;
            }

            setPlayerModalSection('loading');
            if (playerModalCount) playerModalCount.textContent = '...';

            try {
                const data = await fetchPlayersData(serverId, forceRefresh);
                if (requestSequence !== playerRequestSequence || playerModal.hidden) return;
                updateCardFaceitAverage(
                    activePlayerButton,
                    data
                );
                renderPlayers(data);
            } catch (error) {
                if (error && error.name === 'AbortError') return;
                if (requestSequence !== playerRequestSequence || playerModal.hidden) return;
                if (playerModalCount) playerModalCount.textContent = '—';
                if (playerErrorMessage) {
                    playerErrorMessage.textContent = error instanceof Error
                        ? error.message
                        : 'ارتباط با سرویس بازیکنان برقرار نشد.';
                }
                setPlayerModalSection('error');
            }
        }

        function prefetchPlayers(button) {
            if (!button || button.disabled) return;

            const serverId = String(button.dataset.serverId || '').toLowerCase();
            if (!/^[a-f0-9]{16}$/.test(serverId)) return;

            const cacheEntry = playerCache.get(serverId);
            if (cacheEntry && Date.now() - cacheEntry.savedAt < PLAYER_CACHE_TTL_MS) {
                if (cacheEntry.data) updateCardFaceitAverage(button, cacheEntry.data);
                return;
            }
            if (playerRequests.has(serverId)) return;

            // Start fetching on hover/focus, before the player modal is opened.
            void fetchPlayersData(serverId, false).then(function (data) {
                updateCardFaceitAverage(button, data);
            }).catch(function () {
                // Prefetch is silent; opening the modal displays any request error.
            });
        }

        async function hydrateServerFaceitAverages() {
            const buttons = Array.from(list.querySelectorAll('[data-sl-players]:not(:disabled)'));
            if (buttons.length === 0) return;

            let nextIndex = 0;
            const concurrency = Math.min(2, buttons.length);

            async function worker() {
                while (nextIndex < buttons.length) {
                    const button = buttons[nextIndex++];
                    if (!button || !button.isConnected) continue;

                    const serverId = String(button.dataset.serverId || '').toLowerCase();
                    if (!/^[a-f0-9]{16}$/.test(serverId)) continue;

                    const cacheEntry = playerCache.get(serverId);
                    if (cacheEntry && Date.now() - cacheEntry.savedAt < PLAYER_CACHE_TTL_MS) {
                        updateCardFaceitAverage(button, cacheEntry.data);
                        continue;
                    }

                    const card = button.closest('[data-sl-card]');
                    const output = card ? card.querySelector('[data-sl-faceit-average]') : null;
                    const averageRow = card ? card.querySelector('[data-sl-faceit-average-row]') : null;
                    if (output) {
                        output.removeAttribute('src');
                        output.dataset.averageState = 'loading';
                        output.style.visibility = 'hidden';
                    }
                    if (averageRow) averageRow.hidden = true;

                    try {
                        const data = await fetchPlayersData(serverId, false);
                        updateCardFaceitAverage(button, data);
                    } catch (error) {
                        if (output) {
                            output.removeAttribute('src');
                            output.dataset.averageState = 'unavailable';
                            output.style.visibility = 'hidden';
                        }
                        if (averageRow) averageRow.hidden = true;
                    }
                }
            }

            await Promise.all(Array.from({ length: concurrency }, worker));
        }

        function openPlayerModal(button) {
            if (!playerModal) return;

            const serverId = String(button.dataset.serverId || '').toLowerCase();
            const serverName = String(button.dataset.serverName || 'CS2 Community Server');
            const serverAddress = String(button.dataset.serverAddress || '');

            if (!/^[a-f0-9]{16}$/.test(serverId)) {
                showToast('شناسه سرور معتبر نیست');
                return;
            }

            activePlayerButton = button;
            if (playerModalTitle) playerModalTitle.textContent = 'بازیکنان ' + serverName;
            if (playerModalMeta) playerModalMeta.textContent = serverAddress;
            if (playerModalCount) playerModalCount.textContent = '...';
            if (playerTableBody) playerTableBody.replaceChildren();
            setPlayerModalSection('loading');

            window.clearTimeout(modalCloseTimer);
            modalIsClosing = false;
            playerModal.classList.remove('is-closing', 'is-open');
            playerModal.hidden = false;
            playerModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('sl-player-modal-open');

            void playerModal.offsetWidth;
            window.requestAnimationFrame(function () {
                if (!playerModal.hidden) playerModal.classList.add('is-open');
                if (playerModalPanel) playerModalPanel.focus();
            });

            loadPlayers(serverId, false);
        }

        function showToast(message) {
            if (!toast) return;
            toast.textContent = message;
            toast.classList.add('is-visible');
            window.clearTimeout(toastTimer);
            toastTimer = window.setTimeout(function () {
                toast.classList.remove('is-visible');
            }, 1700);
        }

        function compareCards(a, b) {
            const sort = sortSelect ? sortSelect.value : 'default';

            if (sort === 'players-desc') {
                return Number(b.dataset.players || 0) - Number(a.dataset.players || 0);
            }

            if (sort === 'name-asc') {
                return (a.dataset.name || '').localeCompare(b.dataset.name || '', 'fa');
            }

            return 0;
        }

        function applySort() {
            if (!sortSelect) return;
            list.querySelectorAll('[data-sl-group]').forEach(function (group) {
                const grid = group.querySelector('.sl-server-grid');
                if (!grid) return;
                Array.from(grid.querySelectorAll('[data-sl-card]'))
                    .sort(compareCards)
                    .forEach(function (card) {
                        grid.appendChild(card);
                    });
            });
        }

        function applyFilters() {
            let visible = 0;

            list.querySelectorAll('[data-sl-group]').forEach(function (group) {
                let groupVisible = 0;

                group.querySelectorAll('[data-sl-card]').forEach(function (card) {
                    const isVisible = cardMatchesFacet(card, null);
                    card.hidden = !isVisible;
                    if (isVisible) {
                        groupVisible++;
                        visible++;
                    }
                });

                group.hidden = groupVisible === 0;
                const total = group.querySelector('.sl-group-total');
                if (total) total.textContent = groupVisible.toLocaleString('fa-IR') + ' سرور';
            });

            const totalCards = getCards().length;
            if (noMatch) noMatch.hidden = totalCards === 0 || visible !== 0;
            if (resultsLabel) resultsLabel.textContent = 'نمایش ' + visible.toLocaleString('fa-IR') + ' سرور';
            updateFacetCounts();
        }

        if (viewButtons.length) {
            setView(list.dataset.slView);
            viewButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    setView(button.dataset.slViewOption);
                });
            });
        }

        [search, hideOffline, hidePassword, favoritesOnly].forEach(function (control) {
            if (!control) return;
            control.addEventListener(control === search ? 'input' : 'change', applyFilters);
        });

        if (mapSearch) {
            mapSearch.addEventListener('input', updateFacetCounts);
        }

        root.addEventListener('click', function (event) {
            const option = event.target.closest('[data-sl-option]');
            if (option && root.contains(option)) {
                const facet = option.dataset.slOption || '';
                const value = option.dataset.slValue || 'all';
                if (facet === 'vac') activeVac = value;
                if (facet === 'game') activeGame = value;
                if (facet === 'mode') activeMode = value;
                if (facet === 'players') activePlayers = value;
                setFacetSelection(facet, value);
                applyFilters();
                return;
            }

            const mapOption = event.target.closest('[data-sl-map-option]');
            if (mapOption && root.contains(mapOption)) {
                const value = mapOption.dataset.slMapOption || '';
                activeMap = activeMap === value ? '' : value;
                applyFilters();
            }
        });

        if (sortSelect) {
            sortSelect.addEventListener('change', function () {
                applySort();
                applyFilters();
            });
        }

        function resetFilters() {
            activeVac = 'all';
            activeGame = 'all';
            activeMode = 'all';
            activePlayers = 'all';
            activeMap = '';
            if (hideOffline) hideOffline.checked = true;
            if (hidePassword) hidePassword.checked = false;
            if (favoritesOnly) favoritesOnly.checked = false;
            if (search) search.value = '';
            if (mapSearch) mapSearch.value = '';
            if (sortSelect) sortSelect.value = 'default';

            setFacetSelection('vac', 'all');
            setFacetSelection('game', 'all');
            setFacetSelection('mode', 'all');
            setFacetSelection('players', 'all');
            applySort();
            applyFilters();
        }

        if (clearButton) clearButton.addEventListener('click', resetFilters);
        resetButtons.forEach(function (button) {
            button.addEventListener('click', resetFilters);
        });

        list.addEventListener('pointerover', function (event) {
            const playersButton = event.target.closest('[data-sl-players]');
            if (!playersButton || (event.relatedTarget && playersButton.contains(event.relatedTarget))) return;
            prefetchPlayers(playersButton);
        });

        list.addEventListener('focusin', function (event) {
            const playersButton = event.target.closest('[data-sl-players]');
            if (playersButton) prefetchPlayers(playersButton);
        });

        list.addEventListener('click', function (event) {
            const favoriteButton = event.target.closest('[data-sl-favorite]');
            if (favoriteButton) {
                const card = favoriteButton.closest('[data-sl-card]');
                const serverId = card ? String(card.dataset.serverId || '') : '';
                if (!serverId) return;

                if (favoriteIds.has(serverId)) favoriteIds.delete(serverId);
                else favoriteIds.add(serverId);

                persistFavorites();
                updateFavoriteButtons();
                applyFilters();
                showToast(favoriteIds.has(serverId) ? 'به علاقه‌مندی‌ها اضافه شد' : 'از علاقه‌مندی‌ها حذف شد');
                return;
            }

            const playersButton = event.target.closest('[data-sl-players]');
            if (playersButton && !playersButton.disabled) {
                openPlayerModal(playersButton);
                return;
            }

            const copyButton = event.target.closest('[data-sl-copy]');
            if (!copyButton) return;

            const address = copyButton.getAttribute('data-sl-copy') || '';
            if (!address) return;

            function copied() {
                copyButton.classList.add('is-copied');
                const icon = copyButton.querySelector('i');
                if (icon) {
                    icon.classList.remove('fa-copy');
                    icon.classList.add('fa-check');
                }
                showToast('آدرس سرور کپی شد');
                window.setTimeout(function () {
                    copyButton.classList.remove('is-copied');
                    if (icon) {
                        icon.classList.remove('fa-check');
                        icon.classList.add('fa-copy');
                    }
                }, 1300);
            }

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(address).then(copied).catch(function () {
                    showToast('کپی آدرس انجام نشد');
                });
                return;
            }

            const temporaryInput = document.createElement('textarea');
            temporaryInput.value = address;
            temporaryInput.setAttribute('readonly', '');
            temporaryInput.style.position = 'fixed';
            temporaryInput.style.opacity = '0';
            document.body.appendChild(temporaryInput);
            temporaryInput.select();

            try {
                const success = document.execCommand('copy');
                if (success) copied();
                else showToast('کپی آدرس انجام نشد');
            } catch (error) {
                showToast('کپی آدرس انجام نشد');
            } finally {
                temporaryInput.remove();
            }
        });

        if (playerModal) {
            playerModal.addEventListener('click', function (event) {
                if (event.target.closest('[data-sl-modal-dismiss]')) {
                    closePlayerModal();
                }
            });
        }

        function cleanupPlayerModalPortal() {
            if (root.isConnected) return;
            ++playerRequestSequence;
            // Pending prefetches are allowed to finish and populate their short-lived cache.
            window.clearTimeout(modalCloseTimer);
            if (playerModal) playerModal.remove();
            document.body.classList.remove('sl-player-modal-open');
            window.removeEventListener('content-loaded', cleanupPlayerModalPortal);
        }
        window.addEventListener('content-loaded', cleanupPlayerModalPortal);

        if (playerRetry) {
            playerRetry.addEventListener('click', function () {
                const serverId = activePlayerButton ? String(activePlayerButton.dataset.serverId || '') : '';
                loadPlayers(serverId, true);
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && playerModal && !playerModal.hidden) {
                event.preventDefault();
                closePlayerModal();
            }
        });

        root.addEventListener('keydown', function (event) {
            if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey) return;
            const target = event.target;
            if (target && /INPUT|TEXTAREA|SELECT|BUTTON/.test(target.tagName)) return;
            event.preventDefault();
            if (search) search.focus();
        });

        root.querySelectorAll('a[href="#slBrowserControls"]').forEach(function (link) {
            link.addEventListener('click', function (event) {
                const target = root.querySelector('#slBrowserControls');
                if (!target) return;
                event.preventDefault();
                target.scrollIntoView({ behavior: document.documentElement.classList.contains('fc-no-animation') ? 'auto' : 'smooth', block: 'start' });
            });
        });

        updateFavoriteButtons();
        setFacetSelection('vac', 'all');
        setFacetSelection('game', 'all');
        setFacetSelection('mode', 'all');
        setFacetSelection('players', 'all');
        applySort();
        applyFilters();

        // Populate each card average in the background with a small request pool.
        // The same payload cache is reused when a visitor opens that server's player modal.
        void hydrateServerFaceitAverages();
    }

    window.FragCityServerBrowser = { init: init };
    document.addEventListener('DOMContentLoaded', init);
    window.addEventListener('content-loaded', init);

    if (document.readyState !== 'loading') {
        init();
    }
})();

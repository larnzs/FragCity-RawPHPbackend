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

        const faceitIconBase = String(root.dataset.faceitIconBase || '/img/icons/faceit/')
            .replace(/\/?$/, '/');
        const faceitIconVersion = String(root.dataset.faceitIconVersion || '1');

        if (root.dataset.slReady === '1') return;
        root.dataset.slReady = '1';

        const list = root.querySelector('#serverList');
        const pills = root.querySelector('#slFilterPills');
        const search = root.querySelector('#slSearchInput');
        const modeSelect = root.querySelector('#slModeFilter');
        const mapSelect = root.querySelector('#slMapFilter');
        const sortSelect = root.querySelector('#slSortSelect');
        const noMatch = root.querySelector('#slNoMatch');
        const resultsLabel = root.querySelector('#slResultsLabel');
        const clearButton = root.querySelector('#slClearFilters');
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

        let statusFilter = 'online';
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

        function createFaceitLevelIcon(level) {
            const icon = document.createElement('img');
            icon.className = 'sl-faceit-level-icon';
            icon.alt = 'FACEIT Level ' + level;
            icon.width = 30;
            icon.height = 30;
            icon.loading = 'eager';
            icon.decoding = 'async';
            icon.setAttribute('draggable', 'false');
            icon.src = faceitIconBase + encodeURIComponent(String(level)) + '.svg?v=' + encodeURIComponent(faceitIconVersion);

            icon.addEventListener('error', function () {
                const fallback = document.createElement('span');
                fallback.className = 'sl-faceit-level-icon-fallback';
                fallback.textContent = 'L' + level;
                fallback.setAttribute('aria-label', 'FACEIT Level ' + level);
                icon.replaceWith(fallback);
            }, { once: true });

            return icon;
        }

        function updateCardFaceitAverage(button, payload) {
            const card = button ? button.closest('[data-sl-card]') : null;
            const output = card ? card.querySelector('[data-sl-faceit-average]') : null;
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
                output.textContent = '—';
                output.dataset.averageState = 'empty';
                output.title = players.length === 0
                    ? 'در حال حاضر بازیکنی آنلاین نیست.'
                    : 'بازیکن آنلاین دارای FACEIT Level معتبر پیدا نشد.';
                return;
            }

            const average = levels.reduce(function (total, level) {
                return total + level;
            }, 0) / levels.length;

            output.textContent = average.toFixed(1);
            output.dataset.averageState = 'ready';
            output.title = 'میانگین ' + average.toFixed(2) + ' از ' + levels.length
                + ' بازیکن دارای Level معتبر، از مجموع ' + players.length + ' بازیکن آنلاین';
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
                    const levelBadge = document.createElement('span');
                    levelBadge.className = 'sl-faceit-level-badge';
                    levelBadge.title = 'FACEIT Level ' + level;
                    levelBadge.setAttribute('aria-label', 'FACEIT Level ' + level);

                    levelBadge.appendChild(createFaceitLevelIcon(level));
                    faceitCell.appendChild(levelBadge);
                } else {
                    const pending = document.createElement('span');
                    pending.className = 'sl-faceit-pending';
                    const faceitStatus = String(player.faceit_status || 'unknown');
                    const faceitStatusLabels = {
                        steam_id_missing: 'SteamID موجود نیست',
                        not_found: 'SteamID پیدا نشد',
                        ambiguous: 'نام تکراری است',
                        simpleadmin_unavailable: 'SimpleAdmin در دسترس نیست',
                        api_not_configured: 'FACEIT API تنظیم نشده',
                        profile_not_found: 'اکانت FACEIT پیدا نشد',
                        no_cs2_profile: 'پروفایل CS2 ندارد',
                        unranked: 'بدون Level',
                        api_unavailable: 'FACEIT API در دسترس نیست'
                    };

                    if (faceitStatus === 'unranked') {
                        const unrankedBadge = document.createElement('span');
                        unrankedBadge.className = 'sl-faceit-level-badge sl-faceit-level-badge--unranked';
                        const unrankedIcon = createFaceitLevelIcon(0);
                        unrankedIcon.alt = 'FACEIT unranked';
                        unrankedBadge.appendChild(unrankedIcon);
                        const unrankedLabel = document.createElement('span');
                        unrankedLabel.textContent = faceitStatusLabels.unranked;
                        unrankedBadge.appendChild(unrankedLabel);
                        faceitCell.appendChild(unrankedBadge);
                    } else {
                        pending.textContent = faceitStatusLabels[faceitStatus] || 'نامشخص';
                        pending.dataset.faceitState = faceitStatus;
                        if (faceitStatus === 'ambiguous') {
                            pending.title = 'این نام به چند SteamID در SimpleAdmin اشاره می‌کند؛ Level نمایش داده نمی‌شود.';
                        } else if (faceitStatus === 'simpleadmin_unavailable') {
                            pending.title = 'سرویس جست‌وجوی SimpleAdmin موقتاً در دسترس نیست.';
                        } else if (faceitStatus === 'api_unavailable') {
                            pending.title = 'دریافت رتبه از FACEIT موقتاً ناموفق بود؛ فهرست بازیکنان همچنان در دسترس است.';
                        }
                        faceitCell.appendChild(pending);
                    }
                }
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

                    const output = button.closest('[data-sl-card]')
                        ? button.closest('[data-sl-card]').querySelector('[data-sl-faceit-average]')
                        : null;
                    if (output) {
                        output.textContent = '…';
                        output.dataset.averageState = 'loading';
                        output.title = 'در حال دریافت Level بازیکنان آنلاین';
                    }

                    try {
                        const data = await fetchPlayersData(serverId, false);
                        updateCardFaceitAverage(button, data);
                    } catch (error) {
                        if (output) {
                            output.textContent = '—';
                            output.dataset.averageState = 'unavailable';
                            output.title = 'دریافت میانگین FACEIT در حال حاضر ممکن نیست.';
                        }
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
            const query = normalize(search ? search.value : '');
            const mode = normalize(modeSelect ? modeSelect.value : '');
            const map = normalize(mapSelect ? mapSelect.value : '');
            let visible = 0;

            list.querySelectorAll('[data-sl-group]').forEach(function (group) {
                let groupVisible = 0;

                group.querySelectorAll('[data-sl-card]').forEach(function (card) {
                    const statusMatch = statusFilter === 'all' || card.dataset.status === statusFilter;
                    const modeMatch = mode === '' || normalize(card.dataset.mode) === mode;
                    const mapMatch = map === '' || normalize(card.dataset.map) === map;
                    const searchText = [
                        card.dataset.name,
                        card.dataset.map,
                        card.dataset.mode,
                        card.dataset.address
                    ].map(normalize).join(' ');

                    const searchMatch = query === '' || searchText.includes(query);
                    const isVisible = statusMatch && modeMatch && mapMatch && searchMatch;

                    card.hidden = !isVisible;
                    if (isVisible) {
                        groupVisible++;
                        visible++;
                    }
                });

                group.hidden = groupVisible === 0;
            });

            const totalCards = getCards().length;
            if (noMatch) noMatch.hidden = totalCards === 0 || visible !== 0;
            if (resultsLabel) resultsLabel.textContent = 'نمایش ' + visible + ' سرور';
        }

        if (viewButtons.length) {
            setView(list.dataset.slView);
            viewButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    setView(button.dataset.slViewOption);
                });
            });
        }

        if (pills) {
            pills.addEventListener('click', function (event) {
                const button = event.target.closest('[data-sl-filter]');
                if (!button) return;

                statusFilter = button.dataset.slFilter || 'all';
                pills.querySelectorAll('[data-sl-filter]').forEach(function (pill) {
                    const active = pill === button;
                    pill.classList.toggle('active', active);
                    pill.setAttribute('aria-pressed', active ? 'true' : 'false');
                });
                applyFilters();
            });
        }

        [search, modeSelect, mapSelect].forEach(function (control) {
            if (!control) return;
            control.addEventListener(control === search ? 'input' : 'change', applyFilters);
        });

        if (sortSelect) {
            sortSelect.addEventListener('change', function () {
                applySort();
                applyFilters();
            });
        }

        if (clearButton) {
            clearButton.addEventListener('click', function () {
                statusFilter = 'online';
                if (search) search.value = '';
                if (modeSelect) modeSelect.value = '';
                if (mapSelect) mapSelect.value = '';
                if (sortSelect) sortSelect.value = 'default';

                if (pills) {
                    pills.querySelectorAll('[data-sl-filter]').forEach(function (pill) {
                        const active = pill.dataset.slFilter === 'online';
                        pill.classList.toggle('active', active);
                        pill.setAttribute('aria-pressed', active ? 'true' : 'false');
                    });
                }

                applySort();
                applyFilters();
            });
        }

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

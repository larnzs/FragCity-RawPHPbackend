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

        let statusFilter = 'online';
        let toastTimer = 0;
        let activePlayerButton = null;
        let playerRequestController = null;
        let playerRequestSequence = 0;
        const playerCache = new Map();

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
                const steamCell = appendPlayerCell(
                    row,
                    player.steam_id ? String(player.steam_id) : 'نیازمند افزونه CS2',
                    'sl-player-steamid',
                    'ltr'
                );
                if (!player.steam_id) {
                    steamCell.classList.add('sl-player-unavailable');
                    steamCell.title = 'A2S_PLAYER شناسه Steam را ارائه نمی‌کند؛ این مقدار با افزونه سمت سرور تکمیل می‌شود.';
                }

                appendPlayerCell(row, formatPlayerDuration(player.connected_seconds), 'sl-player-duration');
                appendPlayerCell(row, Number.isFinite(Number(player.score)) ? String(Math.trunc(Number(player.score))) : '—', 'sl-player-score', 'ltr');

                const faceitCell = document.createElement('td');
                faceitCell.className = 'sl-player-faceit';
                const level = Number(player.faceit_level);
                if (Number.isInteger(level) && level >= 1 && level <= 10) {
                    const levelBadge = document.createElement('span');
                    levelBadge.className = 'sl-faceit-level-badge';
                    levelBadge.textContent = 'LVL ' + level;
                    faceitCell.appendChild(levelBadge);
                } else {
                    const pending = document.createElement('span');
                    pending.className = 'sl-faceit-pending';
                    pending.textContent = 'در انتظار API';
                    faceitCell.appendChild(pending);
                }
                row.appendChild(faceitCell);
                if (playerTableBody) playerTableBody.appendChild(row);
            });

            setPlayerModalSection('table');
        }

        function closePlayerModal() {
            if (!playerModal || playerModal.hidden) return;

            ++playerRequestSequence;
            if (playerRequestController) {
                playerRequestController.abort();
                playerRequestController = null;
            }

            playerModal.hidden = true;
            playerModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('sl-player-modal-open');

            if (activePlayerButton && document.contains(activePlayerButton)) {
                activePlayerButton.focus();
            }
            activePlayerButton = null;
        }

        async function loadPlayers(serverId, forceRefresh) {
            if (!playerModal || !/^[a-f0-9]{16}$/i.test(serverId)) return;

            const cacheEntry = playerCache.get(serverId);
            if (!forceRefresh && cacheEntry && Date.now() - cacheEntry.savedAt < 5000) {
                renderPlayers(cacheEntry.data);
                return;
            }

            if (playerRequestController) playerRequestController.abort();
            const controller = new AbortController();
            playerRequestController = controller;
            const requestSequence = ++playerRequestSequence;

            setPlayerModalSection('loading');
            if (playerModalCount) playerModalCount.textContent = '...';

            try {
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

                if (requestSequence !== playerRequestSequence || playerModal.hidden) return;

                playerCache.set(serverId, { data: result.data, savedAt: Date.now() });
                renderPlayers(result.data);
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
            } finally {
                if (requestSequence === playerRequestSequence) {
                    playerRequestController = null;
                }
            }
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

            playerModal.hidden = false;
            playerModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('sl-player-modal-open');
            if (playerModalPanel) playerModalPanel.focus();

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

        if (playerRetry) {
            playerRetry.addEventListener('click', function () {
                const serverId = activePlayerButton ? String(activePlayerButton.dataset.serverId || '') : '';
                loadPlayers(serverId, true);
            });
        }

        root.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && playerModal && !playerModal.hidden) {
                event.preventDefault();
                closePlayerModal();
                return;
            }

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
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });

        applySort();
        applyFilters();
    }

    window.FragCityServerBrowser = { init: init };
    document.addEventListener('DOMContentLoaded', init);
    window.addEventListener('content-loaded', init);

    if (document.readyState !== 'loading') {
        init();
    }
})();

(function () {
    'use strict';

    function initServerList() {
        const list = document.getElementById('serverList');
        if (!list || list.dataset.slReady === '1') return;
        list.dataset.slReady = '1';

        const pillsWrap = document.getElementById('slFilterPills');
        const searchInput = document.getElementById('slSearchInput');
        const noMatch = document.getElementById('slNoMatch');
        const sortMenu = document.getElementById('slSortMenu');
        const sortLabel = document.getElementById('slSortLabel');

        const modal = document.getElementById('slInfoModal');
        const modalTitle = document.getElementById('slModalTitle');
        const modalMap = document.getElementById('slModalMap');
        const modalPlayersCount = document.getElementById('slModalPlayersCount');
        const modalIp = document.getElementById('slModalIp');
        const modalCopy = document.getElementById('slModalCopy');
        const modalPlay = document.getElementById('slModalPlay');
        const modalNode = document.getElementById('slModalNode');
        const modalPing = document.getElementById('slModalPing');
        const modalVac = document.getElementById('slModalVac');
        const modalLock = document.getElementById('slModalLock');
        const modalPlayers = document.getElementById('slModalPlayers');
        const modalPlayersState = document.getElementById('slModalPlayersState');

        let currentFilter = 'all';
        let currentSort = 'default';
        let activeCard = null;
        let playerRequestToken = 0;

        const sortLabels = {
            'default': 'پیش‌فرض',
            'players-desc': 'بازیکن بیشتر',
            'players-asc': 'بازیکن کمتر',
            'ping-asc': 'پینگ کمتر',
            'ping-desc': 'پینگ بیشتر',
            'name-asc': 'نام (الف تا ی)'
        };

        function getRows() {
            return Array.from(list.querySelectorAll('.server-row'));
        }

        function getGroups() {
            return Array.from(list.querySelectorAll('.sl-server-group'));
        }

        function escapeHtml(value) {
            return String(value ?? '').replace(/[<>&"']/g, function (c) {
                return {
                    '<': '&lt;',
                    '>': '&gt;',
                    '&': '&amp;',
                    '"': '&quot;',
                    "'": '&#039;'
                }[c];
            });
        }

        function getNumber(row, attr) {
            return parseInt(row.getAttribute(attr) || '0', 10);
        }

        function applyFilterAndSearch() {
            const query = (searchInput ? searchInput.value : '').trim().toLowerCase();
            let visibleCount = 0;

            getGroups().forEach(function (group) {
                let groupVisibleCount = 0;

                group.querySelectorAll('.server-row').forEach(function (row) {
                    const matchesStatus =
                        currentFilter === 'all' ||
                        row.getAttribute('data-status') === currentFilter;

                    const name = (row.getAttribute('data-name') || '').toLowerCase();
                    const map = (row.getAttribute('data-map') || '').toLowerCase();

                    const matchesSearch =
                        query === '' ||
                        name.indexOf(query) !== -1 ||
                        map.indexOf(query) !== -1;

                    const visible = matchesStatus && matchesSearch;

                    row.style.display = visible ? '' : 'none';

                    if (visible) {
                        groupVisibleCount++;
                        visibleCount++;
                    }
                });

                group.style.display = groupVisibleCount > 0 ? '' : 'none';
            });

            if (noMatch) {
                noMatch.style.display = visibleCount === 0 ? '' : 'none';
            }
        }

        function compareRows(a, b) {
            switch (currentSort) {
                case 'players-desc':
                    return getNumber(b, 'data-players') - getNumber(a, 'data-players');

                case 'players-asc':
                    return getNumber(a, 'data-players') - getNumber(b, 'data-players');

                case 'ping-asc':
                    return getNumber(a, 'data-ping') - getNumber(b, 'data-ping');

                case 'ping-desc':
                    return getNumber(b, 'data-ping') - getNumber(a, 'data-ping');

                case 'name-asc':
                    return (a.getAttribute('data-name') || '').localeCompare(
                        b.getAttribute('data-name') || '',
                        'fa'
                    );

                default:
                    return 0;
            }
        }

        function applySort() {
            if (currentSort === 'default') return;

            getGroups().forEach(function (group) {
                const grid = group.querySelector('.sl-group-grid');
                if (!grid) return;

                const rows = Array.from(grid.querySelectorAll('.server-row'));

                rows.sort(compareRows);

                rows.forEach(function (row) {
                    grid.appendChild(row);
                });
            });
        }

        function formatCount(players, maxPlayers) {
            return String(players) + ' : ' + String(maxPlayers);
        }

        function closeInfoModal() {
            if (!modal) return;

            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('sl-modal-open');

            playerRequestToken++;
            activeCard = null;
        }

        function renderPlayers(players, token) {
            if (token !== playerRequestToken || !modalPlayers) return;

            if (!Array.isArray(players) || players.length === 0) {
                modalPlayersState.textContent = 'خالی';

                modalPlayers.innerHTML =
                    '<div class="sl-modal-empty">بازیکنی آنلاین نیست.</div>';

                return;
            }

            modalPlayersState.textContent = players.length + ' بازیکن';

            const html = players.map(function (player) {
                const name = escapeHtml(player.Name || 'Unknown');
                const score = Number(player.Score ?? 0);
                const duration = escapeHtml(
                    player.DurationFormatted || '-'
                );

                return '<div class="sl-modal-player">' +
                    '<span class="sl-modal-player-name">' +
                    name +
                    '</span>' +

                    '<span class="sl-modal-player-info">' +
                    '<strong>' + score + '</strong>' +
                    ' · ' +
                    duration +
                    '</span>' +

                    '</div>';
            }).join('');

            modalPlayers.innerHTML =
                '<div class="sl-modal-player-grid">' +
                html +
                '</div>';
        }

        function loadPlayers(card) {
            if (!card || !modalPlayers) return;

            const token = ++playerRequestToken;

            const ip = card.getAttribute('data-ip') || '';
            const port = card.getAttribute('data-port') || '';

            modalPlayersState.textContent = 'در حال دریافت...';

            modalPlayers.innerHTML =
                '<div class="sl-modal-loading">' +
                '<i class="fas fa-spinner fa-spin"></i> ' +
                'در حال دریافت بازیکنان...' +
                '</div>';

            fetch(
                '/pages/serverlist.php?ajax=1&action=players&ip=' +
                    encodeURIComponent(ip) +
                    '&port=' +
                    encodeURIComponent(port),
                {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                }
            )
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('request failed');
                    }

                    return response.json();
                })
                .then(function (players) {
                    if (players && players.error) {
                        throw new Error(players.error);
                    }

                    renderPlayers(players, token);
                })
                .catch(function () {
                    if (token !== playerRequestToken) return;

                    modalPlayersState.textContent = 'خطا';

                    modalPlayers.innerHTML =
                        '<div class="sl-modal-error">' +
                        'خطا در دریافت اطلاعات بازیکنان.' +
                        '</div>';
                });
        }

        function openInfoModal(card) {
            if (!modal || !card) return;

            activeCard = card;

            const name =
                card.getAttribute('data-name') || 'Server';

            const map =
                card.getAttribute('data-map') || 'Other';

            const ip =
                card.getAttribute('data-ip') || '';

            const port =
                card.getAttribute('data-port') || '';

            const players =
                getNumber(card, 'data-players');

            const maxPlayers =
                getNumber(card, 'data-max-players');

            modalTitle.textContent = name;

            modalMap.textContent = map;

            modalPlayersCount.textContent =
                formatCount(players, maxPlayers);

            modalIp.textContent =
                ip + ':' + port;

            modalNode.textContent =
                card.querySelector('.sl-node')?.textContent.trim() || '-';

            modalPing.textContent =
                card.querySelector('.sl-ping')?.textContent.trim() || '---';

            const status =
                card.getAttribute('data-status') || '';

            const vac =
                card.getAttribute('data-vac') === '1';

            const locked =
                card.getAttribute('data-locked') === '1';

            modalVac.textContent =
                status === 'Running'
                    ? (vac ? 'فعال' : 'غیرفعال')
                    : '-';

            modalLock.textContent =
                status === 'Running'
                    ? (locked ? 'Private' : 'Public')
                    : '-';

            const joinBtn =
                card.querySelector('.sl-join-btn');

            if (joinBtn) {
                modalPlay.href =
                    joinBtn.getAttribute('href') || '#';

                modalPlay.style.display = '';
            } else {
                modalPlay.href = '#';
                modalPlay.style.display = 'none';
            }

            modal.classList.add('open');

            modal.setAttribute(
                'aria-hidden',
                'false'
            );

            document.body.classList.add(
                'sl-modal-open'
            );

            if (status === 'Running') {
                loadPlayers(card);
            } else {
                playerRequestToken++;

                modalPlayersState.textContent =
                    'آفلاین';

                modalPlayers.innerHTML =
                    '<div class="sl-modal-empty">' +
                    'این سرور در حال حاضر در دسترس نیست.' +
                    '</div>';
            }
        }

        // ---- Filter pills ----
        if (pillsWrap) {
            pillsWrap.addEventListener('click', function (event) {
                const pill =
                    event.target.closest('.sl-pill');

                if (!pill) return;

                pillsWrap
                    .querySelectorAll('.sl-pill')
                    .forEach(function (p) {
                        p.classList.remove('active');
                    });

                pill.classList.add('active');

                currentFilter =
                    pill.getAttribute('data-sl-filter') ||
                    'all';

                applyFilterAndSearch();
            });
        }

        // ---- Search ----
        if (searchInput) {
            searchInput.addEventListener(
                'input',
                applyFilterAndSearch
            );
        }

        // ---- Sort dropdown ----
        if (sortMenu) {
            sortMenu.addEventListener('click', function (event) {
                const option =
                    event.target.closest('[data-sl-sort]');

                if (!option) return;

                currentSort =
                    option.getAttribute('data-sl-sort') ||
                    'default';

                sortMenu
                    .querySelectorAll('[data-sl-sort]')
                    .forEach(function (btn) {
                        btn.classList.toggle(
                            'active',
                            btn === option
                        );
                    });

                if (sortLabel) {
                    sortLabel.textContent =
                        sortLabels[currentSort] ||
                        sortLabels.default;
                }

                applySort();
                applyFilterAndSearch();

                const dropdown =
                    sortMenu.closest('.frag-dropdown');

                if (dropdown) {
                    dropdown.classList.remove('open');
                }
            });
        }

        // ---- List actions ----
        list.addEventListener('click', function (event) {
            const copyBtn =
                event.target.closest('.sl-copy-btn');

            if (copyBtn) {
                event.preventDefault();
                event.stopPropagation();

                const ip =
                    copyBtn.getAttribute('data-ip') || '';

                const done = function () {
                    copyBtn.classList.add('copied');

                    const original =
                        copyBtn.innerHTML;

                    copyBtn.innerHTML =
                        '<i class="fas fa-check"></i>';

                    setTimeout(function () {
                        copyBtn.classList.remove('copied');
                        copyBtn.innerHTML = original;
                    }, 1200);
                };

                if (
                    navigator.clipboard &&
                    window.isSecureContext
                ) {
                    navigator.clipboard
                        .writeText(ip)
                        .then(done)
                        .catch(function () {});
                } else {
                    const temp =
                        document.createElement('textarea');

                    temp.value = ip;
                    temp.style.position = 'fixed';
                    temp.style.opacity = '0';

                    document.body.appendChild(temp);

                    temp.select();

                    try {
                        document.execCommand('copy');
                        done();
                    } catch (e) {
                        // ignore
                    }

                    temp.remove();
                }

                return;
            }

            const infoBtn =
                event.target.closest('[data-sl-open-info]');

            if (infoBtn) {
                event.preventDefault();
                event.stopPropagation();

                const card =
                    infoBtn.closest('.server-row');

                openInfoModal(card);

                return;
            }

            const stopCard =
                event.target.closest('[data-sl-stop-card]');

            if (stopCard) {
                event.stopPropagation();
                return;
            }

            const card =
                event.target.closest('.server-row');

            if (card) {
                openInfoModal(card);
            }
        });

        // ---- Modal actions ----
        if (modal) {
            modal.addEventListener('click', function (event) {
                if (
                    event.target.closest(
                        '[data-sl-close-info]'
                    )
                ) {
                    closeInfoModal();
                }
            });
        }

        document.addEventListener('keydown', function (event) {
            if (
                event.key === 'Escape' &&
                modal &&
                modal.classList.contains('open')
            ) {
                closeInfoModal();
            }
        });

        if (modalCopy) {
            modalCopy.addEventListener('click', function () {
                if (!activeCard) return;

                const ip =
                    (activeCard.getAttribute('data-ip') || '') +
                    ':' +
                    (activeCard.getAttribute('data-port') || '');

                const done = function () {
                    const original =
                        modalCopy.innerHTML;

                    modalCopy.innerHTML =
                        '<i class="fas fa-check"></i>';

                    setTimeout(function () {
                        modalCopy.innerHTML = original;
                    }, 1100);
                };

                if (
                    navigator.clipboard &&
                    window.isSecureContext
                ) {
                    navigator.clipboard
                        .writeText(ip)
                        .then(done)
                        .catch(function () {});
                } else {
                    const temp =
                        document.createElement('textarea');

                    temp.value = ip;

                    document.body.appendChild(temp);

                    temp.select();

                    try {
                        document.execCommand('copy');
                        done();
                    } catch (e) {
                        // ignore
                    }

                    temp.remove();
                }
            });
        }

        applyFilterAndSearch();
    }

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            initServerList
        );
    } else {
        initServerList();
    }

    window.addEventListener(
        'content-loaded',
        initServerList
    );
})();
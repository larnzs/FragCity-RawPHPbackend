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

        if (!list) return;

        let statusFilter = 'all';
        let toastTimer = 0;

        const getCards = function () {
            return Array.from(list.querySelectorAll('[data-sl-card]'));
        };

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

            if (sort === 'query-asc') {
                const aValue = a.dataset.query === '' ? Number.POSITIVE_INFINITY : Number(a.dataset.query);
                const bValue = b.dataset.query === '' ? Number.POSITIVE_INFINITY : Number(b.dataset.query);
                return aValue - bValue;
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
                statusFilter = 'all';
                if (search) search.value = '';
                if (modeSelect) modeSelect.value = '';
                if (mapSelect) mapSelect.value = '';
                if (sortSelect) sortSelect.value = 'default';

                if (pills) {
                    pills.querySelectorAll('[data-sl-filter]').forEach(function (pill) {
                        const active = pill.dataset.slFilter === 'all';
                        pill.classList.toggle('active', active);
                        pill.setAttribute('aria-pressed', active ? 'true' : 'false');
                    });
                }

                applySort();
                applyFilters();
            });
        }

        list.addEventListener('click', function (event) {
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

        root.querySelectorAll('a[href="#slBrowserControls"]').forEach(function (link) {
            link.addEventListener('click', function (event) {
                const target = root.querySelector('#slBrowserControls');
                if (!target) return;
                event.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });

        if (search) {
            root.addEventListener('keydown', function (event) {
                if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey) return;
                const target = event.target;
                if (target && /INPUT|TEXTAREA|SELECT/.test(target.tagName)) return;
                event.preventDefault();
                search.focus();
            });
        }

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

(function () {

    'use strict';

    function initLeaderboardPage() {

        const root =
            document.querySelector(
                '.leaderboard-page'
            );

        if (!root) {
            return;
        }

        if (
            root.dataset.leaderboardInitialized === '1'
        ) {
            return;
        }

        root.dataset.leaderboardInitialized =
            '1';

        const searchInput =
            root.querySelector(
                '#leaderboardSearch'
            );

        const clearSearchButton =
            root.querySelector(
                '#clearLeaderboardSearch'
            );

        const sortSelect =
            root.querySelector(
                '#leaderboardSort'
            );

        const orderButton =
            root.querySelector(
                '#leaderboardOrder'
            );

        const dynamicContent =
            root.querySelector(
                '[data-leaderboard-dynamic]'
            );

        const visiblePlayerCount =
            root.querySelector(
                '#visiblePlayerCount'
            );

        const toast =
            root.querySelector(
                '#leaderboardToast'
            );

        let searchValue = '';

        let isLoading = false;


        /* =====================================================
           Get Rows
        ====================================================== */

        function getRows() {

            return Array.from(
                root.querySelectorAll(
                    '#leaderboardBody tr[data-player-row]'
                )
            );

        }


        /* =====================================================
           Toast
        ====================================================== */

        function showToast(message) {

            if (!toast) {
                return;
            }

            const text =
                toast.querySelector(
                    'span'
                );

            if (text) {
                text.textContent =
                    message;
            }

            toast.classList.add(
                'show'
            );

            clearTimeout(
                toast._hideTimer
            );

            toast._hideTimer =
                setTimeout(
                    function () {

                        toast.classList.remove(
                            'show'
                        );

                    },
                    2200
                );

        }


        /* =====================================================
           Search
        ====================================================== */

        function applySearch() {

            const normalizedSearch =
                searchValue
                    .trim()
                    .toLowerCase();

            const rows =
                getRows();

            let visibleCount =
                0;

            rows.forEach(
                function (row) {

                    const searchText =
                        (
                            row.dataset.search ||
                            ''
                        ).toLowerCase();

                    const matches =
                        normalizedSearch === '' ||
                        searchText.includes(
                            normalizedSearch
                        );

                    row.style.display =
                        matches
                            ? ''
                            : 'none';

                    if (matches) {
                        visibleCount++;
                    }

                }
            );

            if (visiblePlayerCount) {

                visiblePlayerCount.textContent =
                    visibleCount.toLocaleString(
                        'en-US'
                    );

            }

            if (
                clearSearchButton
            ) {

                clearSearchButton.classList.toggle(
                    'visible',
                    searchValue.trim().length > 0
                );

            }

        }


        /* =====================================================
           Copy SteamID
        ====================================================== */

        function initCopyButtons() {

            root
                .querySelectorAll(
                    '[data-copy-steam]'
                )
                .forEach(
                    function (button) {

                        if (
                            button.dataset.copyInitialized ===
                            '1'
                        ) {
                            return;
                        }

                        button.dataset.copyInitialized =
                            '1';

                        button.addEventListener(
                            'click',
                            async function () {

                                const steamId =
                                    button.dataset.copySteam ||
                                    '';

                                if (
                                    steamId === ''
                                ) {
                                    return;
                                }

                                try {

                                    if (
                                        navigator.clipboard &&
                                        navigator.clipboard.writeText
                                    ) {

                                        await navigator
                                            .clipboard
                                            .writeText(
                                                steamId
                                            );

                                    } else {

                                        const temp =
                                            document.createElement(
                                                'textarea'
                                            );

                                        temp.value =
                                            steamId;

                                        temp.style.position =
                                            'fixed';

                                        temp.style.opacity =
                                            '0';

                                        document.body.appendChild(
                                            temp
                                        );

                                        temp.select();

                                        document.execCommand(
                                            'copy'
                                        );

                                        temp.remove();

                                    }

                                    showToast(
                                        'SteamID کپی شد'
                                    );

                                } catch (
                                    error
                                ) {

                                    console.error(
                                        'Copy failed:',
                                        error
                                    );

                                }

                            }
                        );

                    }
                );

        }


        /* =====================================================
           Update Order Button
        ====================================================== */

        function updateOrderButton(
            order
        ) {

            if (!orderButton) {
                return;
            }

            orderButton.dataset.order =
                order;

            const icon =
                orderButton.querySelector(
                    'i'
                );

            const text =
                orderButton.querySelector(
                    'span'
                );

            if (icon) {

                icon.className =
                    order === 'ASC'
                        ? 'fas fa-arrow-up'
                        : 'fas fa-arrow-down';

            }

            if (text) {

                text.textContent =
                    order === 'ASC'
                        ? 'صعودی'
                        : 'نزولی';

            }

        }


        /* =====================================================
           Re-init Dynamic Content
        ====================================================== */

        function initDynamicContent() {

            initCopyButtons();

            applySearch();

        }


        /* =====================================================
           AJAX Sort
        ====================================================== */

        async function fetchSortedLeaderboard(
            sort,
            order,
            pageNum,
            perPage,
            targetUrl,
            shouldPushState
        ) {
            if (isLoading || !dynamicContent) {
                return;
            }

            isLoading = true;
            root.classList.add('is-loading');
            root.setAttribute('aria-busy', 'true');

            const currentUrl = targetUrl instanceof URL
                ? new URL(targetUrl.href)
                : new URL(window.location.href);

            // Keep all leaderboard states on the canonical clean route.
            currentUrl.pathname = '/leaderboard/';
            currentUrl.searchParams.delete('page');

            const requestedPage = Number.parseInt(
                String(pageNum ?? currentUrl.searchParams.get('page_num') ?? '1'),
                10
            );
            const requestedPerPage = Number.parseInt(
                String(perPage ?? currentUrl.searchParams.get('per_page') ?? '15'),
                10
            );

            currentUrl.searchParams.set('sort', sort || 'points');
            currentUrl.searchParams.set('order', order === 'ASC' ? 'ASC' : 'DESC');
            currentUrl.searchParams.set(
                'page_num',
                String(Number.isFinite(requestedPage) ? Math.max(1, requestedPage) : 1)
            );
            currentUrl.searchParams.set(
                'per_page',
                String([10, 15, 25, 50, 100].includes(requestedPerPage) ? requestedPerPage : 15)
            );

            try {
                const response = await fetch(currentUrl.toString(), {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'text/html'
                    }
                });

                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }

                const html = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const incomingContent = doc.querySelector('[data-leaderboard-dynamic]');

                if (!incomingContent) {
                    throw new Error('Leaderboard dynamic content not found.');
                }

                dynamicContent.innerHTML = incomingContent.innerHTML;

                if (shouldPushState !== false) {
                    window.history.pushState(
                        {
                            leaderboardSort: sort,
                            leaderboardOrder: order,
                            leaderboardPage: currentUrl.searchParams.get('page_num'),
                            leaderboardPerPage: currentUrl.searchParams.get('per_page')
                        },
                        '',
                        currentUrl.pathname + currentUrl.search
                    );
                }

                root.dataset.currentSort = sort || 'points';
                root.dataset.currentOrder = order === 'ASC' ? 'ASC' : 'DESC';
                root.dataset.currentPage = currentUrl.searchParams.get('page_num') || '1';
                root.dataset.perPage = currentUrl.searchParams.get('per_page') || '15';

                if (sortSelect) {
                    sortSelect.value = sort || 'points';
                }

                updateOrderButton(order === 'ASC' ? 'ASC' : 'DESC');
                initDynamicContent();

                if (
                    window.FragCityMotion &&
                    typeof window.FragCityMotion.refresh === 'function'
                ) {
                    window.FragCityMotion.refresh(dynamicContent);
                }
            } catch (error) {
                console.error('Leaderboard navigation error:', error);
                window.location.href = currentUrl.toString();
                return;
            } finally {
                isLoading = false;
                root.classList.remove('is-loading');
                root.removeAttribute('aria-busy');
            }
        }


        /* =====================================================
           Search Input
        ====================================================== */

        if (
            searchInput
        ) {

            searchInput.addEventListener(
                'input',
                function () {

                    searchValue =
                        this.value;

                    applySearch();

                }
            );

        }


        /* =====================================================
           Clear Search
        ====================================================== */

        if (
            clearSearchButton
        ) {

            clearSearchButton.addEventListener(
                'click',
                function () {

                    if (
                        searchInput
                    ) {

                        searchInput.value =
                            '';

                    }

                    searchValue =
                        '';

                    applySearch();

                }
            );

        }


        /* =====================================================
           AJAX Pagination + Page Size
        ====================================================== */

        if (dynamicContent) {
            dynamicContent.addEventListener('click', function (event) {
                const link = event.target.closest(
                    'a.leaderboard-page-btn, a.leaderboard-size-btn'
                );

                if (!link || !dynamicContent.contains(link)) {
                    return;
                }

                if (
                    event.defaultPrevented ||
                    event.button !== 0 ||
                    event.metaKey ||
                    event.ctrlKey ||
                    event.shiftKey ||
                    event.altKey ||
                    link.classList.contains('disabled') ||
                    link.classList.contains('active')
                ) {
                    return;
                }

                event.preventDefault();

                if (isLoading) {
                    return;
                }

                const targetUrl = new URL(link.href, window.location.href);
                const nextSort = targetUrl.searchParams.get('sort') || 'points';
                const nextOrder = targetUrl.searchParams.get('order') === 'ASC'
                    ? 'ASC'
                    : 'DESC';
                const nextPage = Number.parseInt(
                    targetUrl.searchParams.get('page_num') || '1',
                    10
                );
                const nextPerPage = Number.parseInt(
                    targetUrl.searchParams.get('per_page') || '15',
                    10
                );

                fetchSortedLeaderboard(
                    nextSort,
                    nextOrder,
                    nextPage,
                    nextPerPage,
                    targetUrl
                );
            });
        }


        /* =====================================================
           Sort Select
        ====================================================== */

        if (
            sortSelect
        ) {

            sortSelect.addEventListener(
                'change',
                function () {

                    if (
                        !this.value
                    ) {

                        return;

                    }

                    const sort =
                        this.value;

                    const order =
                        orderButton
                            ? (
                                orderButton.dataset.order ||
                                'DESC'
                            )
                            : 'DESC';

                    const currentUrl = new URL(window.location.href);
                    const perPage = Number.parseInt(
                        currentUrl.searchParams.get('per_page') || '15',
                        10
                    );
                    fetchSortedLeaderboard(sort, order, 1, perPage);

                }
            );

        }


        /* =====================================================
           Order Button
        ====================================================== */

        if (
            orderButton
        ) {

            orderButton.addEventListener(
                'click',
                function () {

                    if (
                        isLoading
                    ) {

                        return;

                    }

                    const currentOrder =
                        this.dataset.order ||
                        'DESC';

                    const nextOrder =
                        currentOrder ===
                            'DESC'
                            ? 'ASC'
                            : 'DESC';


                    this.classList.add(
                        'is-rotating'
                    );


                    window.setTimeout(
                        function () {

                            orderButton.classList.remove(
                                'is-rotating'
                            );

                        },
                        250
                    );


                    updateOrderButton(
                        nextOrder
                    );


                    const sort =
                        sortSelect
                            ? sortSelect.value
                            : 'points';


                    const currentUrl = new URL(window.location.href);
                    const perPage = Number.parseInt(
                        currentUrl.searchParams.get('per_page') || '15',
                        10
                    );
                    fetchSortedLeaderboard(sort, nextOrder, 1, perPage);

                }
            );

        }


        /* =====================================================
           Browser Back / Forward
        ====================================================== */

        window.addEventListener(
            'popstate',
            function () {

                const activeRoot =
                    document.querySelector(
                        '.leaderboard-page'
                    );

                if (
                    activeRoot !== root
                ) {

                    return;

                }

                const currentUrl =
                    new URL(
                        window.location.href
                    );

                const sort =
                    currentUrl.searchParams.get(
                        'sort'
                    ) ||
                    'points';

                const order =
                    currentUrl.searchParams.get(
                        'order'
                    ) ||
                    'DESC';


                if (
                    sortSelect
                ) {

                    sortSelect.value =
                        sort;

                }


                updateOrderButton(
                    order
                );


                const pageNum = Number.parseInt(
                    currentUrl.searchParams.get('page_num') || '1',
                    10
                );
                const perPage = Number.parseInt(
                    currentUrl.searchParams.get('per_page') || '15',
                    10
                );

                fetchSortedLeaderboard(
                    sort,
                    order,
                    pageNum,
                    perPage,
                    currentUrl,
                    false
                );

            }
        );


        /* =====================================================
           Initial
        ====================================================== */

        initDynamicContent();

    }


    window.initLeaderboardPage =
        initLeaderboardPage;


    if (
        document.readyState ===
        'loading'
    ) {

        document.addEventListener(
            'DOMContentLoaded',
            initLeaderboardPage,
            {
                once: true
            }
        );

    } else {

        initLeaderboardPage();

    }


    /* ========================================================
       AJAX Navigation
    ========================================================= */

    window.addEventListener(
        'content-loaded',
        function () {

            initLeaderboardPage();

        }
    );

})();
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
            order
        ) {

            if (
                isLoading ||
                !dynamicContent
            ) {

                return;

            }

            isLoading =
                true;

            root.classList.add(
                'is-loading'
            );

            const currentUrl =
                new URL(
                    window.location.href
                );

            currentUrl.searchParams.set(
                'page',
                'leaderboard'
            );

            currentUrl.searchParams.set(
                'sort',
                sort
            );

            currentUrl.searchParams.set(
                'order',
                order
            );

            try {

                const response =
                    await fetch(
                        currentUrl.toString(),
                        {
                            method:
                                'GET',

                            credentials:
                                'same-origin',

                            headers: {
                                'X-Requested-With':
                                    'XMLHttpRequest',

                                'Accept':
                                    'text/html'
                            }
                        }
                    );

                if (
                    !response.ok
                ) {

                    throw new Error(
                        'HTTP ' +
                        response.status
                    );

                }

                const html =
                    await response.text();

                const parser =
                    new DOMParser();

                const doc =
                    parser.parseFromString(
                        html,
                        'text/html'
                    );

                const incomingContent =
                    doc.querySelector(
                        '[data-leaderboard-dynamic]'
                    );

                if (
                    !incomingContent
                ) {

                    throw new Error(
                        'Leaderboard dynamic content not found.'
                    );

                }

                /*
                 * فقط Dynamic Content جایگزین می‌شود.
                 * Toolbar ثابت می‌ماند.
                 */

                dynamicContent.innerHTML =
                    incomingContent.innerHTML;


                /*
                 * URL بدون Reload تغییر می‌کند.
                 */

                window.history.pushState(
                    {
                        leaderboardSort:
                            sort,

                        leaderboardOrder:
                            order
                    },
                    '',
                    currentUrl.toString()
                );


                root.dataset.currentSort =
                    sort;

                root.dataset.currentOrder =
                    order;


                /*
                 * وضعیت Sort را همگام کن.
                 */

                if (sortSelect) {

                    sortSelect.value =
                        sort;

                }

                updateOrderButton(
                    order
                );


                /*
                 * Search قبلی حفظ می‌شود.
                 */

                initDynamicContent();

            } catch (
                error
            ) {

                console.error(
                    'Leaderboard sort error:',
                    error
                );

                /*
                 * فقط در صورت خطای واقعی AJAX
                 * Navigation عادی انجام شود.
                 */

                window.location.href =
                    currentUrl.toString();

                return;

            } finally {

                window.setTimeout(
                    function () {

                        root.classList.remove(
                            'is-loading'
                        );

                        isLoading =
                            false;

                    },
                    180
                );

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

                    fetchSortedLeaderboard(
                        sort,
                        order
                    );

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


                    fetchSortedLeaderboard(
                        sort,
                        nextOrder
                    );

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


                fetchSortedLeaderboard(
                    sort,
                    order
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
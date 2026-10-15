(function () {

    'use strict';


    function initPlayersPage() {

        const root =
            document.querySelector(
                '.players-page'
            );


        if (!root) {

            return;

        }


        if (
            root.dataset.playersInitialized ===
            '1'
        ) {

            return;

        }


        root.dataset.playersInitialized =
            '1';


        const searchInput =
            root.querySelector(
                '#playersSearch'
            );


        const clearButton =
            root.querySelector(
                '#clearPlayersSearch'
            );


        const countElement =
            root.querySelector(
                '#playersVisibleCount'
            );


        const emptyState =
            root.querySelector(
                '#playersEmptyState'
            );


        const searchEmptyState =
            root.querySelector(
                '#playersSearchEmpty'
            );


        const tabs =
            root.querySelectorAll(
                '[data-player-tab]'
            );


        let rows =
            Array.from(
                root.querySelectorAll(
                    '[data-player-row]'
                )
            );


        function refreshRows() {

            rows =
                Array.from(
                    root.querySelectorAll(
                        '[data-player-row]'
                    )
                );

        }


        function applySearch() {

            refreshRows();


            const query =
                (
                    searchInput
                        ? searchInput.value
                        : ''
                )
                    .trim()
                    .toLowerCase();


            let visible =
                0;


            rows.forEach(
                function (row) {

                    const searchText =
                        (
                            row.dataset.search ||
                            ''
                        ).toLowerCase();


                    const matches =
                        query === '' ||
                        searchText.includes(
                            query
                        );


                    row.hidden =
                        !matches;


                    if (matches) {

                        visible++;

                    }

                }
            );


            if (
                countElement
            ) {

                countElement.textContent =
                    visible.toLocaleString(
                        'en-US'
                    );

            }


            if (
                clearButton
            ) {

                clearButton.classList.toggle(
                    'visible',
                    query.length > 0
                );

            }


            if (
                emptyState
            ) {

                emptyState.hidden =
                    rows.length > 0 ||
                    query.length > 0;

            }


            if (
                searchEmptyState
            ) {

                searchEmptyState.hidden =
                    !(
                        query.length > 0 &&
                        visible === 0 &&
                        rows.length > 0
                    );

            }

        }


        if (
            searchInput
        ) {

            searchInput.addEventListener(
                'input',
                applySearch
            );

        }


        if (
            clearButton
        ) {

            clearButton.addEventListener(
                'click',
                function () {

                    searchInput.value =
                        '';

                    applySearch();

                    searchInput.focus();

                }
            );

        }


        tabs.forEach(
            function (tab) {

                tab.addEventListener(
                    'click',
                    function () {

                        tabs.forEach(
                            function (item) {

                                item.classList.remove(
                                    'active'
                                );

                            }
                        );


                        this.classList.add(
                            'active'
                        );


                        root.dataset.activeTab =
                            this.dataset.playerTab
                            || 'players';

                    }
                );

            }
        );


        applySearch();

    }


    if (
        document.readyState ===
        'loading'
    ) {

        document.addEventListener(
            'DOMContentLoaded',
            initPlayersPage,
            {
                once: true
            }
        );

    } else {

        initPlayersPage();

    }

})();
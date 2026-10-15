(function () {

    'use strict';


    function initServersPage() {

        const root =
            document.querySelector(
                '.servers-page'
            );


        if (!root) {

            return;

        }


        if (
            root.dataset.serversInitialized ===
            '1'
        ) {

            return;

        }


        root.dataset.serversInitialized =
            '1';


        const searchInput =
            root.querySelector(
                '#serversSearch'
            );


        const clearSearchButton =
            root.querySelector(
                '#clearServersSearch'
            );


        const visibleCount =
            root.querySelector(
                '#serversVisibleCount'
            );


        const searchEmptyState =
            root.querySelector(
                '#serversSearchEmpty'
            );


        const rows =
            Array.from(
                root.querySelectorAll(
                    '[data-server-row]'
                )
            );


        if (
            !searchInput ||
            !rows.length
        ) {

            return;

        }


        function applySearch() {

            const value =
                searchInput.value
                    .trim()
                    .toLowerCase();


            let matches =
                0;


            rows.forEach(
                function (row) {

                    const searchText =
                        (
                            row.dataset.search ||
                            ''
                        ).toLowerCase();


                    const isMatch =
                        value === '' ||
                        searchText.includes(
                            value
                        );


                    row.hidden =
                        !isMatch;


                    if (
                        isMatch
                    ) {

                        matches++;

                    }

                }
            );


            if (
                visibleCount
            ) {

                visibleCount.textContent =
                    matches.toLocaleString(
                        'en-US'
                    );

            }


            if (
                clearSearchButton
            ) {

                clearSearchButton.classList.toggle(
                    'visible',
                    value.length > 0
                );

            }


            if (
                searchEmptyState
            ) {

                searchEmptyState.hidden =
                    matches !== 0;

            }

        }


        searchInput.addEventListener(
            'input',
            applySearch
        );


        if (
            clearSearchButton
        ) {

            clearSearchButton.addEventListener(
                'click',
                function () {

                    searchInput.value =
                        '';

                    applySearch();

                    searchInput.focus();

                }
            );

        }


        applySearch();

    }


    if (
        document.readyState ===
        'loading'
    ) {

        document.addEventListener(
            'DOMContentLoaded',
            initServersPage,
            {
                once: true
            }
        );

    } else {

        initServersPage();

    }


})();
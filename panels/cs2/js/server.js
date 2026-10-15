(function () {

    'use strict';


    function initServerPage() {

        const root =
            document.querySelector('.server-page');


        if (!root) {
            return;
        }


        const searchInput =
            root.querySelector('#serverSearch');


        const clearSearch =
            root.querySelector('#serverSearchClear');


        const filterButton =
            root.querySelector('#serverFilterButton');


        const filterMenu =
            root.querySelector('#serverFilterMenu');


        const viewButtons =
            root.querySelectorAll(
                '[data-server-view]'
            );


        const container =
            root.querySelector(
                '.server-card-container'
            );


        const filterButtons =
            root.querySelectorAll(
                '[data-server-filter]'
            );


        if (searchInput) {

            searchInput.addEventListener(
                'input',
                function () {

                    const hasValue =
                        searchInput.value.trim().length > 0;


                    if (clearSearch) {

                        clearSearch.classList.toggle(
                            'visible',
                            hasValue
                        );

                    }

                }
            );

        }


        if (clearSearch) {

            clearSearch.addEventListener(
                'click',
                function () {

                    if (searchInput) {

                        searchInput.value =
                            '';

                        searchInput.dispatchEvent(
                            new Event('input')
                        );

                        searchInput.focus();

                    }

                }
            );

        }


        if (
            filterButton &&
            filterMenu
        ) {

            filterButton.addEventListener(
                'click',
                function (event) {

                    event.stopPropagation();


                    const isOpen =
                        filterMenu.classList.toggle(
                            'open'
                        );


                    filterButton.setAttribute(
                        'aria-expanded',
                        isOpen
                            ? 'true'
                            : 'false'
                    );

                }
            );


            document.addEventListener(
                'click',
                function (event) {

                    if (
                        !filterMenu.contains(
                            event.target
                        ) &&
                        !filterButton.contains(
                            event.target
                        )
                    ) {

                        filterMenu.classList.remove(
                            'open'
                        );


                        filterButton.setAttribute(
                            'aria-expanded',
                            'false'
                        );

                    }

                }
            );

        }


        filterButtons.forEach(
            function (button) {

                button.addEventListener(
                    'click',
                    function () {

                        filterButtons.forEach(
                            function (item) {

                                item.classList.remove(
                                    'selected'
                                );

                            }
                        );


                        button.classList.add(
                            'selected'
                        );


                        if (
                            filterMenu
                        ) {

                            filterMenu.classList.remove(
                                'open'
                            );

                        }

                    }
                );

            }
        );


        viewButtons.forEach(
            function (button) {

                button.addEventListener(
                    'click',
                    function () {

                        viewButtons.forEach(
                            function (item) {

                                item.classList.remove(
                                    'active'
                                );

                            }
                        );


                        button.classList.add(
                            'active'
                        );


                        if (
                            container
                        ) {

                            const view =
                                button.dataset.serverView;


                            container.classList.toggle(
                                'server-grid-view',
                                view === 'grid'
                            );

                        }

                    }
                );

            }
        );

    }


    if (
        document.readyState === 'loading'
    ) {

        document.addEventListener(
            'DOMContentLoaded',
            initServerPage,
            { once: true }
        );

    } else {

        initServerPage();

    }

})();
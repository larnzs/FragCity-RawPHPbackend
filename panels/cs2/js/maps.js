(function () {

    'use strict';


    function initMapsPage() {

        const root =
            document.querySelector(
                '.maps-page'
            );


        if (!root) {

            return;

        }


        if (
            root.dataset.mapsInitialized ===
            '1'
        ) {

            return;

        }


        root.dataset.mapsInitialized =
            '1';


        const rows =
            root.querySelectorAll(
                '[data-map-row]'
            );


        const buttons =
            root.querySelectorAll(
                '[data-map-load]'
            );


        buttons.forEach(
            function (button) {

                button.addEventListener(
                    'click',
                    function () {

                        const targetMap =
                            this.dataset.mapLoad
                            || '';


                        rows.forEach(
                            function (row) {

                                row.classList.remove(
                                    'is-selected'
                                );

                            }
                        );


                        const targetRow =
                            root.querySelector(
                                '[data-map="' +
                                targetMap +
                                '"]'
                            );


                        if (
                            targetRow
                        ) {

                            targetRow.classList.add(
                                'is-selected'
                            );

                        }


                        /*
                         * UI only.
                         * Backend map switching has not been connected yet.
                         */

                    }
                );

            }
        );

    }


    if (
        document.readyState ===
        'loading'
    ) {

        document.addEventListener(
            'DOMContentLoaded',
            initMapsPage,
            {
                once: true
            }
        );

    } else {

        initMapsPage();

    }

})();
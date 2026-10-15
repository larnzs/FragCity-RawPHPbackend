(function () {

    'use strict';


    function initAdminsPage() {

        const button =
            document.getElementById(
                'addAdminButton'
            );


        if (!button) {

            return;

        }


        if (
            button.dataset.initialized ===
            '1'
        ) {

            return;

        }


        button.dataset.initialized =
            '1';


        button.addEventListener(
            'pointerdown',
            function () {

                button.classList.add(
                    'is-pressed'
                );

            }
        );


        [
            'pointerup',
            'pointercancel',
            'pointerleave'
        ].forEach(
            function (eventName) {

                button.addEventListener(
                    eventName,
                    function () {

                        button.classList.remove(
                            'is-pressed'
                        );

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
            initAdminsPage,
            {
                once: true
            }
        );

    } else {

        initAdminsPage();

    }

})();
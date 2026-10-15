(function () {

    'use strict';


    function initAutomationsPage() {

        const button =
            document.getElementById(
                'automationAddButton'
            );


        if (!button) {
            return;
        }


        ['mousedown'].forEach(
            function (eventName) {

                button.addEventListener(
                    eventName,
                    function () {

                        button.classList.add(
                            'is-pressed'
                        );

                    }
                );

            }
        );


        ['mouseup', 'mouseleave'].forEach(
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
        document.readyState === 'loading'
    ) {

        document.addEventListener(
            'DOMContentLoaded',
            initAutomationsPage,
            { once: true }
        );

    } else {

        initAutomationsPage();

    }

})();
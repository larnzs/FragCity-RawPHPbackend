(function () {

    'use strict';


    function initConfigPage() {

        const root =
            document.querySelector('.config-page');


        if (!root) {
            return;
        }


        const editButton =
            root.querySelector('#configEditButton');


        if (!editButton) {
            return;
        }


        editButton.addEventListener(
            'mousedown',
            function () {

                editButton.classList.add(
                    'is-pressed'
                );

            }
        );


        ['mouseup', 'mouseleave'].forEach(
            function (eventName) {

                editButton.addEventListener(
                    eventName,
                    function () {

                        editButton.classList.remove(
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
            initConfigPage,
            { once: true }
        );

    } else {

        initConfigPage();

    }

})();
(function () {

    'use strict';


    function initBackupsPage() {

        const button =
            document.getElementById(
                'createBackupButton'
            );


        if (!button) {
            return;
        }


        button.addEventListener(
            'mousedown',
            function () {

                button.classList.add(
                    'is-pressed'
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
            initBackupsPage,
            { once: true }
        );

    } else {

        initBackupsPage();

    }

})();
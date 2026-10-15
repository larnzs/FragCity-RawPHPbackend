(function () {

    'use strict';


    function initPluginsPage() {

        const root =
            document.querySelector('.plugins-page');


        if (!root) {
            return;
        }


        const tabs =
            root.querySelectorAll(
                '[data-plugin-tab]'
            );


        const views =
            root.querySelectorAll(
                '[data-plugin-view]'
            );


        const installButton =
            root.querySelector(
                '#pluginsInstallButton'
            );


        function showView(
            target
        ) {

            tabs.forEach(
                function (tab) {

                    tab.classList.toggle(
                        'active',
                        tab.dataset.pluginTab === target
                    );

                }
            );


            views.forEach(
                function (view) {

                    view.hidden =
                        view.dataset.pluginView !== target;

                }
            );

        }


        tabs.forEach(
            function (tab) {

                tab.addEventListener(
                    'click',
                    function () {

                        showView(
                            tab.dataset.pluginTab
                        );

                    }
                );

            }
        );


        if (installButton) {

            ['mousedown'].forEach(
                function (eventName) {

                    installButton.addEventListener(
                        eventName,
                        function () {

                            installButton.classList.add(
                                'is-pressed'
                            );

                        }
                    );

                }
            );


            ['mouseup', 'mouseleave'].forEach(
                function (eventName) {

                    installButton.addEventListener(
                        eventName,
                        function () {

                            installButton.classList.remove(
                                'is-pressed'
                            );

                        }
                    );

                }
            );

        }


        showView(
            'installed'
        );

    }


    if (
        document.readyState === 'loading'
    ) {

        document.addEventListener(
            'DOMContentLoaded',
            initPluginsPage,
            { once: true }
        );

    } else {

        initPluginsPage();

    }

})();
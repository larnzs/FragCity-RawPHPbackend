(function () {

    'use strict';


    const SETTINGS_STORAGE_KEY =
        'fragcity_panel_preferences_v1';


    function applyStoredPanelSettings() {

        try {

            const rawSettings =
                window.localStorage.getItem(
                    SETTINGS_STORAGE_KEY
                );


            if (!rawSettings) {

                return;

            }


            const settings =
                JSON.parse(
                    rawSettings
                );


            document.documentElement.classList.toggle(

                'panel-motion-disabled',

                settings.motion === false

            );


            document.documentElement.classList.toggle(

                'panel-compact-sidebar',

                settings.compactSidebar === true

            );

        } catch (error) {

            console.error(
                'Unable to apply panel settings:',
                error
            );

        }

    }


    function initSidebar() {

        applyStoredPanelSettings();


        const sidebar =
            document.querySelector(
                '.sidebar'
            );


        if (!sidebar) {

            return;

        }


        if (
            sidebar.dataset.initialized ===
            '1'
        ) {

            return;

        }


        sidebar.dataset.initialized =
            '1';


        /*
         * =====================================================
         * Active Navigation
         * =====================================================
         */

        const activeItem =
            sidebar.querySelector(
                '.sidebar-item.active'
            );


        if (
            activeItem
        ) {

            activeItem.setAttribute(
                'aria-current',
                'page'
            );


            const menu =
                sidebar.querySelector(
                    '.sidebar-menu'
                );


            if (
                menu
            ) {

                const menuRect =
                    menu.getBoundingClientRect();


                const itemRect =
                    activeItem.getBoundingClientRect();


                if (
                    itemRect.top <
                    menuRect.top ||
                    itemRect.bottom >
                    menuRect.bottom
                ) {

                    activeItem.scrollIntoView(
                        {
                            block:
                                'nearest'
                        }
                    );

                }

            }

        }


        /*
         * =====================================================
         * Brand Logo Fallback
         * =====================================================
         */

        const logo =
            sidebar.querySelector(
                '.sidebar-brand-logo'
            );


        if (
            logo
        ) {

            function showLogoFallback() {

                const wrapper =
                    logo.closest(
                        '.sidebar-brand-logo-wrap'
                    );


                if (
                    wrapper
                ) {

                    wrapper.classList.add(
                        'is-broken'
                    );

                }

            }


            logo.addEventListener(
                'error',
                showLogoFallback
            );


            if (
                logo.complete &&
                logo.naturalWidth === 0
            ) {

                showLogoFallback();

            }

        }

    }


    /*
     * تنظیمات را زودتر اعمال می‌کنیم تا Sidebar
     * پس از نمایش تغییر اندازه ندهد.
     */

    applyStoredPanelSettings();


    if (
        document.readyState ===
        'loading'
    ) {

        document.addEventListener(
            'DOMContentLoaded',
            initSidebar,
            {
                once: true
            }
        );

    } else {

        initSidebar();

    }

})();
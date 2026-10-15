(function () {

    'use strict';


    function initFragHeader() {

        const dropdown =
            document.getElementById(
                'fragUserDropdown'
            );


        const trigger =
            document.getElementById(
                'fragUserTrigger'
            );


        if (
            !dropdown ||
            !trigger
        ) {

            return;

        }


        if (
            dropdown.dataset.initialized ===
            '1'
        ) {

            return;

        }


        dropdown.dataset.initialized =
            '1';


        function closeDropdown() {

            dropdown.classList.remove(
                'open'
            );


            trigger.setAttribute(
                'aria-expanded',
                'false'
            );

        }


        function openDropdown() {

            dropdown.classList.add(
                'open'
            );


            trigger.setAttribute(
                'aria-expanded',
                'true'
            );

        }


        function toggleDropdown() {

            if (
                dropdown.classList.contains(
                    'open'
                )
            ) {

                closeDropdown();

            } else {

                openDropdown();

            }

        }


        /*
         * Toggle Button
         */

        trigger.addEventListener(
            'click',
            function (event) {

                event.preventDefault();

                event.stopPropagation();

                toggleDropdown();

            }
        );


        /*
         * Click Outside
         */

        document.addEventListener(
            'click',
            function (event) {

                if (
                    !dropdown.contains(
                        event.target
                    )
                ) {

                    closeDropdown();

                }

            }
        );


        /*
         * Escape
         */

        document.addEventListener(
            'keydown',
            function (event) {

                if (
                    event.key ===
                    'Escape'
                ) {

                    closeDropdown();

                }

            }
        );


        /*
         * Avatar Fallback
         */

        const avatarImages =
            document.querySelectorAll(
                '.frag-avatar-image'
            );


        avatarImages.forEach(
            function (image) {

                image.addEventListener(
                    'error',
                    function () {

                        const fallback =
                            image.dataset.fallback ||
                            '';


                        const wrapper =
                            image.closest(
                                '.frag-avatar-wrap, .frag-drop-avatar-wrap'
                            );


                        if (
                            fallback &&
                            image.dataset.fallbackUsed !==
                            '1'
                        ) {

                            image.dataset.fallbackUsed =
                                '1';

                            image.src =
                                fallback;

                            return;

                        }


                        if (
                            wrapper
                        ) {

                            wrapper.classList.add(
                                'is-broken'
                            );

                        }

                    }
                );

            }
        );


        /*
         * Global compatibility function
         * برای کدهای قدیمی در صورت نیاز
         */

        window.toggleFragDropdown =
            function () {

                toggleDropdown();

            };

    }


    if (
        document.readyState ===
        'loading'
    ) {

        document.addEventListener(
            'DOMContentLoaded',
            initFragHeader,
            {
                once: true
            }
        );

    } else {

        initFragHeader();

    }

})();
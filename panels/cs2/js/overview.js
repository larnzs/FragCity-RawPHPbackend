(function () {

    'use strict';


    function initOverviewPage() {

        const root =
            document.querySelector(
                '.overview-page'
            );


        if (!root) {

            return;

        }


        if (
            root.dataset.overviewInitialized ===
            '1'
        ) {

            return;

        }


        root.dataset.overviewInitialized =
            '1';


        /*
         * =====================================================
         * Copy IP / Address
         * =====================================================
         */

        const copyButtons =
            root.querySelectorAll(
                '[data-copy-text]'
            );


        copyButtons.forEach(
            function (button) {

                button.addEventListener(
                    'click',
                    async function () {

                        const text =
                            button.dataset.copyText ||
                            '';


                        if (
                            text === ''
                        ) {

                            return;

                        }


                        try {

                            if (
                                navigator.clipboard &&
                                navigator.clipboard.writeText
                            ) {

                                await navigator
                                    .clipboard
                                    .writeText(
                                        text
                                    );

                            } else {

                                const textarea =
                                    document.createElement(
                                        'textarea'
                                    );


                                textarea.value =
                                    text;


                                textarea.setAttribute(
                                    'readonly',
                                    ''
                                );


                                textarea.style.position =
                                    'fixed';


                                textarea.style.opacity =
                                    '0';


                                document.body.appendChild(
                                    textarea
                                );


                                textarea.select();


                                document.execCommand(
                                    'copy'
                                );


                                textarea.remove();

                            }


                            button.classList.add(
                                'copied'
                            );


                            const icon =
                                button.querySelector(
                                    'i'
                                );


                            if (icon) {

                                icon.className =
                                    'fa-solid fa-check';

                            }


                            button.setAttribute(
                                'title',
                                'Copied'
                            );


                            window.setTimeout(
                                function () {

                                    button.classList.remove(
                                        'copied'
                                    );


                                    if (icon) {

                                        icon.className =
                                            'fa-regular fa-copy';

                                    }


                                    button.setAttribute(
                                        'title',
                                        'Copy IP'
                                    );

                                },
                                1500
                            );


                        } catch (
                            error
                        ) {

                            console.error(
                                'Copy failed:',
                                error
                            );

                        }

                    }
                );

            }
        );

    }


    /*
     * =====================================================
     * Initial Load
     * =====================================================
     */

    if (
        document.readyState ===
        'loading'
    ) {

        document.addEventListener(
            'DOMContentLoaded',
            initOverviewPage,
            {
                once: true
            }
        );

    } else {

        initOverviewPage();

    }


})();
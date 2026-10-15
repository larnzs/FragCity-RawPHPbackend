(function () {

    'use strict';


    function initFilesPage() {

        const root =
            document.querySelector('.files-page');


        if (!root) {
            return;
        }


        const uploadButton =
            root.querySelector('#filesUploadButton');


        const uploadInput =
            root.querySelector('#filesUploadInput');


        const newFolderButton =
            root.querySelector('#filesNewFolderButton');


        const rows =
            root.querySelectorAll('[data-file-row]');


        if (
            uploadButton &&
            uploadInput
        ) {

            uploadButton.addEventListener(
                'click',
                function () {

                    uploadInput.click();

                }
            );


            uploadInput.addEventListener(
                'change',
                function () {

                    if (
                        !uploadInput.files ||
                        !uploadInput.files.length
                    ) {
                        return;
                    }


                    uploadButton.classList.add(
                        'is-pressed'
                    );


                    setTimeout(
                        function () {

                            uploadButton.classList.remove(
                                'is-pressed'
                            );

                        },
                        180
                    );

                }
            );

        }


        if (newFolderButton) {

            newFolderButton.addEventListener(
                'mousedown',
                function () {

                    newFolderButton.classList.add(
                        'is-pressed'
                    );

                }
            );


            ['mouseup', 'mouseleave'].forEach(
                function (eventName) {

                    newFolderButton.addEventListener(
                        eventName,
                        function () {

                            newFolderButton.classList.remove(
                                'is-pressed'
                            );

                        }
                    );

                }
            );

        }


        rows.forEach(
            function (row) {

                row.addEventListener(
                    'click',
                    function (event) {

                        if (
                            event.target.closest(
                                '.file-more-btn'
                            )
                        ) {
                            return;
                        }


                        rows.forEach(
                            function (item) {

                                item.classList.remove(
                                    'selected'
                                );

                            }
                        );


                        row.classList.add(
                            'selected'
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
            initFilesPage,
            { once: true }
        );

    } else {

        initFilesPage();

    }

})();
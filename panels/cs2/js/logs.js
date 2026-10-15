(function () {

    'use strict';


    function initLogsPage() {

        const output =
            document.getElementById(
                'logsOutput'
            );


        const downloadButton =
            document.getElementById(
                'downloadLogsButton'
            );


        if (
            !output ||
            !downloadButton
        ) {

            return;

        }


        if (
            downloadButton.dataset.initialized ===
            '1'
        ) {

            return;

        }


        downloadButton.dataset.initialized =
            '1';


        function getLogContent() {

            const lines =
                Array.from(
                    output.querySelectorAll(
                        '[data-log-line]'
                    )
                );


            return lines
                .map(
                    function (line) {

                        return line.innerText
                            .replace(
                                /\s+/g,
                                ' '
                            )
                            .trim();

                    }
                )
                .join(
                    '\n'
                );

        }


        function downloadLogs() {

            const logContent =
                getLogContent();


            if (!logContent) {

                return;

            }


            const blob =
                new Blob(
                    [logContent],
                    {
                        type:
                            'text/plain;charset=utf-8'
                    }
                );


            const objectUrl =
                URL.createObjectURL(
                    blob
                );


            const anchor =
                document.createElement(
                    'a'
                );


            const currentDate =
                new Date()
                    .toISOString()
                    .slice(
                        0,
                        10
                    );


            anchor.href =
                objectUrl;


            anchor.download =
                'fragcity-server-log-' +
                currentDate +
                '.txt';


            document.body.appendChild(
                anchor
            );


            anchor.click();

            anchor.remove();


            URL.revokeObjectURL(
                objectUrl
            );

        }


        downloadButton.addEventListener(
            'click',
            downloadLogs
        );

    }


    if (
        document.readyState ===
        'loading'
    ) {

        document.addEventListener(
            'DOMContentLoaded',
            initLogsPage,
            {
                once: true
            }
        );

    } else {

        initLogsPage();

    }

})();
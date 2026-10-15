(function () {

    'use strict';


    function initConsolePage() {

        const input =
            document.getElementById(
                'command-input'
            );


        const output =
            document.getElementById(
                'console-output'
            );


        const sendBtn =
            document.getElementById(
                'send-btn'
            );


        const clearBtn =
            document.getElementById(
                'clear-console'
            );


        if (
            !input ||
            !output ||
            !sendBtn ||
            !clearBtn
        ) {

            return;

        }


        if (
            input.dataset.consoleInitialized ===
            '1'
        ) {

            return;

        }


        input.dataset.consoleInitialized =
            '1';


        function appendLog(
            text,
            className
        ) {

            const line =
                document.createElement(
                    'div'
                );


            line.className =
                'log-line';


            if (
                className
            ) {

                line.classList.add(
                    className
                );

            }


            line.textContent =
                text;


            output.appendChild(
                line
            );


            output.scrollTop =
                output.scrollHeight;

        }


        function executeCommand() {

            const cmd =
                input.value.trim();


            if (
                !cmd
            ) {

                return;

            }


            appendLog(
                '> ' + cmd,
                'console-command'
            );


            const result =
                document.createElement(
                    'div'
                );


            result.className =
                'log-line';


            if (
                cmd ===
                'status'
            ) {

                result.textContent =
                    'hostname: My CS2 Server\n' +
                    'version : 1.40.5.3/8659 ursa\n' +
                    'players : 0 humans, 10 bots (10 max)';

            } else if (
                cmd.startsWith(
                    'map '
                )
            ) {

                const mapName =
                    cmd
                        .split(
                            ' '
                        )[1]
                    || 'unknown';


                result.textContent =
                    'Loading map ' +
                    mapName +
                    '... Success.';


                result.classList.add(
                    'console-success'
                );

            } else if (
                cmd ===
                'help'
            ) {

                result.textContent =
                    'Available commands: status, map <mapname>, kick <userid>, restart, version';

            } else {

                result.textContent =
                    'Unknown command: "' +
                    cmd +
                    '". Type "help" for a list of valid commands.';


                result.classList.add(
                    'console-error'
                );

            }


            output.appendChild(
                result
            );


            input.value =
                '';


            output.scrollTop =
                output.scrollHeight;

        }


        sendBtn.addEventListener(
            'click',
            executeCommand
        );


        input.addEventListener(
            'keydown',
            function (event) {

                if (
                    event.key ===
                    'Enter'
                ) {

                    event.preventDefault();

                    executeCommand();

                }

            }
        );


        clearBtn.addEventListener(
            'click',
            function (event) {

                event.preventDefault();


                output.innerHTML =
                    '';


                appendLog(
                    'Console cleared by administrator.',
                    'console-muted'
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
            initConsolePage,
            {
                once: true
            }
        );

    } else {

        initConsolePage();

    }

})();
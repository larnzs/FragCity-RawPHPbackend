(function () {

    'use strict';


    const STORAGE_KEY =
        'fragcity_panel_preferences_v1';


    const DEFAULT_SETTINGS = {

        motion:
            true,

        compactSidebar:
            false,

        serverNotifications:
            true,

        backupNotifications:
            true,

        confirmActions:
            true,

        interfaceHints:
            true

    };


    function readStoredSettings() {

        try {

            const rawValue =
                window.localStorage.getItem(
                    STORAGE_KEY
                );


            if (!rawValue) {

                return {
                    ...DEFAULT_SETTINGS
                };

            }


            const parsedValue =
                JSON.parse(
                    rawValue
                );


            return {

                ...DEFAULT_SETTINGS,

                ...parsedValue

            };

        } catch (error) {

            return {
                ...DEFAULT_SETTINGS
            };

        }

    }


    function saveStoredSettings(
        settings
    ) {

        try {

            window.localStorage.setItem(

                STORAGE_KEY,

                JSON.stringify(
                    settings
                )

            );

            return true;

        } catch (error) {

            console.error(
                'Unable to save panel settings:',
                error
            );

            return false;

        }

    }


    function applyVisualSettings(
        settings
    ) {

        document.documentElement.classList.toggle(

            'panel-motion-disabled',

            !settings.motion

        );


        document.documentElement.classList.toggle(

            'panel-compact-sidebar',

            settings.compactSidebar

        );

    }


    function initSettingsPage() {

        const root =
            document.querySelector(
                '.settings-page'
            );


        const form =
            document.getElementById(
                'panelSettingsForm'
            );


        if (
            !root ||
            !form
        ) {

            return;

        }


        if (
            root.dataset.settingsInitialized ===
            '1'
        ) {

            return;

        }


        root.dataset.settingsInitialized =
            '1';


        const controls = {

            motion:
                document.getElementById(
                    'settingsMotion'
                ),

            compactSidebar:
                document.getElementById(
                    'settingsCompactSidebar'
                ),

            serverNotifications:
                document.getElementById(
                    'settingsServerNotifications'
                ),

            backupNotifications:
                document.getElementById(
                    'settingsBackupNotifications'
                ),

            confirmActions:
                document.getElementById(
                    'settingsConfirmActions'
                ),

            interfaceHints:
                document.getElementById(
                    'settingsInterfaceHints'
                )

        };


        const resetButton =
            document.getElementById(
                'resetSettingsButton'
            );


        const changeStatus =
            document.getElementById(
                'settingsChangeStatus'
            );


        const toast =
            document.getElementById(
                'settingsToast'
            );


        let toastTimeout =
            null;


        let savedSettings =
            readStoredSettings();


        function setControls(
            settings
        ) {

            Object.keys(
                controls
            ).forEach(
                function (key) {

                    const control =
                        controls[key];


                    if (!control) {

                        return;

                    }


                    control.checked =
                        Boolean(
                            settings[key]
                        );

                }
            );

        }


        function getCurrentSettings() {

            const settings =
                {};


            Object.keys(
                controls
            ).forEach(
                function (key) {

                    const control =
                        controls[key];


                    settings[key] =
                        control
                            ? control.checked
                            : DEFAULT_SETTINGS[key];

                }
            );


            return settings;

        }


        function settingsAreEqual(
            first,
            second
        ) {

            return Object.keys(
                DEFAULT_SETTINGS
            ).every(
                function (key) {

                    return Boolean(
                        first[key]
                    ) === Boolean(
                        second[key]
                    );

                }
            );

        }


        function updateDirtyState() {

            const currentSettings =
                getCurrentSettings();


            const isDirty =
                !settingsAreEqual(

                    currentSettings,

                    savedSettings

                );


            form.classList.toggle(
                'has-unsaved-changes',
                isDirty
            );


            if (
                changeStatus
            ) {

                changeStatus.textContent =
                    isDirty
                        ? root.dataset.unsavedMessage
                        : '';

            }


            applyVisualSettings(
                currentSettings
            );

        }


        function showToast(
            message
        ) {

            if (!toast) {

                return;

            }


            const textElement =
                toast.querySelector(
                    'span'
                );


            if (textElement) {

                textElement.textContent =
                    message;

            }


            toast.classList.add(
                'show'
            );


            if (
                toastTimeout
            ) {

                window.clearTimeout(
                    toastTimeout
                );

            }


            toastTimeout =
                window.setTimeout(
                    function () {

                        toast.classList.remove(
                            'show'
                        );

                    },
                    2600
                );

        }


        setControls(
            savedSettings
        );


        applyVisualSettings(
            savedSettings
        );


        Object.values(
            controls
        ).forEach(
            function (control) {

                if (!control) {

                    return;

                }


                control.addEventListener(
                    'change',
                    updateDirtyState
                );

            }
        );


        form.addEventListener(
            'submit',
            function (event) {

                event.preventDefault();


                const currentSettings =
                    getCurrentSettings();


                if (
                    saveStoredSettings(
                        currentSettings
                    )
                ) {

                    savedSettings = {

                        ...currentSettings

                    };


                    updateDirtyState();


                    showToast(
                        root.dataset.savedMessage
                    );

                }

            }
        );


        if (
            resetButton
        ) {

            resetButton.addEventListener(
                'click',
                function () {

                    const resetSettings = {

                        ...DEFAULT_SETTINGS

                    };


                    setControls(
                        resetSettings
                    );


                    saveStoredSettings(
                        resetSettings
                    );


                    savedSettings = {

                        ...resetSettings

                    };


                    applyVisualSettings(
                        resetSettings
                    );


                    updateDirtyState();


                    showToast(
                        root.dataset.resetMessage
                    );

                }
            );

        }


        updateDirtyState();

    }


    if (
        document.readyState ===
        'loading'
    ) {

        document.addEventListener(
            'DOMContentLoaded',
            initSettingsPage,
            {
                once: true
            }
        );

    } else {

        initSettingsPage();

    }

})();
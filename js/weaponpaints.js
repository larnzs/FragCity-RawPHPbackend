(() => {
    'use strict';

    /*
    |--------------------------------------------------------------------------
    | FragCity Weapon Paints
    | Optimized single-controller JavaScript
    |--------------------------------------------------------------------------
    | Responsibilities:
    | - Team switching
    | - Tab state
    | - Smooth page navigation
    | - Skin pagination
    | - Pin pagination
    | - Global search
    | - Skin modal rendering
    | - Skin paint search
    | - Sticker selection
    | - Keychain selection
    | - Nested Bootstrap modals
    | - Form state synchronization
    | - Image error fallback
    |--------------------------------------------------------------------------
    */


    /* =========================================================
       01. Configuration
    ========================================================== */

    const config = window.WeaponPaintsConfig || {};

    const csrfToken =
        typeof config.csrfToken === 'string'
            ? config.csrfToken
            : '';

    const weapons =
        config.weapons || {};

    const skins =
        config.skins || {};

    const selectedSkins =
        config.selectedSkins || {};

    const skinDetails =
        config.skinDetails || {};

    const stickersData =
        config.stickersData || {};

    const keychainsData =
        config.keychainsData || {};


    /* =========================================================
       02. State
    ========================================================== */

    const state = {

        currentTeamParam:
            String(
                config.currentTeamParam || '2'
            ),

        currentActiveTab:
            String(
                config.currentTab || 'tab-knives'
            ),

        currentSkinPage:
            Number(
                config.currentSkinPage || 1
            ),

        currentPinPage:
            Number(
                config.currentPinPage || 1
            ),

        activeDefindex:
            null,

        currentStickerSlot:
            null,

        parentModalId:
            null,

        parentModalTemporarilyHidden:
            false,

        navigationLocked:
            false,

        searchTimer:
            null,

        stickerPage:
            0,

        stickerPageSize:
            24,

        stickerFilter:
            '',

        keychainPage:
            0,

        keychainPageSize:
            24,

        keychainFilter:
            '',

        modalSearchTimer:
            null,

        sourceTabForModal:
            null

    };


    /* =========================================================
       03. DOM Cache
    ========================================================== */

    const dom = {

        body:
            document.body,

        mainPageFader:
            document.getElementById(
                'mainPageFader'
            ),

        skinGridFade:
            document.getElementById(
                'skinsFade'
            ),

        pinGridFade:
            document.getElementById(
                'pinsFade'
            ),

        globalSearch:
            document.getElementById(
                'skinSearchGlobal'
            ),

        mainTabs:
            document.getElementById(
                'mainTabs'
            ),

        skinModal:
            document.getElementById(
                'skinModal'
            ),

        skinModalTitle:
            document.getElementById(
                'skinModalTitle'
            ),

        skinModalBody:
            document.getElementById(
                'skinModalBody'
            ),

        stickerModal:
            document.getElementById(
                'stickerModal'
            ),

        stickerModalBody:
            document.getElementById(
                'stickerModalBody'
            ),

        keychainModal:
            document.getElementById(
                'keychainModal'
            ),

        keychainModalBody:
            document.getElementById(
                'keychainModalBody'
            )

    };


    /* =========================================================
       04. Utility Helpers
    ========================================================== */

    function escapeHtml(value) {

        return String(
            value ?? ''
        )
            .replace(
                /&/g,
                '&amp;'
            )
            .replace(
                /</g,
                '&lt;'
            )
            .replace(
                />/g,
                '&gt;'
            )
            .replace(
                /"/g,
                '&quot;'
            )
            .replace(
                /'/g,
                '&#039;'
            );

    }


    function normalizeText(value) {

        return String(
            value ?? ''
        )
            .trim()
            .toLocaleLowerCase();

    }


    function showWpToast(message, type = 'info') {

        let toast =
            document.getElementById('wpToastRuntime');

        if (!toast) {

            toast =
                document.createElement('div');

            toast.id =
                'wpToastRuntime';

            toast.className =
                'wp-toast';

            document.body.appendChild(toast);

        }

        toast.className =
            `wp-toast is-visible wp-toast--${String(type || 'info')}`;

        toast.textContent =
            String(message || '');

        window.clearTimeout(
            toast.__wpToastTimer
        );

        toast.__wpToastTimer =
            window.setTimeout(
                () => {
                    toast.classList.remove('is-visible');
                },
                3200
            );

    }


    /*
     * Normalize image URLs/paths coming from PHP or the database.
     *
     * Supported forms:
     * - Absolute HTTP(S) URLs
     * - Protocol-relative URLs
     * - Root-relative paths
     * - Relative paths
     * - Windows-style paths using backslashes
     * - Public asset paths such as assets/... or img/...
     */
    function normalizeImagePath(value) {

        return String(
            value ?? ''
        )
            .trim()
            .replace(/\\/g, '/')
            .replace(/^\.\//, '');

    }


    function isAbsoluteImageUrl(url) {

        return /^(?:https?:|data:|blob:|\/\/)/i.test(
            String(url || '')
        );

    }


    function getWeaponPaintsBasePath() {

        try {

            const configuredPath =
                config.currentPhpFile ||
                window.location.pathname ||
                '/';

            const url =
                new URL(
                    configuredPath,
                    window.location.href
                );

            const pathname =
                url.pathname || '/';

            return pathname.endsWith('/')
                ? pathname
                : pathname.replace(
                    /[^/]*$/,
                    ''
                );

        } catch (error) {

            return '/';

        }

    }


    function buildImageCandidates(url) {

        const raw =
            normalizeImagePath(url);


        if (!raw) {

            return [];

        }


        const candidates = [];


        const addCandidate =
            candidate => {

                const normalized =
                    String(
                        candidate || ''
                    ).trim();


                if (!normalized) {

                    return;

                }


                if (
                    !candidates.includes(
                        normalized
                    )
                ) {

                    candidates.push(
                        normalized
                    );

                }

            };


        /*
         * External URLs do not need local path rewriting.
         */
        if (
            isAbsoluteImageUrl(
                raw
            )
        ) {

            addCandidate(
                raw
            );

            return candidates;

        }


        const relativePath =
            raw.replace(
                /^\.\//,
                ''
            );


        /*
         * First preserve the current working URL.
         */
        addCandidate(
            relativePath
        );


        /*
         * Common public asset folders should also be tried
         * as root-relative paths.
         */
        if (
            /^(?:assets|img|images|uploads|storage|public|weaponpaints)\//i.test(
                relativePath
            )
        ) {

            addCandidate(
                '/' +
                relativePath
            );

        }


        /*
         * Try relative to the PHP page directory.
         */
        const basePath =
            getWeaponPaintsBasePath();


        if (
            basePath
        ) {

            addCandidate(
                basePath +
                relativePath
            );

        }


        /*
         * Final generic root-relative attempt.
         */
        addCandidate(
            '/' +
            relativePath.replace(
                /^\/+/,
                ''
            )
        );


        /*
         * Older datasets may contain a full local filesystem-style
         * path. Keep the filename and try common FragCity folders.
         */
        if (
            /[\/]/.test(
                relativePath
            )
        ) {

            const filename =
                relativePath.split(
                    '/'
                ).pop();


            if (
                filename &&
                filename !==
                    relativePath
            ) {

                [
                    '/assets/images/weaponpaints/' +
                        filename,

                    '/assets/images/skins/' +
                        filename,

                    '/assets/images/knives/' +
                        filename,

                    '/img/weaponpaints/' +
                        filename,

                    '/img/skins/' +
                        filename,

                    '/img/knives/' +
                        filename

                ].forEach(
                    addCandidate
                );

            }

        }


        return candidates;

    }


    function getTabIdFromElement(element) {

        if (!element) {
            return null;
        }

        const pane =
            element.closest('.tab-pane');

        if (pane && pane.id) {
            return pane.id;
        }

        const target =
            element.getAttribute('data-bs-target') ||
            element.getAttribute('data-target') ||
            '';

        if (target.startsWith('#')) {
            return target.slice(1);
        }

        return null;

    }


    function moveWeaponPaintsModalsToBody() {

        const pageRoot =
            document.querySelector('.wp-app');

        if (!pageRoot) {
            return;
        }

        // Clean up stale Weapon Paints modals/backdrops from a previous
        // AJAX-rendered page instance.
        document
            .querySelectorAll('body > .wp-modal[data-wp-moved="1"]')
            .forEach(modal => modal.remove());

        document
            .querySelectorAll('body > .wp-modal-backdrop[data-wp-modal-backdrop="1"]')
            .forEach(backdrop => backdrop.remove());

        // Bootstrap may have left a backdrop or modal-open class behind
        // when the main site replaced a fragment dynamically.
        document
            .querySelectorAll('body > .modal-backdrop')
            .forEach(backdrop => backdrop.remove());

        document.body.classList.remove(
            'modal-open',
            'wp-modal-open'
        );

        pageRoot
            .querySelectorAll('.wp-modal')
            .forEach(modal => {
                modal.dataset.wpMoved = '1';
                modal.classList.remove(
                    'show',
                    'wp-is-open'
                );
                modal.setAttribute(
                    'aria-hidden',
                    'true'
                );
                modal.style.removeProperty('display');
                document.body.appendChild(modal);
            });

    }


    function getCustomModalBackdrop() {

        let backdrop =
            document.querySelector(
                'body > .wp-modal-backdrop[data-wp-modal-backdrop="1"]'
            );

        if (!backdrop) {

            backdrop =
                document.createElement('div');

            backdrop.className =
                'wp-modal-backdrop';

            backdrop.dataset.wpModalBackdrop =
                '1';

            backdrop.setAttribute(
                'aria-hidden',
                'true'
            );

            backdrop.addEventListener(
                'click',
                event => {

                    if (
                        event.target !==
                        backdrop
                    ) {
                        return;
                    }

                    const openModals =
                        document.querySelectorAll(
                            'body > .wp-modal.wp-is-open'
                        );

                    const topModal =
                        openModals.length
                            ? openModals[openModals.length - 1]
                            : null;

                    if (topModal) {
                        hideCustomModal(
                            topModal
                        );
                    }

                }
            );

            document.body.appendChild(
                backdrop
            );

        }

        return backdrop;

    }


    function getBootstrapModalInstance(element) {

        if (
            !element ||
            typeof window.bootstrap === 'undefined' ||
            !window.bootstrap ||
            !window.bootstrap.Modal
        ) {
            return null;
        }

        try {
            return window.bootstrap.Modal.getOrCreateInstance(
                element,
                {
                    backdrop: true,
                    focus: true,
                    keyboard: true
                }
            );
        } catch (error) {
            return null;
        }

    }


    function ensureModalInBody(element) {

        if (!element || !document.body) {
            return;
        }

        if (element.parentElement !== document.body) {
            document.body.appendChild(element);
        }

        element.style.position = 'fixed';
        element.style.inset = '0';
        element.style.zIndex = '2000';

    }


    function raiseBootstrapBackdrop() {

        const backdrops =
            document.querySelectorAll(
                '.modal-backdrop'
            );

        backdrops.forEach(
            backdrop => {
                backdrop.style.zIndex = '1990';
            }
        );

    }


    function showCustomModal(
        element,
        options = {}
    ) {

        if (!element) {
            return null;
        }

        ensureModalInBody(
            element
        );

        const bootstrapModal =
            getBootstrapModalInstance(
                element
            );

        if (bootstrapModal) {
            const wasOpen =
                element.classList.contains(
                    'wp-is-open'
                );

            element.classList.add(
                'wp-is-open'
            );
            element.setAttribute(
                'aria-hidden',
                'false'
            );
            document.body.classList.add(
                'wp-modal-open'
            );

            if (!wasOpen) {
                element.dispatchEvent(
                    new CustomEvent(
                        'show.wp.modal',
                        {
                            bubbles: false
                        }
                    )
                );
            }

            const onShown = () => {
                element.classList.add(
                    'wp-modal-visible'
                );
                ensureModalInBody(
                    element
                );
                raiseBootstrapBackdrop();

                element.dispatchEvent(
                    new CustomEvent(
                        'shown.wp.modal',
                        {
                            bubbles: false
                        }
                    )
                );
            };

            const onHidden = () => {
                element.classList.remove(
                    'wp-modal-visible',
                    'wp-is-open',
                    'show'
                );
                element.setAttribute(
                    'aria-hidden',
                    'true'
                );
                document.body.classList.remove(
                    'wp-modal-open'
                );

                element.dispatchEvent(
                    new CustomEvent(
                        'hidden.wp.modal',
                        {
                            bubbles: false
                        }
                    )
                );
            };

            element.addEventListener(
                'shown.bs.modal',
                onShown,
                { once: true }
            );
            element.addEventListener(
                'hidden.bs.modal',
                onHidden,
                { once: true }
            );

            const focusTarget =
                element.querySelector(
                    '[autofocus], button, input, select, textarea, [tabindex]:not([tabindex="-1"])'
                );

            if (options.focus !== false && focusTarget) {
                window.setTimeout(
                    () => {
                        try {
                            focusTarget.focus({
                                preventScroll: true
                            });
                        } catch (error) {
                            focusTarget.focus();
                        }
                    },
                    40
                );
            }

            bootstrapModal.show();

            return bootstrapModal;
        }

        const backdrop =
            getCustomModalBackdrop();

        element.classList.add(
            'wp-is-open'
        );
        element.setAttribute(
            'aria-hidden',
            'false'
        );
        element.style.display =
            'flex';
        document.body.classList.add(
            'wp-modal-open'
        );
        backdrop.classList.add(
            'is-visible'
        );
        element.dispatchEvent(
            new CustomEvent(
                'show.wp.modal',
                {
                    bubbles: false
                }
            )
        );

        window.requestAnimationFrame(
            () => {
                element.classList.add(
                    'wp-modal-visible'
                );

                element.dispatchEvent(
                    new CustomEvent(
                        'shown.wp.modal',
                        {
                            bubbles: false
                        }
                    )
                );
            }
        );

        if (
            options.focus !== false
        ) {
            const focusTarget =
                element.querySelector(
                    '[autofocus], button, input, select, textarea, [tabindex]:not([tabindex="-1"])'
                );

            if (focusTarget) {
                window.setTimeout(
                    () => {
                        try {
                            focusTarget.focus({
                                preventScroll: true
                            });
                        } catch (error) {
                            focusTarget.focus();
                        }

                    },
                    80
                );

            }
        }
        return {
            show: () =>
                showCustomModal(
                    element,
                    options
                ),
            hide: () =>
                hideCustomModal(
                    element
                )
        };

    }

    function hideCustomModal(
        element,
        options = {}
    ) {

        if (!element) {
            return null;
        }

        const bootstrapModal =
            getBootstrapModalInstance(
                element
            );

        if (bootstrapModal) {
            window.clearTimeout(
                element.__wpHideTimer
            );

            const wasOpen =
                element.classList.contains(
                    'wp-is-open'
                );

            element.classList.remove(
                'wp-modal-visible',
                'show'
            );
            element.classList.add(
                'wp-is-open'
            );
            element.setAttribute(
                'aria-hidden',
                'true'
            );

            if (wasOpen) {
                element.dispatchEvent(
                    new CustomEvent(
                        'hide.wp.modal',
                        {
                            bubbles: false
                        }
                    )
                );
            }

            const restoreParent =
                options.restoreParent !== false;

            const isParentTemporarilyHidden =
                state.parentModalTemporarilyHidden;

            const parentId =
                state.parentModalId;

            const onHidden = () => {
                element.classList.remove(
                    'wp-modal-visible',
                    'wp-is-open',
                    'show'
                );

                element.setAttribute(
                    'aria-hidden',
                    'true'
                );

                document.body.classList.remove(
                    'wp-modal-open'
                );

                element.dispatchEvent(
                    new CustomEvent(
                        'hidden.wp.modal',
                        {
                            bubbles: false
                        }
                    )
                );

                if (
                    restoreParent &&
                    isParentTemporarilyHidden &&
                    parentId &&
                    element.id !== parentId
                ) {
                    state.parentModalId = null;
                    state.parentModalTemporarilyHidden = false;

                    const parent =
                        document.getElementById(
                            parentId
                        );

                    if (parent) {
                        window.setTimeout(() => {
                            showCustomModal(parent);
                        }, 60);
                    }
                } else if (!isParentTemporarilyHidden) {
                    state.parentModalId = null;
                }
            };

            element.addEventListener(
                'hidden.bs.modal',
                onHidden,
                { once: true }
            );

            bootstrapModal.hide();
            return null;
        }

        window.clearTimeout(
            element.__wpHideTimer
        );

        const wasOpen =
            element.classList.contains(
                'wp-is-open'
            );

        element.classList.remove(
            'wp-modal-visible'
        );

        element.classList.remove(
            'wp-is-open',
            'show'
        );

        element.setAttribute(
            'aria-hidden',
            'true'
        );

        if (wasOpen) {

            element.dispatchEvent(
                new CustomEvent(
                    'hide.wp.modal',
                    {
                        bubbles: false
                    }
                )
            );
        }

        const restoreParent =
            options.restoreParent !== false;

        const isParentTemporarilyHidden =
            state.parentModalTemporarilyHidden;

        const parentId =
            state.parentModalId;

        if (
            restoreParent &&
            isParentTemporarilyHidden &&
            parentId &&
            element.id !== parentId
        ) {

            state.parentModalId =
                null;

            state.parentModalTemporarilyHidden =
                false;

            const parent =
                document.getElementById(
                    parentId
                );

            if (parent) {

                window.setTimeout(
                    () => {
                        showCustomModal(
                            parent
                        );
                    },
                    60
                );

            }

        } else if (
            !isParentTemporarilyHidden
        ) {

            state.parentModalId =
                null;
        }

        element.__wpHideTimer =
            window.setTimeout(
            () => {

                element.style.display =
                    'none';

                element.dispatchEvent(
                    new CustomEvent(
                        'hidden.wp.modal',
                        {
                            bubbles: false
                        }
                    )
                );

                const anyOpen =
                    document.querySelector(
                        'body > .wp-modal.wp-is-open'
                    );

                if (!anyOpen) {

                    const backdrop =
                        document.querySelector(
                            'body > .wp-modal-backdrop[data-wp-modal-backdrop="1"]'
                        );

                    if (backdrop) {
                        backdrop.classList.remove(
                            'is-visible'
                        );
                    }

                    document.body.classList.remove(
                        'wp-modal-open'
                    );

                }

            },
            260
        );

        return null;
    }

    // Backwards-compatible wrappers used by the existing Weapon Paints code.
    function showBootstrapModal(element) {
        return showCustomModal(element);
    }


    function hideBootstrapModal(element) {
        return hideCustomModal(element);
    }


    function getBootstrapModal(element) {
        // Modal handling is intentionally independent from Bootstrap so that
        // AJAX-loaded fragments cannot leave the dialog behind the backdrop.
        if (!element) {
            return null;
        }

        return {
            show: () => showCustomModal(element),
            hide: () => hideCustomModal(element)
        };

    }


    function getBootstrapTab(element) {

        if (
            !element ||
            typeof bootstrap === 'undefined'
        ) {
            return null;
        }

        return bootstrap.Tab.getOrCreateInstance(
            element
        );

    }


    function getActivePane() {

        return document.querySelector(
            '.wp-main .tab-content > .tab-pane.show.active'
        ) || document.querySelector(
            '.wp-main .tab-content > .tab-pane.active'
        );

    }


    /* =========================================================
       Floating Pagination Rail
       Mirrors the active pane's pagination without moving source DOM.
    ========================================================== */

    function syncFloatingPagination() {

        const dock = document.getElementById('wpFloatingPagination');
        const controls = document.getElementById('wpFloatingPaginationControls');
        const info = document.getElementById('wpFloatingPaginationInfo');
        const app = document.querySelector('.wp-app');

        if (!dock || !controls || !app) {
            return;
        }

        if (dock.dataset.wpFloatingBound !== '1') {
            dock.addEventListener('click', event => {
                const target = event.target instanceof Element
                    ? event.target
                    : event.target && event.target.parentElement;
                const link = target && target.closest('a[data-wp-navigation]');

                if (!link || !dock.contains(link)) {
                    return;
                }

                const navigationName = link.dataset.wpNavigation;
                const navigate = navigationName
                    ? window[navigationName]
                    : null;

                if (typeof navigate === 'function') {
                    event.preventDefault();
                    navigate(link.dataset.wpPage || '1');
                }
            });

            dock.dataset.wpFloatingBound = '1';
        }

        const activePane = getActivePane();
        const sourceWrap = activePane
            ? activePane.querySelector('.wp-pagination-wrap')
            : null;
        const sourcePagination = sourceWrap
            ? sourceWrap.querySelector('.wp-pagination')
            : null;

        if (!sourcePagination) {
            controls.replaceChildren();
            if (info) info.textContent = '';
            dock.hidden = true;
            app.classList.remove('has-floating-pagination');
            return;
        }

        const sourcePageNumbers = Array.from(
            sourcePagination.querySelectorAll('.wp-page-btn:not(.wp-page-btn--nav)')
        );

        // A one-page category has no useful paging interaction; leave its original controls in place.
        if (sourcePageNumbers.length <= 1) {
            controls.replaceChildren();
            if (info) info.textContent = '';
            dock.hidden = true;
            app.classList.remove('has-floating-pagination');
            return;
        }

        const floatingPagination = sourcePagination.cloneNode(true);
        floatingPagination.classList.add('wp-pagination--floating');
        floatingPagination.setAttribute('aria-label', 'Page navigation');

        const navLinks = floatingPagination.querySelectorAll('.wp-page-btn--nav');
        if (navLinks.length >= 2) {
            const previousIcon = navLinks[0].querySelector('i');
            const nextIcon = navLinks[navLinks.length - 1].querySelector('i');

            if (previousIcon) {
                previousIcon.className = 'fas fa-angle-up wp-floating-pagination__previous-icon';
            }

            if (nextIcon) {
                nextIcon.className = 'fas fa-angle-down wp-floating-pagination__next-icon';
            }
        }

        floatingPagination.querySelectorAll('a.wp-page-btn').forEach(link => {
            link.dataset.wpFloatingLink = '1';
            if (link.classList.contains('is-active')) {
                link.setAttribute('aria-current', 'page');
            } else {
                link.removeAttribute('aria-current');
            }
        });

        const currentPageLink = floatingPagination.querySelector(
            '.wp-page-btn.is-active:not(.wp-page-btn--nav)'
        );

        if (info) {
            info.textContent = (currentPageLink ? currentPageLink.textContent.trim() : '1')
                + ' / ' + sourcePageNumbers.length;
        }

        controls.replaceChildren(floatingPagination);
        dock.hidden = false;
        app.classList.add('has-floating-pagination');
    }


    function getCurrentUrl() {

        return new URL(
            window.location.href
        );

    }


    function buildNavigationUrl(
        updates = {}
    ) {

        const url =
            getCurrentUrl();


        Object.entries(
            updates
        ).forEach(
            ([key, value]) => {

                if (
                    value ===
                    null ||
                    value ===
                    undefined
                ) {

                    url.searchParams.delete(
                        key
                    );

                    return;

                }


                url.searchParams.set(
                    key,
                    String(value)
                );

            }
        );


        return url.toString();

    }


    function getCurrentForm() {

        return document.getElementById(
            'skinForm'
        );

    }


    function getFormValue(
        form,
        fieldName,
        fallback = ''
    ) {

        if (!form) {

            return fallback;

        }


        const field =
            form.elements.namedItem(
                fieldName
            );


        if (
            !field
        ) {

            return fallback;

        }


        return field.value !==
            undefined
            ? field.value
            : fallback;

    }


    function setFormValue(
        form,
        fieldName,
        value
    ) {

        if (!form) {

            return false;

        }


        const field =
            form.elements.namedItem(
                fieldName
            );


        if (!field) {

            return false;

        }


        field.value =
            String(
                value ?? ''
            );


        return true;

    }


    function imageMarkup(
        url,
        alt = '',
        className = ''
    ) {

        const candidates =
            buildImageCandidates(
                url
            );


        const safeUrl =
            candidates[0] ||
            '';


        const classes =
            [
                'wp-card-image',
                className
            ]
                .filter(Boolean)
                .join(' ');


        if (
            !safeUrl
        ) {

            return `
                <div class="wp-image-placeholder">
                    No Image
                </div>
            `;

        }


        return `
            <img
                src="${escapeHtml(safeUrl)}"
                alt="${escapeHtml(alt)}"
                class="${escapeHtml(classes)}"
                data-wp-image
                data-wp-image-candidates="${escapeHtml(
                    JSON.stringify(
                        candidates
                    )
                )}"
                data-wp-image-attempt="0"
                loading="lazy"
                decoding="async"
            >
            <div class="wp-image-placeholder is-hidden">
                No Image
            </div>
        `;

    }


    function parseStickerValue(
        value
    ) {

        const parts =
            String(
                value ||
                '0;0;0;0;0;0;0'
            )
                .split(';');


        return {

            id:
                parts[0] ||
                '0',

            x:
                parts[2] ||
                '0',

            y:
                parts[3] ||
                '0',

            wear:
                parts[4] ||
                '0.001',

            scale:
                parts[5] ||
                '1',

            rotate:
                parts[6] ||
                '0'

        };

    }


    function parseKeychainValue(
        value
    ) {

        const parts =
            String(
                value ||
                '0;0;0;0;0'
            )
                .split(';');


        return {

            id:
                parts[0] ||
                '0',

            x:
                parts[1] ||
                '0',

            y:
                parts[2] ||
                '0',

            z:
                parts[3] ||
                '0',

            seed:
                parts[4] ||
                '0'

        };

    }


    function getSkinDetailForCurrentTeam(
        defindex
    ) {

        const details =
            skinDetails[
                String(
                    defindex
                )
            ] ||
            skinDetails[
                defindex
            ] ||
            {};


        const teams =
            state.currentTeamParam ===
            'both'
                ? [2, 3]
                : [
                    Number(
                        state.currentTeamParam
                    )
                ];


        for (
            const team
            of teams
        ) {

            const detail =
                details[
                    String(team)
                ] ||
                details[
                    team
                ];


            if (
                detail
            ) {

                return detail;

            }

        }


        return {};

    }


    function getSelectedSkin(
        defindex
    ) {

        return (
            selectedSkins[
                String(
                    defindex
                )
            ] ||
            selectedSkins[
                defindex
            ] ||
            null
        );

    }


    function isKnifeWeapon(
        weaponName
    ) {

        return /knife|dagger|daggers|bayonet|karambit|butterfly|falchion|gut|huntsman|shadow|ursus|navaja|stiletto|talon|skeleton|nomad|survival|classic|paracord|bowie/i
            .test(
                String(
                    weaponName || ''
                )
            );

    }


    /* =========================================================
       05. Page Transition
    ========================================================== */

    function getTransitionTargets() {

        // Each weapon category now has its own fader ID (skinsFade-tab-*).
        // Include every grid fader so the page-ready state clears opacity: 0
        // on Rifles, SMG, Shotguns, Pistols, Snipers and the legacy grids too.
        const categoryGridFaders = Array.from(
            document.querySelectorAll(
                '.wp-app .wp-grid-fader'
            )
        );

        return Array.from(new Set([
            dom.mainPageFader,
            dom.skinGridFade,
            dom.pinGridFade,
            ...categoryGridFaders
        ]))
            .filter(Boolean);

    }


    function setPageReady() {

        getTransitionTargets()
            .forEach(
                element => {

                    element.classList.add(
                        'ready'
                    );

                }
            );


        dom.body.classList.remove(
            'page-navigating'
        );


        state.navigationLocked =
            false;

    }


    function fadeOutPage() {

        getTransitionTargets()
            .forEach(
                element => {

                    element.classList.remove(
                        'ready'
                    );

                }
            );


        dom.body.classList.add(
            'page-navigating'
        );

    }


    function navigateWithFade(
        url
    ) {

        if (
            state.navigationLocked
        ) {

            return false;

        }


        const current =
            getCurrentUrl();


        if (
            url ===
            current.toString()
        ) {

            return false;

        }


        state.navigationLocked =
            true;


        fadeOutPage();


        /*
         * CSS transition in weaponpaints.css
         * is intentionally short enough to feel instant,
         * but visible enough to avoid a hard flash.
         */

        window.setTimeout(
            () => {

                window.location.assign(
                    url
                );

            },
            260
        );


        return false;

    }


    window.changeTeam =
        function (
            team
        ) {

            const url =
                buildNavigationUrl({

                    team:
                        team,

                    tab:
                        state.currentActiveTab,

                    skin_page:
                        state.currentSkinPage,

                    pin_page:
                        state.currentPinPage

                });


            return navigateWithFade(
                url
            );

        };


    window.navigateSkinPage =
        function (
            targetPage
        ) {

            const page =
                Math.max(
                    1,
                    parseInt(
                        targetPage,
                        10
                    ) ||
                    1
                );

            const skinCategoryTabs = [
                'tab-skins',
                'tab-rifles',
                'tab-smg',
                'tab-heavy',
                'tab-pistol',
                'tab-sniper'
            ];

            const targetTab = skinCategoryTabs.includes(state.currentActiveTab)
                ? state.currentActiveTab
                : 'tab-skins';

            const url =
                buildNavigationUrl({

                    team:
                        state.currentTeamParam,

                    tab:
                        targetTab,

                    skin_page:
                        page

                });


            return navigateWithFade(
                url
            );

        };


    window.navigatePinPage =
        function (
            targetPage
        ) {

            const page =
                Math.max(
                    1,
                    parseInt(
                        targetPage,
                        10
                    ) ||
                    1
                );


            const url =
                buildNavigationUrl({

                    team:
                        state.currentTeamParam,

                    tab:
                        'tab-pins',

                    pin_page:
                        page

                });


            return navigateWithFade(
                url
            );

        };


    /* =========================================================
       06. URL / Tab State
    ========================================================== */

    function updateTabUrl(
        tabId
    ) {

        if (
            !tabId
        ) {

            return;

        }


        state.currentActiveTab =
            tabId;


        const url =
            getCurrentUrl();


        url.searchParams.set(
            'tab',
            tabId
        );


        window.history.replaceState(
            {
                tab:
                    tabId
            },
            '',
            url.toString()
        );


        applyGlobalSearch();

    }


    function syncFormActiveTab(
        form
    ) {

        if (
            !form
        ) {

            return;

        }


        const activeTab =
            form.elements.namedItem(
                'active_tab'
            );


        if (
            activeTab
        ) {

            activeTab.value =
                state.currentActiveTab;

        }

    }


    /* =========================================================
       06.5. Main Tab Controller

       Bootstrap 5 handles these tabs when its JS is available,
       but the page can also be loaded through the site's dynamic
       navigation. In that case Bootstrap's tab plugin may not be
       initialized on the fragment. This controller keeps the tabs
       working in both situations without touching the modal system.
    ========================================================== */

    function activateMainTab(
        tabId,
        updateUrl = true
    ) {

        if (
            !dom.mainTabs ||
            !tabId
        ) {
            return false;
        }

        const targetPane =
            document.getElementById(tabId);

        if (
            !targetPane ||
            !targetPane.classList.contains('tab-pane')
        ) {
            return false;
        }

        const previousPane =
            document.querySelector(
                '.wp-main .tab-content > .tab-pane.show.active'
            );

        const tabButtons =
            dom.mainTabs.querySelectorAll(
                '[data-bs-target], [data-target]'
            );

        tabButtons.forEach(button => {

            const targetSelector =
                button.getAttribute('data-bs-target') ||
                button.getAttribute('data-target') ||
                '';

            const buttonTargetId =
                targetSelector.replace(/^#/, '');

            const isActive =
                buttonTargetId === tabId;

            button.classList.toggle(
                'is-active',
                isActive
            );

            button.classList.toggle(
                'active',
                isActive
            );

            button.setAttribute(
                'aria-selected',
                isActive ? 'true' : 'false'
            );

            if (isActive) {
                button.removeAttribute('tabindex');
            } else {
                button.setAttribute('tabindex', '-1');
            }

        });

        document
            .querySelectorAll(
                '.wp-main .tab-content > .tab-pane'
            )
            .forEach(pane => {

                const isActive =
                    pane.id === tabId;

                if (!isActive) {
                    pane.classList.remove(
                        'show',
                        'active',
                        'wp-tab-entering'
                    );
                    pane.style.display = 'none';
                }

            });

        targetPane.style.display = '';
        targetPane.classList.add('active');

        // Force a reflow so the enter animation reliably runs after every tab switch.
        void targetPane.offsetWidth;

        targetPane.classList.add(
            'show',
            'wp-tab-entering'
        );

        window.clearTimeout(
            targetPane.__wpTabAnimationTimer
        );

        targetPane.__wpTabAnimationTimer =
            window.setTimeout(
                () => {
                    targetPane.classList.remove(
                        'wp-tab-entering'
                    );
                },
                460
            );

        state.currentActiveTab = tabId;
        syncFloatingPagination();

        if (updateUrl) {
            updateTabUrl(tabId);
        } else {
            applyGlobalSearch();
        }

        return true;

    }


    function bindMainTabs() {

        if (
            !dom.mainTabs
        ) {

            return;

        }


        const tabButtons =
            dom.mainTabs.querySelectorAll(
                '[data-bs-target], [data-target]'
            );


        tabButtons.forEach(
            button => {

                if (
                    button.dataset.wpTabBound ===
                    '1'
                ) {

                    return;

                }


                button.dataset.wpTabBound =
                    '1';


                button.addEventListener(
                    'click',
                    event => {

                        const targetSelector =
                            button.getAttribute(
                                'data-bs-target'
                            ) ||
                            button.getAttribute(
                                'data-target'
                            ) ||
                            '';


                        const tabId =
                            targetSelector.replace(
                                /^#/,
                                ''
                            );


                        if (
                            !tabId
                        ) {

                            return;

                        }


                        // We own the visible tab state here.
                        // Bootstrap can still handle its own event
                        // afterwards when its JS is available.
                        event.preventDefault();


                        activateMainTab(
                            tabId,
                            true
                        );

                    }
                );

            }
        );


        const initialTab =
            state.currentActiveTab ||
            'tab-knives';


        if (
            document.getElementById(
                initialTab
            )
        ) {

            activateMainTab(
                initialTab,
                false
            );

        }

    }


    /* =========================================================
       07. Global Search
    ========================================================== */

    function getSearchableItems(
        pane
    ) {

        if (
            !pane
        ) {

            return [];

        }


        return Array.from(
            pane.querySelectorAll(
                '.wp-item'
            )
        );

    }


    function ensureSearchEmptyState(
        container
    ) {

        if (
            !container
        ) {

            return null;

        }


        let emptyState =
            container.querySelector(
                '.wp-search-empty[data-generated-search-empty]'
            );


        if (
            !emptyState
        ) {

            emptyState =
                document.createElement(
                    'div'
                );


            emptyState.className =
                'wp-search-empty';


            emptyState.dataset.generatedSearchEmpty =
                'true';


            emptyState.innerHTML = `
                <i class="fas fa-magnifying-glass"></i>
                <span>موردی با این عبارت پیدا نشد.</span>
            `;


            container.appendChild(
                emptyState
            );

        }


        return emptyState;

    }


    function applyGlobalSearch() {

        const term =
            normalizeText(
                dom.globalSearch
                    ? dom.globalSearch.value
                    : ''
            );


        const pane =
            getActivePane();


        if (
            !pane
        ) {

            return;

        }


        const items =
            getSearchableItems(
                pane
            );


        let visibleCount =
            0;


        items.forEach(
            item => {

                const searchableName =
                    item.dataset.name ||
                    item.textContent ||
                    '';


                const matches =
                    !term ||
                    normalizeText(
                        searchableName
                    ).includes(
                        term
                    );


                item.classList.toggle(
                    'is-search-hidden',
                    !matches
                );


                if (
                    matches
                ) {

                    visibleCount++;

                }

            }
        );


        const grid =
            pane.querySelector(
                '.wp-items-grid'
            );


        if (
            grid
        ) {

            const emptyState =
                ensureSearchEmptyState(
                    grid
                );


            if (
                emptyState
            ) {

                emptyState.classList.toggle(
                    'is-hidden',
                    !term ||
                    visibleCount >
                    0
                );

            }

        }

    }


    function queueGlobalSearch() {

        if (
            state.searchTimer
        ) {

            window.clearTimeout(
                state.searchTimer
            );

        }


        state.searchTimer =
            window.setTimeout(
                () => {

                    applyGlobalSearch();

                },
                90
            );

    }


    /* =========================================================
       08. Skin Modal
    ========================================================== */

    function renderSkinModal(
        defindex
    ) {

        const weapon =
            weapons[
                String(
                    defindex
                )
            ] ||
            weapons[
                defindex
            ];


        if (
            !weapon ||
            !dom.skinModalBody
        ) {

            return;

        }


        const skinList =
            skins[
                String(
                    defindex
                )
            ] ||
            skins[
                defindex
            ] ||
            {};


        const selected =
            getSelectedSkin(
                defindex
            );


        const detail =
            getSkinDetailForCurrentTeam(
                defindex
            );


        const weaponName =
            String(
                weapon.weapon_name ||
                ''
            );


        const knifeWeapon =
            isKnifeWeapon(
                weaponName
            );


        const paintEntries =
            Object.entries(
                skinList
            );


        const selectedPaintId =
            selected
                ? String(
                    selected.weapon_paint_id
                )
                : '';


        const selectedWear =
            selected
                ? selected.weapon_wear
                : '0.001';


        const selectedSeed =
            selected
                ? selected.weapon_seed
                : '0';


        const selectedNameTag =
            detail.weapon_nametag ||
            '';


        const selectedStatTrak =
            Number(
                detail.weapon_stattrak ||
                0
            ) === 1;


        if (
            dom.skinModalTitle
        ) {

            dom.skinModalTitle.textContent =
                weaponName;

        }


        let html = `

            <form
                method="post"
                id="skinForm"
                class="wp-skin-form"
                data-wp-source-tab="${escapeHtml(state.currentActiveTab)}"
                data-wp-native-submit="true"
            >

                <input
                    type="hidden"
                    name="wp_csrf_token"
                    value="${escapeHtml(csrfToken)}"
                >

                <input
                    type="hidden"
                    name="active_tab"
                    value="${escapeHtml(state.currentActiveTab)}"
                >

                <input
                    type="hidden"
                    name="team"
                    value="${escapeHtml(state.currentTeamParam)}"
                >

                <input
                    type="hidden"
                    name="skin_page"
                    value="${state.currentSkinPage}"
                >

                <div class="wp-selection-toolbar">

                    <div class="wp-selection-toolbar-info">

                        <span class="wp-meta-pill">

                            <i class="fas fa-gun"></i>

                            ${escapeHtml(weaponName)}

                        </span>

                        <span class="wp-meta-pill">

                            <i class="fas fa-layer-group"></i>

                            ${paintEntries.length}
                            Paints

                        </span>

                    </div>


                    <div class="wp-selection-search">

                        <i class="fas fa-magnifying-glass"></i>

                        <input
                            type="search"
                            id="skinPaintSearch"
                            class="wp-addon-search"
                            placeholder="جستجوی Paint..."
                            autocomplete="off"
                        >

                    </div>

                </div>


                <div
                    class="selection-grid selection-grid--skin"
                    id="skinSelectionGrid"
                >

        `;


        if (
            paintEntries.length ===
            0
        ) {

            html += `

                <div class="wp-search-empty">

                    <i class="fas fa-box-open"></i>

                    <span>
                        اسکینی برای این سلاح پیدا نشد.
                    </span>

                </div>

            `;

        }


        paintEntries.forEach(
            (
                [paintId, paint]
            ) => {

                const checked =
                    selectedPaintId ===
                    String(
                        paintId
                    );


                const paintName =
                    paint.paint_name ||
                    'Unknown Paint';


                html += `

                    <label
                        class="selection-card selection-card--skin ${checked ? 'is-selected' : ''}"
                        data-skin-paint-name="${escapeHtml(
                            normalizeText(
                                paintName
                            )
                        )}"
                    >

                        <input
                            type="radio"
                            name="forma"
                            value="${escapeHtml(
                                String(
                                    defindex
                                )
                                + '-'
                                + String(
                                    paintId
                                )
                            )}"
                            ${checked ? 'checked' : ''}
                        >

                        <div class="selection-image">

                            ${imageMarkup(
                                paint.image_url,
                                paintName
                            )}

                        </div>

                        <div class="selection-title">

                            ${escapeHtml(
                                paintName
                            )}

                        </div>

                        <div class="selection-meta">

                            <i class="fas fa-hashtag"></i>

                            Paint ID:

                            ${escapeHtml(
                                paintId
                            )}

                        </div>

                    </label>

                `;

            }
        );


        html += `

                </div>


                <div class="wp-skin-controls">


                    <div class="wp-control-card">

                        <label class="wp-control-label">

                            <span>
                                Wear
                            </span>

                            <span
                                class="wp-control-value"
                                id="wearValue"
                            >
                                ${escapeHtml(
                                    selectedWear
                                )}
                            </span>

                        </label>

                        <input
                            type="number"
                            name="wear"
                            step="0.001"
                            min="0"
                            max="1"
                            value="${escapeHtml(
                                selectedWear
                            )}"
                            class="wp-control-input"
                            id="skinWearInput"
                        >

                    </div>


                    <div class="wp-control-card">

                        <label class="wp-control-label">

                            <span>
                                Seed
                            </span>

                            <span class="wp-control-value">
                                Pattern
                            </span>

                        </label>

                        <input
                            type="number"
                            name="seed"
                            min="0"
                            value="${escapeHtml(
                                selectedSeed
                            )}"
                            class="wp-control-input"
                        >

                    </div>


                    <div class="wp-control-card">

                        <label class="wp-control-label">

                            <span>
                                Name Tag
                            </span>

                            <span class="wp-control-value">
                                Max 20
                            </span>

                        </label>

                        <input
                            type="text"
                            name="nametag"
                            maxlength="20"
                            value="${escapeHtml(
                                selectedNameTag
                            )}"
                            class="wp-control-input"
                            placeholder="نام سفارشی..."
                        >

                    </div>


                    <div class="wp-control-card">

                        <label class="settings-option wp-inline-toggle">

                            <span class="settings-option-content">

                                <span class="settings-option-icon">

                                    <i class="fas fa-star"></i>

                                </span>

                                <span>

                                    <strong>
                                        StatTrak™
                                    </strong>

                                    <small>
                                        فعال‌سازی شمارنده Kill
                                    </small>

                                </span>

                            </span>

                            <input
                                type="checkbox"
                                name="stattrak"
                                value="1"
                                ${selectedStatTrak ? 'checked' : ''}
                            >

                            <span class="settings-switch">

                                <span></span>

                            </span>

                        </label>

                    </div>


        `;


        if (
            !knifeWeapon
        ) {

            html += `

                    <div class="wp-control-card wp-addon-control-card">

                        <div class="wp-control-label">

                            <span>

                                <i class="fas fa-layer-group"></i>

                                Stickers & Keychain

                            </span>

                            <span class="wp-control-value">
                                5 Slots + 1 Keychain
                            </span>

                        </div>


                        <div
                            class="wp-addons-grid"
                            id="stickerKeychainContainer"
                        >

            `;


            for (
                let slot = 0;
                slot < 5;
                slot++
            ) {

                const stickerRaw =
                    detail[
                        `weapon_sticker_${slot}`
                    ] ||
                    '0;0;0;0.001;1;0';


                const sticker =
                    parseStickerValue(
                        stickerRaw
                    );


                const stickerInfo =
                    stickersData[
                        sticker.id
                    ] ||
                    stickersData[
                        String(
                            sticker.id
                        )
                    ] ||
                    null;


                html += `

                    <button
                        type="button"
                        class="wp-addon-btn wp-sticker-slot"
                        data-sticker-slot="${slot}"
                    >

                        ${
                            stickerInfo
                                ? imageMarkup(
                                    stickerInfo.image_url,
                                    stickerInfo.name,
                                    'wp-addon-preview-image'
                                )
                                : `
                                    <span class="wp-addon-empty-icon">
                                        <i class="fas fa-plus"></i>
                                    </span>
                                `
                        }

                        <span>
                            Sticker ${slot + 1}
                        </span>

                    </button>


                    <input
                        type="hidden"
                        name="sticker[${slot}][id]"
                        value="${escapeHtml(
                            sticker.id
                        )}"
                    >

                    <input
                        type="hidden"
                        name="sticker[${slot}][wear]"
                        value="${escapeHtml(
                            sticker.wear
                        )}"
                    >

                    <input
                        type="hidden"
                        name="sticker[${slot}][x]"
                        value="${escapeHtml(
                            sticker.x
                        )}"
                    >

                    <input
                        type="hidden"
                        name="sticker[${slot}][y]"
                        value="${escapeHtml(
                            sticker.y
                        )}"
                    >

                    <input
                        type="hidden"
                        name="sticker[${slot}][scale]"
                        value="${escapeHtml(
                            sticker.scale
                        )}"
                    >

                    <input
                        type="hidden"
                        name="sticker[${slot}][rotate]"
                        value="${escapeHtml(
                            sticker.rotate
                        )}"
                    >

                `;

            }


            const keychainRaw =
                detail.weapon_keychain ||
                '0;0;0;0;0';


            const keychain =
                parseKeychainValue(
                    keychainRaw
                );


            const keychainInfo =
                keychainsData[
                    keychain.id
                ] ||
                keychainsData[
                    String(
                        keychain.id
                    )
                ] ||
                null;


            html += `

                    <button
                        type="button"
                        class="wp-addon-btn"
                        data-keychain-trigger
                    >

                        ${
                            keychainInfo
                                ? imageMarkup(
                                    keychainInfo.image_url,
                                    keychainInfo.name,
                                    'wp-addon-preview-image'
                                )
                                : `
                                    <span class="wp-addon-empty-icon">
                                        <i class="fas fa-key"></i>
                                    </span>
                                `
                        }

                        <span>
                            Keychain
                        </span>

                    </button>


                    <input
                        type="hidden"
                        name="keychain[id]"
                        value="${escapeHtml(
                            keychain.id
                        )}"
                    >

                    <input
                        type="hidden"
                        name="keychain[seed]"
                        value="${escapeHtml(
                            keychain.seed
                        )}"
                    >

                    <input
                        type="hidden"
                        name="keychain[x]"
                        value="${escapeHtml(
                            keychain.x
                        )}"
                    >

                    <input
                        type="hidden"
                        name="keychain[y]"
                        value="${escapeHtml(
                            keychain.y
                        )}"
                    >

                    <input
                        type="hidden"
                        name="keychain[z]"
                        value="${escapeHtml(
                            keychain.z
                        )}"
                    >

            `;

        } else {

            html += `

                <div class="wp-control-card">

                    <div class="wp-selection-warning">

                        <i class="fas fa-lock"></i>

                        <span>
                            Knife skins do not support
                            stickers or keychains.
                        </span>

                    </div>

                </div>

            `;

        }


        html += `

                </div>


                <div class="selection-footer">

                    <button
                        type="submit"
                        class="wp-btn wp-btn--primary"
                    >

                        <i class="fas fa-save"></i>

                        Save Customization

                    </button>

                </div>


            </form>

        `;


        dom.skinModalBody.innerHTML =
            html;


        bindSkinModalInteractions();

    }


    function bindSkinModalInteractions() {

        if (
            !dom.skinModalBody
        ) {

            return;

        }


        const paintSearch =
            document.getElementById(
                'skinPaintSearch'
            );


        if (
            paintSearch
        ) {

            paintSearch.addEventListener(
                'input',
                () => {

                    if (
                        state.modalSearchTimer
                    ) {

                        window.clearTimeout(
                            state.modalSearchTimer
                        );

                    }


                    state.modalSearchTimer =
                        window.setTimeout(
                            () => {

                                filterSkinPaints(
                                    paintSearch.value
                                );

                            },
                            80
                        );

                }
            );

        }


        const wearInput =
            document.getElementById(
                'skinWearInput'
            );


        const wearValue =
            document.getElementById(
                'wearValue'
            );


        if (
            wearInput &&
            wearValue
        ) {

            wearInput.addEventListener(
                'input',
                () => {

                    wearValue.textContent =
                        Number(
                            wearInput.value
                        )
                            .toFixed(
                                3
                            );

                }
            );

        }

    }


    function filterSkinPaints(
        term
    ) {

        const normalized =
            normalizeText(
                term
            );


        const cards =
            dom.skinModalBody
                ? dom.skinModalBody.querySelectorAll(
                    '.selection-card--skin'
                )
                : [];


        let visible =
            0;


        cards.forEach(
            card => {

                const name =
                    card.dataset.skinPaintName ||
                    card.textContent ||
                    '';


                const matches =
                    !normalized ||
                    normalizeText(
                        name
                    ).includes(
                        normalized
                    );


                card.classList.toggle(
                    'is-search-hidden',
                    !matches
                );


                if (
                    matches
                ) {

                    visible++;

                }

            }
        );


        const grid =
            document.getElementById(
                'skinSelectionGrid'
            );


        if (
            !grid
        ) {

            return;

        }


        let empty =
            grid.querySelector(
                '[data-skin-search-empty]'
            );


        if (
            !empty
        ) {

            empty =
                document.createElement(
                    'div'
                );


            empty.className =
                'wp-search-empty';


            empty.dataset.skinSearchEmpty =
                'true';


            empty.innerHTML = `

                <i class="fas fa-magnifying-glass"></i>

                <span>
                    اسکینی با این عبارت پیدا نشد.
                </span>

            `;


            grid.appendChild(
                empty
            );

        }


        empty.classList.toggle(
            'is-hidden',
            visible >
            0
        );

    }


    /* =========================================================
       09. Skin Modal Opening
    ========================================================== */

    function openSkinModal(
        defindex
    ) {

        state.activeDefindex =
            String(
                defindex
            );

        renderSkinModal(
            defindex
        );

        if (dom.skinModal) {
            ensureModalInBody(
                dom.skinModal
            );
            showCustomModal(
                dom.skinModal
            );
        }

    }


    /* =========================================================
       10. Nested Modal System
    ========================================================== */

    function openNestedModal(
        childModal,
        parentModal
    ) {

        const child =
            typeof childModal === 'string'
                ? document.getElementById(childModal)
                : childModal;

        const parent =
            typeof parentModal === 'string'
                ? document.getElementById(parentModal)
                : parentModal;

        if (!child) {
            return;
        }

        if (
            parent &&
            parent.classList.contains('wp-is-open')
        ) {

            state.parentModalId =
                parent.id || null;

            state.parentModalTemporarilyHidden =
                true;

            hideCustomModal(
                parent,
                {
                    restoreParent: false
                }
            );

        }

        ensureModalInBody(
            child
        );
        showCustomModal(
            child
        );

    }


    function restoreParentModal() {

        const parentId =
            state.parentModalId;

        if (
            !state.parentModalTemporarilyHidden ||
            !parentId
        ) {
            return;
        }

        const parent =
            document.getElementById(
                parentId
            );

        state.parentModalId =
            null;

        state.parentModalTemporarilyHidden =
            false;

        if (parent) {

            window.setTimeout(
                () => {
                    showCustomModal(
                        parent
                    );
                },
                60
            );

        }

    }


    /* =========================================================
       11. Sticker Modal
    ========================================================== */

    function getStickerFormState() {

        const form =
            getCurrentForm();


        if (
            !form ||
            state.currentStickerSlot ===
                null
        ) {

            return {

                id:
                    '0',

                wear:
                    '0.001',

                x:
                    '0',

                y:
                    '0',

                scale:
                    '1',

                rotate:
                    '0'

            };

        }


        const slot =
            state.currentStickerSlot;


        return {

            id:
                getFormValue(
                    form,
                    `sticker[${slot}][id]`,
                    '0'
                ),

            wear:
                getFormValue(
                    form,
                    `sticker[${slot}][wear]`,
                    '0.001'
                ),

            x:
                getFormValue(
                    form,
                    `sticker[${slot}][x]`,
                    '0'
                ),

            y:
                getFormValue(
                    form,
                    `sticker[${slot}][y]`,
                    '0'
                ),

            scale:
                getFormValue(
                    form,
                    `sticker[${slot}][scale]`,
                    '1'
                ),

            rotate:
                getFormValue(
                    form,
                    `sticker[${slot}][rotate]`,
                    '0'
                )

        };

    }


    function renderStickerModal() {

        if (
            !dom.stickerModalBody
        ) {

            return;

        }


        const current =
            getStickerFormState();


        state.stickerPage =
            0;


        state.stickerFilter =
            '';


        dom.stickerModalBody.innerHTML = `

            <div class="wp-addon-toolbar">

                <div class="wp-selection-toolbar-info">

                    <span class="wp-meta-pill">

                        <i class="fas fa-sticky-note"></i>

                        Sticker

                    </span>

                </div>


                <input
                    type="search"
                    id="stickerSearchInput"
                    class="wp-addon-search"
                    placeholder="جستجوی استیکر..."
                    autocomplete="off"
                >

            </div>


            <div
                class="wp-addon-grid"
                id="stickerGrid"
            ></div>


            <button
                type="button"
                class="wp-btn wp-btn--secondary"
                id="stickerLoadMore"
                hidden
            >

                <i class="fas fa-plus"></i>

                نمایش بیشتر

            </button>


            <div class="wp-control-card">

                <div class="wp-control-label">

                    <span>
                        Sticker Properties
                    </span>

                    <span class="wp-control-value">
                        Advanced
                    </span>

                </div>


                <div class="wp-skin-controls">


                    <input
                        type="number"
                        id="stickerWear"
                        class="wp-control-input"
                        min="0"
                        max="1"
                        step="0.001"
                        value="${escapeHtml(
                            current.wear
                        )}"
                        placeholder="Wear"
                    >


                    <input
                        type="number"
                        id="stickerX"
                        class="wp-control-input"
                        value="${escapeHtml(
                            current.x
                        )}"
                        placeholder="X"
                    >


                    <input
                        type="number"
                        id="stickerY"
                        class="wp-control-input"
                        value="${escapeHtml(
                            current.y
                        )}"
                        placeholder="Y"
                    >


                    <input
                        type="number"
                        id="stickerScale"
                        class="wp-control-input"
                        step="0.01"
                        value="${escapeHtml(
                            current.scale
                        )}"
                        placeholder="Scale"
                    >


                    <input
                        type="number"
                        id="stickerRotate"
                        class="wp-control-input"
                        value="${escapeHtml(
                            current.rotate
                        )}"
                        placeholder="Rotate"
                    >

                </div>

            </div>


            <div class="selection-footer">

                <button
                    type="button"
                    class="wp-btn wp-btn--primary"
                    id="applyStickerButton"
                >

                    <i class="fas fa-check"></i>

                    اعمال Sticker

                </button>

            </div>

        `;


        renderStickerPage(
            false
        );


        bindStickerModalEvents();

    }


    function getFilteredStickerIds() {

        const ids =
            Object.keys(
                stickersData
            );


        const filter =
            normalizeText(
                state.stickerFilter
            );


        return ids.filter(
            id => {

                if (
                    !filter
                ) {

                    return true;

                }


                const item =
                    stickersData[id] ||
                    {};


                return normalizeText(
                    item.name
                )
                    .includes(
                        filter
                    );

            }
        );

    }


    function createAddonNoneCard(
        type,
        selected
    ) {

        const activeClass =
            String(
                selected
            ) ===
            '0'
                ? 'is-selected'
                : '';


        const icon =
            type ===
            'sticker'
                ? 'fa-ban'
                : 'fa-xmark';


        const title =
            type ===
            'sticker'
                ? 'بدون استیکر'
                : 'بدون جاکلیدی';


        const inputName =
            type ===
            'sticker'
                ? 'sticker_select'
                : 'keychain_select';


        return `

            <label
                class="wp-addon-card ${activeClass}"
                data-addon-id="0"
            >

                <input
                    type="radio"
                    name="${inputName}"
                    value="0"
                    ${String(selected) === '0' ? 'checked' : ''}
                >

                <div class="wp-addon-none-icon">

                    <i class="fas ${icon}"></i>

                </div>

                <div class="wp-addon-card-title">

                    ${title}

                </div>

            </label>

        `;

    }


    function renderStickerPage(
        append = false
    ) {

        const grid =
            document.getElementById(
                'stickerGrid'
            );


        if (
            !grid
        ) {

            return;

        }


        const ids =
            getFilteredStickerIds();


        const start =
            state.stickerPage *
            state.stickerPageSize;


        const end =
            start +
            state.stickerPageSize;


        const pageIds =
            ids.slice(
                start,
                end
            );


        let html =
            '';


        if (
            !append &&
            state.stickerPage ===
                0
        ) {

            const current =
                getStickerFormState();


            html +=
                createAddonNoneCard(
                    'sticker',
                    current.id
                );

        }


        pageIds.forEach(
            id => {

                const sticker =
                    stickersData[id] ||
                    {};


                const current =
                    getStickerFormState();


                const selected =
                    String(
                        current.id
                    ) ===
                    String(
                        id
                    );


                html += `

                    <label
                        class="wp-addon-card ${selected ? 'is-selected' : ''}"
                        data-addon-id="${escapeHtml(id)}"
                    >

                        <input
                            type="radio"
                            name="sticker_select"
                            value="${escapeHtml(id)}"
                            ${selected ? 'checked' : ''}
                        >


                        <div class="wp-addon-card-media">

                            ${imageMarkup(
                                sticker.image_url,
                                sticker.name,
                                'wp-addon-image'
                            )}

                        </div>


                        <div class="wp-addon-card-title">

                            ${escapeHtml(
                                sticker.name ||
                                'Sticker'
                            )}

                        </div>


                        <div class="wp-addon-card-meta">

                            Sticker ID:

                            ${escapeHtml(id)}

                        </div>

                    </label>

                `;

            }
        );


        if (
            !append
        ) {

            grid.innerHTML =
                html;

        } else {

            grid.insertAdjacentHTML(
                'beforeend',
                html
            );

        }


        const hasMore =
            ids.length >
            end;


        const loadMore =
            document.getElementById(
                'stickerLoadMore'
            );


        if (
            loadMore
        ) {

            loadMore.hidden =
                !hasMore;

        }


        if (
            !pageIds.length &&
            state.stickerPage ===
                0
        ) {

            grid.innerHTML = `

                <div class="wp-search-empty">

                    <i class="fas fa-magnifying-glass"></i>

                    <span>
                        موردی یافت نشد.
                    </span>

                </div>

            `;

        }

    }


    function bindStickerModalEvents() {

        const search =
            document.getElementById(
                'stickerSearchInput'
            );


        if (
            search
        ) {

            search.addEventListener(
                'input',
                () => {

                    state.stickerFilter =
                        search.value;


                    state.stickerPage =
                        0;


                    renderStickerPage(
                        false
                    );

                }
            );

        }


        const loadMore =
            document.getElementById(
                'stickerLoadMore'
            );


        if (
            loadMore
        ) {

            loadMore.addEventListener(
                'click',
                () => {

                    state.stickerPage++;

                    renderStickerPage(
                        true
                    );

                }
            );

        }


        const applyButton =
            document.getElementById(
                'applyStickerButton'
            );


        if (
            applyButton
        ) {

            applyButton.addEventListener(
                'click',
                applySticker
            );

        }

    }


    function applySticker() {

        const form =
            getCurrentForm();


        if (
            !form ||
            state.currentStickerSlot ===
                null
        ) {

            return;

        }


        const selected =
            dom.stickerModal
                ? dom.stickerModal.querySelector(
                    'input[name="sticker_select"]:checked'
                )
                : null;


        const id =
            selected
                ? selected.value
                : '0';


        const slot =
            state.currentStickerSlot;


        setFormValue(
            form,
            `sticker[${slot}][id]`,
            id
        );


        setFormValue(
            form,
            `sticker[${slot}][wear]`,
            document.getElementById(
                'stickerWear'
            )?.value ||
            '0.001'
        );


        setFormValue(
            form,
            `sticker[${slot}][x]`,
            document.getElementById(
                'stickerX'
            )?.value ||
            '0'
        );


        setFormValue(
            form,
            `sticker[${slot}][y]`,
            document.getElementById(
                'stickerY'
            )?.value ||
            '0'
        );


        setFormValue(
            form,
            `sticker[${slot}][scale]`,
            document.getElementById(
                'stickerScale'
            )?.value ||
            '1'
        );


        setFormValue(
            form,
            `sticker[${slot}][rotate]`,
            document.getElementById(
                'stickerRotate'
            )?.value ||
            '0'
        );


        updateStickerSlotPreview(
            slot,
            id
        );


        getBootstrapModal(
            dom.stickerModal
        )?.hide();

    }


    function updateStickerSlotPreview(
        slot,
        id
    ) {

        const slotButton =
            dom.skinModalBody
                ? dom.skinModalBody.querySelector(
                    `[data-sticker-slot="${CSS.escape(
                        String(slot)
                    )}"]`
                )
                : null;


        if (
            !slotButton
        ) {

            return;

        }


        const sticker =
            stickersData[id] ||
            stickersData[
                String(id)
            ] ||
            null;


        slotButton.innerHTML = `

            ${
                sticker
                    ? imageMarkup(
                        sticker.image_url,
                        sticker.name,
                        'wp-addon-preview-image'
                    )
                    : `
                        <span class="wp-addon-empty-icon">
                            <i class="fas fa-plus"></i>
                        </span>
                    `
            }

            <span>
                Sticker ${slot + 1}
            </span>

        `;

    }


    window.openStickerModal =
        function (
            def,
            slot
        ) {

            state.activeDefindex =
                String(
                    def
                );


            state.currentStickerSlot =
                Number(
                    slot
                );


            renderStickerModal();


            openNestedModal(
                dom.stickerModal,
                dom.skinModal
            );

        };


    window.applySticker =
        applySticker;


    /* =========================================================
       12. Keychain Modal
    ========================================================== */

    function getKeychainFormState() {

        const form =
            getCurrentForm();


        if (
            !form
        ) {

            return {

                id:
                    '0',

                seed:
                    '0',

                x:
                    '0',

                y:
                    '0',

                z:
                    '0'

            };

        }


        return {

            id:
                getFormValue(
                    form,
                    'keychain[id]',
                    '0'
                ),

            seed:
                getFormValue(
                    form,
                    'keychain[seed]',
                    '0'
                ),

            x:
                getFormValue(
                    form,
                    'keychain[x]',
                    '0'
                ),

            y:
                getFormValue(
                    form,
                    'keychain[y]',
                    '0'
                ),

            z:
                getFormValue(
                    form,
                    'keychain[z]',
                    '0'
                )

        };

    }


    function renderKeychainModal() {

        if (
            !dom.keychainModalBody
        ) {

            return;

        }


        const current =
            getKeychainFormState();


        state.keychainPage =
            0;


        state.keychainFilter =
            '';


        dom.keychainModalBody.innerHTML = `

            <div class="wp-addon-toolbar">

                <div class="wp-selection-toolbar-info">

                    <span class="wp-meta-pill">

                        <i class="fas fa-key"></i>

                        Keychain

                    </span>

                </div>


                <input
                    type="search"
                    id="keychainSearchInput"
                    class="wp-addon-search"
                    placeholder="جستجوی جاکلیدی..."
                    autocomplete="off"
                >

            </div>


            <div
                class="wp-addon-grid"
                id="keychainGrid"
            ></div>


            <button
                type="button"
                class="wp-btn wp-btn--secondary"
                id="keychainLoadMore"
                hidden
            >

                <i class="fas fa-plus"></i>

                نمایش بیشتر

            </button>


            <div class="wp-control-card">

                <div class="wp-control-label">

                    <span>
                        Keychain Properties
                    </span>

                    <span class="wp-control-value">
                        Advanced
                    </span>

                </div>


                <div class="wp-skin-controls">

                    <input
                        type="number"
                        id="keychainSeed"
                        class="wp-control-input"
                        value="${escapeHtml(
                            current.seed
                        )}"
                        placeholder="Seed"
                    >


                    <input
                        type="number"
                        id="keychainX"
                        class="wp-control-input"
                        value="${escapeHtml(
                            current.x
                        )}"
                        placeholder="X"
                    >


                    <input
                        type="number"
                        id="keychainY"
                        class="wp-control-input"
                        value="${escapeHtml(
                            current.y
                        )}"
                        placeholder="Y"
                    >


                    <input
                        type="number"
                        id="keychainZ"
                        class="wp-control-input"
                        value="${escapeHtml(
                            current.z
                        )}"
                        placeholder="Z"
                    >

                </div>

            </div>


            <div class="selection-footer">

                <button
                    type="button"
                    class="wp-btn wp-btn--primary"
                    id="applyKeychainButton"
                >

                    <i class="fas fa-check"></i>

                    اعمال Keychain

                </button>

            </div>

        `;


        renderKeychainPage(
            false
        );


        bindKeychainModalEvents();

    }


    function getFilteredKeychainIds() {

        const ids =
            Object.keys(
                keychainsData
            );


        const filter =
            normalizeText(
                state.keychainFilter
            );


        return ids.filter(
            id => {

                if (
                    !filter
                ) {

                    return true;

                }


                const item =
                    keychainsData[id] ||
                    {};


                return normalizeText(
                    item.name
                )
                    .includes(
                        filter
                    );

            }
        );

    }


    function renderKeychainPage(
        append = false
    ) {

        const grid =
            document.getElementById(
                'keychainGrid'
            );


        if (
            !grid
        ) {

            return;

        }


        const ids =
            getFilteredKeychainIds();


        const start =
            state.keychainPage *
            state.keychainPageSize;


        const end =
            start +
            state.keychainPageSize;


        const pageIds =
            ids.slice(
                start,
                end
            );


        let html =
            '';


        if (
            !append &&
            state.keychainPage ===
                0
        ) {

            const current =
                getKeychainFormState();


            html +=
                createAddonNoneCard(
                    'keychain',
                    current.id
                );

        }


        pageIds.forEach(
            id => {

                const keychain =
                    keychainsData[id] ||
                    {};


                const current =
                    getKeychainFormState();


                const selected =
                    String(
                        current.id
                    ) ===
                    String(
                        id
                    );


                html += `

                    <label
                        class="wp-addon-card ${selected ? 'is-selected' : ''}"
                        data-addon-id="${escapeHtml(id)}"
                    >

                        <input
                            type="radio"
                            name="keychain_select"
                            value="${escapeHtml(id)}"
                            ${selected ? 'checked' : ''}
                        >


                        <div class="wp-addon-card-media">

                            ${imageMarkup(
                                keychain.image_url,
                                keychain.name,
                                'wp-addon-image'
                            )}

                        </div>


                        <div class="wp-addon-card-title">

                            ${escapeHtml(
                                keychain.name ||
                                'Keychain'
                            )}

                        </div>


                        <div class="wp-addon-card-meta">

                            Keychain ID:

                            ${escapeHtml(id)}

                        </div>

                    </label>

                `;

            }
        );


        if (
            !append
        ) {

            grid.innerHTML =
                html;

        } else {

            grid.insertAdjacentHTML(
                'beforeend',
                html
            );

        }


        const hasMore =
            ids.length >
            end;


        const loadMore =
            document.getElementById(
                'keychainLoadMore'
            );


        if (
            loadMore
        ) {

            loadMore.hidden =
                !hasMore;

        }


        if (
            !pageIds.length &&
            state.keychainPage ===
                0
        ) {

            grid.innerHTML = `

                <div class="wp-search-empty">

                    <i class="fas fa-magnifying-glass"></i>

                    <span>
                        موردی یافت نشد.
                    </span>

                </div>

            `;

        }

    }


    function bindKeychainModalEvents() {

        const search =
            document.getElementById(
                'keychainSearchInput'
            );


        if (
            search
        ) {

            search.addEventListener(
                'input',
                () => {

                    state.keychainFilter =
                        search.value;


                    state.keychainPage =
                        0;


                    renderKeychainPage(
                        false
                    );

                }
            );

        }


        const loadMore =
            document.getElementById(
                'keychainLoadMore'
            );


        if (
            loadMore
        ) {

            loadMore.addEventListener(
                'click',
                () => {

                    state.keychainPage++;

                    renderKeychainPage(
                        true
                    );

                }
            );

        }


        const applyButton =
            document.getElementById(
                'applyKeychainButton'
            );


        if (
            applyButton
        ) {

            applyButton.addEventListener(
                'click',
                applyKeychain
            );

        }

    }


    function applyKeychain() {

        const form =
            getCurrentForm();


        if (
            !form
        ) {

            return;

        }


        const selected =
            dom.keychainModal
                ? dom.keychainModal.querySelector(
                    'input[name="keychain_select"]:checked'
                )
                : null;


        const id =
            selected
                ? selected.value
                : '0';


        setFormValue(
            form,
            'keychain[id]',
            id
        );


        setFormValue(
            form,
            'keychain[seed]',
            document.getElementById(
                'keychainSeed'
            )?.value ||
            '0'
        );


        setFormValue(
            form,
            'keychain[x]',
            document.getElementById(
                'keychainX'
            )?.value ||
            '0'
        );


        setFormValue(
            form,
            'keychain[y]',
            document.getElementById(
                'keychainY'
            )?.value ||
            '0'
        );


        setFormValue(
            form,
            'keychain[z]',
            document.getElementById(
                'keychainZ'
            )?.value ||
            '0'
        );


        updateKeychainSlotPreview(
            id
        );


        getBootstrapModal(
            dom.keychainModal
        )?.hide();

    }


    function updateKeychainSlotPreview(
        id
    ) {

        const slotButton =
            dom.skinModalBody
                ? dom.skinModalBody.querySelector(
                    '[data-keychain-trigger]'
                )
                : null;


        if (
            !slotButton
        ) {

            return;

        }


        const keychain =
            keychainsData[id] ||
            keychainsData[
                String(id)
            ] ||
            null;


        slotButton.innerHTML = `

            ${
                keychain
                    ? imageMarkup(
                        keychain.image_url,
                        keychain.name,
                        'wp-addon-preview-image'
                    )
                    : `
                        <span class="wp-addon-empty-icon">
                            <i class="fas fa-key"></i>
                        </span>
                    `
            }

            <span>
                Keychain
            </span>

        `;

    }


    window.openKeychainModal =
        function (
            def
        ) {

            state.activeDefindex =
                String(
                    def
                );


            renderKeychainModal();


            openNestedModal(
                dom.keychainModal,
                dom.skinModal
            );

        };


    window.applyKeychain =
        applyKeychain;


    /* =========================================================
       13. Generic Selection Card State
    ========================================================== */

    function updateSelectionCardState(
        input
    ) {

        if (
            !input
        ) {

            return;

        }


        const scope =
            input.closest(
                '.selection-grid, .wp-addon-grid'
            );


        if (
            !scope
        ) {

            return;

        }


        scope
            .querySelectorAll(
                '.selection-card.is-selected, .wp-addon-card.is-selected'
            )
            .forEach(
                card => {

                    card.classList.remove(
                        'is-selected'
                    );

                }
            );


        const card =
            input.closest(
                '.selection-card, .wp-addon-card'
            );


        if (
            card
        ) {

            card.classList.add(
                'is-selected'
            );

        }

    }


    /* =========================================================
       14. Modal Event Wiring
    ========================================================== */

    function prepareCustomModalTriggers() {

        const triggers =
            document.querySelectorAll(
                '.wp-app [data-bs-toggle="modal"][data-bs-target], .wp-app [data-wp-modal-trigger="1"][data-bs-target]'
            );

        triggers.forEach(
            trigger => {
                trigger.setAttribute(
                    'data-wp-modal-trigger',
                    '1'
                );
            }
        );

        const closeButtons =
            document.querySelectorAll(
                '.wp-modal [data-bs-dismiss="modal"], .wp-modal [data-wp-modal-close="1"]'
            );

        closeButtons.forEach(
            button => {
                button.setAttribute(
                    'data-wp-modal-close',
                    '1'
                );
            }
        );

        const modals =
            document.querySelectorAll(
                '.wp-modal'
            );

        modals.forEach(
            modal => {
                ensureModalInBody(modal);
            }
        );

    }


    function getModalFromTrigger(
        trigger
    ) {

        if (!trigger) {
            return null;
        }

        const selector =
            trigger.getAttribute(
                'data-bs-target'
            ) ||
            trigger.getAttribute(
                'data-wp-modal-target'
            ) ||
            '';

        if (!selector) {
            return null;
        }

        try {
            return document.querySelector(
                selector
            );
        } catch (error) {
            return null;
        }

    }


    function handleWeaponPaintsModalTrigger(
        trigger
    ) {

        const modal =
            getModalFromTrigger(
                trigger
            );

        if (!modal) {
            return;
        }

        const sourceTab =
            getTabIdFromElement(
                trigger
            );

        if (sourceTab) {

            state.sourceTabForModal =
                sourceTab;

            state.currentActiveTab =
                sourceTab;

            updateTabUrl(
                sourceTab
            );

        }

        if (
            modal === dom.skinModal
        ) {

            const defindex =
                trigger.getAttribute(
                    'data-def'
                ) || trigger.closest('[data-def]')?.getAttribute('data-def');

            if (defindex === null) {
                return;
            }

            openSkinModal(
                defindex
            );
            return;

        }

        ensureModalInBody(
            modal
        );

        showCustomModal(
            modal
        );

    }


    function bindModalEvents() {

        prepareCustomModalTriggers();

        // Make clickable knife cards usable from the keyboard as well.
        document.addEventListener(
            'keydown',
            event => {
                if (event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }

                const trigger = event.target.closest(
                    '[data-wp-modal-trigger="1"][role="button"]'
                );

                if (!trigger) {
                    return;
                }

                event.preventDefault();
                trigger.click();
            },
            true
        );

        document.addEventListener(
            'click',
            event => {

                const trigger =
                    event.target.closest(
                        '[data-wp-modal-trigger="1"]'
                    );

                if (trigger) {

                    event.preventDefault();
                    event.stopImmediatePropagation();

                    handleWeaponPaintsModalTrigger(
                        trigger
                    );

                    return;

                }

                const closeButton =
                    event.target.closest(
                        '[data-wp-modal-close="1"]'
                    );

                if (closeButton) {

                    const modal =
                        closeButton.closest(
                            '.wp-modal'
                        );

                    if (modal) {

                        event.preventDefault();
                        event.stopImmediatePropagation();

                        hideCustomModal(
                            modal
                        );

                    }

                    return;

                }

            },
            true
        );

        document.addEventListener(
            'click',
            event => {

                const modal =
                    event.target.closest(
                        '.wp-modal'
                    );

                if (
                    modal &&
                    event.target === modal
                ) {

                    hideCustomModal(
                        modal
                    );

                }

            }
        );

        if (dom.skinModal) {

            dom.skinModal.addEventListener(
                'hidden.wp.modal',
                () => {

                    if (
                        state.parentModalTemporarilyHidden
                    ) {
                        return;
                    }

                    state.activeDefindex =
                        null;

                    state.sourceTabForModal =
                        null;

                    if (dom.skinModalBody) {

                        dom.skinModalBody.innerHTML =
                            '';

                    }

                }
            );

        }

        if (dom.stickerModal) {

            dom.stickerModal.addEventListener(
                'hidden.wp.modal',
                () => {
                    restoreParentModal();
                }
            );

        }

        if (dom.keychainModal) {

            dom.keychainModal.addEventListener(
                'hidden.wp.modal',
                () => {
                    restoreParentModal();
                }
            );

        }

        window.addEventListener(
            'keydown',
            event => {

                if (
                    event.key !== 'Escape'
                ) {
                    return;
                }

                const openModals =
                    document.querySelectorAll(
                        'body > .wp-modal.wp-is-open'
                    );

                const topModal =
                    openModals.length
                        ? openModals[openModals.length - 1]
                        : null;

                if (topModal) {

                    event.preventDefault();

                    hideCustomModal(
                        topModal
                    );

                }

            },
            true
        );

    }


    /* =========================================================
       15. Delegated Events
    ========================================================== */

    function bindDelegatedEvents() {

        document.addEventListener(
            'change',
            event => {

                const target =
                    event.target;

                if (
                    target.matches(
                        '.selection-card input[type="radio"], .wp-addon-card input[type="radio"]'
                    )
                ) {

                    updateSelectionCardState(
                        target
                    );

                }

            }
        );

        document.addEventListener(
            'click',
            event => {

                const stickerTrigger =
                    event.target.closest(
                        '[data-sticker-slot]'
                    );

                if (stickerTrigger) {

                    event.preventDefault();

                    state.currentStickerSlot =
                        Number(
                            stickerTrigger.dataset.stickerSlot
                        );

                    renderStickerModal();

                    openNestedModal(
                        dom.stickerModal,
                        dom.skinModal
                    );

                    return;

                }

                const keychainTrigger =
                    event.target.closest(
                        '[data-keychain-trigger]'
                    );

                if (keychainTrigger) {

                    event.preventDefault();

                    renderKeychainModal();

                    openNestedModal(
                        dom.keychainModal,
                        dom.skinModal
                    );

                    return;

                }

            }
        );

        // Use window capture so the shared site's AJAX/navigation submit
        // listener cannot steal Weapon Paints forms before this handler.
        window.addEventListener(
            'submit',
            async event => {

                const form =
                    event.target;

                if (
                    !(form instanceof HTMLFormElement) ||
                    !(
                        form.closest('.wp-app') ||
                        form.closest('.wp-modal')
                    )
                ) {
                    return;
                }

                event.preventDefault();
                event.stopImmediatePropagation();

                if (
                    form.dataset.wpSubmitInProgress === '1'
                ) {
                    return;
                }

                const sourceTab =
                    state.sourceTabForModal ||
                    getTabIdFromElement(form) ||
                    state.currentActiveTab ||
                    'tab-knives';

                state.currentActiveTab =
                    sourceTab;

                syncFormActiveTab(
                    form
                );

                if (
                    form.id === 'skinForm'
                ) {

                    const selectedPaint =
                        form.querySelector(
                            'input[name="forma"]:checked'
                        );

                    if (!selectedPaint) {

                        const grid =
                            document.getElementById(
                                'skinSelectionGrid'
                            );

                        if (grid) {

                            grid.scrollIntoView({
                                behavior: 'smooth',
                                block: 'center'
                            });

                        }

                        return;

                    }

                }

                form.dataset.wpSubmitInProgress =
                    '1';

                form.classList.add(
                    'wp-form-submitting'
                );

                const submitter =
                    event.submitter;

                const formData =
                    new FormData(
                        form
                    );

                if (
                    submitter &&
                    submitter.name
                ) {

                    formData.set(
                        submitter.name,
                        submitter.value || ''
                    );

                }

                // The real page endpoint is used explicitly. This avoids
                // posting to a fragment URL that the main site's AJAX layer
                // may render as a blank response.
                const actionUrl =
                    new URL(
                        '/index.php?page=skins',
                        window.location.origin
                    );

                try {

                    const response =
                        await fetch(
                            actionUrl.toString(),
                            {
                                method: 'POST',
                                body: formData,
                                credentials: 'same-origin',
                                redirect: 'follow',
                                cache: 'no-store'
                            }
                        );

                    if (!response.ok) {
                        throw new Error(
                            `HTTP ${response.status}`
                        );
                    }

                    // redirectToSelf() returns a 303 after saving to SQL.
                    // fetch follows it and exposes the final canonical URL.
                    const finalUrl =
                        response.url ||
                        actionUrl.toString();

                    window.location.replace(
                        finalUrl
                    );

                } catch (error) {

                    console.error(
                        'Weapon Paints save failed:',
                        error
                    );

                    form.dataset.wpSubmitInProgress =
                        '0';

                    form.classList.remove(
                        'wp-form-submitting'
                    );

                    showWpToast(
                        'ذخیره تغییرات انجام نشد. لطفاً دوباره تلاش کنید.',
                        'error'
                    );

                }

            },
            true
        );

        if (dom.globalSearch) {

            dom.globalSearch.addEventListener(
                'input',
                queueGlobalSearch
            );

        }

        if (dom.mainTabs) {

            dom.mainTabs.addEventListener(
                'shown.bs.tab',
                event => {

                    const target =
                        event.target;

                    const targetSelector =
                        target.getAttribute(
                            'data-bs-target'
                        );

                    if (!targetSelector) {
                        return;
                    }

                    const tabId =
                        targetSelector.replace(
                            '#',
                            ''
                        );

                    updateTabUrl(
                        tabId
                    );

                }
            );

        }

    }


    /* =========================================================
       16. Image Fallback
    ========================================================== */

    function tryNextImageCandidate(
        image
    ) {

        if (
            !(image instanceof HTMLImageElement)
        ) {

            return false;

        }


        const rawCandidates =
            image.getAttribute(
                'data-wp-image-candidates'
            );


        if (
            !rawCandidates
        ) {

            return false;

        }


        let candidates = [];


        try {

            candidates =
                JSON.parse(
                    rawCandidates
                );

        } catch (error) {

            return false;

        }


        if (
            !Array.isArray(
                candidates
            )
        ) {

            return false;

        }


        const currentAttempt =
            Number(
                image.dataset.wpImageAttempt ||
                0
            );


        const nextAttempt =
            currentAttempt +
            1;


        if (
            nextAttempt >=
            candidates.length
        ) {

            return false;

        }


        const nextUrl =
            candidates[
                nextAttempt
            ];


        if (
            !nextUrl
        ) {

            return false;

        }


        image.dataset.wpImageAttempt =
            String(
                nextAttempt
            );


        image.classList.remove(
            'is-hidden',
            'is-broken'
        );


        image.src =
            nextUrl;


        return true;

    }


    function finalizeImageFallback(
        image
    ) {

        image.classList.add(
            'is-hidden',
            'is-broken'
        );


        const placeholder =
            image.nextElementSibling;


        if (
            placeholder &&
            placeholder.classList.contains(
                'wp-image-placeholder'
            )
        ) {

            placeholder.classList.remove(
                'is-hidden'
            );

        }

    }


    function bindImageFallback() {

        document.addEventListener(
            'error',
            event => {

                const image =
                    event.target;


                if (
                    !(image instanceof HTMLImageElement)
                ) {

                    return;

                }


                if (
                    !image.hasAttribute(
                        'data-wp-image'
                    )
                ) {

                    return;

                }


                /*
                 * Try the next candidate URL before
                 * falling back to the "No Image" state.
                 */
                if (
                    tryNextImageCandidate(
                        image
                    )
                ) {

                    return;

                }


                finalizeImageFallback(
                    image
                );

            },
            true
        );


        document.addEventListener(
            'load',
            event => {

                const image =
                    event.target;


                if (
                    !(image instanceof HTMLImageElement)
                ) {

                    return;

                }


                if (
                    !image.hasAttribute(
                        'data-wp-image'
                    )
                ) {

                    return;

                }


                image.classList.remove(
                    'is-hidden',
                    'is-broken'
                );


                const placeholder =
                    image.nextElementSibling;


                if (
                    placeholder &&
                    placeholder.classList.contains(
                        'wp-image-placeholder'
                    )
                ) {

                    placeholder.classList.add(
                        'is-hidden'
                    );

                }

            },
            true
        );

    }


    /* =========================================================
       17. Weapon Paints Layout Mode
    ========================================================== */

    function applyWeaponPaintsLayoutMode() {

        /*
         * Weapon Paints is rendered inside shared site wrappers.
         * Scoped classes let weaponpaints.css remove outer padding
         * without changing other pages.
         */

        const pageRoot =
            document.querySelector(
                '.wp-app'
            );


        if (
            !pageRoot
        ) {

            return;

        }


        document.body.classList.add(
            'wp-fullbleed-active'
        );


        pageRoot.classList.add(
            'wp-fullbleed-root'
        );


        const fader =
            dom.mainPageFader;


        if (
            fader
        ) {

            fader.classList.add(
                'wp-fullbleed-target'
            );

        }


        const contentFader =
            pageRoot.closest(
                '.content-fader'
            );


        if (
            contentFader
        ) {

            contentFader.classList.add(
                'wp-fullbleed-target'
            );

        }


        const mainContent =
            pageRoot.closest(
                '.main-content'
            );


        if (
            mainContent
        ) {

            mainContent.classList.add(
                'wp-fullbleed-target'
            );

        }


        const appContainer =
            pageRoot.closest(
                '.app-container'
            );


        if (
            appContainer
        ) {

            appContainer.classList.add(
                'wp-fullbleed-target'
            );

        }

    }


    /* =========================================================
       18. Keyboard Shortcuts
    ========================================================== */

    function bindKeyboardShortcuts() {

        document.addEventListener(
            'keydown',
            event => {

                const activeElement =
                    document.activeElement;


                const isTyping =
                    activeElement &&
                    (
                        activeElement.tagName ===
                            'INPUT' ||
                        activeElement.tagName ===
                            'TEXTAREA' ||
                        activeElement.tagName ===
                            'SELECT'
                    );


                if (
                    (
                        event.ctrlKey ||
                        event.metaKey
                    ) &&
                    event.key.toLowerCase() ===
                        'k'
                ) {

                    event.preventDefault();


                    dom.globalSearch?.focus();


                    return;

                }


                if (
                    event.key ===
                        '/' &&
                    !isTyping
                ) {

                    event.preventDefault();


                    dom.globalSearch?.focus();

                }

            }
        );

    }


    /* =========================================================
       18. Initialization
    ========================================================== */

    function init() {

        moveWeaponPaintsModalsToBody();

        // Remove any Bootstrap modal state left by the host site's AJAX layer.
        document.body.classList.remove('modal-open');

        bindModalEvents();

        bindDelegatedEvents();

        bindMainTabs();

        bindImageFallback();

        applyWeaponPaintsLayoutMode();

        bindKeyboardShortcuts();


        /*
         * Make sure the server-rendered
         * active state is reflected immediately.
         */

        setPageReady();


        /*
         * Apply initial global search,
         * in case the browser restored
         * the input value automatically.
         */

        applyGlobalSearch();

    }


    /* =========================================================
       19. Lifecycle Events
    ========================================================== */

    window.addEventListener(
        'pageshow',
        () => {

            setPageReady();

            applyWeaponPaintsLayoutMode();

            applyGlobalSearch();

        }
    );


    window.addEventListener(
        'pagehide',
        () => {

            state.navigationLocked =
                false;

        }
    );


    if (
        document.readyState ===
        'loading'
    ) {

        document.addEventListener(
            'DOMContentLoaded',
            init,
            {
                once:
                    true
            }
        );

    } else {

        init();

    }

})();
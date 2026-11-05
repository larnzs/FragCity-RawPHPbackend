(function () {

    'use strict';


    function initBanlistPage() {

        const root =
            document.querySelector(
                '.banlist-page'
            );


        if (!root) {

            return;

        }


        /*
         * اگر همین instance قبلاً initialize شده،
         * دوباره event listener اضافه نکن.
         */

        if (
            root.dataset.banlistInitialized === '1'
        ) {

            return;

        }


        root.dataset.banlistInitialized =
            '1';


        const tableWrapper =
            root.querySelector(
                '.banlist-table-wrapper'
            );


        const tabs =
            root.querySelectorAll(
                '.ban-tab'
            );


        if (
            !tableWrapper ||
            !tabs.length
        ) {

            return;

        }


        let isLoading = false;


        /*
         * =====================================================
         * دریافت type از URL
         * =====================================================
         */

        function syncSearchControlsFromUrl(url) {
            const parsedUrl = new URL(url, window.location.href);
            const perPageInput = root.querySelector('.banlist-search-form input[name="per_page"]');
            const pageNumInput = root.querySelector('.banlist-search-form input[name="page_num"]');

            if (perPageInput) {
                perPageInput.value = parsedUrl.searchParams.get('per_page') || '15';
            }

            if (pageNumInput) {
                pageNumInput.value = parsedUrl.searchParams.get('page_num') || '1';
            }
        }


        function getTypeFromUrl(
            url
        ) {

            const parsedUrl =
                new URL(
                    url,
                    window.location.href
                );


            return (
                parsedUrl.searchParams.get(
                    'type'
                ) ||
                'bans'
            );

        }


        /*
         * =====================================================
         * فعال کردن تب صحیح
         * =====================================================
         */

        function setActiveTab(
            type
        ) {

            tabs.forEach(
                function (
                    tab
                ) {

                    const tabUrl =
                        new URL(
                            tab.href,
                            window.location.href
                        );


                    const tabType =
                        tabUrl.searchParams.get(
                            'type'
                        ) ||
                        'bans';


                    const isActive = tabType === type;

                    tab.classList.toggle('active', isActive);
                    tab.setAttribute('aria-current', isActive ? 'page' : 'false');

                }
            );

        }


        /*
         * =====================================================
         * جایگزینی فقط Table Content
         * =====================================================
         */

        async function loadBanlistTab(
            url,
            pushState
        ) {

            if (
                isLoading
            ) {

                return;

            }


            isLoading =
                true;


            const requestedType =
                getTypeFromUrl(
                    url
                );


            root.classList.add(
                'is-loading'
            );


            try {

                const response =
                    await fetch(
                        url,
                        {
                            method:
                                'GET',

                            credentials:
                                'same-origin',

                            headers: {
                                'X-Requested-With':
                                    'XMLHttpRequest'
                            }
                        }
                    );


                if (
                    !response.ok
                ) {

                    throw new Error(
                        'HTTP ' +
                        response.status
                    );

                }


                const html =
                    await response.text();


                const parser =
                    new DOMParser();


                const doc =
                    parser.parseFromString(
                        html,
                        'text/html'
                    );


                const incomingWrapper = doc.querySelector('.banlist-table-wrapper');
                const incomingStats = doc.querySelector('.banlist-stats-grid');
                const incomingHeroMeta = doc.querySelector('.banlist-hero-meta');

                if (!incomingWrapper) {
                    throw new Error('Banlist table wrapper not found.');
                }

                /*
                 * Keep the summary in sync with the selected record type.
                 * The authorization decision remains server-rendered.
                 */
                const currentStats = root.querySelector('.banlist-stats-grid');
                const currentHeroMeta = root.querySelector('.banlist-hero-meta');

                if (incomingStats && currentStats) {
                    currentStats.innerHTML = incomingStats.innerHTML;
                }

                if (incomingHeroMeta && currentHeroMeta) {
                    currentHeroMeta.innerHTML = incomingHeroMeta.innerHTML;
                }

                // Keep the search form's hidden pagination fields aligned with
                // the URL after an AJAX page-size or page-number change.
                syncSearchControlsFromUrl(url);

                /*
                 * فقط محتوای Table Wrapper عوض می‌شود.
                 *
                 * Header ثابت می‌ماند.
                 * Tabs ثابت می‌مانند.
                 * Container ثابت می‌ماند.
                 */

                tableWrapper.innerHTML =
                    incomingWrapper.innerHTML;


                /*
                 * تب فعال را تغییر بده.
                 */

                setActiveTab(
                    requestedType
                );


                /*
                 * تغییر URL بدون Reload
                 */

                if (
                    pushState
                ) {

                    window.history.pushState(
                        {
                            banlistType:
                                requestedType
                        },
                        '',
                        url
                    );

                }


                /*
                 * بعد از جایگزینی محتوا،
                 * اسکرول داخل صفحه به ابتدای جدول برگردد.
                 */

                // Deliberately preserve the current scroll position after a tab swap.


            } catch (
                error
            ) {

                console.error(
                    'Banlist tab error:',
                    error
                );


                /*
                 * اگر Fetch شکست خورد،
                 * navigation عادی انجام شود.
                 */

                window.location.href =
                    url;

                return;

            } finally {

                window.setTimeout(
                    function () {

                        root.classList.remove(
                            'is-loading'
                        );


                        isLoading =
                            false;

                    },
                    180
                );

            }

        }


        /*
         * =====================================================
         * Click روی تب‌ها
         * =====================================================
         */

        tabs.forEach(
            function (
                tab
            ) {

                tab.addEventListener(
                    'click',
                    function (
                        event
                    ) {

                        /*
                         * Ctrl / CMD / Shift / Alt
                         * اجازه navigation عادی بده.
                         */

                        if (
                            event.ctrlKey ||
                            event.metaKey ||
                            event.shiftKey ||
                            event.altKey ||
                            event.button !== 0
                        ) {

                            return;

                        }


                        event.preventDefault();


                        const url =
                            tab.href;


                        const type =
                            getTypeFromUrl(
                                url
                            );


                        /*
                         * فوراً تب فعال را تغییر بده
                         * تا UI سریع واکنش نشان دهد.
                         */

                        setActiveTab(
                            type
                        );


                        /*
                         * فقط جدول را لود کن.
                         */

                        loadBanlistTab(
                            url,
                            true
                        );

                    }
                );

            }
        );


        /*
         * =====================================================
         * AJAX pagination and page-size controls
         * =====================================================
         * These links stay on the canonical /banlist/ route and replace only
         * the table area instead of triggering a complete document reload.
         */
        root.addEventListener('click', function (event) {
            const target = event.target instanceof Element ? event.target : null;
            const link = target
                ? target.closest('.banlist-pagination .ban-page-btn, .banlist-page-size .ban-size-btn')
                : null;

            if (!link || !root.contains(link)) {
                return;
            }

            if (
                event.ctrlKey
                || event.metaKey
                || event.shiftKey
                || event.altKey
                || event.button !== 0
            ) {
                return;
            }

            if (link.classList.contains('disabled')) {
                event.preventDefault();
                return;
            }

            const destination = new URL(link.href, window.location.href);
            if (destination.origin !== window.location.origin) {
                return;
            }

            event.preventDefault();
            loadBanlistTab(destination.href, true);
        });


        /*
         * =====================================================
         * Browser Back / Forward
         * =====================================================
         */

        window.addEventListener(
            'popstate',
            function () {

                /*
                 * اگر دیگر روی Banlist نیستیم،
                 * این listener کاری انجام ندهد.
                 */

                const currentRoot =
                    document.querySelector(
                        '.banlist-page'
                    );


                if (
                    currentRoot !== root
                ) {

                    return;

                }


                const currentUrl =
                    window.location.href;


                const currentType =
                    getTypeFromUrl(
                        currentUrl
                    );


                setActiveTab(
                    currentType
                );


                loadBanlistTab(
                    currentUrl,
                    false
                );

            }
        );

    }


    /*
     * =====================================================
     * Expose Global Init
     * =====================================================
     */

    window.initBanlistPage =
        initBanlistPage;


    /*
     * =====================================================
     * Initial Page Load
     * =====================================================
     */

    if (
        document.readyState ===
        'loading'
    ) {

        document.addEventListener(
            'DOMContentLoaded',
            initBanlistPage,
            {
                once: true
            }
        );

    } else {

        initBanlistPage();

    }


    /*
     * =====================================================
     * AJAX Navigation
     *
     * وقتی از یک صفحه دیگر به Banlist می‌آییم،
     * سیستم کلی سایت event مربوط به content-loaded
     * را اجرا می‌کند.
     * =====================================================
     */

    window.addEventListener(
        'content-loaded',
        function () {

            initBanlistPage();

        }
    );

})();
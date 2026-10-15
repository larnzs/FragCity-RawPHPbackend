(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        const page = document.querySelector('.rules-page');
        if (!page) return;
        page.classList.add('page-ready');
    }, { once: true });
})();

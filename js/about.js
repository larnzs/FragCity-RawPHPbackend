(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        const page = document.querySelector('.about-page');
        if (!page) return;
        page.classList.add('page-ready');
    }, { once: true });
})();

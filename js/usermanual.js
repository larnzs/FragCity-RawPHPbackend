(function () {
    'use strict';

    function initManual() {
        const page = document.querySelector('.manual-page');
        if (!page) return;

        const cards = page.querySelectorAll('.manual-card, .warning-card');

        cards.forEach(function (card) {
            const header = card.querySelector('.manual-card-header, .warning-card-header');
            const body = card.querySelector('.manual-card-body, .warning-card-body');
            if (!header || !body) return;

            header.setAttribute('role', 'button');
            header.setAttribute('tabindex', '0');
            header.setAttribute('aria-expanded', 'false');

            function toggle() {
                const open = card.classList.toggle('active');
                header.setAttribute('aria-expanded', open ? 'true' : 'false');
                body.style.maxHeight = open ? body.scrollHeight + 'px' : '0px';
            }

            header.addEventListener('click', toggle);
            header.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    toggle();
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initManual, { once: true });
    } else {
        initManual();
    }
})();

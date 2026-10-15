(function () {
    'use strict';

    // Static, full-width footer - no hover/peek behavior needed anymore.
    // Footer links use [data-ajax-nav], so SPA navigation is already
    // handled by the site-wide click listener (header.js / script.js) -
    // this file just marks the footer as ready if you need a CSS/JS hook.
    const footer = document.getElementById('siteFooter');
    if (!footer) return;

    footer.classList.add('is-static-footer');
})();

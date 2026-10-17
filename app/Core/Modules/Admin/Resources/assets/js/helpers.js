function togglePassword(event) {
    var button = event.currentTarget;
    var input = button
        .closest('.input-wrapper')
        .querySelector('input[type="password"], input[type="text"]');
    var iconEye = button.querySelector('.icon-eye');
    var iconEyeSlash = button.querySelector('.icon-eye-slash');

    if (input.type === 'password') {
        input.type = 'text';
        iconEye.style.display = 'none';
        iconEyeSlash.style.display = 'inline';
    } else {
        input.type = 'password';
        iconEye.style.display = 'inline';
        iconEyeSlash.style.display = 'none';
    }
}

function isMobileDevice() {
    return window.innerWidth <= 768;
}

// FilePond removed from admin UI. Stubs kept for backward-compat calls from modules.
function destroyFilePondsIn() {}
function initializeFilePondElement() {}
function _registerFilePondPlugins() {}

window._registerFilePondPlugins = _registerFilePondPlugins;
window.initializeFilePondElement = initializeFilePondElement;

// ── Abort stale boost navigations to #main ──────────────────────────────────
// When the user clicks a new sidebar link before the previous page finishes
// loading, cancel the in-flight request so only the latest one renders.
(function () {
    let pendingMainElt = null;

    if (typeof htmx === 'undefined') return;

    htmx.on('htmx:beforeRequest', function (event) {
        const elt = event.detail.elt;
        if (!elt) return;

        const target = elt.getAttribute('hx-target') ||
            (elt.closest('[hx-target]') ? elt.closest('[hx-target]').getAttribute('hx-target') : null);
        if (target !== '#main') return;

        // Abort the previous in-flight request to #main
        if (pendingMainElt && pendingMainElt !== elt) {
            htmx.trigger(pendingMainElt, 'htmx:abort');
        }
        pendingMainElt = elt;

        const xhr = event.detail.xhr;
        if (xhr) {
            const origChange = xhr.onreadystatechange;
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4) pendingMainElt = null;
                if (origChange) origChange.apply(this, arguments);
            };
        }
    });
})();


// ── Admin profile dropdown ───────────────────────────────────────────────
(function () {
    function initProfileDropdown() {
        const toggle = document.querySelector('[data-admin-profile-toggle]');
        const dropdown = document.querySelector('[data-admin-profile-dropdown]');
        if (!toggle || !dropdown) return;

        function open() {
            dropdown.classList.add('is-open');
            dropdown.setAttribute('aria-hidden', 'false');
            toggle.setAttribute('aria-expanded', 'true');
        }

        function close() {
            dropdown.classList.remove('is-open');
            dropdown.setAttribute('aria-hidden', 'true');
            toggle.setAttribute('aria-expanded', 'false');
        }

        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            dropdown.classList.contains('is-open') ? close() : open();
        });

        document.addEventListener('click', function (e) {
            if (!dropdown.contains(e.target) && !toggle.contains(e.target)) close();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') close();
        });

        // Close after navigation
        dropdown.addEventListener('click', function (e) {
            if (e.target.closest('a') || e.target.closest('button[type="submit"]')) {
                setTimeout(close, 100);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initProfileDropdown);
    } else {
        initProfileDropdown();
    }
})();

let _enforcingLanguages = false;
document.addEventListener('change', function (event) {
    const checkbox = event.target;
    if (!_enforcingLanguages && checkbox && checkbox.matches && checkbox.matches('input[type="checkbox"][name^="available["]')) {
        const boxes = Array.from(document.querySelectorAll('input[type="checkbox"][name^="available["]'));
        if (!boxes.length) return;

        const anyChecked = boxes.some((el) => el.checked);
        if (anyChecked) return;

        _enforcingLanguages = true;
        try {
            const en = document.querySelector('input[type="checkbox"][name="available[en]"]');
            const fallback = en || boxes[0];
            if (fallback) {
                fallback.checked = true;
            }
        } finally {
            setTimeout(() => {
                _enforcingLanguages = false;
            }, 0);
        }
    }
});

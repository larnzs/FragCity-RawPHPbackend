(function () {
    'use strict';

    function initServerGuide() {
        const root = document.querySelector('#fragcityServerGuide');
        if (!root || root.dataset.sgInitialized === '1') return;
        root.dataset.sgInitialized = '1';

        const search = root.querySelector('#sgSearch');
        const clearSearch = root.querySelector('#sgClearSearch');
        const categoryNav = root.querySelector('#sgCategoryNav');
        const categoryButtons = Array.from(root.querySelectorAll('[data-guide-filter]'));
        const entries = Array.from(root.querySelectorAll('[data-guide-entry]'));
        const resultsTitle = root.querySelector('#sgResultsTitle');
        const resultsCount = root.querySelector('#sgResultsCount');
        const emptyState = root.querySelector('#sgEmptyState');
        const resetButton = root.querySelector('#sgResetFilters');
        const toast = root.querySelector('#sgToast');

        if (!search || !categoryNav || !entries.length) return;

        let activeCategory = 'all';
        let toastTimeout = 0;

        function normalize(value) {
            return String(value || '')
                .toLocaleLowerCase('fa-IR')
                .replace(/[ي]/g, 'ی')
                .replace(/[ك]/g, 'ک')
                .trim();
        }

        function updateResults() {
            const query = normalize(search.value);
            let visible = 0;

            entries.forEach(function (entry) {
                const categoryMatches = activeCategory === 'all'
                    || entry.dataset.guideCategory === activeCategory;
                const searchMatches = query === ''
                    || normalize(entry.dataset.guideSearch).includes(query);
                const show = categoryMatches && searchMatches;

                entry.hidden = !show;
                if (show) visible += 1;
            });

            if (resultsCount) {
                resultsCount.textContent = visible.toLocaleString('fa-IR') + ' مورد';
            }
            if (emptyState) emptyState.hidden = visible !== 0;

            if (clearSearch) {
                clearSearch.hidden = search.value.trim() === '';
            }

            if (categoryButtons.length && resultsTitle) {
                const activeButton = categoryButtons.find(function (button) {
                    return button.dataset.guideFilter === activeCategory;
                });
                resultsTitle.textContent = activeButton
                    ? (activeButton.dataset.guideLabel || activeButton.textContent.trim())
                    : 'همه دستورات';
            }
        }

        function setCategory(category) {
            activeCategory = category || 'all';

            categoryButtons.forEach(function (button) {
                const active = button.dataset.guideFilter === activeCategory;
                button.classList.toggle('active', active);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
            });

            updateResults();
        }

        function showToast(message) {
            if (!toast) return;

            toast.textContent = message;
            toast.classList.add('visible');
            window.clearTimeout(toastTimeout);
            toastTimeout = window.setTimeout(function () {
                toast.classList.remove('visible');
            }, 1900);
        }

        function fallbackCopy(value) {
            const helper = document.createElement('textarea');
            helper.value = value;
            helper.setAttribute('readonly', '');
            helper.style.position = 'fixed';
            helper.style.opacity = '0';
            helper.style.pointerEvents = 'none';
            document.body.appendChild(helper);
            helper.select();

            let copied = false;
            try {
                copied = document.execCommand('copy');
            } catch (error) {
                copied = false;
            }

            helper.remove();
            return copied;
        }

        async function copyText(button) {
            const value = button.dataset.guideCopy || '';
            if (!value) return;

            let copied = false;
            try {
                if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                    await navigator.clipboard.writeText(value);
                    copied = true;
                } else {
                    copied = fallbackCopy(value);
                }
            } catch (error) {
                copied = fallbackCopy(value);
            }

            if (!copied) {
                showToast('کپی انجام نشد؛ دستور را دستی انتخاب کن.');
                return;
            }

            const icon = button.querySelector('i');
            const label = button.querySelector('span');
            const originalTitle = button.getAttribute('title') || 'کپی دستور';
            const originalAriaLabel = button.getAttribute('aria-label');

            button.classList.add('is-copied');
            button.setAttribute('title', 'کپی شد');
            button.setAttribute('aria-label', 'کپی شد');

            if (icon) {
                icon.classList.remove('fa-copy');
                icon.classList.add('fa-check');
            }
            if (label) label.textContent = 'کپی شد';

            showToast('دستور کپی شد');

            window.setTimeout(function () {
                button.classList.remove('is-copied');
                button.setAttribute('title', originalTitle);
                if (originalAriaLabel) {
                    button.setAttribute('aria-label', originalAriaLabel);
                } else {
                    button.removeAttribute('aria-label');
                }

                if (icon) {
                    icon.classList.remove('fa-check');
                    icon.classList.add('fa-copy');
                }
                if (label) label.textContent = 'کپی';
            }, 1500);
        }

        categoryButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                setCategory(button.dataset.guideFilter);
            });
        });

        search.addEventListener('input', updateResults);

        if (clearSearch) {
            clearSearch.addEventListener('click', function () {
                search.value = '';
                updateResults();
                search.focus();
            });
        }

        if (resetButton) {
            resetButton.addEventListener('click', function () {
                search.value = '';
                setCategory('all');
                search.focus();
            });
        }

        root.addEventListener('click', function (event) {
            const copyButton = event.target.closest('[data-guide-copy]');
            if (copyButton && root.contains(copyButton)) {
                copyText(copyButton);
            }
        });

        root.addEventListener('keydown', function (event) {
            const target = event.target;
            const typing = target && (
                /INPUT|TEXTAREA|SELECT/.test(target.tagName)
                || target.isContentEditable
            );

            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                search.focus();
                search.select();
                return;
            }

            if (event.key === '/' && !typing && !event.ctrlKey && !event.metaKey && !event.altKey) {
                event.preventDefault();
                search.focus();
            }
        });

        setCategory('all');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initServerGuide, { once: true });
    } else {
        initServerGuide();
    }
})();

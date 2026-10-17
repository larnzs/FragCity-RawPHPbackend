window.FluteRichText = window.FluteRichText || {};

(function () {
    var Icons = {};
    var tpl = document.getElementById('richtext-editor-icons');

    if (tpl) {
        tpl.content.querySelectorAll('[data-icon]').forEach(function (el) {
            Icons[el.dataset.icon] = el.innerHTML;
        });
    }

    window.FluteRichText.Icons = Icons;

    window.FluteRichText.icon = function (name) {
        return Icons[name] || '';
    };
})();

// ─── Pickr-based color picker helper ───
(function () {
    var FRT = window.FluteRichText;
    var pending = [];
    var loading = false;

    function poll(cb) {
        var iv = setInterval(function () {
            if (window.Pickr) { clearInterval(iv); cb(); }
        }, 50);
    }

    FRT.ensurePickr = function (cb) {
        if (window.Pickr) return cb();
        pending.push(cb);
        if (loading) return;
        loading = true;
        var loader = window.AdminAssetLoader || window.ThemeAssetLoader;
        var done = function () {
            loading = false;
            var cbs = pending;
            pending = [];
            cbs.forEach(function (c) { c(); });
        };
        if (loader && typeof loader.ensure === 'function') {
            loader.ensure('pickr', function () {
                if (window.Pickr) done();
                else poll(done);
            });
        } else {
            poll(done);
        }
    };

    FRT.bindColorPicker = function (picker, editor) {
        if (!picker || picker._rtBound) return;
        picker._rtBound = true;

        var mode = picker.dataset.colorMode === 'highlight' ? 'highlight' : 'color';
        var toggle = picker.querySelector('.toolbar-color-toggle, .bubble-color-toggle');
        var menu = picker.querySelector('.toolbar-color-menu, .bubble-color-menu');
        var host = menu && menu.querySelector('[data-pickr-host]');
        var reset = menu && menu.querySelector('[data-color-reset]');
        if (!toggle || !menu || !host) return;

        var pickrInstance = null;

        var applyColor = function (hex) {
            if (mode === 'highlight') {
                editor.chain().focus().setHighlight({ color: hex }).run();
            } else {
                editor.chain().focus().setColor(hex).run();
            }
        };
        var resetColor = function () {
            if (mode === 'highlight') {
                editor.chain().focus().unsetHighlight().run();
            } else {
                editor.chain().focus().unsetColor().run();
            }
        };

        var initIfNeeded = function () {
            if (pickrInstance) return;
            FRT.ensurePickr(function () {
                var attrs = mode === 'highlight'
                    ? editor.getAttributes('highlight')
                    : editor.getAttributes('textStyle');
                var current = (attrs && attrs.color) || (mode === 'highlight' ? '#fef08a' : '#000000');
                pickrInstance = FRT.createPickr(host, current, applyColor);
            });
        };

        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            // Close siblings
            var scope = picker.closest('.tiptap-toolbar, .tiptap-bubble-menu') || document;
            scope.querySelectorAll('.toolbar-color-picker.is-open, .bubble-color-dropdown.is-open').forEach(function (p) {
                if (p !== picker) p.classList.remove('is-open');
            });
            scope.querySelectorAll('.bubble-heading-menu.is-open').forEach(function (m) { m.classList.remove('is-open'); });
            picker.classList.toggle('is-open');
            if (picker.classList.contains('is-open')) {
                initIfNeeded();
                // Sync Pickr with current editor color
                if (pickrInstance) {
                    var attrs = mode === 'highlight'
                        ? editor.getAttributes('highlight')
                        : editor.getAttributes('textStyle');
                    var cur = attrs && attrs.color;
                    if (cur) {
                        try { pickrInstance.setColor(cur, true); } catch (_) {}
                    }
                }
            }
        });

        if (reset) {
            reset.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                resetColor();
                picker.classList.remove('is-open');
            });
        }

        // Prevent menu clicks from bubbling to outside-click handlers
        menu.addEventListener('mousedown', function (e) { e.stopPropagation(); });
    };

    FRT.createPickr = function (container, initialColor, onChange) {
        if (!window.Pickr || !container) return null;

        container.querySelectorAll('.pcr-app, .pickr-inline-trigger').forEach(function (el) { el.remove(); });
        container.classList.add('rt-pickr-container');

        var trigger = document.createElement('div');
        trigger.className = 'pickr-inline-trigger';
        container.appendChild(trigger);

        try {
            var pickr = window.Pickr.create({
                el: trigger,
                theme: 'nano',
                container: container,
                default: initialColor || '#000000',
                inline: true,
                showAlways: true,
                useAsButton: false,
                comparison: false,
                lockOpacity: true,
                swatches: [
                    '#000000', '#434343', '#666666', '#999999', '#cccccc', '#ffffff',
                    '#980000', '#ff0000', '#ff9900', '#ffff00', '#00ff00', '#00ffff',
                    '#4a86e8', '#0000ff', '#9900ff', '#ff00ff',
                    '#e6b8af', '#f4cccc', '#fce5cd', '#fff2cc', '#d9ead3', '#cfe2f3',
                ],
                components: {
                    preview: false,
                    opacity: false,
                    hue: true,
                    interaction: {
                        input: false,
                        cancel: false,
                        clear: false,
                        save: false,
                    },
                },
            });

            var debounceTimer = null;
            pickr.on('change', function (color) {
                if (!color || typeof onChange !== 'function') return;
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function () {
                    onChange(color.toHEXA().toString());
                }, 30);
            });

            return pickr;
        } catch (e) {
            console.error('Pickr init failed:', e);
            return null;
        }
    };
})();

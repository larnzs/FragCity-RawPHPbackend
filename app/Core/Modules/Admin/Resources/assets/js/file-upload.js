'use strict';

class UploadInstance {
    constructor(input, manager) {
        this.input   = input;
        this.manager = manager;
        this.files   = [];
        this._ready     = false;
        this._hadFile   = false;
        this._cropBusy  = false;
        this._destroyed = false;
        this._errorTimer = null;

        this._readConfig();
        this._build();
        this._bindEvents();
        this._loadDefault();
    }

    _readConfig() {
        const el = this.input;
        this.fieldName   = el.name || '';
        this.multiple    = el.hasAttribute('multiple');
        this.defaultFile = el.dataset.defaultFile || null;

        this.defaultFiles = [];
        if (el.dataset.defaultFiles) {
            try {
                const parsed = JSON.parse(el.dataset.defaultFiles);
                if (Array.isArray(parsed)) this.defaultFiles = parsed.filter(Boolean);
            } catch (_) {}
        }

        const acceptRaw = (el.getAttribute('accept') || el.dataset.accept || '');
        this.accept = acceptRaw.split(',').map(s => s.trim()).filter(Boolean);

        this.maxSize  = this._parseSize(el.dataset.maxSize);
        this.minSize  = this._parseSize(el.dataset.minSize);
        this.maxFiles = el.dataset.maxFiles ? parseInt(el.dataset.maxFiles, 10) : (this.multiple ? Infinity : 1);

        this.cropCfg = null;
        if (el.dataset.cropAspect !== undefined) {
            this.cropCfg = {
                aspectRatio: el.dataset.cropAspect ? parseFloat(el.dataset.cropAspect) : NaN,
                round:  el.dataset.cropRound  === 'true',
                width:  el.dataset.cropWidth  ? parseInt(el.dataset.cropWidth,  10) : undefined,
                height: el.dataset.cropHeight ? parseInt(el.dataset.cropHeight, 10) : undefined,
            };
        }
    }

    _parseSize(raw) {
        if (!raw) return null;
        const m = String(raw).trim().match(/^(\d+(?:\.\d+)?)\s*(b|kb|mb|gb)?$/i);
        if (!m) return null;
        const n = parseFloat(m[1]);
        const unit = (m[2] || 'b').toLowerCase();
        const mult = { b: 1, kb: 1024, mb: 1048576, gb: 1073741824 }[unit];
        return n * mult;
    }

    _t(key, fallback) {
        return typeof translate === 'function' ? translate(key) : fallback;
    }

    _humanAccept() {
        if (!this.accept.length) return '';
        return this.accept.map(a => {
            if (a.startsWith('.')) return a.slice(1).toUpperCase();
            if (a.endsWith('/*')) return a.slice(0, -2).toUpperCase();
            return a.split('/').pop().toUpperCase();
        }).join(', ');
    }

    _build() {
        const input = this.input;

        this.wrapper = document.createElement('div');
        this.wrapper.className = 'fu-wrapper';
        if (this.multiple) this.wrapper.classList.add('fu-wrapper--multiple');

        this.dropzone = document.createElement('div');
        this.dropzone.className = 'fu-dropzone';
        this.dropzone.setAttribute('role', 'button');
        this.dropzone.setAttribute('tabindex', '0');
        this.dropzone.setAttribute('aria-label', this._t('def.upload_file', 'Upload file'));

        const hint = [];
        if (this.accept.length) hint.push(this._humanAccept());
        if (this.maxSize) hint.push(this._t('def.up_to', 'up to') + ' ' + this._formatSize(this.maxSize));
        if (this.multiple && Number.isFinite(this.maxFiles)) {
            hint.push(this.maxFiles + ' ' + this._t('def.files_max', 'files max'));
        }

        this.dropzone.innerHTML =
            '<div class="fu-dropzone__icon" aria-hidden="true">' +
                '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 256 256" fill="currentColor">' +
                    '<path d="M240,136v64a16,16,0,0,1-16,16H32a16,16,0,0,1-16-16V136a8,8,0,0,1,16,0v64H224V136a8,8,0,0,1,16,0ZM85.66,77.66,120,43.31V152a8,8,0,0,0,16,0V43.31l34.34,34.35a8,8,0,0,0,11.32-11.32l-48-48a8,8,0,0,0-11.32,0l-48,48A8,8,0,0,0,85.66,77.66Z"/>' +
                '</svg>' +
            '</div>' +
            '<div class="fu-dropzone__body">' +
                '<span class="fu-dropzone__label">' +
                    this._t('def.drag_and_drop', 'Drag & Drop your files or') +
                    ' <span class="fu-dropzone__action">' + this._t('def.browse', 'Browse') + '</span>' +
                '</span>' +
                (hint.length ? '<span class="fu-dropzone__hint">' + hint.join(' • ') + '</span>' : '') +
            '</div>';

        this.errorEl = document.createElement('div');
        this.errorEl.className = 'fu-error';
        this.errorEl.setAttribute('role', 'alert');
        this.errorEl.hidden = true;

        this.fileList = document.createElement('ul');
        this.fileList.className = 'fu-list';
        this.fileList.setAttribute('role', 'list');

        input.style.cssText = 'position:absolute;width:1px;height:1px;opacity:0;pointer-events:none;';

        const parent = input.parentElement;
        parent.insertBefore(this.wrapper, input);
        this.wrapper.appendChild(this.dropzone);
        this.wrapper.appendChild(this.errorEl);
        this.wrapper.appendChild(this.fileList);
        this.wrapper.appendChild(input);
    }

    _bindEvents() {
        const dz = this.dropzone;

        const triggerBrowse = () => { if (!this._destroyed) this.input.click(); };
        dz.addEventListener('click', triggerBrowse);
        dz.addEventListener('keydown', e => {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); triggerBrowse(); }
        });

        this.input.addEventListener('change', e => {
            const incoming = Array.from(e.target.files || []);
            this.input.value = '';
            this._handleIncoming(incoming);
        });

        const hasFiles = e => {
            const t = e.dataTransfer && e.dataTransfer.types;
            return t && (Array.from(t).includes('Files'));
        };

        dz.addEventListener('dragover', e => {
            if (!hasFiles(e)) return;
            e.preventDefault();
            dz.classList.add('fu-dropzone--over');
        });
        dz.addEventListener('dragleave', e => {
            if (!dz.contains(e.relatedTarget)) dz.classList.remove('fu-dropzone--over');
        });
        dz.addEventListener('drop', e => {
            if (!hasFiles(e)) return;
            e.preventDefault();
            dz.classList.remove('fu-dropzone--over');
            this._handleIncoming(Array.from(e.dataTransfer.files || []));
        });

        this._pasteHandler = e => {
            if (!this.wrapper.isConnected) return;
            if (document.activeElement && !this.wrapper.contains(document.activeElement)) return;
            const items = e.clipboardData && e.clipboardData.files;
            if (items && items.length) {
                this._handleIncoming(Array.from(items));
            }
        };
        document.addEventListener('paste', this._pasteHandler);
    }

    _handleIncoming(incoming) {
        if (!incoming.length) return;
        this._clearError();
        if (!this.multiple) this._clearFiles();

        this._batchIndex = 0;
        for (const f of incoming) {
            if (this.files.length >= this.maxFiles) {
                this._showError(this._t('def.max_files_exceeded', 'Maximum number of files exceeded'));
                break;
            }
            const err = this._validate(f);
            if (err) { this._showError(err); continue; }
            this._addFile(f);
        }
    }

    _validate(file) {
        if (this.accept.length && !this._validateType(file)) {
            return this._t('def.file_type_not_allowed', 'File type not allowed') + ': ' + (file.name || '');
        }
        if (this.maxSize && file.size > this.maxSize) {
            return this._t('def.file_too_large', 'File too large') + ' (' + this._formatSize(file.size) + ' > ' + this._formatSize(this.maxSize) + ')';
        }
        if (this.minSize && file.size < this.minSize) {
            return this._t('def.file_too_small', 'File too small') + ' (' + this._formatSize(file.size) + ' < ' + this._formatSize(this.minSize) + ')';
        }
        return null;
    }

    _validateType(file) {
        const name = (file.name || '').toLowerCase();
        return this.accept.some(a => {
            const v = a.toLowerCase();
            if (v.startsWith('.')) return name.endsWith(v);
            if (v.endsWith('/*')) return file.type.startsWith(v.slice(0, -1));
            return file.type === v;
        });
    }

    _showError(msg) {
        this.errorEl.textContent = msg;
        this.errorEl.hidden = false;
        this.wrapper.classList.add('fu-wrapper--error');
        clearTimeout(this._errorTimer);
        this._errorTimer = setTimeout(() => this._clearError(), 5000);
    }

    _clearError() {
        clearTimeout(this._errorTimer);
        this.errorEl.hidden = true;
        this.errorEl.textContent = '';
        this.wrapper.classList.remove('fu-wrapper--error');
    }

    _addFile(file) {
        const id    = Math.random().toString(36).slice(2);
        const entry = { id, file, url: null, name: file.name };

        if (this.cropCfg && file.type.startsWith('image/') && typeof window.ImageCropper !== 'undefined') {
            this._cropBusy = true;
            window.ImageCropper.open(file, this.cropCfg)
                .then(cropped => {
                    this._cropBusy = false;
                    entry.file = cropped;
                    this._commitFile(entry);
                })
                .catch(() => { this._cropBusy = false; });
            return;
        }

        this._commitFile(entry);
    }

    _commitFile(entry) {
        this.files.push(entry);
        this._renderFile(entry);
        this._syncInput();
        this._updateDropzoneVisibility();
    }

    _updateDropzoneVisibility() {
        const atLimit = this.files.length >= this.maxFiles;
        this.dropzone.classList.toggle('fu-dropzone--hidden', !this.multiple ? this.files.length > 0 : atLimit);
    }

    _renderFile(entry) {
        const li = document.createElement('li');
        li.className = 'fu-item';
        li.dataset.id = entry.id;
        if (this.multiple) {
            li.draggable = true;
            this._bindReorder(li);
        }

        const isImg = entry.file
            ? entry.file.type.startsWith('image/')
            : /\.(jpg|jpeg|png|gif|webp|svg|bmp|avif)(\?.*)?$/i.test(entry.url || '');

        const thumb = document.createElement('div');
        thumb.className = 'fu-item__thumb';

        if (isImg) {
            const img = document.createElement('img');
            img.alt = '';
            img.loading = 'lazy';
            if (entry.file) {
                const reader = new FileReader();
                reader.onload = e => { img.src = e.target.result; };
                reader.readAsDataURL(entry.file);
            } else {
                img.src = entry.url;
            }
            thumb.appendChild(img);
        } else {
            thumb.classList.add('fu-item__thumb--file');
            const ext = (entry.name || '').split('.').pop().slice(0, 4).toUpperCase();
            thumb.innerHTML =
                '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true">' +
                    '<path d="M213.66,82.34l-56-56A8,8,0,0,0,152,24H56A16,16,0,0,0,40,40V216a16,16,0,0,0,16,16H200a16,16,0,0,0,16-16V88A8,8,0,0,0,213.66,82.34ZM160,51.31,188.69,80H160ZM200,216H56V40h88V88a8,8,0,0,0,8,8h48V216Z"/>' +
                '</svg>' +
                (ext ? '<span class="fu-item__ext">' + ext + '</span>' : '');
        }

        const info = document.createElement('div');
        info.className = 'fu-item__info';

        const nameEl = document.createElement('span');
        nameEl.className = 'fu-item__name';
        nameEl.textContent = entry.name
            || (entry.url ? entry.url.split('/').pop().split('?')[0] : 'file');
        nameEl.title = nameEl.textContent;
        info.appendChild(nameEl);

        const meta = document.createElement('span');
        meta.className = 'fu-item__meta';
        const parts = [];
        if (entry.file && entry.file.size) parts.push(this._formatSize(entry.file.size));
        const typeLabel = entry.file ? (entry.file.type || '').split('/').pop() : '';
        if (typeLabel) parts.push(typeLabel.toUpperCase());
        meta.textContent = parts.join(' • ');
        if (parts.length) info.appendChild(meta);

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'fu-item__remove';
        removeBtn.setAttribute('aria-label', this._t('def.remove', 'Remove'));
        removeBtn.innerHTML =
            '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true">' +
                '<path d="M205.66,194.34a8,8,0,0,1-11.32,11.32L128,139.31,61.66,205.66a8,8,0,0,1-11.32-11.32L116.69,128,50.34,61.66A8,8,0,0,1,61.66,50.34L128,116.69l66.34-66.35a8,8,0,0,1,11.32,11.32L139.31,128Z"/>' +
            '</svg>';
        removeBtn.addEventListener('click', e => { e.stopPropagation(); this._removeFile(entry.id); });

        li.appendChild(thumb);
        li.appendChild(info);
        li.appendChild(removeBtn);
        this.fileList.appendChild(li);

        const idx = this._batchIndex || 0;
        this._batchIndex = idx + 1;
        if (idx > 0) li.style.transitionDelay = (idx * 50) + 'ms';

        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                li.classList.add('fu-item--in');
                setTimeout(() => { li.style.transitionDelay = ''; }, 400 + idx * 50);
            });
        });
    }

    _bindReorder(li) {
        li.addEventListener('dragstart', e => {
            li.classList.add('fu-item--dragging');
            e.dataTransfer.effectAllowed = 'move';
            try { e.dataTransfer.setData('text/plain', li.dataset.id); } catch (_) {}
        });
        li.addEventListener('dragend', () => {
            li.classList.remove('fu-item--dragging');
            this.fileList.querySelectorAll('.fu-item').forEach(el => el.classList.remove('fu-item--drop-target'));
        });
        li.addEventListener('dragover', e => {
            e.preventDefault();
            const dragging = this.fileList.querySelector('.fu-item--dragging');
            if (!dragging || dragging === li) return;
            const rect = li.getBoundingClientRect();
            const after = (e.clientY - rect.top) > rect.height / 2;
            li.parentNode.insertBefore(dragging, after ? li.nextSibling : li);
        });
        li.addEventListener('drop', e => {
            e.preventDefault();
            this._commitReorder();
        });
    }

    _commitReorder() {
        const order = Array.from(this.fileList.querySelectorAll('.fu-item')).map(el => el.dataset.id);
        this.files.sort((a, b) => order.indexOf(a.id) - order.indexOf(b.id));
        this._syncInput();
    }

    _removeFile(id) {
        this.files = this.files.filter(f => f.id !== id);
        const li = this.fileList.querySelector(`[data-id="${id}"]`);
        if (li) {
            li.classList.add('fu-item--out');
            setTimeout(() => li.remove(), 180);
        }
        this._syncInput();
        this._updateDropzoneVisibility();
    }

    _clearFiles() {
        this.files = [];
        this.fileList.innerHTML = '';
        this.dropzone.classList.remove('fu-dropzone--hidden');
        this._syncInput();
    }

    _syncInput() {
        try {
            const dt = new DataTransfer();
            this.files.filter(f => f.file).forEach(f => dt.items.add(f.file));
            this.input.files = dt.files;
        } catch (_) {}
        this._syncClearInput();
    }

    _syncClearInput() {
        if (!this.fieldName || this._destroyed) return;
        const inputWrapper = this.wrapper.closest('.input-wrapper');
        const searchRoot = (inputWrapper ? inputWrapper.parentElement : null)
            || this.wrapper.parentElement;
        if (!searchRoot) return;
        const clearInput = searchRoot.querySelector(
            `input[data-filepond-clear="${this.fieldName}"]`
        );
        if (!clearInput) return;

        if (this.files.length > 0) {
            this._hadFile = true;
            clearInput.value = '0';
        } else if (this._ready && this._hadFile && !this._cropBusy) {
            clearInput.value = '1';
        }
    }

    _loadDefault() {
        const urls = [];
        if (this.defaultFile) urls.push(this.defaultFile);
        if (this.defaultFiles.length) urls.push(...this.defaultFiles);
        if (!urls.length) {
            this._ready = true;
            return;
        }

        const loadOne = url => fetch(url, { credentials: 'same-origin' })
            .then(r => r.ok ? r.blob() : Promise.reject(r.status))
            .then(blob => {
                const name  = url.split('/').pop().split('?')[0] || 'file';
                const file  = new File([blob], name, { type: blob.type });
                const id    = Math.random().toString(36).slice(2);
                const entry = { id, file, url, name };
                this.files.push(entry);
                this._renderFile(entry);
            })
            .catch(() => {});

        Promise.all(urls.map(loadOne)).finally(() => {
            this._syncInput();
            this._updateDropzoneVisibility();
            this._hadFile = this.files.length > 0;
            this._ready = true;
        });
    }

    _formatSize(bytes) {
        if (bytes < 1024)    return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        if (bytes < 1073741824) return (bytes / 1048576).toFixed(1) + ' MB';
        return (bytes / 1073741824).toFixed(2) + ' GB';
    }

    destroy() {
        if (this._destroyed) return;
        this._destroyed = true;
        if (this._pasteHandler) document.removeEventListener('paste', this._pasteHandler);
        clearTimeout(this._errorTimer);
        if (this.wrapper && this.wrapper.parentElement) {
            const parent = this.wrapper.parentElement;
            this.input.style.cssText = '';
            parent.insertBefore(this.input, this.wrapper);
            this.wrapper.remove();
        }
        if (this.manager) this.manager.instances.delete(this.input);
    }
}

class FluteUpload {
    constructor() {
        this.instances = new Map();
    }

    init(root) {
        this._getElements(root).forEach(el => {
            if (!this.instances.has(el)) this.createInstance(el);
        });
    }

    _getElements(root) {
        const scope = (root instanceof Element) ? root : document;
        const els = Array.from(scope.querySelectorAll('input[data-upload]'));
        if (scope instanceof Element && scope.matches('input[data-upload]')) els.unshift(scope);
        return els;
    }

    createInstance(input) {
        const inst = new UploadInstance(input, this);
        this.instances.set(input, inst);
        return inst;
    }

    destroyIn(container) {
        if (!container) return;
        const toDestroy = [];
        this.instances.forEach((inst, input) => {
            if (container.contains(input)) toDestroy.push(inst);
        });
        toDestroy.forEach(inst => inst.destroy());
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.Upload = new FluteUpload();
    window.Upload.init();

    document.body.addEventListener('htmx:beforeSwap', e => {
        if (e.detail && e.detail.target) window.Upload.destroyIn(e.detail.target);
    });
    document.body.addEventListener('htmx:beforeCleanupElement', e => {
        window.Upload.destroyIn(e.target);
    });
});

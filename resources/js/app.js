/**
 * Rich text editor — global Alpine component.
 *
 * Features: undo/redo, bold/italic/underline/strikethrough, headings,
 * blockquote, code, text alignment, bullet/numbered lists, indent/outdent,
 * link insert/remove, horizontal rule, text color, highlight color,
 * remove formatting, word + char count.
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('helpGuideSearch', () => ({
        query: '',
        matches: [],
        activeIndex: 0,
        contentRoot: null,

        prepareContent() {
            this.contentRoot = this.$refs.content ?? null;
            if (!this.contentRoot) return;

            this.contentRoot.querySelectorAll('a[href^="#"]').forEach((link) => {
                link.addEventListener('click', () => {
                    this.matches.forEach((match) => {
                        match.removeAttribute('data-active');
                    });
                    this.activeIndex = 0;
                });
            });

            this.search();
        },

        matchLabel() {
            if (!this.query.trim()) return '';
            if (this.matches.length === 0) return '0 matches';

            return `${this.activeIndex + 1} / ${this.matches.length}`;
        },

        search() {
            if (!this.contentRoot) return;

            this.clearHighlights();

            const term = this.query.trim();
            if (term.length < 2) {
                this.matches = [];
                this.activeIndex = 0;
                return;
            }

            const walker = document.createTreeWalker(this.contentRoot, NodeFilter.SHOW_TEXT, {
                acceptNode: (node) => {
                    const parent = node.parentElement;
                    if (!parent) return NodeFilter.FILTER_REJECT;
                    if (['SCRIPT', 'STYLE', 'MARK'].includes(parent.tagName)) return NodeFilter.FILTER_REJECT;
                    if (!node.nodeValue.toLowerCase().includes(term.toLowerCase())) return NodeFilter.FILTER_REJECT;

                    return NodeFilter.FILTER_ACCEPT;
                },
            });

            const nodes = [];
            while (walker.nextNode()) nodes.push(walker.currentNode);

            for (const node of nodes) {
                this.highlightNode(node, term);
            }

            this.matches = [...this.contentRoot.querySelectorAll('mark[data-help-search]')];
            this.activeIndex = 0;
            this.focusActive();
        },

        highlightNode(node, term) {
            const text = node.nodeValue;
            const lowerText = text.toLowerCase();
            const lowerTerm = term.toLowerCase();
            const fragment = document.createDocumentFragment();

            let cursor = 0;
            let index = lowerText.indexOf(lowerTerm, cursor);

            while (index !== -1) {
                if (index > cursor) {
                    fragment.appendChild(document.createTextNode(text.slice(cursor, index)));
                }

                const mark = document.createElement('mark');
                mark.dataset.helpSearch = 'true';
                mark.textContent = text.slice(index, index + term.length);
                fragment.appendChild(mark);

                cursor = index + term.length;
                index = lowerText.indexOf(lowerTerm, cursor);
            }

            if (cursor < text.length) {
                fragment.appendChild(document.createTextNode(text.slice(cursor)));
            }

            node.parentNode.replaceChild(fragment, node);
        },

        clearHighlights() {
            if (!this.contentRoot) return;

            this.contentRoot.querySelectorAll('mark[data-help-search]').forEach((mark) => {
                mark.replaceWith(document.createTextNode(mark.textContent));
            });
            this.contentRoot.normalize();
        },

        clear() {
            this.query = '';
            this.search();
        },

        focusActive() {
            this.matches.forEach((match) => {
                match.removeAttribute('data-active');
            });
            const active = this.matches[this.activeIndex];
            if (!active) return;

            active.dataset.active = 'true';
            active.scrollIntoView({ behavior: 'smooth', block: 'center' });
        },

        next() {
            if (this.matches.length === 0) return;

            this.activeIndex = (this.activeIndex + 1) % this.matches.length;
            this.focusActive();
        },

        previous() {
            if (this.matches.length === 0) return;

            this.activeIndex = (this.activeIndex - 1 + this.matches.length) % this.matches.length;
            this.focusActive();
        },
    }));

    Alpine.data('gradebook', (initialScores, compsMeta) => ({
        scores: { ...initialScores },
        dirty: {},
        saving: false,

        get changeCount() {
            return Object.values(this.dirty).filter(Boolean).length;
        },

        isDirty(key) {
            return !!this.dirty[key];
        },

        handleInput(event, sid, subid, cid) {
            const key = `${sid}-${subid}-${cid}`;
            const raw = event.target.value;
            const val = raw === '' ? null : parseFloat(raw);
            this.scores[key] = val;
            this.dirty[key] = true;
        },

        clampValue(event) {
            const max = parseFloat(event.target.max);
            const min = parseFloat(event.target.min) || 0;
            let val = parseFloat(event.target.value);
            if (!Number.isNaN(val)) {
                val = Math.min(Math.max(val, min), max);
                event.target.value = val;
                const key = event.target.dataset.key;
                if (key && this.dirty[key]) this.scores[key] = val;
            }
        },

        liveTotal(sid, subid) {
            let total = 0;
            for (const comp of compsMeta) {
                const key = `${sid}-${subid}-${comp.id}`;
                const score =
                    this.scores[key] !== undefined && this.scores[key] !== null ? parseFloat(this.scores[key]) : null;
                if (score !== null && !Number.isNaN(score) && comp.max > 0) {
                    total += (score / comp.max) * comp.weight;
                }
            }

            return Math.round(total * 10) / 10;
        },

        formatTotal(val) {
            if (val === 0) return '-';

            return `${val.toFixed(1)}%`;
        },

        // Same subject + component in the nearest row above/below, skipping locked cells.
        // Matched by data-key (studentId-subjectId-componentId), so it holds however many subjects are shown.
        cellInColumn(input, direction) {
            const column = input.dataset.key.split('-').slice(1).join('-');
            let row = input.closest('tr')[direction];
            while (row) {
                const candidate = row.querySelector(`input.cell-input[data-key$="-${column}"]`);
                if (candidate && !candidate.disabled) return candidate;
                row = row[direction];
            }

            return null;
        },

        handleKeydown(e) {
            const target = e.target;
            if (!target.matches('input.cell-input')) return;

            const inputs = [...this.$el.querySelectorAll('input.cell-input:not([disabled])')];
            const idx = inputs.indexOf(target);
            if (idx === -1) return;

            let next = null;

            if (e.key === 'Tab' && !e.shiftKey) {
                e.preventDefault();
                next = inputs[idx + 1];
            } else if (e.key === 'Tab' && e.shiftKey) {
                e.preventDefault();
                next = inputs[idx - 1];
            } else if (e.key === 'Enter' || e.key === 'ArrowDown') {
                e.preventDefault();
                next = this.cellInColumn(target, 'nextElementSibling');
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                next = this.cellInColumn(target, 'previousElementSibling');
            } else if (e.key === 'ArrowRight') {
                if (target.selectionStart === target.value.length) {
                    e.preventDefault();
                    next = inputs[idx + 1];
                }
            } else if (e.key === 'ArrowLeft') {
                if (target.selectionStart === 0) {
                    e.preventDefault();
                    next = inputs[idx - 1];
                }
            }

            if (next) {
                next.focus();
                next.select();
            }
        },

        async save() {
            if (this.changeCount === 0 || this.saving) return;
            this.saving = true;

            const changes = Object.entries(this.dirty)
                .filter(([, d]) => d)
                .map(([key]) => {
                    const parts = key.split('-');
                    return {
                        student_id: parseInt(parts[0], 10),
                        subject_id: parseInt(parts[1], 10),
                        component_id: parseInt(parts[2], 10),
                        score: this.scores[key],
                    };
                });

            try {
                await this.$wire.saveScores(changes);
            } finally {
                this.saving = false;
            }
        },

        // Unsaved scores are saved before another subject replaces the columns on screen
        async switchSubject(select, confirmOptions) {
            const value = select.value === '' ? null : parseInt(select.value, 10);
            if (this.changeCount > 0) {
                const ok = await window.appConfirm(confirmOptions);
                if (ok) await this.save();
                if (!ok || this.changeCount > 0) {
                    select.value = this.$wire.subjectId ?? '';
                    return;
                }
            }
            this.$wire.set('subjectId', value);
        },

        onSaved() {
            this.dirty = {};
            this.$nextTick(() => {
                this.$el.querySelectorAll('input.cell-input').forEach((input) => {
                    const key = input.dataset.key;
                    if (key) {
                        this.scores[key] = input.value === '' ? null : parseFloat(input.value);
                    }
                });
            });
        },

        discard() {
            this.$el.querySelectorAll('input.cell-input').forEach((input) => {
                const key = input.dataset.key;
                if (key && this.dirty[key]) {
                    const original = initialScores[key];
                    input.value = original !== null && original !== undefined ? original : '';
                    this.scores[key] = original !== null && original !== undefined ? original : null;
                }
            });
            this.dirty = {};
        },
    }));

    Alpine.data('richEditor', () => ({
        showLinkDialog: false,
        showTextColorPicker: false,
        showHighlightPicker: false,
        linkUrl: 'https://',
        savedSelection: null,
        charCount: 0,
        wordCount: 0,

        textColors: [
            '#000000',
            '#374151',
            '#6b7280',
            '#ef4444',
            '#f97316',
            '#eab308',
            '#22c55e',
            '#3b82f6',
            '#6366f1',
            '#a855f7',
            '#ec4899',
            '#ffffff',
        ],

        highlightColors: ['#fef08a', '#bbf7d0', '#a5f3fc', '#fecaca', '#fed7aa', '#e9d5ff', '#f9a8d4'],

        init() {
            const hidden = this.$refs.hiddenBody ?? this.$refs.hiddenContent;
            if (hidden?.value) {
                this.$refs.editor.innerHTML = hidden.value;
            }
            this._updateCounts();
            document.addEventListener('selectionchange', () => this.$nextTick(() => {}));
        },

        exec(command, value = null) {
            this.$refs.editor.focus();
            if (command === 'formatBlock') {
                document.execCommand('formatBlock', false, `<${value}>`);
            } else {
                document.execCommand(command, false, value);
            }
            this.updateHidden();
        },

        execColor(type, color) {
            this.restoreSelection();
            this.$refs.editor.focus();
            if (type === 'text') {
                document.execCommand('foreColor', false, color);
                this.showTextColorPicker = false;
            } else {
                document.execCommand('hiliteColor', false, color);
                this.showHighlightPicker = false;
            }
            this.updateHidden();
        },

        insertHR() {
            this.$refs.editor.focus();
            document.execCommand('insertHorizontalRule', false, null);
            this.updateHidden();
        },

        removeLink() {
            this.$refs.editor.focus();
            document.execCommand('unlink', false, null);
            this.updateHidden();
        },

        isActive(command) {
            try {
                return document.queryCommandState(command);
            } catch {
                return false;
            }
        },

        isInLink() {
            try {
                const sel = window.getSelection();
                if (!sel || sel.rangeCount === 0) return false;
                let node = sel.getRangeAt(0).commonAncestorContainer;
                while (node && node !== this.$refs.editor) {
                    if (node.nodeName === 'A') return true;
                    node = node.parentNode;
                }
                return false;
            } catch {
                return false;
            }
        },

        saveSelection() {
            const sel = window.getSelection();
            if (sel && sel.rangeCount > 0) {
                this.savedSelection = sel.getRangeAt(0).cloneRange();
            }
        },

        restoreSelection() {
            if (this.savedSelection) {
                const sel = window.getSelection();
                if (sel) {
                    sel.removeAllRanges();
                    sel.addRange(this.savedSelection);
                }
            }
        },

        openLinkDialog() {
            this.saveSelection();
            // Pre-fill URL if cursor is already inside a link
            let existingUrl = 'https://';
            const sel = window.getSelection();
            if (sel && sel.rangeCount > 0) {
                let node = sel.getRangeAt(0).commonAncestorContainer;
                while (node && node !== this.$refs.editor) {
                    if (node.nodeName === 'A') {
                        existingUrl = node.href || 'https://';
                        break;
                    }
                    node = node.parentNode;
                }
            }
            this.linkUrl = existingUrl;
            this.showLinkDialog = true;
            this.showTextColorPicker = false;
            this.showHighlightPicker = false;
            this.$nextTick(() => {
                this.$refs.linkUrlInput?.focus();
                this.$refs.linkUrlInput?.select();
            });
        },

        applyLink() {
            const url = this.linkUrl.trim();
            if (url && url !== 'https://') {
                this.restoreSelection();
                this.$refs.editor.focus();
                document.execCommand('createLink', false, url);
                this.updateHidden();
            }
            this.closeLinkDialog();
        },

        closeLinkDialog() {
            this.showLinkDialog = false;
            this.linkUrl = 'https://';
            this.savedSelection = null;
        },

        toggleColorPicker(type) {
            this.saveSelection();
            this.showLinkDialog = false;
            if (type === 'text') {
                this.showHighlightPicker = false;
                this.showTextColorPicker = !this.showTextColorPicker;
            } else {
                this.showTextColorPicker = false;
                this.showHighlightPicker = !this.showHighlightPicker;
            }
        },

        updateHidden() {
            const hidden = this.$refs.hiddenBody ?? this.$refs.hiddenContent;
            if (hidden) hidden.value = this.$refs.editor.innerHTML;
            this._updateCounts();
        },

        _updateCounts() {
            const text = this.$refs.editor?.innerText ?? '';
            this.charCount = text.replace(/\n/g, '').length;
            this.wordCount = text.trim() ? text.trim().split(/\s+/).length : 0;
        },
    }));
});

/**
 * Global form loading state enhancement.
 *
 * Automatically shows a spinner and disables submit buttons when
 * POST/PUT/PATCH/DELETE forms are submitted. Prevents double-submission.
 *
 * Skips: GET forms, Livewire forms (wire:submit), forms with [data-no-loading].
 */
document.addEventListener('submit', (e) => {
    const form = e.target;
    const method = (form.getAttribute('method') || 'get').toUpperCase();

    // Skip search/filter forms and Livewire-managed forms
    if (method === 'GET') return;
    if (form.hasAttribute('wire:submit') || form.hasAttribute('wire:submit.prevent')) return;
    if ('noLoading' in form.dataset) return;

    const buttons = form.querySelectorAll('[type="submit"]');

    buttons.forEach((btn) => {
        if (btn.disabled) return;

        btn.disabled = true;
        btn.style.opacity = '0.7';
        btn.style.cursor = 'wait';

        // Replace text content with spinner — preserves outer <button> styling (Flux classes)
        const hasText = btn.textContent.trim().length > 0;
        if (hasText) {
            btn.dataset.originalHtml = btn.innerHTML;
            btn.innerHTML =
                '<svg class="animate-spin h-4 w-4 shrink-0 -ml-0.5 mr-1.5 inline-block" viewBox="0 0 24 24" fill="none" aria-hidden="true">' +
                '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
                '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>' +
                '</svg> Processing\u2026';
        }
    });

    // Safety: re-enable after 15s if page didn't navigate (e.g. validation error redirect)
    setTimeout(() => {
        buttons.forEach((btn) => {
            btn.disabled = false;
            btn.style.opacity = '';
            btn.style.cursor = '';
            if (btn.dataset.originalHtml) {
                btn.innerHTML = btn.dataset.originalHtml;
                delete btn.dataset.originalHtml;
            }
        });
    }, 15000);
});

// ── PWA Service Worker ──────────────────────────────────────────────────────
// Register only for students (manifest link is also student-only in head.blade.php).
// The SW is scoped to /portal/student so it never intercepts admin/teacher requests.
if ('serviceWorker' in navigator && document.querySelector('link[rel="manifest"]')) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/portal/student/' }).catch((err) => {
            // Silently swallow registration errors (e.g. localhost dev, cross-origin)
            console.debug('[PWA] SW registration skipped:', err.message);
        });
    });
}

/*
 * The app's confirm dialog (resources/views/components/app-confirm-dialog.blade.php), used
 * instead of the browser's confirm(). Resolves true when the user confirms.
 *
 *   appConfirm({ title, message, confirmLabel, cancelLabel, variant: 'danger' }).then(ok => ...)
 */
window.appConfirm = (options = {}) =>
    new Promise((resolve) => {
        window.dispatchEvent(new CustomEvent('app-confirm', { detail: { ...options, resolve } }));
    });

/*
 * Livewire answers an expired session (and, in production, some server errors) with HTTP 419
 * and shows a browser confirm() box. Show the app's own dialog instead.
 */
document.addEventListener('livewire:init', () => {
    window.Livewire.interceptRequest(({ onError }) => {
        onError(({ response, preventDefault }) => {
            // A super admin viewing a school tried to change something — viewing is read-only
            if (response?.status === 403 && response.headers?.get('X-Impersonation-Read-Only')) {
                preventDefault();
                window.dispatchEvent(new CustomEvent('impersonation-read-only'));
                return;
            }

            if (response?.status !== 419) return;

            preventDefault();
            window.dispatchEvent(new CustomEvent('livewire-page-expired'));
        });
    });
});

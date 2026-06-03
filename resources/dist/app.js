/**
 * laravel-modules-i18n — translation manager UI ("i18n Studio").
 *
 * Framework-free. Reads prefix/csrf from the #i18n-bootstrap JSON blob, talks to
 * the package JSON API, and renders a type chooser, a PHP group picker, and a
 * locale-comparison grid.
 *
 * Security: every translation value reaches the DOM via textContent or an
 * input's `.value` (never innerHTML), so file contents can't inject markup.
 */
(() => {
    'use strict';

    const BOOT = JSON.parse(document.getElementById('i18n-bootstrap')?.textContent || '{}');
    const PREFIX = BOOT.prefix || '';
    const CSRF = BOOT.csrf || '';

    const LOCALE_LABELS = {
        en: 'English', tr: 'Türkçe', fr: 'Français', de: 'Deutsch', es: 'Español', it: 'Italiano',
        nl: 'Nederlands', pt: 'Português', ru: 'Русский', ar: 'العربية', zh: '中文', ja: '日本語',
    };

    async function api(method, path, body) {
        const res = await fetch(`${PREFIX}/api${path}`, {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(body ? { 'Content-Type': 'application/json' } : {}),
                ...(CSRF ? { 'X-CSRF-TOKEN': CSRF } : {}),
            },
            body: body ? JSON.stringify(body) : undefined,
        });
        let data = null;
        try {
            data = await res.json();
        } catch (e) {
            data = null;
        }
        return { ok: res.ok, status: res.status, data };
    }

    /* ============================== app ============================== */
    const root = document.getElementById('view');
    const elCrumbs = document.getElementById('crumbs');
    const elActions = document.getElementById('topactions');

    const state = {
        catalog: null, view: 'home', type: null, group: null, target: null, refs: [], grid: null,
        deleted: new Set(), renames: [], search: '', missingOnly: false,
    };

    const ICONS = {
        globe: '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 2.5 15.4 0 18M12 3c-2.5 2.6-2.5 15.4 0 18"/>',
        back: '<path d="m15 6-6 6 6 6"/>',
        save: '<path d="M5 4h11l3 3v13H5z"/><path d="M8 4v5h7M8 20v-6h8v6"/>',
        braces: '<path d="M8 4c-1.6 0-2 1-2 3s.2 3-1.6 3c1.8 0 1.6 1 1.6 3s.4 3 2 3M16 4c1.6 0 2 1 2 3s-.2 3 1.6 3c-1.8 0-1.6 1-1.6 3s-.4 3-2 3"/>',
        layers: '<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 13 9 5 9-5"/>',
        chevron: '<path d="m9 6 6 6-6 6"/>',
        search: '<circle cx="11" cy="11" r="7"/><path d="m21 21-4-4"/>',
        arrowLeft: '<path d="M20 12H4M10 6l-6 6 6 6"/>',
        edit: '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        trash: '<path d="M4 7h16M9 7V4h6v3m-7 0 1 13h6l1-13"/>',
        plus: '<path d="M12 5v14M5 12h14"/>',
        target: '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/>',
        jump: '<path d="M13 5l7 7-7 7M4 12h16"/>',
        check: '<path d="m5 13 4 4L19 7"/>',
        warn: '<path d="M12 9v4M12 17h.01M10.3 4 2.5 18a2 2 0 0 0 1.7 3h15.6a2 2 0 0 0 1.7-3L13.7 4a2 2 0 0 0-3.4 0Z"/>',
        empty: '<path d="M3 7h18v13H3zM3 7l3-3h12l3 3"/>',
    };
    function svg(name, w = 18) {
        const m = `<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${w}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">${ICONS[name] || ''}</svg>`;
        const s = document.createElement('span');
        s.style.display = 'inline-flex';
        s.append(new DOMParser().parseFromString(m, 'image/svg+xml').documentElement);
        return s;
    }

    function h(tag, attrs, ...kids) {
        const e = document.createElement(tag);
        for (const [k, v] of Object.entries(attrs || {})) {
            if (v == null || v === false) continue;
            if (k === 'class') e.className = v;
            else if (k === 'value') e.value = v;
            else if (k === 'dataset') Object.assign(e.dataset, v);
            else if (k.startsWith('on') && typeof v === 'function') e.addEventListener(k.slice(2).toLowerCase(), v);
            else e.setAttribute(k, v === true ? '' : String(v));
        }
        for (const kid of kids.flat()) {
            if (kid == null || kid === false) continue;
            e.append(kid.nodeType ? kid : document.createTextNode(String(kid)));
        }
        return e;
    }

    let toastTimer;
    function toast(title, sub, kind = 'success') {
        document.querySelectorAll('.toast').forEach((t) => t.remove());
        const icon = kind === 'error' ? 'warn' : kind === 'info' ? 'globe' : 'check';
        const t = h('div', { class: `toast ${kind}`, role: 'status' }, h('span', { class: 'ti' }, svg(icon, 16)), h('div', {}, h('b', {}, title), sub ? h('small', {}, sub) : null));
        document.body.append(t);
        requestAnimationFrame(() => t.classList.add('show'));
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 300); }, 3400);
    }

    const labelFor = (c) => (LOCALE_LABELS[c] ? `${LOCALE_LABELS[c]} · ${c}` : c);
    const currentLocales = () => [state.target, ...state.refs].filter((v, i, a) => v && a.indexOf(v) === i);

    /* ------------------------------ data ------------------------------ */
    async function boot() {
        const { ok, data } = await api('GET', '/catalog');
        if (!ok || !data) {
            root.replaceChildren(emptyState('warn', 'Could not load translations', 'The API did not respond. Check that you are authorized and the i18n routes are registered.'));
            return;
        }
        state.catalog = data;
        render();
    }

    async function loadGrid() {
        const locales = currentLocales();
        if (!locales.length) { state.grid = { keys: [], rows: {}, hashes: {} }; return render(); }
        const q = `?locales=${locales.join(',')}`;
        const path = state.type === 'json' ? `/json${q}` : `/php/${state.group}${q}`;
        const { ok, data } = await api('GET', path);
        if (!ok || !data) { toast('Failed to load', 'Could not read the translation files.', 'error'); return; }
        state.grid = data;
        state.deleted = new Set();
        state.renames = [];
        render();
    }

    function buildOps() {
        if (!state.grid) return [];
        const ops = [];
        state.renames.forEach((r) => ops.push({ op: 'rename', from: r.from, to: r.to }));
        state.deleted.forEach((k) => ops.push({ op: 'delete', key: k }));
        root.querySelectorAll('[data-cell]').forEach((t) => {
            const { key, locale } = t.dataset;
            if (state.deleted.has(key)) return;
            const orig = (state.grid.rows[key] && state.grid.rows[key][locale]) || '';
            if (t.value !== orig) ops.push({ op: 'set', locale, key, value: t.value });
        });
        return ops;
    }
    const dirtyCount = () => buildOps().length;

    async function doSave() {
        const ops = buildOps();
        if (!ops.length) { toast('Nothing to save', 'Make an edit first.', 'info'); return; }
        const path = state.type === 'json' ? '/json' : `/php/${state.group}`;
        const { ok, status, data } = await api('PATCH', path, { baseHashes: state.grid.hashes, ops });
        if (status === 409) { toast('Files changed on disk', 'Reloading the latest version.', 'error'); await loadGrid(); return; }
        if (!ok) { toast('Save failed', 'Your changes were not written.', 'error'); return; }
        toast('Saved', `${(data && data.changed ? data.changed.length : 0)} file(s) updated · ${ops.length} change(s).`);
        await loadGrid();
    }

    async function addLocale() {
        const loc = (window.prompt('New locale code (e.g. fr):') || '').trim();
        if (!loc) return;
        if (!/^[A-Za-z0-9_-]+$/.test(loc)) { toast('Invalid code', 'Use letters, digits, _ or -', 'error'); return; }
        const body = { type: state.type, locale: loc };
        if (state.type === 'php') body.group = state.group;
        const { ok } = await api('POST', '/locales', body);
        if (!ok) { toast('Could not create locale', '', 'error'); return; }
        if (!state.catalog.locales.includes(loc)) state.catalog.locales.push(loc);
        if (loc !== state.target && !state.refs.includes(loc)) state.refs.push(loc);
        toast('Locale added', `Created ${loc}.`);
        loadGrid();
    }

    /* ------------------------------ mutations ------------------------------ */
    const dirtyGuard = () => !dirtyCount() || window.confirm('Discard unsaved changes?');

    function chooseType(type) {
        state.type = type;
        const ls = state.catalog.locales;
        state.target = ls[0] || null;
        state.refs = ls.slice(1, 3);
        if (type === 'php') { state.view = 'groups'; render(); } else { state.group = null; state.view = 'grid'; loadGrid(); }
    }
    function chooseGroup(g) { state.group = g; state.view = 'grid'; loadGrid(); }
    function goHome() { if (!dirtyGuard()) return; Object.assign(state, { view: 'home', type: null, group: null, grid: null, search: '', missingOnly: false }); render(); }
    function setTarget(l) { if (!dirtyGuard()) return; state.target = l; state.refs = state.refs.filter((x) => x !== l); loadGrid(); }
    function toggleRef(l) { if (!dirtyGuard()) return; state.refs = state.refs.includes(l) ? state.refs.filter((x) => x !== l) : [...state.refs, l]; loadGrid(); }

    function addKey() {
        const lbl = state.type === 'php' ? 'New key (dot-path, e.g. title.icon_tooltip):' : 'New key:';
        const k = (window.prompt(lbl) || '').trim();
        if (!k) return;
        if (state.grid.keys.includes(k)) { toast('Key exists', '', 'error'); return; }
        state.grid.keys.push(k); state.grid.rows[k] = {}; state.deleted.delete(k); render();
        const c = root.querySelector(`[data-cell][data-key="${CSS.escape(k)}"][data-locale="${CSS.escape(state.target)}"]`);
        if (c) c.focus();
    }
    function deleteKey(k) { state.deleted.add(k); render(); }
    function renameKey(k) {
        const to = (window.prompt('Rename key to:', k) || '').trim();
        if (!to || to === k) return;
        if (state.grid.keys.includes(to)) { toast('Key exists', '', 'error'); return; }
        state.renames.push({ from: k, to });
        state.grid.rows[to] = state.grid.rows[k] || {}; delete state.grid.rows[k];
        state.grid.keys = state.grid.keys.map((x) => (x === k ? to : x)); render();
    }

    function copyInto(key, fromLocale) {
        const v = state.grid.rows[key]?.[fromLocale];
        if (v == null) return;
        const c = root.querySelector(`[data-cell][data-key="${CSS.escape(key)}"][data-locale="${CSS.escape(state.target)}"]`);
        if (c) { c.value = v; autoGrow(c); c.focus(); onCellInput(c); }
    }
    function nextMissing() {
        const cells = [...root.querySelectorAll(`[data-cell][data-locale="${CSS.escape(state.target)}"]`)].filter((c) => !c.value.trim());
        if (!cells.length) { toast('All done', `No missing translations for ${state.target}.`, 'success'); return; }
        const c = cells[0]; c.scrollIntoView({ block: 'center', behavior: 'smooth' }); c.focus();
    }

    function autoGrow(el) { el.style.height = 'auto'; el.style.height = el.scrollHeight + 'px'; }
    function onCellInput(t) {
        const cell = t.closest('.cell');
        if (cell && cell.classList.contains('tgt')) {
            const empty = !t.value.trim();
            cell.classList.toggle('empty', empty);
            const tr = cell.closest('tr');
            const kc = tr && tr.querySelector('.keycell');
            if (kc) { kc.classList.toggle('has', !empty); kc.classList.toggle('miss', empty); }
        }
        updateProgress(); updateSave();
    }

    function updateSave() {
        const btn = document.getElementById('saveBtn');
        if (!btn) return;
        const n = dirtyCount();
        btn.disabled = !n;
        const b = btn.querySelector('.badge');
        if (b) { b.textContent = String(n); b.style.display = n ? 'inline-grid' : 'none'; }
    }
    function updateProgress() {
        const el = document.getElementById('prog');
        if (!el || !state.grid) return;
        const keys = state.grid.keys.filter((k) => !state.deleted.has(k));
        const total = keys.length;
        let done = 0;
        keys.forEach((k) => {
            const c = root.querySelector(`[data-cell][data-key="${CSS.escape(k)}"][data-locale="${CSS.escape(state.target)}"]`);
            const v = c ? c.value.trim() : ((state.grid.rows[k] && state.grid.rows[k][state.target]) || '');
            if (v) done++;
        });
        const pct = total ? Math.round(done / total * 100) : 100;
        el.querySelector('.fill').style.width = pct + '%';
        const txt = el.querySelector('.ptxt');
        txt.replaceChildren(h('b', {}, `${done}/${total}`), document.createTextNode(` ${state.target} translated · `), h('span', { class: (total - done) ? '' : 'done' }, `${total - done} missing`));
    }

    function visibleKeys() {
        return state.grid.keys.filter((k) => !state.deleted.has(k))
            .filter((k) => !state.search || k.toLowerCase().includes(state.search.toLowerCase()))
            .filter((k) => !state.missingOnly || !(state.grid.rows[k] && state.grid.rows[k][state.target]));
    }

    /* ------------------------------ render ------------------------------ */
    function render() {
        renderCrumbs(); renderActions();
        root.replaceChildren(state.view === 'home' ? renderHome() : state.view === 'groups' ? renderGroups() : renderGrid());
        if (state.view === 'grid') { updateProgress(); updateSave(); }
    }

    function renderCrumbs() {
        const items = [];
        items.push(h('button', { class: 'iconbtn', title: 'Back to start', 'aria-label': 'Back to start', onclick: goHome, style: state.view === 'home' ? 'visibility:hidden' : '' }, svg('back', 18)));
        items.push(h('span', { class: state.view === 'home' ? 'here' : '' }, 'Translations'));
        if (state.type) items.push(h('span', { class: 'sep' }, '/'), h('span', { class: 'chip-type' }, state.type === 'json' ? 'JSON' : 'PHP'), h('span', { class: state.group ? '' : 'here' }, state.type === 'json' ? 'JSON files' : 'PHP files'));
        if (state.group) items.push(h('span', { class: 'sep' }, '/'), h('span', { class: 'here' }, state.group));
        elCrumbs.replaceChildren(...items);
    }

    function renderActions() {
        if (state.view !== 'grid') { elActions.replaceChildren(); return; }
        const btn = h('button', { id: 'saveBtn', class: 'btn btn-primary', onclick: doSave, title: 'Save (Ctrl/⌘ + S)' }, svg('save', 16), 'Save', h('span', { class: 'badge', style: 'display:none' }, '0'));
        elActions.replaceChildren(btn);
    }

    function renderHome() {
        const card = (type, icon, title, desc, meta) => h('button', { class: 'card', onclick: () => chooseType(type) },
            h('span', { class: 'go' }, svg('chevron', 20)),
            h('span', { class: 'ic' }, svg(icon, 24)),
            h('h2', {}, title), h('p', {}, desc),
            h('div', { class: 'meta' }, ...meta));
        return h('div', { class: 'fade' },
            h('div', { class: 'intro' },
                h('div', { class: 'kicker' }, h('span', { class: 'dot' }), 'Localization workspace'),
                h('h1', {}, 'Manage every ', h('span', { class: 'grad' }, 'translation'), ' in one deck.'),
                h('p', { class: 'sub' }, 'Edit your Laravel JSON and PHP language files side by side — pick a target locale, keep references in view, and ship complete translations faster.')),
            h('div', { class: 'cards stagger' },
                card('json', 'braces', 'JSON files', 'Flat key → value strings in lang/{locale}.json.',
                    [h('span', {}, h('b', {}, String(state.catalog.json.locales.length)), ' locales')]),
                card('php', 'layers', 'PHP array files', 'Grouped, deeply-nestable keys in lang/{locale}/*.php.',
                    [h('span', {}, h('b', {}, String(state.catalog.php.groups.length)), ' groups')])));
    }

    function renderGroups() {
        const groups = state.catalog.php.groups;
        if (!groups.length) return emptyState('empty', 'No PHP groups', 'No PHP array files found under your lang path.');
        return h('div', { class: 'fade' },
            h('div', { class: 'intro' }, h('div', { class: 'kicker' }, h('span', { class: 'dot' }), 'PHP array files'), h('h1', { style: 'font-size:26px' }, 'Choose a file')),
            h('div', { class: 'panel glist stagger' },
                ...groups.map((g) => h('button', { class: 'gitem', onclick: () => chooseGroup(g) },
                    h('span', { class: 'gic' }, svg('layers', 16)), h('span', { class: 'gname' }, `${g}.php`), h('span', { class: 'go' }, svg('chevron', 18))))));
    }

    function keyMarkup(key) {
        const wrap = h('span', { class: 'keytxt' });
        const parts = key.split('.');
        parts.forEach((p, i) => { wrap.append(h('span', { class: i === parts.length - 1 ? 'leaf' : 'seg' }, p)); if (i < parts.length - 1) wrap.append(h('span', { class: 'seg' }, '.')); });
        return wrap;
    }

    function renderGrid() {
        if (!state.grid) return emptyState('globe', 'Loading…', '');
        const locales = currentLocales();
        const toolbar = renderToolbar(locales);
        if (!locales.length) return h('div', { class: 'fade' }, toolbar, emptyState('target', 'No target locale', 'Pick a target locale to start translating.'));

        const keys = visibleKeys();
        const thead = h('tr', {},
            h('th', { class: 'k' }, 'Key'),
            ...locales.map((l) => { const tgt = l === state.target; return h('th', { class: tgt ? 'tgt' : '' }, l, tgt ? h('span', { class: 'tgtpill' }, 'TARGET') : null); }),
            h('th', {}, ''));
        const body = keys.length ? keys.map((k) => gridRow(k, locales)) :
            [h('tr', {}, h('td', { class: 'k' }, ''), h('td', { colspan: locales.length + 1, style: 'padding:46px;text-align:center;color:var(--faint)' }, 'No keys match your filters.'))];

        return h('div', { class: 'fade' }, toolbar,
            h('div', { class: 'progress', id: 'prog' }, h('div', { class: 'bar' }, h('div', { class: 'fill' })), h('span', { class: 'ptxt' }, '')),
            h('div', { class: 'gridwrap' }, h('table', {}, h('thead', {}, thead), h('tbody', { class: 'stagger' }, ...body))),
            h('div', { class: 'tablefoot' },
                h('div', { class: 'legend' },
                    h('i', {}, h('span', { class: 'sw', style: 'background:var(--emerald);box-shadow:0 0 8px var(--emerald)' }), 'translated'),
                    h('i', {}, h('span', { class: 'sw', style: 'background:var(--amber);box-shadow:0 0 8px var(--amber)' }), 'missing')),
                h('span', { id: 'kcount' }, `${keys.length} of ${state.grid.keys.length} keys`)));
    }

    function renderToolbar(locales) {
        const refChips = h('div', { class: 'chips' }, ...state.catalog.locales.filter((l) => l !== state.target).map((l) =>
            h('button', { class: 'chip', 'aria-pressed': String(state.refs.includes(l)), onclick: () => toggleRef(l) }, l)));
        const sel = h('select', { class: 'sel', 'aria-label': 'Target locale', onchange: (e) => setTarget(e.target.value) },
            ...state.catalog.locales.map((l) => h('option', { value: l, ...(l === state.target ? { selected: true } : {}) }, labelFor(l))));
        const searchInput = h('input', { type: 'search', id: 'searchBox', placeholder: 'Search keys', value: state.search, 'aria-label': 'Search keys', oninput: (e) => { state.search = e.target.value; refreshBody(); } });
        return h('div', { class: 'toolbar' },
            h('div', { class: 'field' }, h('span', { class: 'lbl' }, 'Target'), h('div', { class: 'select' }, sel, h('span', { class: 'car' }, svg('chevron', 16)))),
            h('div', { class: 'field' }, h('span', { class: 'lbl' }, 'Reference'), refChips),
            h('span', { class: 'grow' }),
            h('div', { class: 'search' }, h('span', { class: 'si' }, svg('search', 16)), searchInput, h('kbd', {}, '/')),
            h('label', { class: 'toggle' }, h('input', { type: 'checkbox', ...(state.missingOnly ? { checked: true } : {}), onchange: (e) => { state.missingOnly = e.target.checked; refreshBody(); } }), h('span', { class: 'tk' }), 'Missing only'),
            h('button', { class: 'btn btn-ghost', onclick: nextMissing, title: 'Jump to next missing' }, svg('jump', 16), 'Next missing'),
            h('button', { class: 'btn', onclick: addKey }, svg('plus', 16), 'Key'),
            h('button', { class: 'btn', onclick: addLocale }, svg('plus', 16), 'Locale'));
    }

    function gridRow(key, locales) {
        const filled = !!(state.grid.rows[key] && state.grid.rows[key][state.target]);
        const kcell = h('td', { class: 'k' }, h('div', { class: `keycell ${filled ? 'has' : 'miss'}` }, h('span', { class: 'kdot' }), keyMarkup(key)));
        const cells = locales.map((loc) => {
            const val = (state.grid.rows[key] && state.grid.rows[key][loc]) || '';
            const tgt = loc === state.target;
            const ta = h('textarea', { rows: 1, spellcheck: 'false', 'data-cell': '1', dataset: { key, locale: loc }, placeholder: tgt ? 'Add translation…' : '—', value: val, oninput: (e) => { autoGrow(e.target); onCellInput(e.target); } });
            queueMicrotask(() => autoGrow(ta));
            return h('td', { class: tgt ? 'tgt' : '' }, h('div', { class: `cell ${tgt ? 'tgt' : 'ref'} ${tgt && !val ? 'empty' : ''}` }, ta,
                (!tgt) ? h('button', { class: 'tocopy', title: `Copy ${loc} → ${state.target}`, 'aria-label': `Copy ${loc} into target`, onclick: () => copyInto(key, loc) }, svg('arrowLeft', 13)) : null));
        });
        const act = h('td', {}, h('div', { class: 'rowact' },
            h('button', { title: 'Rename key', 'aria-label': 'Rename key', onclick: () => renameKey(key) }, svg('edit', 15)),
            h('button', { class: 'del', title: 'Delete key', 'aria-label': 'Delete key', onclick: () => deleteKey(key) }, svg('trash', 15))));
        return h('tr', {}, kcell, ...cells, act);
    }

    function refreshBody() {
        const wrap = root.querySelector('.gridwrap');
        if (!wrap) return render();
        const locales = currentLocales();
        const keys = visibleKeys();
        const tb = wrap.querySelector('tbody');
        tb.replaceChildren(...(keys.length ? keys.map((k) => gridRow(k, locales)) :
            [h('tr', {}, h('td', { class: 'k' }, ''), h('td', { colspan: locales.length + 1, style: 'padding:46px;text-align:center;color:var(--faint)' }, 'No keys match your filters.'))]));
        const kc = document.getElementById('kcount');
        if (kc) kc.textContent = `${keys.length} of ${state.grid.keys.length} keys`;
        updateProgress();
    }

    function emptyState(icon, title, desc) {
        return h('div', { class: 'empty fade' }, h('div', { class: 'ei' }, svg(icon, 26)), h('h3', {}, title), desc ? h('p', {}, desc) : null);
    }

    /* ------------------------------ shortcuts ------------------------------ */
    document.addEventListener('keydown', (e) => {
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 's') { e.preventDefault(); if (state.view === 'grid') doSave(); }
        if (e.key === '/' && !/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)) { const s = document.getElementById('searchBox'); if (s) { e.preventDefault(); s.focus(); } }
        if (e.key === 'Escape') { const s = document.getElementById('searchBox'); if (document.activeElement === s) { s.value = ''; state.search = ''; refreshBody(); } }
    });

    document.querySelector('.brand .mark')?.append(svg('globe', 19));
    boot();
})();

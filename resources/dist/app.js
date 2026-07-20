/**
 * laravel-modules-i18n — translation manager UI ("i18n Studio").
 *
 * Framework-free. Reads prefix/csrf from the #i18n-bootstrap JSON blob, talks to
 * the package JSON API, and renders a type chooser (JSON / PHP / Vendor), a PHP
 * group folder browser, a vendor package browser, and a locale-comparison grid.
 * Navigation is driven by location.hash so the browser's back/forward buttons,
 * refresh, and bookmarks all work.
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
        const res = await fetch(`${PREFIX}${path}`, {
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
        // The API kit wraps successful payloads in a `{ data, meta }` envelope;
        // unwrap it so callers keep seeing the bare payload they expect.
        if (res.ok && data && typeof data === 'object' && 'data' in data) {
            data = data.data;
        }
        return { ok: res.ok, status: res.status, data };
    }

    /* ============================== app ============================== */
    const root = document.getElementById('view');
    const elCrumbs = document.getElementById('crumbs');
    const elActions = document.getElementById('topactions');

    const state = {
        catalog: null, view: 'home', type: null, group: null, namespace: null, groupPath: '',
        target: null, refs: [], grid: null, deleted: new Set(), renames: [], search: '', missingOnly: false,
    };

    const ICONS = {
        globe: '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 2.5 15.4 0 18M12 3c-2.5 2.6-2.5 15.4 0 18"/>',
        back: '<path d="m15 6-6 6 6 6"/>',
        save: '<path d="M5 4h11l3 3v13H5z"/><path d="M8 4v5h7M8 20v-6h8v6"/>',
        braces: '<path d="M8 4c-1.6 0-2 1-2 3s.2 3-1.6 3c1.8 0 1.6 1 1.6 3s.4 3 2 3M16 4c1.6 0 2 1 2 3s-.2 3 1.6 3c-1.8 0-1.6 1-1.6 3s-.4 3-2 3"/>',
        layers: '<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 13 9 5 9-5"/>',
        folder: '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/>',
        package: '<path d="M21 8 12 3 3 8v8l9 5 9-5Z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
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
    const joinPath = (a, b) => (a ? `${a}/${b}` : b);
    // The group id sent to the API: namespaced for vendor groups, plain otherwise.
    const apiGroup = () => (state.namespace ? `${state.namespace}::${state.group}` : state.group);

    /* ------------------------------ routing ------------------------------ */
    // location.hash is the single source of truth for which view is shown.
    //   (home)                     #
    //   JSON grid                  #e/json
    //   PHP folder browser         #g | #g/<folder>
    //   PHP group grid             #e/php/<group>
    //   Vendor package list        #v
    //   Vendor package browser     #v/<package> | #v/<package>/<folder>
    //   Vendor group grid          #e/v/<package>/<group>
    function go(target) {
        if (state.view === 'grid' && dirtyCount() > 0 && !window.confirm('Discard unsaved changes?')) return;
        const next = target || '';
        if (location.hash.replace(/^#/, '') === next) applyRoute();
        else location.hash = next;
    }

    function applyRoute() {
        if (!state.catalog) return;
        const raw = location.hash.replace(/^#/, '');
        const parts = raw.split('/').filter((s, i) => !(i === 0 && s === ''));
        state.namespace = null;

        if (raw === '') { state.view = 'home'; state.type = null; return render(); }

        if (parts[0] === 'g') {
            state.type = 'php';
            state.view = 'groups';
            state.groupPath = parts.slice(1).join('/');
            return render();
        }

        if (parts[0] === 'v') {
            state.type = 'php';
            if (parts.length === 1) { state.view = 'vendors'; return render(); }
            state.namespace = parts[1];
            state.view = 'groups';
            state.groupPath = parts.slice(2).join('/');
            return render();
        }

        if (parts[0] === 'e') {
            if (parts[1] === 'json') { state.type = 'json'; state.group = null; }
            else if (parts[1] === 'php') { state.type = 'php'; state.group = parts.slice(2).join('/'); }
            else if (parts[1] === 'v') { state.type = 'php'; state.namespace = parts[2]; state.group = parts.slice(3).join('/'); }
            else { state.view = 'home'; return render(); }
            state.view = 'grid';
            const ls = state.catalog.locales;
            state.target = ls[0] || null;
            state.refs = ls.slice(1, 3);
            state.grid = null;
            render();
            return loadGrid();
        }

        state.view = 'home';
        render();
    }

    /* ------------------------------ data ------------------------------ */
    async function boot() {
        const { ok, data } = await api('GET', '/catalog');
        if (!ok || !data) {
            root.replaceChildren(emptyState('warn', 'Could not load translations', 'The API did not respond. Check that you are authorized and the i18n routes are registered.'));
            return;
        }
        state.catalog = data;
        window.addEventListener('hashchange', applyRoute);
        applyRoute();
    }

    async function loadGrid() {
        const locales = currentLocales();
        if (!locales.length) { state.grid = { keys: [], rows: {}, hashes: {} }; return render(); }
        const q = `?locales=${locales.join(',')}`;
        const path = state.type === 'json' ? `/json${q}` : `/php/${apiGroup()}${q}`;
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
        const path = state.type === 'json' ? '/json' : `/php/${apiGroup()}`;
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
        if (state.type === 'php') body.group = apiGroup();
        const { ok } = await api('POST', '/locales', body);
        if (!ok) { toast('Could not create locale', '', 'error'); return; }
        if (!state.catalog.locales.includes(loc)) state.catalog.locales.push(loc);
        if (loc !== state.target && !state.refs.includes(loc)) state.refs.push(loc);
        toast('Locale added', `Created ${loc}.`);
        loadGrid();
    }

    /* ------------------------------ grid mutations ------------------------------ */
    const dirtyGuard = () => !dirtyCount() || window.confirm('Discard unsaved changes?');

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

    // Split a list of groups into the folders and files directly under `prefix`.
    function phpTree(groups, prefix) {
        const base = prefix ? `${prefix}/` : '';
        const folders = {};
        const files = [];
        for (const g of groups) {
            if (prefix && !(g === prefix || g.startsWith(base))) continue;
            const rest = prefix ? (g === prefix ? '' : g.slice(base.length)) : g;
            if (rest === '') continue;
            const i = rest.indexOf('/');
            if (i === -1) files.push(g);
            else { const seg = rest.slice(0, i); folders[seg] = (folders[seg] || 0) + 1; }
        }
        return {
            folders: Object.keys(folders).sort().map((name) => ({ name, count: folders[name] })),
            files: files.sort(),
        };
    }

    /* ------------------------------ render ------------------------------ */
    function render() {
        renderCrumbs(); renderActions();
        const view = state.view === 'home' ? renderHome()
            : state.view === 'vendors' ? renderVendors()
                : state.view === 'groups' ? renderGroups()
                    : renderGrid();
        root.replaceChildren(view);
        if (state.view === 'grid') { updateProgress(); updateSave(); }
    }

    function sep() { return h('span', { class: 'sep' }, '/'); }
    function crumbLink(label, onclick) { return h('button', { class: 'crumblink', onclick }, label); }
    function crumbHere(label) { return h('span', { class: 'here' }, label); }

    function renderCrumbs() {
        const items = [
            h('button', { class: 'iconbtn', title: 'Back', 'aria-label': 'Back', onclick: () => history.back(), style: state.view === 'home' ? 'visibility:hidden' : '' }, svg('back', 18)),
            state.view === 'home' ? crumbHere('Translations') : crumbLink('Translations', () => go('')),
        ];

        if (state.view === 'vendors' || state.namespace) {
            items.push(sep(), h('span', { class: 'chip-type' }, 'VENDOR'));
            items.push(sep(), state.view === 'vendors' ? crumbHere('Vendor packages') : crumbLink('Vendor packages', () => go('v')));
            if (state.namespace) {
                const pkgHere = state.view === 'groups' && !state.groupPath;
                items.push(sep(), pkgHere ? crumbHere(state.namespace) : crumbLink(state.namespace, () => go('v/' + state.namespace)));
                pushPathCrumbs(items, (p) => 'v/' + state.namespace + '/' + p);
            }
        } else if (state.type === 'json') {
            items.push(sep(), h('span', { class: 'chip-type' }, 'JSON'), sep(), crumbHere('JSON files'));
        } else if (state.type === 'php') {
            items.push(sep(), h('span', { class: 'chip-type' }, 'PHP'));
            const rootHere = state.view === 'groups' && !state.groupPath;
            items.push(sep(), rootHere ? crumbHere('PHP files') : crumbLink('PHP files', () => go('g')));
            pushPathCrumbs(items, (p) => 'g/' + p);
        }
        elCrumbs.replaceChildren(...items);
    }

    // Append folder/group path crumbs for the current groups or grid view.
    function pushPathCrumbs(items, hashFor) {
        if (state.view === 'groups' && state.groupPath) {
            const segs = state.groupPath.split('/');
            let path = '';
            segs.forEach((s, i) => {
                path = joinPath(path, s);
                const p = path;
                items.push(sep(), i === segs.length - 1 ? crumbHere(s) : crumbLink(s, () => go(hashFor(p))));
            });
        } else if (state.view === 'grid' && state.group) {
            const parts = state.group.split('/');
            let path = '';
            parts.forEach((s, i) => {
                if (i === parts.length - 1) { items.push(sep(), crumbHere(s)); return; }
                path = joinPath(path, s);
                const p = path;
                items.push(sep(), crumbLink(s, () => go(hashFor(p))));
            });
        }
    }

    function renderActions() {
        if (state.view !== 'grid') { elActions.replaceChildren(); return; }
        const btn = h('button', { id: 'saveBtn', class: 'btn btn-primary', onclick: doSave, title: 'Save (Ctrl/⌘ + S)' }, svg('save', 16), 'Save', h('span', { class: 'badge', style: 'display:none' }, '0'));
        elActions.replaceChildren(btn);
    }

    function renderHome() {
        const card = (target, icon, title, desc, meta) => h('button', { class: 'card', onclick: () => go(target) },
            h('span', { class: 'go' }, svg('chevron', 20)),
            h('span', { class: 'ic' }, svg(icon, 24)),
            h('h2', {}, title), h('p', {}, desc),
            h('div', { class: 'meta' }, ...meta));
        const vendorCount = (state.catalog.vendor || []).length;
        return h('div', { class: 'fade' },
            h('div', { class: 'intro' },
                h('div', { class: 'kicker' }, h('span', { class: 'dot' }), 'Localization workspace'),
                h('h1', {}, 'Manage every ', h('span', { class: 'grad' }, 'translation'), ' in one deck.'),
                h('p', { class: 'sub' }, 'Edit your Laravel JSON, PHP, and vendor language files side by side — pick a target locale, keep references in view, and ship complete translations faster.')),
            h('div', { class: 'cards stagger' },
                card('e/json', 'braces', 'JSON files', 'Flat key → value strings in lang/{locale}.json.',
                    [h('span', {}, h('b', {}, String(state.catalog.json.locales.length)), ' locales')]),
                card('g', 'layers', 'PHP array files', 'Grouped, deeply-nestable keys in lang/{locale}/*.php.',
                    [h('span', {}, h('b', {}, String(state.catalog.php.groups.length)), ' groups')]),
                card('v', 'package', 'Vendor packages', 'Namespaced package translations in lang/vendor/{package}.',
                    [h('span', {}, h('b', {}, String(vendorCount)), vendorCount === 1 ? ' package' : ' packages')])));
    }

    function renderVendors() {
        const pkgs = state.catalog.vendor || [];
        const list = pkgs.length
            ? h('div', { class: 'panel glist stagger' },
                ...pkgs.map((p) => h('button', { class: 'gitem', onclick: () => go('v/' + p.name) },
                    h('span', { class: 'gic folder' }, svg('package', 16)),
                    h('span', { class: 'gname' }, p.name),
                    h('span', { class: 'gcount' }, `${p.groups.length} ${p.groups.length === 1 ? 'file' : 'files'} · ${p.locales.length} ${p.locales.length === 1 ? 'locale' : 'locales'}`),
                    h('span', { class: 'go' }, svg('chevron', 18)))))
            : emptyState('package', 'No vendor translations', 'Nothing published under lang/vendor yet.');
        return h('div', { class: 'fade' },
            h('div', { class: 'intro' }, h('div', { class: 'kicker' }, h('span', { class: 'dot' }), 'Vendor packages'), h('h1', { style: 'font-size:26px' }, 'Choose a package')),
            list);
    }

    function renderGroups() {
        const source = state.namespace
            ? ((state.catalog.vendor || []).find((p) => p.name === state.namespace) || { groups: [] }).groups
            : state.catalog.php.groups;
        const { folders, files } = phpTree(source, state.groupPath);
        const heading = state.groupPath || state.namespace || 'Choose a file';
        const folderBase = state.namespace ? 'v/' + state.namespace : 'g';
        const kicker = state.namespace ? `Vendor · ${state.namespace}` : 'PHP array files';

        const list = (folders.length || files.length)
            ? h('div', { class: 'panel glist stagger' },
                ...folders.map((f) => h('button', { class: 'gitem', onclick: () => go(folderBase + '/' + joinPath(state.groupPath, f.name)) },
                    h('span', { class: 'gic folder' }, svg('folder', 16)),
                    h('span', { class: 'gname' }, `${f.name}/`),
                    h('span', { class: 'gcount' }, `${f.count} ${f.count === 1 ? 'file' : 'files'}`),
                    h('span', { class: 'go' }, svg('chevron', 18)))),
                ...files.map((g) => {
                    const leaf = state.groupPath ? g.slice(state.groupPath.length + 1) : g;
                    return h('button', { class: 'gitem', onclick: () => go(state.namespace ? 'e/v/' + state.namespace + '/' + g : 'e/php/' + g) },
                        h('span', { class: 'gic' }, svg('layers', 16)),
                        h('span', { class: 'gname' }, `${leaf}.php`),
                        h('span', { class: 'go' }, svg('chevron', 18)));
                }))
            : emptyState('empty', state.namespace ? 'Empty package' : 'Empty folder', 'No PHP array files here.');

        return h('div', { class: 'fade' },
            h('div', { class: 'intro' }, h('div', { class: 'kicker' }, h('span', { class: 'dot' }), kicker), h('h1', { style: 'font-size:26px' }, heading)),
            list);
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

    const mark = document.querySelector('.brand .mark');
    if (mark) mark.append(svg('globe', 19));
    const brand = document.querySelector('.brand');
    if (brand) { brand.style.cursor = 'pointer'; brand.addEventListener('click', () => go('')); }

    boot();
})();

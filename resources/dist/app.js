/**
 * laravel-modules-i18n — translation manager UI.
 *
 * Framework-free. Reads its configuration from the #i18n-bootstrap JSON blob,
 * talks to the package's JSON API, and renders a type chooser, a PHP group
 * picker, and a locale-comparison grid. No build step beyond Tailwind for CSS.
 *
 * Security note: all translation values reach the DOM via textContent or an
 * input's `.value` (never innerHTML), so file contents can never inject markup.
 */
(() => {
    'use strict';

    const boot = JSON.parse(document.getElementById('i18n-bootstrap')?.textContent || '{}');
    const root = document.getElementById('i18n-app');
    const STR = boot.strings || {};

    const state = {
        prefix: boot.prefix || '',
        csrf: boot.csrf || '',
        catalog: boot.catalog || { locales: [], json: { locales: [] }, php: { groups: [] } },
        view: 'home', // home | groups | grid
        type: null, // 'json' | 'php'
        group: null,
        target: null,
        refs: [],
        grid: null, // { keys, rows, hashes }
        deleted: new Set(),
        renames: [],
        search: '',
        missingOnly: false,
    };

    // ---------------------------------------------------------------- helpers

    function h(tag, attrs, ...kids) {
        const el = document.createElement(tag);
        for (const [k, v] of Object.entries(attrs || {})) {
            if (v == null || v === false) continue;
            if (k === 'class') el.className = v;
            else if (k === 'value') el.value = v;
            else if (k === 'dataset') Object.assign(el.dataset, v);
            else if (k.startsWith('on') && typeof v === 'function') el.addEventListener(k.slice(2).toLowerCase(), v);
            else el.setAttribute(k, v === true ? '' : String(v));
        }
        for (const kid of kids.flat()) {
            if (kid == null || kid === false) continue;
            el.append(kid.nodeType ? kid : document.createTextNode(String(kid)));
        }
        return el;
    }

    function t(path, fallback) {
        const value = path.split('.').reduce((o, k) => (o && o[k] != null ? o[k] : null), STR);
        return value == null ? fallback : value;
    }

    async function api(method, path, body) {
        const res = await fetch(`${state.prefix}/api${path}`, {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(body ? { 'Content-Type': 'application/json' } : {}),
                ...(state.csrf ? { 'X-CSRF-TOKEN': state.csrf } : {}),
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

    function toast(message, kind = 'success') {
        const colors = { success: 'bg-emerald-600', error: 'bg-rose-600', info: 'bg-slate-800' };
        const node = h('div', {
            class: `fixed bottom-5 left-1/2 -translate-x-1/2 z-50 rounded-lg px-4 py-2 text-sm font-medium text-white shadow-lg ${colors[kind] || colors.info}`,
        }, message);
        document.body.append(node);
        setTimeout(() => node.remove(), 3200);
    }

    function currentLocales() {
        return [state.target, ...state.refs].filter((v, i, a) => v && a.indexOf(v) === i);
    }

    function localeLabel(code) {
        const map = state.catalog.localeLabels || {};
        return map[code] ? `${map[code]} (${code})` : code;
    }

    // ------------------------------------------------------------------- data

    async function loadGrid() {
        const locales = currentLocales();
        if (locales.length === 0) {
            state.grid = { keys: [], rows: {}, hashes: {} };
            render();
            return;
        }
        const query = `?locales=${locales.join(',')}`;
        const path = state.type === 'json' ? `/json${query}` : `/php/${state.group}${query}`;
        const { ok, data } = await api('GET', path);
        if (!ok || !data) {
            toast('Failed to load translations.', 'error');
            return;
        }
        state.grid = data;
        state.deleted = new Set();
        state.renames = [];
        render();
    }

    function buildOps() {
        if (!state.grid) return [];
        const ops = [];
        for (const r of state.renames) ops.push({ op: 'rename', from: r.from, to: r.to });
        for (const key of state.deleted) ops.push({ op: 'delete', key });

        root.querySelectorAll('[data-cell]').forEach((input) => {
            const { key, locale } = input.dataset;
            if (state.deleted.has(key)) return;
            const original = (state.grid.rows[key] && state.grid.rows[key][locale]) || '';
            if (input.value !== original) {
                ops.push({ op: 'set', locale, key, value: input.value });
            }
        });
        return ops;
    }

    function hasChanges() {
        return buildOps().length > 0;
    }

    async function save() {
        const ops = buildOps();
        if (ops.length === 0) {
            toast(t('messages.nothing_to_save', 'No changes to save.'), 'info');
            return;
        }
        const path = state.type === 'json' ? '/json' : `/php/${state.group}`;
        const { ok, status } = await api('PATCH', path, { baseHashes: state.grid.hashes, ops });
        if (status === 409) {
            toast(t('messages.conflict', 'Files changed on disk. Reloading.'), 'error');
            await loadGrid();
            return;
        }
        if (!ok) {
            toast('Save failed.', 'error');
            return;
        }
        toast(t('messages.saved', 'Translations saved.'));
        await loadGrid();
    }

    async function addLocale() {
        const locale = (window.prompt('New locale code (e.g. fr):') || '').trim();
        if (!locale) return;
        if (!/^[A-Za-z0-9_-]+$/.test(locale)) {
            toast('Invalid locale code.', 'error');
            return;
        }
        const body = { type: state.type, locale };
        if (state.type === 'php') body.group = state.group;
        const { ok } = await api('POST', '/locales', body);
        if (!ok) {
            toast('Could not create the locale.', 'error');
            return;
        }
        if (!state.catalog.locales.includes(locale)) state.catalog.locales.push(locale);
        if (locale !== state.target && !state.refs.includes(locale)) state.refs.push(locale);
        toast(`Added ${locale}.`);
        await loadGrid();
    }

    // -------------------------------------------------------------- mutations

    function guardDirty() {
        return !hasChanges() || window.confirm('You have unsaved changes. Discard them?');
    }

    function chooseType(type) {
        state.type = type;
        state.target = state.catalog.locales[0] || null;
        state.refs = [];
        if (type === 'php') {
            state.view = 'groups';
            render();
        } else {
            state.group = null;
            state.view = 'grid';
            loadGrid();
        }
    }

    function chooseGroup(group) {
        state.group = group;
        state.view = 'grid';
        loadGrid();
    }

    function backToHome() {
        if (!guardDirty()) return;
        Object.assign(state, { view: 'home', type: null, group: null, grid: null, search: '', missingOnly: false });
        render();
    }

    function setTarget(locale) {
        if (!guardDirty()) return;
        state.target = locale;
        state.refs = state.refs.filter((l) => l !== locale);
        loadGrid();
    }

    function toggleRef(locale) {
        if (!guardDirty()) return;
        state.refs = state.refs.includes(locale)
            ? state.refs.filter((l) => l !== locale)
            : [...state.refs, locale];
        loadGrid();
    }

    function addKey() {
        const label = state.type === 'php' ? 'New key (dot-path, e.g. title.icon_tooltip):' : 'New key:';
        const key = (window.prompt(label) || '').trim();
        if (!key) return;
        if (state.grid.keys.includes(key)) {
            toast('That key already exists.', 'error');
            return;
        }
        state.grid.keys.push(key);
        state.grid.rows[key] = {};
        state.deleted.delete(key);
        render();
        const cell = root.querySelector(`[data-cell][data-key="${CSS.escape(key)}"][data-locale="${CSS.escape(state.target)}"]`);
        if (cell) cell.focus();
    }

    function deleteKey(key) {
        state.deleted.add(key);
        render();
    }

    function renameKey(key) {
        const to = (window.prompt('Rename key to:', key) || '').trim();
        if (!to || to === key) return;
        if (state.grid.keys.includes(to)) {
            toast('That key already exists.', 'error');
            return;
        }
        state.renames.push({ from: key, to });
        state.grid.rows[to] = state.grid.rows[key] || {};
        delete state.grid.rows[key];
        state.grid.keys = state.grid.keys.map((k) => (k === key ? to : k));
        render();
    }

    // ----------------------------------------------------------------- render

    function render() {
        root.replaceChildren(topBar(), main());
    }

    function topBar() {
        const crumbs = [t('title', 'Translations')];
        if (state.type) crumbs.push(state.type === 'json' ? t('modes.json', 'JSON files') : t('modes.php', 'PHP array files'));
        if (state.group) crumbs.push(state.group);

        return h('header', { class: 'sticky top-0 z-30 flex items-center justify-between gap-4 border-b border-slate-200 bg-white/90 px-5 py-3 backdrop-blur' },
            h('div', { class: 'flex min-w-0 items-center gap-3' },
                h('button', {
                    class: 'rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700' + (state.view === 'home' ? ' invisible' : ''),
                    title: 'Back to start',
                    onclick: backToHome,
                }, icon('home')),
                h('nav', { class: 'flex min-w-0 items-center gap-2 text-sm' },
                    ...crumbs.flatMap((c, i) => [
                        i ? h('span', { class: 'text-slate-300' }, '/') : null,
                        h('span', { class: `truncate ${i === crumbs.length - 1 ? 'font-semibold text-slate-900' : 'text-slate-500'}` }, c),
                    ].filter(Boolean)),
                ),
            ),
            state.view === 'grid'
                ? h('button', {
                    class: 'inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500',
                    onclick: save,
                }, icon('save'), t('actions.save', 'Save'))
                : null,
        );
    }

    function main() {
        const wrap = h('main', { class: 'mx-auto max-w-7xl px-5 py-8' });
        if (state.view === 'home') wrap.append(renderHome());
        else if (state.view === 'groups') wrap.append(renderGroups());
        else wrap.append(renderGrid());
        return wrap;
    }

    function renderHome() {
        const card = (type, title, desc, badge) => h('button', {
            class: 'group flex flex-col items-start gap-3 rounded-2xl border border-slate-200 bg-white p-6 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-md',
            onclick: () => chooseType(type),
        },
            h('div', { class: 'flex w-full items-center justify-between' },
                h('div', { class: 'rounded-xl bg-indigo-50 p-3 text-indigo-600 group-hover:bg-indigo-100' }, icon(type === 'json' ? 'braces' : 'php')),
                h('span', { class: 'rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-500' }, badge),
            ),
            h('h2', { class: 'text-lg font-semibold text-slate-900' }, title),
            h('p', { class: 'text-sm text-slate-500' }, desc),
        );

        return h('div', {},
            h('div', { class: 'mb-6' },
                h('h1', { class: 'text-2xl font-bold text-slate-900' }, t('title', 'Translations')),
                h('p', { class: 'mt-1 text-sm text-slate-500' }, 'Choose what you want to edit.'),
            ),
            h('div', { class: 'grid gap-5 sm:grid-cols-2' },
                card('json', t('modes.json', 'JSON files'), 'Flat key/value strings in lang/{locale}.json.', `${(state.catalog.json.locales || []).length} locales`),
                card('php', t('modes.php', 'PHP array files'), 'Grouped, nestable keys in lang/{locale}/*.php.', `${(state.catalog.php.groups || []).length} groups`),
            ),
        );
    }

    function renderGroups() {
        const groups = state.catalog.php.groups || [];
        if (groups.length === 0) {
            return emptyState('No PHP groups found', 'There are no PHP array files under your lang path yet.');
        }
        return h('div', {},
            h('h1', { class: 'mb-4 text-xl font-semibold text-slate-900' }, 'Pick a file'),
            h('ul', { class: 'divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 bg-white' },
                ...groups.map((g) => h('li', {},
                    h('button', {
                        class: 'flex w-full items-center justify-between px-5 py-3.5 text-left hover:bg-slate-50',
                        onclick: () => chooseGroup(g),
                    },
                        h('span', { class: 'font-mono text-sm text-slate-700' }, `${g}.php`),
                        h('span', { class: 'text-slate-300' }, icon('chevron')),
                    ),
                )),
            ),
        );
    }

    function renderGrid() {
        if (!state.grid) return emptyState('Loading…', '');

        const locales = currentLocales();
        if (locales.length === 0) {
            return h('div', {}, localeBar(), emptyState('No locale selected', 'Pick a target locale, or add one.'));
        }

        const visibleKeys = state.grid.keys
            .filter((k) => !state.deleted.has(k))
            .filter((k) => !state.search || k.toLowerCase().includes(state.search.toLowerCase()))
            .filter((k) => !state.missingOnly || !(state.grid.rows[k] && state.grid.rows[k][state.target]));

        const headerRow = h('tr', {},
            h('th', { class: 'sticky left-0 z-10 bg-slate-50 px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500' }, t('columns.key', 'Key')),
            ...locales.map((loc) => h('th', {
                class: `px-3 py-2.5 text-left text-xs font-semibold ${loc === state.target ? 'bg-indigo-50 text-indigo-700' : 'text-slate-500'}`,
            },
                h('span', { class: 'inline-flex items-center gap-1.5' },
                    loc,
                    loc === state.target ? h('span', { class: 'rounded bg-indigo-600 px-1.5 py-0.5 text-[10px] font-bold uppercase text-white' }, 'target') : null,
                ),
            )),
            h('th', { class: 'w-px bg-slate-50' }),
        );

        const rows = visibleKeys.length
            ? visibleKeys.map((key) => gridRow(key, locales))
            : [h('tr', {}, h('td', { colspan: locales.length + 2, class: 'px-4 py-10 text-center text-sm text-slate-400' }, 'No matching keys.'))];

        return h('div', {},
            localeBar(),
            h('div', { class: 'overflow-auto rounded-xl border border-slate-200 bg-white shadow-sm' },
                h('table', { class: 'min-w-full border-collapse text-sm' },
                    h('thead', { class: 'sticky top-0 z-10' }, headerRow),
                    h('tbody', { class: 'divide-y divide-slate-100' }, ...rows),
                ),
            ),
            h('p', { class: 'mt-3 text-xs text-slate-400' }, `${visibleKeys.length} of ${state.grid.keys.length} keys`),
        );
    }

    function gridRow(key, locales) {
        return h('tr', { class: 'group/row hover:bg-slate-50/60' },
            h('td', { class: 'sticky left-0 z-[5] bg-white px-4 py-1.5 align-top group-hover/row:bg-slate-50' },
                h('span', { class: 'block break-all font-mono text-xs leading-6 text-slate-600' }, key),
            ),
            ...locales.map((loc) => {
                const value = (state.grid.rows[key] && state.grid.rows[key][loc]) || '';
                const isTarget = loc === state.target;
                const missing = isTarget && value === '';
                const input = h('textarea', {
                    rows: 1,
                    class: `w-full resize-y rounded-md border bg-white px-2.5 py-1.5 text-sm leading-6 outline-none transition focus:ring-2 ${
                        missing
                            ? 'border-amber-300 bg-amber-50 focus:border-amber-400 focus:ring-amber-200'
                            : 'border-slate-200 focus:border-indigo-400 focus:ring-indigo-200'
                    } ${isTarget ? 'font-medium' : 'text-slate-500'}`,
                    dataset: { cell: '1', key, locale: loc },
                    value,
                    oninput: (e) => autoGrow(e.target),
                });
                queueMicrotask(() => autoGrow(input));
                return h('td', { class: `px-2 py-1.5 align-top ${isTarget ? 'bg-indigo-50/40' : ''}` }, input);
            }),
            h('td', { class: 'whitespace-nowrap px-2 py-1.5 align-top' },
                h('div', { class: 'flex items-center gap-1 opacity-0 transition group-hover/row:opacity-100' },
                    state.refs.length ? rowButton('copy', 'Copy a reference value into the target', () => copyReference(key)) : null,
                    rowButton('rename', 'Rename key', () => renameKey(key)),
                    rowButton('trash', 'Delete key', () => deleteKey(key)),
                ),
            ),
        );
    }

    function copyReference(key) {
        const fromLocale = state.refs.find((l) => (state.grid.rows[key] && state.grid.rows[key][l]));
        if (!fromLocale) return;
        const cell = root.querySelector(`[data-cell][data-key="${CSS.escape(key)}"][data-locale="${CSS.escape(state.target)}"]`);
        if (cell) {
            cell.value = state.grid.rows[key][fromLocale];
            autoGrow(cell);
            cell.focus();
        }
    }

    function localeBar() {
        const all = state.catalog.locales || [];
        const options = (all.length ? all : [state.target].filter(Boolean));
        return h('div', { class: 'mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm' },
            h('label', { class: 'flex items-center gap-2 text-sm' },
                h('span', { class: 'font-medium text-slate-600' }, t('columns.target', 'Target')),
                h('select', {
                    class: 'rounded-md border border-slate-200 px-2.5 py-1.5 text-sm font-medium focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-200',
                    onchange: (e) => setTarget(e.target.value),
                }, ...options.map((l) => h('option', { value: l, ...(l === state.target ? { selected: true } : {}) }, localeLabel(l)))),
            ),
            h('div', { class: 'flex items-center gap-2' },
                h('span', { class: 'text-sm font-medium text-slate-600' }, t('columns.reference', 'Reference')),
                h('div', { class: 'flex flex-wrap gap-1.5' },
                    ...all.filter((l) => l !== state.target).map((l) => h('button', {
                        class: `rounded-full px-2.5 py-1 text-xs font-medium transition ${
                            state.refs.includes(l) ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                        }`,
                        onclick: () => toggleRef(l),
                    }, l)),
                ),
            ),
            h('div', { class: 'ml-auto flex items-center gap-2' },
                h('div', { class: 'relative' },
                    h('input', {
                        type: 'search',
                        placeholder: t('filters.search', 'Search keys…'),
                        value: state.search,
                        class: 'w-48 rounded-md border border-slate-200 py-1.5 pl-8 pr-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-200',
                        oninput: (e) => { state.search = e.target.value; refreshRows(); },
                    }),
                    h('span', { class: 'pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400' }, icon('search')),
                ),
                h('label', { class: 'flex cursor-pointer items-center gap-1.5 text-sm text-slate-600' },
                    h('input', {
                        type: 'checkbox',
                        class: 'rounded border-slate-300 text-indigo-600 focus:ring-indigo-200',
                        ...(state.missingOnly ? { checked: true } : {}),
                        onchange: (e) => { state.missingOnly = e.target.checked; refreshRows(); },
                    }),
                    t('filters.missing_only', 'Missing only'),
                ),
                h('button', { class: 'rounded-md border border-slate-200 px-2.5 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-50', onclick: addKey },
                    '+ ' + t('actions.add_key', 'Add key')),
                h('button', { class: 'rounded-md border border-slate-200 px-2.5 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-50', onclick: addLocale },
                    '+ ' + t('actions.add_locale', 'Add locale')),
            ),
        );
    }

    // Re-render only the <main> region when filters change, so the locale bar
    // controls keep focus while typing in the search box.
    function refreshRows() {
        const current = root.querySelector('main');
        if (current) current.replaceChildren(renderGrid());
    }

    function rowButton(name, title, onclick) {
        return h('button', { class: 'rounded p-1.5 text-slate-400 hover:bg-slate-200 hover:text-slate-700', title, onclick }, icon(name));
    }

    function emptyState(title, desc) {
        return h('div', { class: 'rounded-xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center' },
            h('p', { class: 'text-sm font-semibold text-slate-700' }, title),
            desc ? h('p', { class: 'mt-1 text-sm text-slate-400' }, desc) : null,
        );
    }

    function autoGrow(el) {
        el.style.height = 'auto';
        el.style.height = `${Math.min(el.scrollHeight, 240)}px`;
    }

    // Icons are a fixed, developer-controlled set built from static SVG strings
    // via DOMParser (no innerHTML, no user input).
    function icon(name) {
        const paths = {
            home: '<path d="M3 9.5 12 3l9 6.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1V9.5Z"/>',
            save: '<path d="M5 3h11l3 3v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path d="M8 3v5h7V3M8 21v-6h8v6"/>',
            braces: '<path d="M8 3c-2 0-2 2-2 4s0 3-2 3c2 0 2 2 2 4s0 3 2 3M16 3c2 0 2 2 2 4s0 3 2 3c-2 0-2 2-2 4s0 3-2 3"/>',
            php: '<path d="M3 12h18M3 7h18M3 17h18"/>',
            chevron: '<path d="m9 6 6 6-6 6"/>',
            search: '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
            copy: '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/>',
            rename: '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
            trash: '<path d="M4 7h16M9 7V4h6v3m-8 0 1 13h8l1-13"/>',
        };
        const markup = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">${paths[name] || ''}</svg>`;
        const parsed = new DOMParser().parseFromString(markup, 'image/svg+xml').documentElement;
        const span = document.createElement('span');
        span.className = 'inline-flex';
        span.append(parsed);
        return span;
    }

    render();
})();

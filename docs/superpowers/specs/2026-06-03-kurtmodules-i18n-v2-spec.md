# `ozankurt/laravel-modules-i18n` v2.0 — Spec

**Repo:** `KurtModules-i18n`
**Date:** 2026-06-03
**Status:** Approved
**Family:** KurtModules (depends on Core only)

---

## 1. Purpose

A translation-file manager for Laravel with its **own web UI** (Tailwind 4 + vanilla JS, prebuilt and
shipped — no consumer build step). It reads and writes the consuming app's language files on disk:

- **JSON mode** — flat `lang/{locale}.json` files (keys are opaque source strings).
- **PHP array mode** — `lang/{locale}/**.php` group files with arbitrarily **nested** keys
  (`'foo' => ['bar' => 'baz']`), addressed in the UI as dot-paths (`foo.bar`).

Unlike the rest of the family this module is **not headless** and ships **no Filament, no database,
no models, no migrations**. Its data store is the filesystem.

## 2. Identity

- Package `ozankurt/laravel-modules-i18n`; namespace `Kurt\Modules\I18n\` → `src/`.
- Provider `Kurt\Modules\I18n\Providers\I18nServiceProvider` extends Core's `PackageServiceProvider`.
- `module(): 'i18n'`; config `config/i18n.php` (`config('i18n.*')`).
- Requires `ozankurt/laravel-modules-core: ^2.0`, `spatie/laravel-package-tools`, `illuminate/{contracts,filesystem,support}`.

## 3. Architecture

Three layers; the file domain is pure and fully unit-tested, the HTTP layer is thin, the UI is static
prebuilt assets calling the JSON API.

```
Blade shell -> vanilla JS SPA (Tailwind 4) -> fetch() JSON API
                                                   |
UiController        Authorize middleware      Api\* controllers + FormRequests
                                                   |
                    ---------- TranslationManager ----------
   LangPaths · LocaleScanner · JsonTranslationFile · PhpArrayFile · ArrayExporter
```

## 4. File domain (`src/`)

| Class | Responsibility | Key signatures |
|---|---|---|
| `Enums/FileType` | `enum FileType: string { case Json='json'; case Php='php'; }` | — |
| `Support/LangPaths` | Resolve root (`config('i18n.paths.root')` ?? `lang_path()`); build + validate every path is inside root. | `root(): string`, `jsonPath(string $locale): string`, `phpPath(string $group, string $locale): string`, `assertSafe(string $abs): void`, `relative(string $abs): string` |
| `Support/ArrayExporter` | Safe PHP-array pretty-printer: short arrays, 4-space indent, single-quoted escaped string literals, recursive. | `export(array $data): string` |
| `Support/PhpArrayFile` | Read via isolated `require` (assert array). Write: export → temp → self-verify (`require` temp == data) → atomic rename. | `read(): array`, `write(array $data): void`, `exists(): bool`, `hash(): ?string` |
| `Support/JsonTranslationFile` | Read/write `{locale}.json` flat, `JSON_PRETTY_PRINT|UNESCAPED_UNICODE|UNESCAPED_SLASHES`, atomic. | `read(): array`, `write(array $data): void`, `exists(): bool`, `hash(): ?string` |
| `Support/LocaleScanner` | Discover locales, JSON locales, PHP groups (nested dirs → `admin/users`). | `scan(): TranslationCatalog` |
| `Support/TranslationCatalog` | readonly value object: `locales[]`, `jsonLocales[]`, `phpGroups[]`. | — |
| `Support/EditOperation` | readonly value object for one op (`set`/`delete`/`rename`). | — |
| `Support/TranslationManager` | Build grids; apply edit-op batches per affected file; hash-based conflict detection; add locale. | see §5–6 |

**Nesting rule:** PHP groups are real nested arrays mapped to dot-paths via `Arr::get/set/forget`.
JSON keys are opaque flat strings — never dot-split.

**Safety invariants:** locale `^[A-Za-z0-9_-]+$`; group segments safe-charset, no `..`/leading-slash,
`.php` enforced; every absolute path validated under root (normalized realpath prefix); PHP emitted as
escaped string literals only (never code, never `eval`); atomic write + read-back verify; optional
timestamped backup before overwrite.

## 5. HTTP / API (`src/Http/`)

Routes in `routes/i18n.php`, registered with `config('i18n.route.prefix')` +
`config('i18n.route.middleware')` + the gate middleware.

| Route | Controller | Purpose |
|---|---|---|
| `GET  {prefix}` | `UiController@index` | Blade shell + bootstrap JSON. |
| `GET  {prefix}/api/catalog` | `Api\CatalogController` | `{ locales, json:{locales}, php:{groups} }`. |
| `GET  {prefix}/api/json?locales=…` | `Api\JsonTranslationController@show` | Grid `{ keys, rows:{key:{loc:val}}, hashes:{loc:sha1} }`. |
| `PATCH {prefix}/api/json` | `Api\JsonTranslationController@update` | Apply ops; 409 on hash mismatch. |
| `GET  {prefix}/api/php/{group}?locales=…` | `Api\PhpGroupController@show` | Same grid; keys are dot-paths. |
| `PATCH {prefix}/api/php/{group}` | `Api\PhpGroupController@update` | Apply ops; 409 on mismatch. |
| `POST {prefix}/api/locales` | `Api\LocaleController@store` | Create empty `{locale}.json` or `{locale}/{group}.php`. |

- `Http/Middleware/Authorize` — allow if `app()->environment(config('i18n.enabled_environments'))`
  **or** `Gate::check('viewI18n')`; else `abort(403)`.
- `Http/Requests/{ApplyEditsRequest, AddLocaleRequest}` — validate ops vocabulary + locale/group/key
  charset; reject traversal at the boundary.
- `Exceptions/{TranslationPathException, InvalidTranslationFileException}`.

## 6. Edit-op batch + conflict model

`GET` returns per-file `hashes`. Client sends `{ baseHashes:{loc:sha1}, ops:[…] }`:

- `{op:'set', locale, key, value}` — set one cell.
- `{op:'delete', key}` — remove key in **all** locales of the file/group.
- `{op:'rename', from, to}` — rename key in all locales.

For each affected file the server recomputes the current hash; any mismatch with `baseHashes` → **409**
(no partial write). Otherwise apply ops in-memory per locale, write only changed files atomically
(+ backup), return fresh hashes. Add-key = `set` on a new key; per-locale clear = `set` with `""`.

## 7. UI / assets (`resources/`)

- `views/app.blade.php` — shell; loads `asset('vendor/i18n/app.css')` + `app.js`; injects bootstrap
  `<script type="application/json" id="i18n-bootstrap">`.
- `js/app.js` — vanilla-JS SPA: type chooser → (PHP) group list → grid (locale picker target+refs,
  inline edit, missing highlight, search, missing-only filter, add/delete/rename key, add locale,
  copy reference→target, Save with 409 handling).
- `css/app.css` — `@import "tailwindcss";`.
- `dist/app.css`, `dist/app.js` — **committed** built output; published source.
- `package.json` — `@tailwindcss/cli` v4; `build` compiles CSS → `dist/app.css` and copies JS → `dist/app.js`.

Provider publishes `resources/dist` → `public/vendor/i18n` (tag `i18n-assets`); views under `i18n::`.

## 8. Config (`config/i18n.php`)

`enabled_environments` (`['local']`), `route.{prefix,middleware}`, `paths.root` (null → `lang_path()`),
`backups.{enabled,path}`, `locales` (null → auto-detect). Gate name: `viewI18n`.

## 9. Provider wiring

`configurePackage`: `->name('laravel-modules-i18n')->hasConfigFile('i18n')->hasTranslations()`.
`packageBooted`: `loadViewsFrom(…, 'i18n')`, publish assets (`i18n-assets`), register routes
(prefix + `['web', Authorize::class]`), register default-deny `viewI18n` gate.

## 10. Testing (Pest 3, extend Core `PackageTestCase`)

ArrayExporter (escaping `'` `\` newline unicode `:name` `a|b`, nesting, idempotent re-export);
Php/Json file round-trips + self-verify abort + stable hash; LangPaths traversal rejection;
LocaleScanner discovery on fixtures; API catalog/JSON/PHP GET+PATCH (set/add/delete/rename, nested
dot-keys), 409 conflict, add-locale, traversal/charset 422/403; access gate/env enforcement + CSRF;
UI shell 200 + backup written. Target **≥ 80% PHP lines**.

## 11. CI

PHP job: PHP 8.4 × Laravel 12 (testbench 10), pint `--test`, phpstan lvl 8, `pest --coverage --min=80`.
Assets job: `npm ci && npm run build` then `git diff --exit-code -- resources/dist`.

## 12. Directory layout

```
src/
  Enums/FileType.php
  Exceptions/{TranslationPathException,InvalidTranslationFileException}.php
  Http/
    Controllers/{UiController.php, Api/{CatalogController,JsonTranslationController,PhpGroupController,LocaleController}.php}
    Middleware/Authorize.php
    Requests/{ApplyEditsRequest,AddLocaleRequest}.php
  Providers/I18nServiceProvider.php
  Support/{LangPaths,ArrayExporter,PhpArrayFile,JsonTranslationFile,LocaleScanner,TranslationCatalog,EditOperation,TranslationManager}.php
config/i18n.php
routes/i18n.php
resources/{views/app.blade.php, css/app.css, js/app.js, dist/{app.css,app.js}}
lang/{en,tr}/i18n.php
package.json
tests/{Unit,Feature}/…
```

## 13. Definition of done

- [ ] CI green (PHP 8.4 × Laravel 12); pint, phpstan lvl 8, pest ≥ 80%; `dist` in sync.
- [ ] JSON + nested PHP round-trip losslessly (values) with self-verify, atomic writes, backups.
- [ ] API enforces gate/env, CSRF, path allowlist, charset, 409 conflict.
- [ ] UI: type-first nav → group pick → grid (target/reference locales, edit, search, missing filter, add/delete/rename, add locale, copy ref→target, save).
- [ ] Tagged `v2.0.0`.
```

# laravel-modules-i18n Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: use superpowers:subagent-driven-development or
> superpowers:executing-plans to implement task-by-task. Steps use checkbox (`- [ ]`) syntax.
> Full design lives in [the v2 spec](../specs/2026-06-03-kurtmodules-i18n-v2-spec.md) — read it first.

**Goal:** A Laravel package with its own Tailwind-4 + vanilla-JS UI for editing JSON and nested-PHP
translation files on disk, safely and gated.

**Architecture:** Pure file domain (fully unit-tested) ← thin HTTP/JSON API ← prebuilt static UI.

**Tech Stack:** PHP 8.4, Laravel 12, spatie/laravel-package-tools, Pest 3 + Testbench, Tailwind 4 CLI.

**Local toolchain (Windows/Laragon):** run PHP/composer via
`C:/laragon/bin/php/php-8.4.5-nts-Win32-vs17-x64/php.exe` and
`C:/laragon/bin/composer/composer.phar`. Run tests: `php84 vendor/bin/pest`.

---

## Phase 0 — Scaffold ✅

- [x] Repo tree, `composer.json` (Core via VCS repo), tooling configs, `config/i18n.php`, provider
      skeleton, `tests/` base + sanity test, `lang/{en,tr}/i18n.php`, CI, README/CHANGELOG/etc.
- [x] `composer update` resolves Core `v2.0.0`; sanity test green; initial commit.

## Phase 1 — File domain (TDD)

Build in dependency order; each class: failing test → minimal impl → green → commit.

- [ ] **`Enums/FileType`** — `Json`/`Php` cases.
- [ ] **`Support/ArrayExporter`** — `export(array): string`.
  - Tests: nested arrays; escaping single-quote, backslash, newline; unicode kept literal; `:name`
    placeholders and `a|b` plurals untouched; **idempotent** (`require` of output `==` input).
- [ ] **`Support/LangPaths`** — root resolution + `assertSafe`/`jsonPath`/`phpPath`/`relative`.
  - Tests: rejects `..`, absolute paths, outside-root, bad locale/group charset; builds correct paths.
- [ ] **`Support/PhpArrayFile`** — read/write/exists/hash with temp + self-verify + atomic rename + backup.
  - Tests: read→edit→write round-trip equals intended nested array; reading a non-array throws
    `InvalidTranslationFileException`; hash stable; backup written when enabled.
- [ ] **`Support/JsonTranslationFile`** — read/write/exists/hash, pretty + unescaped unicode/slashes.
  - Tests: round-trip preserves keys with `.`/`:`; empty file handling; hash stable.
- [ ] **`Support/TranslationCatalog`** + **`Support/LocaleScanner`** — discovery.
  - Tests (fixture `lang/`): finds JSON locales, PHP groups incl. nested `admin/users`, union of locales.
- [ ] **`Support/EditOperation`** + **`Support/TranslationManager`** — grids, apply ops, conflict, add-locale.
  - Tests: build JSON grid + PHP grid (nested → dot-paths); apply set/delete/rename across locales;
    stale `baseHashes` → conflict signal; add locale creates empty file.

## Phase 2 — HTTP layer (TDD)

- [ ] **`Http/Middleware/Authorize`** — env allow OR `Gate::check('viewI18n')` else 403.
  - Tests: 403 in non-enabled env without gate; 200 when env enabled; 200 when gate passes.
- [ ] **`routes/i18n.php`** + provider `registerRoutes()` + `registerGate()` (default deny).
- [ ] **`Http/Requests/{ApplyEditsRequest,AddLocaleRequest}`** — validate ops + charset.
- [ ] **Controllers** `Api\{Catalog,JsonTranslation,PhpGroup,Locale}` + `UiController`.
  - Tests: catalog shape; JSON & PHP GET grids; PATCH set/add/delete/rename incl. nested dot-keys;
    409 on stale hash; add-locale; traversal/charset → 422/403; CSRF on PATCH/POST; UI shell 200.

## Phase 3 — UI assets

- [ ] `resources/views/app.blade.php` shell + bootstrap JSON.
- [ ] `package.json` (`@tailwindcss/cli`), `resources/css/app.css`, `npm run build` → `resources/dist`.
- [ ] `resources/js/app.js` SPA: type chooser → group list → grid (target+reference locales, inline
      edit, missing highlight, search, missing-only, add/delete/rename key, add locale, copy ref→target,
      Save + 409). Copy JS to `dist/`.
- [ ] Provider `packageBooted()` publishes `resources/dist` → `public/vendor/i18n`; `loadViewsFrom(... 'i18n')`.
- [ ] Add the CI `assets` job (build + `git diff --exit-code resources/dist`).
- [ ] Manual smoke in a local app (see spec §13 / plan-file verification).

## Phase 4 — Polish & release

- [ ] Backups wiring + config; finalize `lang/{en,tr}/i18n.php`; README usage + security.
- [ ] Self-review vs spec; full green: pint, phpstan, pest ≥ 80%, dist in sync.
- [ ] Tag `v2.0.0`, push, open PR.

## Verification

```bash
php84 vendor/bin/pest                 # all green
php84 vendor/bin/pest --coverage --min=80
php84 vendor/bin/pint --test
php84 vendor/bin/phpstan analyse --memory-limit=2G
npm run build && git diff --exit-code -- resources/dist
```

Manual: in a Laravel app, `vendor:publish` config+assets, define `viewI18n`, visit `/i18n`; edit a
JSON key and a nested PHP key; confirm correct on-disk output, backup created, traversal rejected, 403
in non-local without gate.

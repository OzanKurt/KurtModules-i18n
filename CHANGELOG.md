# Changelog

All notable changes follow [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and [SemVer](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.1.0] - 2026-06-03

### Changed

- Redesigned the translation UI ("i18n Studio"): a custom dark "command-deck" theme (glassmorphism, neon accents) replacing the Tailwind-utility interface.
- The grid gains a live translation-progress bar, a "next missing" jump, per-reference copy-to-target, keyboard shortcuts (`/` to search, `Ctrl`/`⌘`+`S` to save), and full keyboard/ARIA accessibility.

### Added

- Standalone, fully interactive UI reference at `template/index.html`.

### Upgrade

- Re-publish the UI assets to pick up the new look:
  `php artisan vendor:publish --tag="i18n-assets" --force`

## [2.0.0] - 2026-06-03

### Added

- Initial release.
- Web UI (Tailwind 4 + vanilla JS, zero consumer build step) for managing Laravel translation files.
- JSON mode for flat `lang/{locale}.json` files.
- PHP array mode for `lang/{locale}/**.php` group files, including deeply nested keys via dot-paths.
- Locale-comparison grid with a choosable target locale and toggleable reference locales.
- Safe, atomic file writes with read-back self-verification and optional timestamped backups.
- `viewI18n` authorization gate + environment restriction (non-production by default).
- Path-allowlisted file access (traversal rejected) and escaped-string-literal-only PHP emission.
- Optimistic concurrency control (per-file hash, HTTP 409 on conflict).

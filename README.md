# laravel-modules-i18n

A self-hosted **translation-file manager** for Laravel. It ships its own UI (Tailwind 4 + vanilla
JS — no build step required in your app) for editing both **JSON** and **PHP array** language files
on disk, including deeply nested PHP keys like `'foo' => ['bar' => 'baz']`.

Part of the [KurtModules](https://github.com/ozankurt) family. Requires
[`ozankurt/laravel-modules-core`](https://github.com/OzanKurt/KurtModules-Core).

## Features

- **Two file types, one workspace.** Flat `lang/{locale}.json` files and nested `lang/{locale}/**.php`
  group files. You pick the type first, then (for PHP) the file/group, then edit.
- **Locale-comparison grid.** Choose a target locale to fill in and toggle any number of reference
  locales for context — translate `users.title.icon_tooltip` into French while reading the EN and TR
  values side by side.
- **Safe writes.** Atomic writes with read-back self-verification, optional timestamped backups, and
  PHP files emitted as escaped string literals only (your input is never written as code).
- **Locked down by default.** Gated by the `viewI18n` authorization gate and restricted to
  non-production environments unless you opt in. Only files inside the configured lang root are ever
  touched.

## Requirements

- PHP 8.3+
- Laravel 12.x (13.x once the test toolchain supports it)

## Installation

```bash
composer require ozankurt/laravel-modules-i18n
```

Publish the config and the prebuilt UI assets:

```bash
php artisan vendor:publish --tag="i18n-config"
php artisan vendor:publish --tag="i18n-assets"
```

## Access control

The UI writes files on your server, so it is restricted by default:

- In any environment listed in `config('i18n.enabled_environments')` (default `['local']`) access is
  granted automatically.
- In every other environment a request must pass the `viewI18n` gate. Define it (typically in
  `app/Providers/AppServiceProvider.php`) to enable the UI, e.g. in production:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('viewI18n', function ($user) {
    return in_array($user->email, ['you@example.com'], true);
});
```

## Usage

Visit `/i18n` (configurable via `config('i18n.route.prefix')`). Pick **JSON** or **PHP array**; for
PHP choose a group (file); then edit in the grid:

- select a **target** locale and any **reference** locales,
- filter to **missing only**, search keys, copy a reference value into the target,
- add / rename / delete keys, add a new locale,
- **Save** writes only the files that changed.

By default the manager reads and writes the application's `lang_path()`. Point it elsewhere with
`config('i18n.paths.root')`.

## Configuration

See [`config/i18n.php`](config/i18n.php) — environment allowlist, route prefix/middleware, lang root,
backups, and an optional explicit locale → label map.

## Security

This is a filesystem-writing admin tool. Review [SECURITY.md](SECURITY.md) before exposing it. Never
serve it publicly without authentication and a restrictive `viewI18n` gate.

## Development

The UI assets are prebuilt and committed under `resources/dist/`. To rebuild after changing
`resources/css` or `resources/js`:

```bash
npm install
npm run build
```

```bash
composer test     # pest
composer lint     # pint --test
composer stan     # phpstan
```

## License

MIT © Ozan Kurt

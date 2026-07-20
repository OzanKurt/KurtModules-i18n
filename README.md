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

## JSON API

The UI is a thin client over a small JSON API mounted under the same prefix (default `i18n`). Every
route is behind the same access guard as the UI (`web` middleware + the environment/`viewI18n` gate).
All request and response bodies are JSON.

| Method & path              | Purpose                                        |
| -------------------------- | ---------------------------------------------- |
| `GET /i18n/api/catalog`    | List locales, JSON files, PHP groups, vendor packages. |
| `GET /i18n/api/json`       | Read the JSON translation grid.                |
| `PATCH /i18n/api/json`     | Apply a batch of edits to JSON files.          |
| `GET /i18n/api/php/{group}`| Read a PHP group grid (`{group}` may be nested, e.g. `admin/users`, or namespaced, e.g. `firewall::notifications`). |
| `PATCH /i18n/api/php/{group}` | Apply a batch of edits to a PHP group.       |
| `POST /i18n/api/locales`   | Create a new empty locale file.                |

### Reading a grid

`GET /i18n/api/json?locales=en,tr` (omit `locales` to load every known locale) returns:

```json
{
  "keys": ["greeting"],
  "rows": { "greeting": { "en": "Hi", "tr": "Selam" } },
  "hashes": { "en": "b1c9…", "tr": "4af0…" }
}
```

`hashes` is a `locale => SHA-1` map of each file's current on-disk contents (`null` when the file
does not exist yet). You send these back unchanged as the `baseHashes` of a later edit; they are how
the server detects that a file changed under you.

### Applying edits

`PATCH /i18n/api/json` (or `/i18n/api/php/{group}`) takes the loaded `baseHashes` plus an ordered
list of `ops`:

```json
{
  "baseHashes": { "en": "b1c9…", "tr": "4af0…" },
  "ops": [
    { "op": "set", "locale": "en", "key": "greeting", "value": "Hello" },
    { "op": "rename", "from": "greeting", "to": "welcome" },
    { "op": "delete", "key": "obsolete" }
  ]
}
```

- `set` writes one cell (`locale` + `key` + `value`); the locale must be one of the loaded `baseHashes`.
- `rename` moves `from` to `to` in every loaded locale (a no-op for a non-leaf PHP key, so a subtree is never collapsed).
- `delete` removes `key` from every loaded locale.

On success (`200 OK`) the response mirrors a fresh read of the affected files:

```json
{ "changed": ["en"], "hashes": { "en": "77de…", "tr": "4af0…" } }
```

`changed` lists only the locales whose files were actually rewritten (a batch that resolves to the
current contents changes nothing and writes nothing). Use the returned `hashes` as the `baseHashes`
for your next edit.

### Conflicts (`409`)

When any file's current hash no longer matches the `baseHashes` you sent, the whole batch is rejected
and nothing is written:

```json
{ "message": "conflict", "locales": ["en"] }
```

`locales` names the files that changed underneath you. Re-read the grid (to get fresh values and
hashes), reapply your edits, and retry. Invalid input (bad locale/group, unknown op, out-of-root
path) returns `422` with `{ "message": "…" }` instead.

### Concurrency semantics

Saves are serialized per group with an exclusive file lock, and the optimistic hash check runs
**inside** that lock immediately before writing. Two concurrent `PATCH`es that started from the same
`baseHashes` can therefore never both succeed: the first wins, and the second sees the file it just
changed and gets a `409` — there is no silent last-writer-wins. A multi-locale batch is atomic: all
locales are staged and verified first, then swapped in together, and any failure rolls the batch back
so the files are never left half-applied.

### Extending: the `TranslationsChanged` event

After a batch actually changes at least one file, the manager dispatches
`Kurt\Modules\I18n\Events\TranslationsChanged` with the file `type`, the `group` (or `null` for
JSON), the `changedLocales`, the applied `ops`, and the `actor` (the authenticated user, when
resolvable). Listen for it to keep an audit log, fire a webhook, bust a translation cache, or trigger
a redeploy. It does not fire for a no-op batch.

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

<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled environments
    |--------------------------------------------------------------------------
    |
    | The UI writes translation files on disk, so it is restricted by default.
    | In any of these environments access is granted automatically. In every
    | other environment (e.g. "production") a request must additionally pass
    | the "viewI18n" gate — define it in your app to enable the UI there.
    |
    */

    'enabled_environments' => ['local'],

    /*
    |--------------------------------------------------------------------------
    | Route
    |--------------------------------------------------------------------------
    |
    | The UI and its JSON API are mounted under this prefix with the given
    | middleware. The package always appends its own gate/environment guard.
    |
    */

    'route' => [
        'prefix' => 'i18n',
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Paths
    |--------------------------------------------------------------------------
    |
    | The root translation directory the manager reads from and writes to.
    | When null it resolves to the application's lang_path(). Only files that
    | live inside this root are ever touched (path-traversal is rejected).
    |
    */

    'paths' => [
        'root' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Backups
    |--------------------------------------------------------------------------
    |
    | When enabled, a timestamped copy of a translation file is written before
    | it is overwritten. When the path is null it resolves to
    | storage_path('i18n-backups').
    |
    */

    'backups' => [
        'enabled' => true,
        'path' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    |
    | When null, locales are auto-detected from the translation files on disk.
    | Optionally provide an explicit map of locale code => display label to
    | control ordering and naming, e.g. ['en' => 'English', 'tr' => 'Türkçe'].
    |
    */

    'locales' => null,

];

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Kurt\Modules\I18n\Support\TranslationManager;

beforeEach(function (): void {
    $this->root = i18n_tmp_dir();
    $this->app->instance(TranslationManager::class, i18n_manager($this->root));
    Gate::define('viewI18n', fn ($user = null): bool => true);
});

afterEach(function (): void {
    i18n_rrmdir($this->root);
});

it('returns the catalog of locales, json files and php groups', function (): void {
    file_put_contents($this->root.'/en.json', '{}');
    mkdir($this->root.'/en', 0777, true);
    file_put_contents($this->root.'/en/users.php', '<?php return [];');

    $this->getJson('/i18n/api/catalog')
        ->assertOk()
        ->assertJsonPath('json.locales.0', 'en')
        ->assertJsonPath('php.groups.0', 'users')
        ->assertJsonPath('locales.0', 'en');
});

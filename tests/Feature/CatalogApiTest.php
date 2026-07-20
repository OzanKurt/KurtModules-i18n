<?php

declare(strict_types=1);

use Kurt\Modules\I18n\Support\TranslationManager;

beforeEach(function (): void {
    $this->root = i18n_tmp_dir();
    $this->app->instance(TranslationManager::class, i18n_manager($this->root));
    config()->set('i18n.enabled_environments', ['testing']);
    $this->actingAs(i18n_actor());
});

afterEach(function (): void {
    i18n_rrmdir($this->root);
});

it('returns the catalog of locales, json files and php groups', function (): void {
    file_put_contents($this->root.'/en.json', '{}');
    mkdir($this->root.'/en', 0777, true);
    file_put_contents($this->root.'/en/users.php', '<?php return [];');

    $this->getJson('/api/i18n/catalog')
        ->assertOk()
        ->assertJsonPath('data.json.locales.0', 'en')
        ->assertJsonPath('data.php.groups.0', 'users')
        ->assertJsonPath('data.locales.0', 'en');
});

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

it('creates a new json locale file', function (): void {
    $this->postJson('/i18n/api/locales', ['type' => 'json', 'locale' => 'fr'])
        ->assertCreated()
        ->assertJsonPath('locale', 'fr');

    expect(is_file($this->root.'/fr.json'))->toBeTrue();
});

it('creates a new php group locale file', function (): void {
    $this->postJson('/i18n/api/locales', ['type' => 'php', 'locale' => 'fr', 'group' => 'users'])
        ->assertCreated();

    expect(is_file($this->root.'/fr/users.php'))->toBeTrue();
});

it('requires a group when creating a php locale', function (): void {
    $this->postJson('/i18n/api/locales', ['type' => 'php', 'locale' => 'fr'])->assertStatus(422);
});

it('rejects an invalid locale', function (): void {
    $this->postJson('/i18n/api/locales', ['type' => 'json', 'locale' => 'b@d'])->assertStatus(422);
});

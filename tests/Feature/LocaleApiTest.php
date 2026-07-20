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

it('lists the known locales', function (): void {
    file_put_contents($this->root.'/en.json', '{}');
    file_put_contents($this->root.'/tr.json', '{}');

    $this->getJson('/api/i18n/locales')
        ->assertOk()
        ->assertJsonPath('data', ['en', 'tr']);
});

it('creates a new json locale file', function (): void {
    $this->postJson('/api/i18n/locales', ['type' => 'json', 'locale' => 'fr'])
        ->assertCreated()
        ->assertJsonPath('data.locale', 'fr');

    expect(is_file($this->root.'/fr.json'))->toBeTrue();
});

it('creates a new php group locale file', function (): void {
    $this->postJson('/api/i18n/locales', ['type' => 'php', 'locale' => 'fr', 'group' => 'users'])
        ->assertCreated();

    expect(is_file($this->root.'/fr/users.php'))->toBeTrue();
});

it('requires a group when creating a php locale', function (): void {
    $this->postJson('/api/i18n/locales', ['type' => 'php', 'locale' => 'fr'])->assertStatus(422);
});

it('rejects an invalid locale', function (): void {
    $this->postJson('/api/i18n/locales', ['type' => 'json', 'locale' => 'b@d'])->assertStatus(422);
});

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

it('returns a php grid flattening nested keys to dot-paths', function (): void {
    mkdir($this->root.'/en', 0777, true);
    file_put_contents($this->root.'/en/users.php', "<?php return ['title' => ['icon_tooltip' => 'Manage']];");

    $data = $this->getJson('/i18n/api/php/users?locales=en')->assertOk()->json();

    expect($data['keys'])->toBe(['title.icon_tooltip'])
        ->and($data['rows']['title.icon_tooltip']['en'])->toBe('Manage');
});

it('applies a nested set op writing valid nested php', function (): void {
    $base = $this->getJson('/i18n/api/php/users?locales=en')->json('hashes');

    $this->patchJson('/i18n/api/php/users', [
        'baseHashes' => $base,
        'ops' => [['op' => 'set', 'locale' => 'en', 'key' => 'title.icon_tooltip', 'value' => 'Manage users']],
    ])->assertOk()->assertJsonPath('changed.0', 'en');

    expect(require $this->root.'/en/users.php')->toBe(['title' => ['icon_tooltip' => 'Manage users']]);
});

it('rejects an invalid group name', function (): void {
    $this->getJson('/i18n/api/php/bad.name?locales=en')->assertStatus(422);
});

it('rejects an invalid locale query', function (): void {
    $this->getJson('/i18n/api/php/users?locales=en,b@d')->assertStatus(422);
});

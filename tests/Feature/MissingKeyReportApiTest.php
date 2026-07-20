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

it('reports missing keys across json and php groups for a reference locale', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['greeting' => 'Hi', 'bye' => 'Bye']));
    file_put_contents($this->root.'/tr.json', json_encode(['greeting' => 'Selam']));
    mkdir($this->root.'/en', 0777, true);
    file_put_contents($this->root.'/en/users.php', "<?php return ['title' => 'Users'];");

    $data = $this->getJson('/i18n/api/report/missing?reference=en')->assertOk()->json();

    expect($data['reference'])->toBe('en')
        ->and($data['locales'])->toBe(['tr']);

    $json = collect($data['groups'])->firstWhere('type', 'json');
    $php = collect($data['groups'])->firstWhere('group', 'users');

    expect($json['missing'])->toBe(['tr' => ['bye']])
        ->and($php['missing'])->toBe(['tr' => ['title']]);
});

it('accepts an explicit target locale filter', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['a' => '1']));
    file_put_contents($this->root.'/tr.json', json_encode([]));
    file_put_contents($this->root.'/de.json', json_encode([]));

    $data = $this->getJson('/i18n/api/report/missing?reference=en&locales=de')->assertOk()->json();

    expect($data['locales'])->toBe(['de'])
        ->and($data['groups'][0]['missing'])->toBe(['de' => ['a']]);
});

it('requires a reference locale', function (): void {
    $this->getJson('/i18n/api/report/missing')->assertStatus(422);
});

it('rejects an invalid reference locale', function (): void {
    $this->getJson('/i18n/api/report/missing?reference=../etc')->assertStatus(422);
});

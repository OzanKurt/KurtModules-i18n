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

it('returns a json grid with the locale union', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['greeting' => 'Hi']));
    file_put_contents($this->root.'/tr.json', json_encode([]));

    $data = $this->getJson('/i18n/api/json?locales=en,tr')->assertOk()->json();

    expect($data['keys'])->toBe(['greeting'])
        ->and($data['rows']['greeting']['en'])->toBe('Hi')
        ->and($data['rows']['greeting']['tr'])->toBeNull();
});

it('applies a set op and writes the json file', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['greeting' => 'Hi']));
    $base = $this->getJson('/i18n/api/json?locales=en')->json('hashes');

    $this->patchJson('/i18n/api/json', [
        'baseHashes' => $base,
        'ops' => [['op' => 'set', 'locale' => 'en', 'key' => 'bye', 'value' => 'Bye']],
    ])->assertOk()->assertJsonPath('changed.0', 'en');

    expect(json_decode((string) file_get_contents($this->root.'/en.json'), true))
        ->toBe(['greeting' => 'Hi', 'bye' => 'Bye']);
});

it('deletes and renames keys in one batch', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['a' => '1', 'b' => '2']));
    $base = $this->getJson('/i18n/api/json?locales=en')->json('hashes');

    $this->patchJson('/i18n/api/json', [
        'baseHashes' => $base,
        'ops' => [
            ['op' => 'delete', 'key' => 'a'],
            ['op' => 'rename', 'from' => 'b', 'to' => 'c'],
        ],
    ])->assertOk();

    expect(json_decode((string) file_get_contents($this->root.'/en.json'), true))->toBe(['c' => '2']);
});

it('returns 409 when a base hash is stale', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['a' => '1']));

    $this->patchJson('/i18n/api/json', [
        'baseHashes' => ['en' => 'stale'],
        'ops' => [['op' => 'set', 'locale' => 'en', 'key' => 'a', 'value' => '2']],
    ])->assertStatus(409)->assertJsonPath('locales.0', 'en');
});

it('validates the ops payload', function (): void {
    $this->patchJson('/i18n/api/json', [
        'baseHashes' => [],
        'ops' => [['op' => 'nope']],
    ])->assertStatus(422);
});

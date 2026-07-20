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

it('shows a single json key across locales with hashes', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['greeting' => 'Hi']));
    file_put_contents($this->root.'/tr.json', json_encode([]));

    $data = $this->getJson('/api/i18n/translations?type=json&key=greeting&locales=en,tr')
        ->assertOk()
        ->json('data');

    expect($data['key'])->toBe('greeting')
        ->and($data['exists'])->toBeTrue()
        ->and($data['values']['en'])->toBe('Hi')
        ->and($data['values']['tr'])->toBeNull()
        ->and($data['hashes'])->toHaveKeys(['en', 'tr']);
});

it('shows a nested php key by dot-path', function (): void {
    mkdir($this->root.'/en', 0777, true);
    file_put_contents($this->root.'/en/users.php', "<?php return ['title' => ['icon' => 'Manage']];");

    $this->getJson('/api/i18n/translations?type=php&group=users&key=title.icon&locales=en')
        ->assertOk()
        ->assertJsonPath('data.values.en', 'Manage');
});

it('reports a missing key as non-existent', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['greeting' => 'Hi']));

    $this->getJson('/api/i18n/translations?type=json&key=nope&locales=en')
        ->assertOk()
        ->assertJsonPath('data.exists', false)
        ->assertJsonPath('data.values.en', null);
});

it('requires a key to show a translation', function (): void {
    $this->getJson('/api/i18n/translations?type=json&locales=en')->assertStatus(422);
});

it('sets a single json key over the safe write path', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['greeting' => 'Hi']));
    $base = $this->getJson('/api/i18n/json?locales=en')->json('data.hashes');

    $this->putJson('/api/i18n/translations', [
        'type' => 'json',
        'locale' => 'en',
        'key' => 'bye',
        'value' => 'Bye',
        'baseHashes' => $base,
    ])->assertOk()->assertJsonPath('data.changed.0', 'en');

    expect(json_decode((string) file_get_contents($this->root.'/en.json'), true))
        ->toBe(['greeting' => 'Hi', 'bye' => 'Bye']);
});

it('sets a nested php key', function (): void {
    $base = $this->getJson('/api/i18n/php/users?locales=en')->json('data.hashes');

    $this->putJson('/api/i18n/translations', [
        'type' => 'php',
        'group' => 'users',
        'locale' => 'en',
        'key' => 'title.icon',
        'value' => 'Manage',
        'baseHashes' => $base,
    ])->assertOk();

    expect(require $this->root.'/en/users.php')->toBe(['title' => ['icon' => 'Manage']]);
});

it('returns 409 when setting a key with a stale base hash', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['greeting' => 'Hi']));

    $this->putJson('/api/i18n/translations', [
        'type' => 'json',
        'locale' => 'en',
        'key' => 'greeting',
        'value' => 'Hello',
        'baseHashes' => ['en' => 'stale'],
    ])->assertStatus(409)->assertJsonPath('errors.locales.0', 'en');
});

it('requires a group when setting a php key', function (): void {
    $this->putJson('/api/i18n/translations', [
        'type' => 'php',
        'locale' => 'en',
        'key' => 'title',
        'value' => 'X',
        'baseHashes' => ['en' => null],
    ])->assertStatus(422);
});

it('deletes a single key from every loaded locale', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['greeting' => 'Hi', 'bye' => 'Bye']));
    file_put_contents($this->root.'/tr.json', json_encode(['bye' => 'Guele']));
    $base = $this->getJson('/api/i18n/json?locales=en,tr')->json('data.hashes');

    $this->deleteJson('/api/i18n/translations', [
        'type' => 'json',
        'key' => 'bye',
        'baseHashes' => $base,
    ])->assertOk()->assertJsonPath('data.changed', ['en', 'tr']);

    expect(json_decode((string) file_get_contents($this->root.'/en.json'), true))->toBe(['greeting' => 'Hi'])
        ->and(json_decode((string) file_get_contents($this->root.'/tr.json'), true))->toBe([]);
});

it('validates the set payload', function (): void {
    $this->putJson('/api/i18n/translations', ['type' => 'json'])->assertStatus(422);
});

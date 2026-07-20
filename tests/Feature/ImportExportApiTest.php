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

it('exports a json group as downloadable json rows', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['greeting' => 'Hi']));

    $response = $this->get('/i18n/api/export?type=json&locale=en&format=json')->assertOk();

    $response->assertHeader('content-disposition', 'attachment; filename="en-json.json"');
    expect($response->json())->toBe([['key' => 'greeting', 'value' => 'Hi']]);
});

it('exports a php group as downloadable csv', function (): void {
    mkdir($this->root.'/en', 0777, true);
    file_put_contents($this->root.'/en/users.php', "<?php return ['title' => 'Users'];");

    $response = $this->get('/i18n/api/export?type=php&group=users&locale=en&format=csv')->assertOk();

    $response->assertHeader('content-disposition', 'attachment; filename="en-users.csv"');
    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($response->getContent())->toContain('key,value')->toContain('title');
});

it('exports all groups with type and group columns', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['a' => '1']));
    mkdir($this->root.'/en', 0777, true);
    file_put_contents($this->root.'/en/users.php', "<?php return ['b' => '2'];");

    $rows = $this->get('/i18n/api/export?locale=en&format=json')->assertOk()->json();

    expect($rows)->toContain(['type' => 'json', 'group' => '', 'key' => 'a', 'value' => '1'])
        ->toContain(['type' => 'php', 'group' => 'users', 'key' => 'b', 'value' => '2']);
});

it('imports csv rows into a group through the safe write path', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['greeting' => 'Hi']));
    $base = $this->getJson('/i18n/api/json?locales=en')->json('hashes');

    $this->postJson('/i18n/api/import', [
        'type' => 'json',
        'locale' => 'en',
        'format' => 'csv',
        'content' => "key,value\ngreeting,Hello\nbye,Bye\n",
        'baseHashes' => $base,
    ])->assertOk()->assertJsonPath('changed.0', 'en');

    expect(json_decode((string) file_get_contents($this->root.'/en.json'), true))
        ->toBe(['greeting' => 'Hello', 'bye' => 'Bye']);
});

it('imports json rows into a php group', function (): void {
    $this->postJson('/i18n/api/import', [
        'type' => 'php',
        'group' => 'users',
        'locale' => 'en',
        'format' => 'json',
        'content' => '[{"key":"title.icon","value":"Manage"}]',
        'baseHashes' => ['en' => null],
    ])->assertOk();

    expect(require $this->root.'/en/users.php')->toBe(['title' => ['icon' => 'Manage']]);
});

it('rejects a malformed import with 422', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['a' => '1']));
    $base = $this->getJson('/i18n/api/json?locales=en')->json('hashes');

    $this->postJson('/i18n/api/import', [
        'type' => 'json',
        'locale' => 'en',
        'format' => 'csv',
        'content' => "name,text\ngreeting,Hi\n",
        'baseHashes' => $base,
    ])->assertStatus(422);

    // The file must be untouched by a rejected import.
    expect(json_decode((string) file_get_contents($this->root.'/en.json'), true))->toBe(['a' => '1']);
});

it('returns 409 when the import base hash is stale', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['a' => '1']));

    $this->postJson('/i18n/api/import', [
        'type' => 'json',
        'locale' => 'en',
        'format' => 'json',
        'content' => '[{"key":"a","value":"2"}]',
        'baseHashes' => ['en' => 'stale'],
    ])->assertStatus(409);
});

it('validates the import payload', function (): void {
    $this->postJson('/i18n/api/import', ['type' => 'php', 'format' => 'csv'])->assertStatus(422);
});

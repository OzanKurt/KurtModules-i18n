<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Kurt\Modules\I18n\Contracts\Translator;
use Kurt\Modules\I18n\Support\TranslationManager;

beforeEach(function (): void {
    $this->root = i18n_tmp_dir();
    $this->app->instance(TranslationManager::class, i18n_manager($this->root));
    Gate::define('viewI18n', fn ($user = null): bool => true);
});

afterEach(function (): void {
    i18n_rrmdir($this->root);
});

/**
 * Bind a translator that uppercases the source text.
 */
function i18n_bind_uppercasing_translator(): void
{
    app()->bind(Translator::class, fn (): Translator => new class implements Translator
    {
        public function translate(string $text, string $from, string $to): string
        {
            return strtoupper($text);
        }
    });
}

it('translates the missing keys for a locale from the reference', function (): void {
    i18n_bind_uppercasing_translator();

    file_put_contents($this->root.'/en.json', json_encode(['greeting' => 'Hi', 'bye' => 'Bye']));
    file_put_contents($this->root.'/tr.json', json_encode(['greeting' => 'Selam']));
    $base = $this->getJson('/i18n/api/json?locales=en,tr')->json('hashes');

    $this->postJson('/i18n/api/translate-missing', [
        'type' => 'json',
        'reference' => 'en',
        'locale' => 'tr',
        'baseHashes' => $base,
    ])->assertOk()
        ->assertJsonPath('translated.0', 'bye')
        ->assertJsonPath('changed.0', 'tr');

    expect(json_decode((string) file_get_contents($this->root.'/tr.json'), true))
        ->toBe(['greeting' => 'Selam', 'bye' => 'BYE']);
});

it('returns 501 when no translator is configured and keys are missing', function (): void {
    // Default binding is the NullTranslator, which refuses to translate.
    file_put_contents($this->root.'/en.json', json_encode(['a' => '1']));
    $base = $this->getJson('/i18n/api/json?locales=en,tr')->json('hashes');

    $this->postJson('/i18n/api/translate-missing', [
        'type' => 'json',
        'reference' => 'en',
        'locale' => 'tr',
        'baseHashes' => $base,
    ])->assertStatus(501);
});

it('rejects translating a locale into itself', function (): void {
    $this->postJson('/i18n/api/translate-missing', [
        'type' => 'json',
        'reference' => 'en',
        'locale' => 'en',
        'baseHashes' => [],
    ])->assertStatus(422);
});

it('returns 409 when a base hash is stale', function (): void {
    i18n_bind_uppercasing_translator();

    file_put_contents($this->root.'/en.json', json_encode(['a' => '1']));

    $this->postJson('/i18n/api/translate-missing', [
        'type' => 'json',
        'reference' => 'en',
        'locale' => 'tr',
        'baseHashes' => ['en' => 'stale', 'tr' => null],
    ])->assertStatus(409);
});

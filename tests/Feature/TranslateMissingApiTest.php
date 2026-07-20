<?php

declare(strict_types=1);

use Kurt\Modules\I18n\Contracts\Translator;
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
    $base = $this->getJson('/api/i18n/json?locales=en,tr')->json('data.hashes');

    $this->postJson('/api/i18n/translate-missing', [
        'type' => 'json',
        'reference' => 'en',
        'locale' => 'tr',
        'baseHashes' => $base,
    ])->assertOk()
        ->assertJsonPath('data.translated.0', 'bye')
        ->assertJsonPath('data.changed.0', 'tr');

    expect(json_decode((string) file_get_contents($this->root.'/tr.json'), true))
        ->toBe(['greeting' => 'Selam', 'bye' => 'BYE']);
});

it('returns 501 when no translator is configured and keys are missing', function (): void {
    // Default binding is the NullTranslator, which refuses to translate.
    file_put_contents($this->root.'/en.json', json_encode(['a' => '1']));
    $base = $this->getJson('/api/i18n/json?locales=en,tr')->json('data.hashes');

    $this->postJson('/api/i18n/translate-missing', [
        'type' => 'json',
        'reference' => 'en',
        'locale' => 'tr',
        'baseHashes' => $base,
    ])->assertStatus(501);
});

it('rejects translating a locale into itself', function (): void {
    $this->postJson('/api/i18n/translate-missing', [
        'type' => 'json',
        'reference' => 'en',
        'locale' => 'en',
        'baseHashes' => [],
    ])->assertStatus(422);
});

it('returns 409 when a base hash is stale', function (): void {
    i18n_bind_uppercasing_translator();

    file_put_contents($this->root.'/en.json', json_encode(['a' => '1']));

    $this->postJson('/api/i18n/translate-missing', [
        'type' => 'json',
        'reference' => 'en',
        'locale' => 'tr',
        'baseHashes' => ['en' => 'stale', 'tr' => null],
    ])->assertStatus(409);
});

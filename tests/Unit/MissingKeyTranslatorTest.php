<?php

declare(strict_types=1);

use Kurt\Modules\I18n\Contracts\Translator;
use Kurt\Modules\I18n\Enums\FileType;
use Kurt\Modules\I18n\Exceptions\TranslatorNotConfiguredException;
use Kurt\Modules\I18n\Support\ArrayExporter;
use Kurt\Modules\I18n\Support\LangPaths;
use Kurt\Modules\I18n\Support\MissingKeyTranslator;
use Kurt\Modules\I18n\Support\NullTranslator;
use Kurt\Modules\I18n\Support\TranslationManager;

/**
 * A test double that uppercases the source text and records its arguments.
 */
function i18n_uppercasing_translator(): Translator
{
    return new class implements Translator
    {
        /** @var list<array{text: string, from: string, to: string}> */
        public array $calls = [];

        public function translate(string $text, string $from, string $to): string
        {
            $this->calls[] = ['text' => $text, 'from' => $from, 'to' => $to];

            return strtoupper($text);
        }
    };
}

beforeEach(function (): void {
    $this->root = i18n_tmp_dir();
    $this->manager = new TranslationManager(new LangPaths($this->root), new ArrayExporter);
});

afterEach(function (): void {
    i18n_rrmdir($this->root);
});

it('fills only the target locale missing keys by translating the reference value', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['greeting' => 'Hi', 'bye' => 'Bye']));
    file_put_contents($this->root.'/tr.json', json_encode(['greeting' => 'Selam']));

    $translator = i18n_uppercasing_translator();
    $filler = new MissingKeyTranslator($this->manager, $translator);
    $base = $this->manager->grid(FileType::Json, null, ['en', 'tr'])['hashes'];

    $result = $filler->fill(FileType::Json, null, 'en', 'tr', $base);

    expect($result['translated'])->toBe(['bye'])
        ->and($result['changed'])->toBe(['tr']);

    // Only the missing key was translated; the existing "greeting" is untouched.
    expect(json_decode((string) file_get_contents($this->root.'/tr.json'), true))
        ->toBe(['greeting' => 'Selam', 'bye' => 'BYE']);

    expect($translator->calls)->toBe([['text' => 'Bye', 'from' => 'en', 'to' => 'tr']]);
});

it('fills nested php keys through the safe write path', function (): void {
    mkdir($this->root.'/en', 0777, true);
    file_put_contents($this->root.'/en/users.php', "<?php return ['title' => ['icon' => 'Manage']];");

    $filler = new MissingKeyTranslator($this->manager, i18n_uppercasing_translator());
    $base = $this->manager->grid(FileType::Php, 'users', ['en', 'tr'])['hashes'];

    $filler->fill(FileType::Php, 'users', 'en', 'tr', $base);

    expect(require $this->root.'/tr/users.php')->toBe(['title' => ['icon' => 'MANAGE']]);
});

it('changes nothing when the target already has every reference key', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['a' => '1']));
    file_put_contents($this->root.'/tr.json', json_encode(['a' => 'x']));

    $filler = new MissingKeyTranslator($this->manager, i18n_uppercasing_translator());
    $base = $this->manager->grid(FileType::Json, null, ['en', 'tr'])['hashes'];

    $result = $filler->fill(FileType::Json, null, 'en', 'tr', $base);

    expect($result['translated'])->toBe([])
        ->and($result['changed'])->toBe([]);
});

it('propagates the not-configured error from the null translator', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['a' => '1']));
    $base = $this->manager->grid(FileType::Json, null, ['en', 'tr'])['hashes'];

    $filler = new MissingKeyTranslator($this->manager, new NullTranslator);

    expect(fn () => $filler->fill(FileType::Json, null, 'en', 'tr', $base))
        ->toThrow(TranslatorNotConfiguredException::class);
});

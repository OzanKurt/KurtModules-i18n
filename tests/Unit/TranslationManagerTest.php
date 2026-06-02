<?php

declare(strict_types=1);

use Kurt\Modules\I18n\Enums\FileType;
use Kurt\Modules\I18n\Exceptions\TranslationConflictException;
use Kurt\Modules\I18n\Support\ArrayExporter;
use Kurt\Modules\I18n\Support\EditOperation;
use Kurt\Modules\I18n\Support\LangPaths;
use Kurt\Modules\I18n\Support\TranslationManager;

beforeEach(function (): void {
    $this->root = i18n_tmp_dir();
    $this->manager = new TranslationManager(new LangPaths($this->root), new ArrayExporter);
});

afterEach(function (): void {
    i18n_rrmdir($this->root);
});

it('builds a json grid with the locale union and null for missing cells', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['greeting' => 'Hi', 'bye' => 'Bye']));
    file_put_contents($this->root.'/tr.json', json_encode(['greeting' => 'Selam']));

    $grid = $this->manager->grid(FileType::Json, null, ['tr', 'en']);

    expect($grid['keys'])->toBe(['bye', 'greeting'])
        ->and($grid['rows']['greeting'])->toBe(['tr' => 'Selam', 'en' => 'Hi'])
        ->and($grid['rows']['bye'])->toBe(['tr' => null, 'en' => 'Bye'])
        ->and($grid['hashes']['en'])->not->toBeNull();
});

it('builds a php grid flattening nested keys to dot-paths', function (): void {
    mkdir($this->root.'/en', 0777, true);
    file_put_contents($this->root.'/en/users.php', "<?php return ['title' => ['icon_tooltip' => 'Manage']];");

    $grid = $this->manager->grid(FileType::Php, 'users', ['en']);

    expect($grid['keys'])->toBe(['title.icon_tooltip'])
        ->and($grid['rows']['title.icon_tooltip'])->toBe(['en' => 'Manage']);
});

it('applies a set op, creating a nested php file and reporting the change', function (): void {
    $result = $this->manager->apply(
        FileType::Php,
        'users',
        ['en' => null],
        [EditOperation::set('en', 'title.icon_tooltip', 'Manage users')],
    );

    expect($result['changed'])->toBe(['en'])
        ->and(is_file($this->root.'/en/users.php'))->toBeTrue()
        ->and($this->manager->grid(FileType::Php, 'users', ['en'])['rows']['title.icon_tooltip']['en'])
        ->toBe('Manage users');
});

it('deletes a key across the loaded locales', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['a' => '1', 'b' => '2']));
    file_put_contents($this->root.'/tr.json', json_encode(['a' => 'x', 'b' => 'y']));
    $base = $this->manager->grid(FileType::Json, null, ['en', 'tr'])['hashes'];

    $this->manager->apply(FileType::Json, null, $base, [EditOperation::delete('a')]);

    expect($this->manager->grid(FileType::Json, null, ['en', 'tr'])['keys'])->toBe(['b']);
});

it('renames a key preserving each locale value', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['old' => 'value-en']));
    file_put_contents($this->root.'/tr.json', json_encode(['old' => 'value-tr']));
    $base = $this->manager->grid(FileType::Json, null, ['en', 'tr'])['hashes'];

    $this->manager->apply(FileType::Json, null, $base, [EditOperation::rename('old', 'new')]);

    expect($this->manager->grid(FileType::Json, null, ['en', 'tr'])['rows']['new'])
        ->toBe(['en' => 'value-en', 'tr' => 'value-tr']);
});

it('throws a conflict when a base hash is stale', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['a' => '1']));

    $this->manager->apply(FileType::Json, null, ['en' => 'deadbeef'], [EditOperation::set('en', 'a', '2')]);
})->throws(TranslationConflictException::class);

it('adds a new empty locale file', function (): void {
    $result = $this->manager->addLocale(FileType::Json, 'fr');

    expect($result['locale'])->toBe('fr')
        ->and(is_file($this->root.'/fr.json'))->toBeTrue()
        ->and($this->manager->grid(FileType::Json, null, ['fr'])['keys'])->toBe([]);
});

it('rejects a set op for a locale that was not loaded', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['a' => '1']));
    $base = $this->manager->grid(FileType::Json, null, ['en'])['hashes'];

    $this->manager->apply(FileType::Json, null, $base, [EditOperation::set('tr', 'a', '2')]);
})->throws(InvalidArgumentException::class);

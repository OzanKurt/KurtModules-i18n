<?php

declare(strict_types=1);

use Kurt\Modules\I18n\Enums\FileType;
use Kurt\Modules\I18n\Enums\PortableFormat;
use Kurt\Modules\I18n\Exceptions\MalformedImportException;
use Kurt\Modules\I18n\Exceptions\TranslationConflictException;
use Kurt\Modules\I18n\Support\ArrayExporter;
use Kurt\Modules\I18n\Support\FileBackup;
use Kurt\Modules\I18n\Support\LangPaths;
use Kurt\Modules\I18n\Support\TranslationExporter;
use Kurt\Modules\I18n\Support\TranslationImporter;
use Kurt\Modules\I18n\Support\TranslationManager;

beforeEach(function (): void {
    $this->root = i18n_tmp_dir();
    $this->manager = new TranslationManager(new LangPaths($this->root), new ArrayExporter);
    $this->importer = new TranslationImporter($this->manager);
});

afterEach(function (): void {
    i18n_rrmdir($this->root);
});

it('parses csv key,value rows into a map', function (): void {
    $csv = "key,value\ngreeting,Hi\nbye,Bye\n";

    expect($this->importer->parse(PortableFormat::Csv, $csv))
        ->toBe(['greeting' => 'Hi', 'bye' => 'Bye']);
});

it('parses csv regardless of column order and ignores extra columns', function (): void {
    $csv = "type,group,value,key\njson,,Hi,greeting\n";

    expect($this->importer->parse(PortableFormat::Csv, $csv))
        ->toBe(['greeting' => 'Hi']);
});

it('parses json rows and a flat json object', function (): void {
    expect($this->importer->parse(PortableFormat::Json, '[{"key":"a","value":"1"}]'))->toBe(['a' => '1'])
        ->and($this->importer->parse(PortableFormat::Json, '{"a":"1","b":"2"}'))->toBe(['a' => '1', 'b' => '2']);
});

it('rejects csv without key and value columns', function (): void {
    $this->importer->parse(PortableFormat::Csv, "name,text\ngreeting,Hi\n");
})->throws(MalformedImportException::class);

it('rejects a csv data row with an empty key', function (): void {
    $this->importer->parse(PortableFormat::Csv, "key,value\n,orphan\n");
})->throws(MalformedImportException::class);

it('rejects invalid json', function (): void {
    $this->importer->parse(PortableFormat::Json, '{not json');
})->throws(MalformedImportException::class);

it('rejects a json object with nested (non-scalar) values', function (): void {
    $this->importer->parse(PortableFormat::Json, '{"a":{"nested":"x"}}');
})->throws(MalformedImportException::class);

it('round-trips an export back through import', function (): void {
    mkdir($this->root.'/en', 0777, true);
    file_put_contents($this->root.'/en/users.php', "<?php return ['title' => ['icon' => 'Manage'], 'count' => '3'];");

    $exporter = new TranslationExporter($this->manager);
    $rows = $exporter->group(FileType::Php, 'users', 'en');

    foreach ([PortableFormat::Csv, PortableFormat::Json] as $format) {
        // Import the en export into a fresh de group.
        $payload = $format === PortableFormat::Csv ? $exporter->toCsv($rows, ['key', 'value']) : $exporter->toJson($rows);
        $parsed = $this->importer->parse($format, $payload);
        $this->importer->apply(FileType::Php, 'users', 'de', $parsed, ['de' => null]);

        expect(require $this->root.'/de/users.php')->toEqualCanonicalizing(['title' => ['icon' => 'Manage'], 'count' => '3']);

        @unlink($this->root.'/de/users.php');
    }
});

it('applies an import through the safe write path (optimistic-hash conflict)', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['a' => '1']));

    // A stale base hash must be rejected, proving the import goes through apply().
    expect(fn () => $this->importer->apply(FileType::Json, null, 'en', ['a' => '2'], ['en' => 'stale']))
        ->toThrow(TranslationConflictException::class);

    // The file is untouched.
    expect(json_decode((string) file_get_contents($this->root.'/en.json'), true))->toBe(['a' => '1']);
});

it('backs up the target file when the manager has backups enabled', function (): void {
    $backupDir = $this->root.'/.backups';
    $manager = new TranslationManager(new LangPaths($this->root), new ArrayExporter, new FileBackup($backupDir));
    $importer = new TranslationImporter($manager);

    file_put_contents($this->root.'/en.json', json_encode(['a' => '1']));
    $base = $manager->grid(FileType::Json, null, ['en'])['hashes'];

    $importer->apply(FileType::Json, null, 'en', ['a' => '2'], $base);

    expect(glob($backupDir.'/*_en.json.bak') ?: [])->toHaveCount(1);
});

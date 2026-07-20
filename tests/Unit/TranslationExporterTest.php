<?php

declare(strict_types=1);

use Kurt\Modules\I18n\Enums\FileType;
use Kurt\Modules\I18n\Support\ArrayExporter;
use Kurt\Modules\I18n\Support\LangPaths;
use Kurt\Modules\I18n\Support\TranslationExporter;
use Kurt\Modules\I18n\Support\TranslationManager;

beforeEach(function (): void {
    $this->root = i18n_tmp_dir();
    $this->manager = new TranslationManager(new LangPaths($this->root), new ArrayExporter);
    $this->exporter = new TranslationExporter($this->manager);
});

afterEach(function (): void {
    i18n_rrmdir($this->root);
});

it('exports a json group as key,value rows', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['greeting' => 'Hi', 'bye' => 'Bye']));

    expect($this->exporter->group(FileType::Json, null, 'en'))
        ->toBe([
            ['key' => 'bye', 'value' => 'Bye'],
            ['key' => 'greeting', 'value' => 'Hi'],
        ]);
});

it('flattens nested php keys to dot-paths on export', function (): void {
    mkdir($this->root.'/en', 0777, true);
    file_put_contents($this->root.'/en/users.php', "<?php return ['title' => ['icon' => 'Manage']];");

    expect($this->exporter->group(FileType::Php, 'users', 'en'))
        ->toBe([['key' => 'title.icon', 'value' => 'Manage']]);
});

it('exports every group for a locale with type and group columns', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['a' => '1']));
    mkdir($this->root.'/en', 0777, true);
    file_put_contents($this->root.'/en/users.php', "<?php return ['b' => '2'];");

    expect($this->exporter->all('en'))->toBe([
        ['type' => 'json', 'group' => '', 'key' => 'a', 'value' => '1'],
        ['type' => 'php', 'group' => 'users', 'key' => 'b', 'value' => '2'],
    ]);
});

it('serializes rows to csv with a header and rfc-4180 quoting', function (): void {
    $rows = [['key' => 'greeting', 'value' => 'Hi, there'], ['key' => 'quote', 'value' => 'say "hi"']];

    $csv = $this->exporter->toCsv($rows, ['key', 'value']);
    $lines = explode("\n", trim($csv));

    expect($lines[0])->toBe('key,value')
        // A value with a comma is enclosed; an embedded quote is doubled.
        ->and($csv)->toContain('"Hi, there"')
        ->and($csv)->toContain('"say ""hi"""');
});

it('serializes rows to pretty json', function (): void {
    $json = $this->exporter->toJson([['key' => 'a', 'value' => '1']]);

    expect(json_decode($json, true))->toBe([['key' => 'a', 'value' => '1']]);
});

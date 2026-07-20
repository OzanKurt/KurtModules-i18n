<?php

declare(strict_types=1);

use Kurt\Modules\I18n\Support\ArrayExporter;
use Kurt\Modules\I18n\Support\LangPaths;
use Kurt\Modules\I18n\Support\MissingKeyReport;
use Kurt\Modules\I18n\Support\TranslationManager;

beforeEach(function (): void {
    $this->root = i18n_tmp_dir();
    $this->manager = new TranslationManager(new LangPaths($this->root), new ArrayExporter);
    $this->report = new MissingKeyReport($this->manager);
});

afterEach(function (): void {
    i18n_rrmdir($this->root);
});

it('reports keys missing per target locale across json and php groups', function (): void {
    // JSON: tr is missing "bye".
    file_put_contents($this->root.'/en.json', json_encode(['greeting' => 'Hi', 'bye' => 'Bye']));
    file_put_contents($this->root.'/tr.json', json_encode(['greeting' => 'Selam']));

    // PHP group "users": tr is missing the nested title.icon key.
    mkdir($this->root.'/en', 0777, true);
    mkdir($this->root.'/tr', 0777, true);
    file_put_contents($this->root.'/en/users.php', "<?php return ['title' => ['icon' => 'Manage', 'label' => 'Users']];");
    file_put_contents($this->root.'/tr/users.php', "<?php return ['title' => ['label' => 'Kullanicilar']];");

    $report = $this->report->generate('en');

    expect($report['reference'])->toBe('en')
        ->and($report['locales'])->toBe(['tr']);

    $json = collect($report['groups'])->firstWhere('group', null);
    $php = collect($report['groups'])->firstWhere('group', 'users');

    expect($json['type'])->toBe('json')
        ->and($json['missing'])->toBe(['tr' => ['bye']])
        ->and($php['type'])->toBe('php')
        ->and($php['missing'])->toBe(['tr' => ['title.icon']]);
});

it('omits a group entirely when no target is missing anything', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['a' => '1']));
    file_put_contents($this->root.'/tr.json', json_encode(['a' => 'x']));

    expect($this->report->generate('en')['groups'])->toBe([]);
});

it('reports a wholly-absent group file as every reference key missing', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['a' => '1']));
    mkdir($this->root.'/en', 0777, true);
    file_put_contents($this->root.'/en/users.php', "<?php return ['x' => '1', 'y' => '2'];");
    // tr exists as a locale (json) but has no users.php at all.
    file_put_contents($this->root.'/tr.json', json_encode(['a' => 'x']));

    $php = collect($this->report->generate('en')['groups'])->firstWhere('group', 'users');

    expect($php['missing'])->toBe(['tr' => ['x', 'y']]);
});

it('restricts the report to an explicit target locale list', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['a' => '1']));
    file_put_contents($this->root.'/tr.json', json_encode([]));
    file_put_contents($this->root.'/de.json', json_encode([]));

    $report = $this->report->generate('en', ['tr']);

    expect($report['locales'])->toBe(['tr'])
        ->and($report['groups'][0]['missing'])->toBe(['tr' => ['a']]);
});

it('never lists the reference locale as its own target', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['a' => '1']));

    $report = $this->report->generate('en', ['en', 'tr']);

    expect($report['locales'])->toBe(['tr']);
});

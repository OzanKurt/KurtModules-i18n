<?php

declare(strict_types=1);

use Kurt\Modules\I18n\Exceptions\InvalidTranslationFileException;
use Kurt\Modules\I18n\Support\FileBackup;
use Kurt\Modules\I18n\Support\JsonTranslationFile;

beforeEach(function (): void {
    $this->dir = i18n_tmp_dir();
});

afterEach(function (): void {
    i18n_rrmdir($this->dir);
});

it('round-trips flat json with opaque dotted and colon keys', function (): void {
    $path = $this->dir.'/en.json';
    $data = ['Welcome, :name' => 'Hi, :name', 'auth.failed' => 'These credentials failed.', 'a/b' => 'c'];

    (new JsonTranslationFile($path))->write($data);

    expect((new JsonTranslationFile($path))->read())->toBe($data);
});

it('writes unicode and slashes unescaped', function (): void {
    $path = $this->dir.'/tr.json';

    (new JsonTranslationFile($path))->write(['x' => 'Türkçe / 日本語']);

    expect((string) file_get_contents($path))->toContain('Türkçe / 日本語');
});

it('reads a missing file as empty with a null hash', function (): void {
    $file = new JsonTranslationFile($this->dir.'/none.json');

    expect($file->read())->toBe([])
        ->and($file->hash())->toBeNull();
});

it('writes a backup before overwriting a json file when configured', function (): void {
    $backupDir = $this->dir.'/backups';
    $path = $this->dir.'/en.json';
    $file = new JsonTranslationFile($path, new FileBackup($backupDir));

    $file->write(['a' => '1']);
    $file->write(['a' => '2']);

    expect(glob($backupDir.'/*.bak') ?: [])->toHaveCount(1)
        ->and((new JsonTranslationFile($path))->read())->toBe(['a' => '2']);
});

it('throws on invalid json', function (): void {
    $path = $this->dir.'/broken.json';
    file_put_contents($path, '{ not json ');

    (new JsonTranslationFile($path))->read();
})->throws(InvalidTranslationFileException::class);

<?php

declare(strict_types=1);

use Kurt\Modules\I18n\Exceptions\InvalidTranslationFileException;
use Kurt\Modules\I18n\Support\ArrayExporter;
use Kurt\Modules\I18n\Support\FileBackup;
use Kurt\Modules\I18n\Support\PhpArrayFile;

beforeEach(function (): void {
    $this->dir = i18n_tmp_dir();
    $this->exporter = new ArrayExporter;
});

afterEach(function (): void {
    i18n_rrmdir($this->dir);
});

it('round-trips nested php arrays and creates parent directories', function (): void {
    $path = $this->dir.'/en/users.php';
    $data = ['title' => ['icon_tooltip' => 'Manage'], 'count' => ['one' => '1 user']];

    (new PhpArrayFile($path, $this->exporter))->write($data);

    expect(is_file($path))->toBeTrue()
        ->and((new PhpArrayFile($path, $this->exporter))->read())->toBe($data);
});

it('returns an empty array and null hash when the file is missing', function (): void {
    $file = new PhpArrayFile($this->dir.'/missing.php', $this->exporter);

    expect($file->read())->toBe([])
        ->and($file->hash())->toBeNull();
});

it('throws when the file does not return an array', function (): void {
    $path = $this->dir.'/bad.php';
    file_put_contents($path, "<?php return 'not an array';");

    (new PhpArrayFile($path, $this->exporter))->read();
})->throws(InvalidTranslationFileException::class);

it('writes a backup before overwriting when configured', function (): void {
    $backupDir = $this->dir.'/backups';
    $path = $this->dir.'/en/users.php';
    $file = new PhpArrayFile($path, $this->exporter, new FileBackup($backupDir));

    $file->write(['a' => '1']);
    $file->write(['a' => '2']);

    expect(glob($backupDir.'/*.bak') ?: [])->toHaveCount(1)
        ->and((new PhpArrayFile($path, $this->exporter))->read())->toBe(['a' => '2']);
});

it('aborts the write and leaves the target intact when verification fails', function (): void {
    $path = $this->dir.'/en/users.php';
    (new PhpArrayFile($path, $this->exporter))->write(['a' => 'original']);

    $liar = new class extends ArrayExporter
    {
        public function export(array $data): string
        {
            return "<?php\n\nreturn ['a' => 'WRONG'];\n";
        }
    };

    expect(fn () => (new PhpArrayFile($path, $liar))->write(['a' => 'updated']))
        ->toThrow(InvalidTranslationFileException::class);

    expect((new PhpArrayFile($path, $this->exporter))->read())->toBe(['a' => 'original']);
});

<?php

declare(strict_types=1);

use Kurt\Modules\I18n\Support\FileBackup;

beforeEach(function (): void {
    $this->dir = i18n_tmp_dir();
});

afterEach(function (): void {
    i18n_rrmdir($this->dir);
});

it('keeps at most the configured number of backups per source file, pruning the oldest', function (): void {
    $backupDir = $this->dir.'/backups';
    $source = $this->dir.'/users.php';
    file_put_contents($source, '<?php return [];');

    $backup = new FileBackup($backupDir, keep: 3);

    $created = [];
    for ($i = 0; $i < 6; $i++) {
        $created[] = $backup->backup($source);
    }

    // Only the cap survives. Ordered by the timestamped filename (oldest first),
    // the three oldest must be the ones pruned and the three newest retained.
    expect(glob($backupDir.'/*.bak') ?: [])->toHaveCount(3);

    sort($created);

    foreach (array_slice($created, 0, 3) as $pruned) {
        expect(is_file($pruned))->toBeFalse();
    }

    foreach (array_slice($created, 3) as $kept) {
        expect(is_file($kept))->toBeTrue();
    }
});

it('does not prune when keep is zero (unlimited retention)', function (): void {
    $backupDir = $this->dir.'/backups';
    $source = $this->dir.'/users.php';
    file_put_contents($source, '<?php return [];');

    $backup = new FileBackup($backupDir, keep: 0);

    for ($i = 0; $i < 5; $i++) {
        $backup->backup($source);
    }

    expect(glob($backupDir.'/*.bak') ?: [])->toHaveCount(5);
});

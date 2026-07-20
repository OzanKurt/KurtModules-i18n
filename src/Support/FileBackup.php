<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Support;

use RuntimeException;

/**
 * Writes a timestamped copy of a file before it is overwritten.
 *
 * At most {@see self::$keep} backups are retained per source file; older ones
 * are pruned after each write so the backup directory cannot grow unbounded.
 */
final class FileBackup
{
    public function __construct(
        private readonly string $directory,
        private readonly int $keep = 10,
    ) {}

    public function backup(string $file): string
    {
        if (! is_dir($this->directory) && ! @mkdir($this->directory, 0775, true) && ! is_dir($this->directory)) {
            throw new RuntimeException("Unable to create backup directory [{$this->directory}].");
        }

        $name = date('Ymd_His').'_'.bin2hex(random_bytes(3)).'_'.basename($file).'.bak';
        $destination = rtrim($this->directory, '/\\').'/'.$name;

        if (! @copy($file, $destination)) {
            throw new RuntimeException("Unable to back up [{$file}].");
        }

        $this->prune(basename($file));

        return $destination;
    }

    /**
     * Delete the oldest backups of a source file beyond the retention cap.
     */
    private function prune(string $basename): void
    {
        if ($this->keep <= 0) {
            return;
        }

        $pattern = rtrim($this->directory, '/\\').'/*_'.$basename.'.bak';

        /** @var list<string> $backups */
        $backups = glob($pattern) ?: [];

        if (count($backups) <= $this->keep) {
            return;
        }

        // The Ymd_His filename prefix sorts oldest-first lexicographically.
        sort($backups);

        foreach (array_slice($backups, 0, count($backups) - $this->keep) as $old) {
            @unlink($old);
        }
    }
}

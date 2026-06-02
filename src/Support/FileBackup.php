<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Support;

use RuntimeException;

/**
 * Writes a timestamped copy of a file before it is overwritten.
 */
final class FileBackup
{
    public function __construct(private readonly string $directory) {}

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

        return $destination;
    }
}

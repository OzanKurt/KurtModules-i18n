<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Support;

use RuntimeException;

/**
 * A cross-process advisory lock backed by {@see flock()} on a dedicated lockfile.
 *
 * The {@see TranslationManager} holds one of these exclusively around the whole
 * read-modify-write of a group so two concurrent saves cannot both pass the
 * optimistic-hash check and silently clobber each other (last-writer-wins).
 */
final class TranslationLock
{
    public function __construct(private readonly string $path) {}

    /**
     * Run the callback while holding an exclusive lock, releasing it afterwards
     * whether the callback returns or throws.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withExclusive(callable $callback): mixed
    {
        $directory = dirname($this->path);

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create lock directory [{$directory}].");
        }

        $handle = @fopen($this->path, 'c');

        if ($handle === false) {
            throw new RuntimeException("Unable to open lock file [{$this->path}].");
        }

        if (! flock($handle, LOCK_EX)) {
            fclose($handle);

            throw new RuntimeException("Unable to acquire lock [{$this->path}].");
        }

        try {
            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}

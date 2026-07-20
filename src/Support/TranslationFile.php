<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Support;

use RuntimeException;
use Throwable;

/**
 * Base class for a single translation file on disk.
 *
 * Writes are atomic and self-verifying: the encoded contents are written to a
 * temporary file, read back, and compared to the intended data before the
 * temporary file replaces the target. If anything is off, the target is left
 * untouched. An optional {@see FileBackup} snapshots the previous contents first.
 */
abstract class TranslationFile
{
    public function __construct(
        protected readonly string $path,
        protected readonly ?FileBackup $backup = null,
    ) {}

    public function path(): string
    {
        return $this->path;
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    public function hash(): ?string
    {
        return $this->exists() ? (hash_file('sha1', $this->path) ?: null) : null;
    }

    /**
     * @return array<array-key, mixed>
     */
    abstract public function read(): array;

    /**
     * @param  array<array-key, mixed>  $data
     */
    abstract protected function encode(array $data): string;

    /**
     * @param  array<array-key, mixed>  $intended
     */
    abstract protected function verify(string $temporaryPath, array $intended): void;

    /**
     * @param  array<array-key, mixed>  $data
     */
    public function write(array $data): void
    {
        $contents = $this->encode($data);

        $directory = dirname($this->path);

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create directory [{$directory}].");
        }

        if ($this->backup !== null && $this->exists()) {
            $this->backup->backup($this->path);
        }

        $temporary = $this->path.'.tmp'.bin2hex(random_bytes(4));

        if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
            throw new RuntimeException("Unable to write temporary file [{$temporary}].");
        }

        try {
            $this->verify($temporary, $data);
            $this->replace($temporary, $this->path);
        } catch (Throwable $e) {
            @unlink($temporary);

            throw $e;
        }

        $this->afterReplace();
    }

    /**
     * Hook invoked after the target file has been atomically replaced. Subclasses
     * may override it to react to a completed write (e.g. cache invalidation).
     */
    protected function afterReplace(): void {}

    private function replace(string $from, string $to): void
    {
        if (@rename($from, $to)) {
            return;
        }

        // Windows: rename() fails if the destination already exists.
        if (is_file($to) && @unlink($to) && @rename($from, $to)) {
            return;
        }

        if (@copy($from, $to)) {
            @unlink($from);

            return;
        }

        @unlink($from);

        throw new RuntimeException("Unable to move [{$from}] to [{$to}].");
    }
}

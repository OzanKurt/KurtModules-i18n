<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Support;

use Kurt\Modules\I18n\Exceptions\InvalidTranslationFileException;

/**
 * A `lang/{locale}/{group}.php` file that returns a (possibly nested) array.
 */
final class PhpArrayFile extends TranslationFile
{
    public function __construct(
        string $path,
        private readonly ArrayExporter $exporter,
        ?FileBackup $backup = null,
    ) {
        parent::__construct($path, $backup);
    }

    public function read(): array
    {
        if (! $this->exists()) {
            return [];
        }

        return self::load($this->path);
    }

    protected function encode(array $data): string
    {
        return $this->exporter->export($data);
    }

    protected function verify(string $temporaryPath, array $intended): void
    {
        if (serialize(self::load($temporaryPath)) !== serialize($intended)) {
            throw InvalidTranslationFileException::verificationFailed($this->path);
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function load(string $path): array
    {
        /** @var mixed $data */
        $data = (static fn () => require $path)();

        if (! is_array($data)) {
            throw InvalidTranslationFileException::notAnArray($path);
        }

        return $data;
    }
}

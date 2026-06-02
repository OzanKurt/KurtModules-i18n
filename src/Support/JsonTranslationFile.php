<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Support;

use Kurt\Modules\I18n\Exceptions\InvalidTranslationFileException;

/**
 * A flat `lang/{locale}.json` file. Keys are opaque source strings and are never
 * treated as dot-paths.
 */
final class JsonTranslationFile extends TranslationFile
{
    public function read(): array
    {
        if (! $this->exists()) {
            return [];
        }

        $raw = (string) file_get_contents($this->path);

        if (trim($raw) === '') {
            return [];
        }

        /** @var mixed $data */
        $data = json_decode($raw, true);

        if (! is_array($data)) {
            throw InvalidTranslationFileException::invalidJson($this->path, json_last_error_msg());
        }

        return $data;
    }

    protected function encode(array $data): string
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw InvalidTranslationFileException::invalidJson($this->path, json_last_error_msg());
        }

        return $json."\n";
    }

    protected function verify(string $temporaryPath, array $intended): void
    {
        /** @var mixed $readBack */
        $readBack = json_decode((string) file_get_contents($temporaryPath), true);

        if (! is_array($readBack) || serialize($readBack) !== serialize($intended)) {
            throw InvalidTranslationFileException::verificationFailed($this->path);
        }
    }
}

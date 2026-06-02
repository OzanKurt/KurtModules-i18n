<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Exceptions;

use RuntimeException;

final class InvalidTranslationFileException extends RuntimeException
{
    public static function notAnArray(string $path): self
    {
        return new self("Translation file [{$path}] must return an array.");
    }

    public static function verificationFailed(string $path): self
    {
        return new self("Refusing to write [{$path}]: the re-emitted file did not round-trip to the intended data.");
    }

    public static function invalidJson(string $path, string $reason): self
    {
        return new self("Translation file [{$path}] is not valid JSON: {$reason}.");
    }
}

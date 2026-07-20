<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when an uploaded import payload cannot be parsed into `key,value` rows.
 *
 * Extends {@see InvalidArgumentException} so the HTTP layer maps it to a 422 the
 * same way it treats other bad input.
 */
final class MalformedImportException extends InvalidArgumentException
{
    public static function missingColumns(): self
    {
        return new self('Import is malformed: a "key" and "value" column are required.');
    }

    public static function emptyKey(): self
    {
        return new self('Import is malformed: a row is missing its key.');
    }

    public static function invalidJson(string $reason): self
    {
        return new self("Import is malformed: not valid JSON ({$reason}).");
    }

    public static function invalidShape(): self
    {
        return new self('Import is malformed: expected key,value rows or a flat key => value object of scalars.');
    }
}

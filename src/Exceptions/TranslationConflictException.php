<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Exceptions;

use RuntimeException;

/**
 * Thrown when a translation file changed on disk between the time the client
 * loaded it and the time it tried to save — i.e. an optimistic-lock conflict.
 */
final class TranslationConflictException extends RuntimeException
{
    /**
     * @param  list<string>  $locales  the locales whose files are now stale
     */
    public function __construct(public readonly array $locales)
    {
        parent::__construct('Translation files changed on disk: '.implode(', ', $locales).'.');
    }
}

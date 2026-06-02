<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Exceptions;

use RuntimeException;

final class TranslationPathException extends RuntimeException
{
    public static function outsideRoot(string $path): self
    {
        return new self("Refusing to access [{$path}]: it is outside the configured translation root.");
    }

    public static function invalidLocale(string $locale): self
    {
        return new self("Invalid locale [{$locale}]. Allowed characters: A-Z a-z 0-9 _ -.");
    }

    public static function invalidGroup(string $group): self
    {
        return new self("Invalid translation group [{$group}].");
    }
}

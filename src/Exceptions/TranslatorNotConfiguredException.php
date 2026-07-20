<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Exceptions;

use Kurt\Modules\I18n\Support\NullTranslator;
use RuntimeException;

/**
 * Thrown by {@see NullTranslator} when a translation
 * is requested but no real translator has been wired up.
 *
 * Failing loudly is deliberate: silently returning the untranslated source
 * would fill a target locale with the reference language, which is worse than
 * an obvious error the operator can act on.
 */
final class TranslatorNotConfiguredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'No translator is configured. Bind an implementation of '
            .'Kurt\Modules\I18n\Contracts\Translator via config("i18n.translator") to translate keys.'
        );
    }
}

<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Support;

use Kurt\Modules\I18n\Contracts\Translator;
use Kurt\Modules\I18n\Exceptions\TranslatorNotConfiguredException;

/**
 * The default {@see Translator}: it refuses to translate.
 *
 * It exists so the seam is always bound and the "translate missing keys" action
 * fails with a clear, actionable error until the consumer provides a real
 * implementation. It never silently passes the source text through.
 */
final class NullTranslator implements Translator
{
    public function translate(string $text, string $from, string $to): string
    {
        throw new TranslatorNotConfiguredException;
    }
}

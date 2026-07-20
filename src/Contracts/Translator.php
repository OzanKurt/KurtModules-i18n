<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Contracts;

/**
 * A machine-translation seam.
 *
 * This package ships only the contract and a {@see
 * \Kurt\Modules\I18n\Support\NullTranslator} default that refuses to run; the
 * consumer binds a real implementation (DeepL, Google Translate, an LLM, …) via
 * `config('i18n.translator')`. Implementations translate a single string and
 * should be pure with respect to the given text — no key or file context leaks
 * in, so they stay trivially testable and swappable.
 */
interface Translator
{
    /**
     * Translate a single string.
     *
     * @param  string  $text  the source string
     * @param  string  $from  the source locale code (e.g. "en")
     * @param  string  $to  the target locale code (e.g. "tr")
     */
    public function translate(string $text, string $from, string $to): string;
}

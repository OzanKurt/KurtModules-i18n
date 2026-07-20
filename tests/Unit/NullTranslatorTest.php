<?php

declare(strict_types=1);

use Kurt\Modules\I18n\Contracts\Translator;
use Kurt\Modules\I18n\Exceptions\TranslatorNotConfiguredException;
use Kurt\Modules\I18n\Support\NullTranslator;

it('is the default translator implementation', function (): void {
    expect(new NullTranslator)->toBeInstanceOf(Translator::class);
});

it('refuses to translate rather than passing the source through', function (): void {
    (new NullTranslator)->translate('Hello', 'en', 'tr');
})->throws(TranslatorNotConfiguredException::class);

<?php

declare(strict_types=1);

use Kurt\Modules\Core\Contracts\ModuleRegistry;

it('declares its manifest into the registry', function () {
    $registry = app(ModuleRegistry::class);

    expect($registry->has('i18n'))->toBeTrue()
        ->and($registry->get('i18n')->getName())->toBe('i18n');
});

<?php

declare(strict_types=1);

it('boots and merges the i18n config', function (): void {
    expect(config('i18n.route.prefix'))->toBe('i18n')
        ->and(config('i18n.enabled_environments'))->toBe(['local'])
        ->and(config('i18n.backups.enabled'))->toBeTrue();
});

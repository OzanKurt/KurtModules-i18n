<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;

it('boots and merges the i18n config', function (): void {
    expect(config('i18n.route.prefix'))->toBe('i18n')
        ->and(config('i18n.enabled_environments'))->toBe(['local'])
        ->and(config('i18n.backups.enabled'))->toBeTrue();
});

it('exposes the api kit http config block', function (): void {
    expect(config('i18n.http.prefix'))->toBe('api/i18n')
        ->and(config('i18n.http.middleware'))->toBe(['api'])
        ->and(config('i18n.http.auth_middleware'))->toBe(['auth'])
        ->and(config('i18n.http.rate_limit'))->toBe('60,1');
});

it('registers the manageTranslations gate', function (): void {
    expect(Gate::has('i18n.manageTranslations'))->toBeTrue();
});

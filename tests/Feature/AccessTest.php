<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Kurt\Modules\I18n\Support\TranslationManager;

beforeEach(function (): void {
    $this->root = i18n_tmp_dir();
    $this->app->instance(TranslationManager::class, i18n_manager($this->root));
});

afterEach(function (): void {
    i18n_rrmdir($this->root);
});

it('denies access outside enabled environments without the gate', function (): void {
    $this->get('/i18n')->assertForbidden();
});

it('allows access when the current environment is enabled', function (): void {
    config()->set('i18n.enabled_environments', ['testing']);

    $this->get('/i18n')->assertOk();
});

it('allows access when the viewI18n gate passes', function (): void {
    Gate::define('viewI18n', fn ($user = null): bool => true);

    $this->get('/i18n')->assertOk();
});

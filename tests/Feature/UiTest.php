<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Kurt\Modules\I18n\Support\TranslationManager;

beforeEach(function (): void {
    $this->root = i18n_tmp_dir();
    $this->app->instance(TranslationManager::class, i18n_manager($this->root));
    Gate::define('viewI18n', fn ($user = null): bool => true);
});

afterEach(function (): void {
    i18n_rrmdir($this->root);
});

it('renders the ui shell with bootstrap data when authorized', function (): void {
    $this->get('/i18n')
        ->assertOk()
        ->assertSee('id="view"', false)
        ->assertSee('id="i18n-bootstrap"', false);
});

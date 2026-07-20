<?php

declare(strict_types=1);

beforeEach(function (): void {
    // Even a fully-privileged actor must not reach routes that were never
    // registered: safe-by-default means headless registers nothing at all.
    config()->set('i18n.enabled_environments', ['testing']);
    $this->actingAs(i18n_actor());
});

it('registers no REST API routes in headless mode', function (): void {
    $this->getJson('/api/i18n/catalog')->assertNotFound();
    $this->getJson('/api/i18n/groups')->assertNotFound();
    $this->getJson('/api/i18n/locales')->assertNotFound();
    $this->patchJson('/api/i18n/json', ['baseHashes' => [], 'ops' => []])->assertNotFound();
});

it('registers no UI shell in headless mode', function (): void {
    $this->get('/i18n')->assertNotFound();
});

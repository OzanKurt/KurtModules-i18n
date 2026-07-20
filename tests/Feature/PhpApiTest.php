<?php

declare(strict_types=1);

use Kurt\Modules\I18n\Support\TranslationManager;

beforeEach(function (): void {
    $this->root = i18n_tmp_dir();
    $this->app->instance(TranslationManager::class, i18n_manager($this->root));
    config()->set('i18n.enabled_environments', ['testing']);
    $this->actingAs(i18n_actor());
});

afterEach(function (): void {
    i18n_rrmdir($this->root);
});

it('returns a php grid flattening nested keys to dot-paths', function (): void {
    mkdir($this->root.'/en', 0777, true);
    file_put_contents($this->root.'/en/users.php', "<?php return ['title' => ['icon_tooltip' => 'Manage']];");

    $data = $this->getJson('/api/i18n/php/users?locales=en')->assertOk()->json('data');

    expect($data['keys'])->toBe(['title.icon_tooltip'])
        ->and($data['rows']['title.icon_tooltip']['en'])->toBe('Manage');
});

it('applies a nested set op writing valid nested php', function (): void {
    $base = $this->getJson('/api/i18n/php/users?locales=en')->json('data.hashes');

    $this->patchJson('/api/i18n/php/users', [
        'baseHashes' => $base,
        'ops' => [['op' => 'set', 'locale' => 'en', 'key' => 'title.icon_tooltip', 'value' => 'Manage users']],
    ])->assertOk()->assertJsonPath('data.changed.0', 'en');

    expect(require $this->root.'/en/users.php')->toBe(['title' => ['icon_tooltip' => 'Manage users']]);
});

it('rejects an invalid group name', function (): void {
    $this->getJson('/api/i18n/php/bad.name?locales=en')->assertStatus(422);
});

it('rejects an invalid locale query', function (): void {
    $this->getJson('/api/i18n/php/users?locales=en,b@d')->assertStatus(422);
});

it('reads and writes a vendor namespaced group over the api', function (): void {
    mkdir($this->root.'/vendor/firewall/en', 0777, true);
    file_put_contents($this->root.'/vendor/firewall/en/notifications.php', "<?php return ['greeting' => 'Hello'];");

    $base = $this->getJson('/api/i18n/php/firewall::notifications?locales=en')
        ->assertOk()
        ->assertJsonPath('data.keys.0', 'greeting')
        ->json('data.hashes');

    $this->patchJson('/api/i18n/php/firewall::notifications', [
        'baseHashes' => $base,
        'ops' => [['op' => 'set', 'locale' => 'en', 'key' => 'greeting', 'value' => 'Hi']],
    ])->assertOk()->assertJsonPath('data.changed.0', 'en');

    expect(require $this->root.'/vendor/firewall/en/notifications.php')->toBe(['greeting' => 'Hi']);
});

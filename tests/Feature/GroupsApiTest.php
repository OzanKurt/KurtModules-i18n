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

it('lists every translation group as type/group pairs', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['a' => '1']));
    mkdir($this->root.'/en', 0777, true);
    file_put_contents($this->root.'/en/users.php', "<?php return ['title' => 'Users'];");
    mkdir($this->root.'/vendor/firewall/en', 0777, true);
    file_put_contents($this->root.'/vendor/firewall/en/notifications.php', "<?php return ['hi' => 'Hi'];");

    $groups = $this->getJson('/api/i18n/groups')
        ->assertOk()
        ->assertJsonPath('meta.total', 3)
        ->json('data');

    expect($groups)->toContain(['type' => 'json', 'group' => null])
        ->toContain(['type' => 'php', 'group' => 'users'])
        ->toContain(['type' => 'php', 'group' => 'firewall::notifications']);
});

it('returns an empty group list when no translations exist', function (): void {
    $this->getJson('/api/i18n/groups')
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.total', 0);
});

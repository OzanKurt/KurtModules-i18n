<?php

declare(strict_types=1);

use Kurt\Modules\I18n\Support\LangPaths;
use Kurt\Modules\I18n\Support\LocaleScanner;

beforeEach(function (): void {
    $this->dir = i18n_tmp_dir();
});

afterEach(function (): void {
    i18n_rrmdir($this->dir);
});

it('discovers json locales, php groups (including nested) and the locale union', function (): void {
    file_put_contents($this->dir.'/en.json', '{}');
    file_put_contents($this->dir.'/tr.json', '{}');
    mkdir($this->dir.'/en/admin', 0777, true);
    file_put_contents($this->dir.'/en/users.php', '<?php return [];');
    file_put_contents($this->dir.'/en/admin/users.php', '<?php return [];');
    mkdir($this->dir.'/de', 0777, true);
    file_put_contents($this->dir.'/de/users.php', '<?php return [];');

    $catalog = (new LocaleScanner(new LangPaths($this->dir)))->scan();

    expect($catalog->jsonLocales)->toBe(['en', 'tr'])
        ->and($catalog->phpGroups)->toBe(['admin/users', 'users'])
        ->and($catalog->locales)->toBe(['de', 'en', 'tr']);
});

it('returns an empty catalog for a missing root', function (): void {
    $catalog = (new LocaleScanner(new LangPaths($this->dir.'/nope')))->scan();

    expect($catalog->locales)->toBe([])
        ->and($catalog->jsonLocales)->toBe([])
        ->and($catalog->phpGroups)->toBe([]);
});

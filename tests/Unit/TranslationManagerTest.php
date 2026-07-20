<?php

declare(strict_types=1);

use Illuminate\Events\Dispatcher;
use Kurt\Modules\I18n\Enums\FileType;
use Kurt\Modules\I18n\Events\TranslationsChanged;
use Kurt\Modules\I18n\Exceptions\TranslationConflictException;
use Kurt\Modules\I18n\Support\ArrayExporter;
use Kurt\Modules\I18n\Support\EditOperation;
use Kurt\Modules\I18n\Support\LangPaths;
use Kurt\Modules\I18n\Support\TranslationManager;

beforeEach(function (): void {
    $this->root = i18n_tmp_dir();
    $this->manager = new TranslationManager(new LangPaths($this->root), new ArrayExporter);
});

afterEach(function (): void {
    i18n_rrmdir($this->root);
});

it('builds a json grid with the locale union and null for missing cells', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['greeting' => 'Hi', 'bye' => 'Bye']));
    file_put_contents($this->root.'/tr.json', json_encode(['greeting' => 'Selam']));

    $grid = $this->manager->grid(FileType::Json, null, ['tr', 'en']);

    expect($grid['keys'])->toBe(['bye', 'greeting'])
        ->and($grid['rows']['greeting'])->toBe(['tr' => 'Selam', 'en' => 'Hi'])
        ->and($grid['rows']['bye'])->toBe(['tr' => null, 'en' => 'Bye'])
        ->and($grid['hashes']['en'])->not->toBeNull();
});

it('builds a php grid flattening nested keys to dot-paths', function (): void {
    mkdir($this->root.'/en', 0777, true);
    file_put_contents($this->root.'/en/users.php', "<?php return ['title' => ['icon_tooltip' => 'Manage']];");

    $grid = $this->manager->grid(FileType::Php, 'users', ['en']);

    expect($grid['keys'])->toBe(['title.icon_tooltip'])
        ->and($grid['rows']['title.icon_tooltip'])->toBe(['en' => 'Manage']);
});

it('applies a set op, creating a nested php file and reporting the change', function (): void {
    $result = $this->manager->apply(
        FileType::Php,
        'users',
        ['en' => null],
        [EditOperation::set('en', 'title.icon_tooltip', 'Manage users')],
    );

    expect($result['changed'])->toBe(['en'])
        ->and(is_file($this->root.'/en/users.php'))->toBeTrue()
        ->and($this->manager->grid(FileType::Php, 'users', ['en'])['rows']['title.icon_tooltip']['en'])
        ->toBe('Manage users');
});

it('deletes a key across the loaded locales', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['a' => '1', 'b' => '2']));
    file_put_contents($this->root.'/tr.json', json_encode(['a' => 'x', 'b' => 'y']));
    $base = $this->manager->grid(FileType::Json, null, ['en', 'tr'])['hashes'];

    $this->manager->apply(FileType::Json, null, $base, [EditOperation::delete('a')]);

    expect($this->manager->grid(FileType::Json, null, ['en', 'tr'])['keys'])->toBe(['b']);
});

it('renames a key preserving each locale value', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['old' => 'value-en']));
    file_put_contents($this->root.'/tr.json', json_encode(['old' => 'value-tr']));
    $base = $this->manager->grid(FileType::Json, null, ['en', 'tr'])['hashes'];

    $this->manager->apply(FileType::Json, null, $base, [EditOperation::rename('old', 'new')]);

    expect($this->manager->grid(FileType::Json, null, ['en', 'tr'])['rows']['new'])
        ->toBe(['en' => 'value-en', 'tr' => 'value-tr']);
});

it('does not collapse a subtree when renaming a non-leaf key', function (): void {
    mkdir($this->root.'/en', 0777, true);
    file_put_contents($this->root.'/en/users.php', "<?php return ['title' => ['icon' => 'Manage', 'label' => 'Users']];");
    $base = $this->manager->grid(FileType::Php, 'users', ['en'])['hashes'];

    // 'title' is a non-leaf (its value is an array); renaming it must be a
    // no-op rather than wiping its children.
    $this->manager->apply(FileType::Php, 'users', $base, [EditOperation::rename('title', 'heading')]);

    expect($this->manager->grid(FileType::Php, 'users', ['en'])['keys'])
        ->toBe(['title.icon', 'title.label']);
});

it('throws a conflict when a base hash is stale', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['a' => '1']));

    $this->manager->apply(FileType::Json, null, ['en' => 'deadbeef'], [EditOperation::set('en', 'a', '2')]);
})->throws(TranslationConflictException::class);

it('adds a new empty locale file', function (): void {
    $result = $this->manager->addLocale(FileType::Json, 'fr');

    expect($result['locale'])->toBe('fr')
        ->and(is_file($this->root.'/fr.json'))->toBeTrue()
        ->and($this->manager->grid(FileType::Json, null, ['fr'])['keys'])->toBe([]);
});

it('rejects a set op for a locale that was not loaded', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['a' => '1']));
    $base = $this->manager->grid(FileType::Json, null, ['en'])['hashes'];

    $this->manager->apply(FileType::Json, null, $base, [EditOperation::set('tr', 'a', '2')]);
})->throws(InvalidArgumentException::class);

it('raises a conflict instead of overwriting when a second writer changes a file mid-apply', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['a' => '1']));
    $base = $this->manager->grid(FileType::Json, null, ['en'])['hashes'];

    // A manager whose in-flight apply is interrupted by a concurrent writer that
    // rewrites the file after we read it but before we write our own edits.
    $manager = new class(new LangPaths($this->root), new ArrayExporter) extends TranslationManager
    {
        public ?Closure $onBeforeWrites = null;

        protected function beforeApplyWrites(): void
        {
            if ($this->onBeforeWrites !== null) {
                ($this->onBeforeWrites)();
            }
        }
    };

    $manager->onBeforeWrites = function () use ($manager): void {
        // Prevent infinite recursion; the racing writer only strikes once.
        $manager->onBeforeWrites = null;
        file_put_contents($this->root.'/en.json', json_encode(['a' => 'from-other-writer']));
    };

    expect(fn () => $manager->apply(FileType::Json, null, $base, [EditOperation::set('en', 'a', '2')]))
        ->toThrow(TranslationConflictException::class);

    // The other writer's value must survive: no silent last-writer-wins overwrite.
    expect(json_decode((string) file_get_contents($this->root.'/en.json'), true))
        ->toBe(['a' => 'from-other-writer']);
});

it('leaves every locale unchanged when one locale in the batch fails to commit', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['keep' => 'en-value']));
    // A directory sitting where tr.json should be makes the tr write fail at the
    // swap step, after en has already been committed — forcing a rollback.
    mkdir($this->root.'/tr.json', 0777, true);

    $base = ['en' => hash_file('sha1', $this->root.'/en.json'), 'tr' => null];

    expect(fn () => $this->manager->apply(FileType::Json, null, $base, [
        EditOperation::set('en', 'new', 'en-added'),
        EditOperation::set('tr', 'new', 'tr-added'),
    ]))->toThrow(RuntimeException::class);

    // en must be rolled back to its pre-batch contents (no partial apply).
    expect(json_decode((string) file_get_contents($this->root.'/en.json'), true))
        ->toBe(['keep' => 'en-value'])
        ->and(is_dir($this->root.'/tr.json'))->toBeTrue();
});

it('dispatches TranslationsChanged with the changed locales and ops on a real change', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['a' => '1']));
    file_put_contents($this->root.'/tr.json', json_encode(['a' => 'x']));

    $dispatcher = new Dispatcher;
    $captured = [];
    $dispatcher->listen(TranslationsChanged::class, function (TranslationsChanged $event) use (&$captured): void {
        $captured[] = $event;
    });

    $manager = new TranslationManager(new LangPaths($this->root), new ArrayExporter, null, $dispatcher);
    $base = $manager->grid(FileType::Json, null, ['en', 'tr'])['hashes'];
    $ops = [EditOperation::set('en', 'a', '2')];

    $manager->apply(FileType::Json, null, $base, $ops);

    expect($captured)->toHaveCount(1)
        ->and($captured[0]->type)->toBe(FileType::Json)
        ->and($captured[0]->group)->toBeNull()
        ->and($captured[0]->changedLocales)->toBe(['en'])
        ->and($captured[0]->ops)->toBe($ops)
        ->and($captured[0]->actor)->toBeNull();
});

it('does not dispatch TranslationsChanged on a no-op batch', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['a' => '1']));

    $dispatcher = new Dispatcher;
    $fired = false;
    $dispatcher->listen(TranslationsChanged::class, function () use (&$fired): void {
        $fired = true;
    });

    $manager = new TranslationManager(new LangPaths($this->root), new ArrayExporter, null, $dispatcher);
    $base = $manager->grid(FileType::Json, null, ['en'])['hashes'];

    // Setting the key to its current value changes nothing on disk.
    $result = $manager->apply(FileType::Json, null, $base, [EditOperation::set('en', 'a', '1')]);

    expect($result['changed'])->toBe([])
        ->and($fired)->toBeFalse();
});

it('passes the resolved actor to the dispatched event', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['a' => '1']));

    $dispatcher = new Dispatcher;
    $captured = [];
    $dispatcher->listen(TranslationsChanged::class, function (TranslationsChanged $event) use (&$captured): void {
        $captured[] = $event;
    });

    $manager = new TranslationManager(
        new LangPaths($this->root),
        new ArrayExporter,
        null,
        $dispatcher,
        static fn (): string => 'editor@example.com',
    );
    $base = $manager->grid(FileType::Json, null, ['en'])['hashes'];

    $manager->apply(FileType::Json, null, $base, [EditOperation::set('en', 'a', '2')]);

    expect($captured)->toHaveCount(1)
        ->and($captured[0]->actor)->toBe('editor@example.com');
});

it('memoizes the catalog and refreshes it after a write', function (): void {
    file_put_contents($this->root.'/en.json', json_encode(['a' => '1']));

    $first = $this->manager->catalog();

    // A file added out of band is not reflected while the memo stands.
    file_put_contents($this->root.'/tr.json', json_encode(['a' => 'x']));

    expect($this->manager->catalog())->toBe($first)
        ->and($first->jsonLocales)->toBe(['en']);

    // A write through the manager invalidates the memo, so the next scan is fresh.
    $this->manager->addLocale(FileType::Json, 'de');

    expect($this->manager->catalog())->not->toBe($first)
        ->and($this->manager->catalog()->jsonLocales)->toBe(['de', 'en', 'tr']);
});

it('reads and writes a vendor namespaced group under lang/vendor', function (): void {
    mkdir($this->root.'/vendor/firewall/en', 0777, true);
    file_put_contents($this->root.'/vendor/firewall/en/notifications.php', "<?php return ['greeting' => 'Hello'];");

    $grid = $this->manager->grid(FileType::Php, 'firewall::notifications', ['en']);
    expect($grid['rows']['greeting']['en'])->toBe('Hello');

    $this->manager->apply(FileType::Php, 'firewall::notifications', $grid['hashes'], [EditOperation::set('en', 'greeting', 'Hi')]);
    expect(require $this->root.'/vendor/firewall/en/notifications.php')->toBe(['greeting' => 'Hi']);

    $this->manager->addLocale(FileType::Php, 'de', 'firewall::notifications');
    expect(is_file($this->root.'/vendor/firewall/de/notifications.php'))->toBeTrue();
});

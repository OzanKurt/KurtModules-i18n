<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Support;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Kurt\Modules\I18n\Enums\FileType;
use Kurt\Modules\I18n\Events\TranslationsChanged;
use Kurt\Modules\I18n\Exceptions\TranslationConflictException;
use Throwable;

/**
 * Orchestrates reading and writing translation files for the HTTP layer.
 *
 * Builds locale-comparison grids, applies batches of {@see EditOperation}s under
 * an exclusive per-group lock with optimistic-hash conflict detection, and
 * creates new locale files. It never touches a file outside the configured root
 * (paths flow through {@see LangPaths}).
 *
 * The class is left non-final so a test can subclass it to exercise the
 * concurrency guard via {@see self::beforeApplyWrites()}; treat it as final.
 */
class TranslationManager
{
    private ?TranslationCatalog $catalog = null;

    /**
     * @param  Closure(): mixed|null  $actorResolver  resolves the actor behind a change (e.g. the authenticated user)
     */
    public function __construct(
        private readonly LangPaths $paths,
        private readonly ArrayExporter $exporter,
        private readonly ?FileBackup $backup = null,
        private readonly ?Dispatcher $events = null,
        private readonly ?Closure $actorResolver = null,
    ) {}

    public function catalog(): TranslationCatalog
    {
        // Memoized for the lifetime of the instance so a single request that
        // hits the catalog several times (e.g. the default-locales fallback
        // plus a grid render) scans the tree once. Writes reset the memo.
        return $this->catalog ??= (new LocaleScanner($this->paths))->scan();
    }

    /**
     * Every translation group known on disk, as (type, group) pairs: the JSON
     * pseudo-group (group `null`) followed by every project and vendor PHP
     * group. Vendor groups are namespaced as `{package}::{group}`.
     *
     * Used by the cross-group tools (missing-key report, export) so they iterate
     * exactly the same set of files the UI can open.
     *
     * @return list<array{type: FileType, group: string|null}>
     */
    public function groups(): array
    {
        $catalog = $this->catalog();

        $groups = [];

        if ($catalog->jsonLocales !== []) {
            $groups[] = ['type' => FileType::Json, 'group' => null];
        }

        foreach ($catalog->phpGroups as $group) {
            $groups[] = ['type' => FileType::Php, 'group' => $group];
        }

        foreach ($catalog->vendor as $package) {
            foreach ($package['groups'] as $group) {
                $groups[] = ['type' => FileType::Php, 'group' => $package['name'].'::'.$group];
            }
        }

        return $groups;
    }

    /**
     * @param  list<string>  $locales
     * @return array{keys: list<string>, rows: array<string, array<string, string|null>>, hashes: array<string, string|null>}
     */
    public function grid(FileType $type, ?string $group, array $locales): array
    {
        $hashes = [];
        $values = [];
        $keys = [];

        foreach ($locales as $locale) {
            $file = $this->file($type, $group, $locale);
            $hashes[$locale] = $file->hash();

            foreach ($this->flatten($type, $file->read()) as $key => $value) {
                $keys[$key] = true;
                $values[$key][$locale] = $value;
            }
        }

        $keyList = array_keys($keys);
        sort($keyList);

        $rows = [];

        foreach ($keyList as $key) {
            foreach ($locales as $locale) {
                $rows[$key][$locale] = $values[$key][$locale] ?? null;
            }
        }

        return ['keys' => $keyList, 'rows' => $rows, 'hashes' => $hashes];
    }

    /**
     * @param  array<string, string|null>  $baseHashes  locale => hash the client loaded
     * @param  list<EditOperation>  $ops
     * @return array{hashes: array<string, string|null>, changed: list<string>}
     *
     * @throws TranslationConflictException when a file changed on disk since load
     */
    public function apply(FileType $type, ?string $group, array $baseHashes, array $ops): array
    {
        $locales = array_keys($baseHashes);

        // Hold an exclusive per-group lock across the whole read-modify-write.
        // Without it two saves sharing the same base hashes could both pass the
        // optimistic check and the last writer would silently clobber the first.
        return $this->lock($type, $group)->withExclusive(
            fn (): array => $this->applyLocked($type, $group, $baseHashes, $ops, $locales),
        );
    }

    /**
     * @param  array<string, string|null>  $baseHashes
     * @param  list<EditOperation>  $ops
     * @param  list<string>  $locales
     * @return array{hashes: array<string, string|null>, changed: list<string>}
     *
     * @throws TranslationConflictException
     */
    private function applyLocked(FileType $type, ?string $group, array $baseHashes, array $ops, array $locales): array
    {
        $files = [];
        $data = [];
        $original = [];
        $snapshot = [];
        $stale = [];

        foreach ($locales as $locale) {
            $file = $this->file($type, $group, $locale);
            $hash = $file->hash();

            if (($baseHashes[$locale] ?? null) !== $hash) {
                $stale[] = $locale;
            }

            $files[$locale] = $file;
            $data[$locale] = $file->read();
            $original[$locale] = $data[$locale];
            $snapshot[$locale] = $hash;
        }

        if ($stale !== []) {
            throw new TranslationConflictException($stale);
        }

        foreach ($ops as $op) {
            $this->applyOp($type, $op, $data, $locales);
        }

        // Test seam: lets a subclass simulate a second writer landing between the
        // read above and the re-check below. No-op in production.
        $this->beforeApplyWrites();

        // Re-compare every file hash under the lock immediately before writing.
        // A change here means a writer raced in after our read, so we abort with
        // a conflict rather than overwriting their work.
        $stale = [];

        foreach ($locales as $locale) {
            if ($files[$locale]->hash() !== $snapshot[$locale]) {
                $stale[] = $locale;
            }
        }

        if ($stale !== []) {
            throw new TranslationConflictException($stale);
        }

        $changed = $this->commitBatch($files, $data, $original);

        $hashes = [];

        foreach ($locales as $locale) {
            $hashes[$locale] = $files[$locale]->hash();
        }

        if ($changed !== []) {
            $this->catalog = null;
            $this->dispatchChanged($type, $group, $changed, $ops);
        }

        return ['hashes' => $hashes, 'changed' => $changed];
    }

    /**
     * Stage every changed locale, then swap them all in. Because the fallible
     * work (encode + verify) happens for the whole batch before any file is
     * touched, and a mid-swap failure rolls previously swapped files back to
     * their original contents, the batch is all-or-nothing across locales.
     *
     * @param  array<string, TranslationFile>  $files
     * @param  array<string, array<array-key, mixed>>  $data  post-edit data, keyed by locale
     * @param  array<string, array<array-key, mixed>>  $original  pre-edit data, keyed by locale
     * @return list<string> the locales whose files changed
     */
    private function commitBatch(array $files, array $data, array $original): array
    {
        $staged = [];

        try {
            foreach ($files as $locale => $file) {
                if (serialize($data[$locale]) !== serialize($original[$locale])) {
                    $staged[$locale] = $file->stage($data[$locale]);
                }
            }
        } catch (Throwable $e) {
            foreach ($staged as $locale => $temporary) {
                $files[$locale]->discard($temporary);
            }

            throw $e;
        }

        $committed = [];

        try {
            foreach ($staged as $locale => $temporary) {
                $files[$locale]->commit($temporary);
                $committed[] = $locale;
            }
        } catch (Throwable $e) {
            // Roll back any file already swapped in, and drop the rest.
            foreach ($committed as $locale) {
                try {
                    $files[$locale]->write($original[$locale]);
                } catch (Throwable) {
                    // Best effort: keep restoring the remaining locales.
                }
            }

            foreach ($staged as $locale => $temporary) {
                if (! in_array($locale, $committed, true)) {
                    $files[$locale]->discard($temporary);
                }
            }

            throw $e;
        }

        return array_keys($staged);
    }

    /**
     * Hook fired after the batch is read and edited in memory but before any
     * file is written. A no-op by default; overridden only in tests.
     */
    protected function beforeApplyWrites(): void {}

    private function lock(FileType $type, ?string $group): TranslationLock
    {
        $key = sha1($this->paths->root().'|'.$type->value.'|'.($group ?? ''));

        return new TranslationLock(sys_get_temp_dir().'/i18n-locks/'.$key.'.lock');
    }

    /**
     * @param  list<string>  $changedLocales
     * @param  list<EditOperation>  $ops
     */
    private function dispatchChanged(FileType $type, ?string $group, array $changedLocales, array $ops): void
    {
        if ($this->events === null) {
            return;
        }

        $actor = $this->actorResolver !== null ? ($this->actorResolver)() : null;

        $this->events->dispatch(new TranslationsChanged($type, $group, $changedLocales, $ops, $actor));
    }

    /**
     * @return array{locale: string, hash: string|null}
     */
    public function addLocale(FileType $type, string $locale, ?string $group = null): array
    {
        $file = $this->file($type, $group, $locale);

        if (! $file->exists()) {
            $file->write([]);
            $this->catalog = null;
        }

        return ['locale' => $locale, 'hash' => $file->hash()];
    }

    /**
     * @param  array<string, array<array-key, mixed>>  $data  keyed by locale, mutated in place
     * @param  list<string>  $locales
     */
    private function applyOp(FileType $type, EditOperation $op, array &$data, array $locales): void
    {
        switch ($op->op) {
            case 'set':
                $locale = (string) $op->locale;

                if (! in_array($locale, $locales, true)) {
                    throw new InvalidArgumentException("Edit targets locale [{$locale}] which was not loaded.");
                }

                $this->setValue($type, $data[$locale], (string) $op->key, (string) $op->value);
                break;

            case 'delete':
                foreach ($locales as $locale) {
                    $this->forgetValue($type, $data[$locale], (string) $op->key);
                }
                break;

            case 'rename':
                foreach ($locales as $locale) {
                    if (! $this->hasValue($type, $data[$locale], (string) $op->from)) {
                        continue;
                    }

                    $value = $this->getValue($type, $data[$locale], (string) $op->from);

                    // Refuse to rename a non-leaf key: collapsing its subtree to
                    // an empty string would silently drop all child translations.
                    if (! is_string($value)) {
                        continue;
                    }

                    $this->forgetValue($type, $data[$locale], (string) $op->from);
                    $this->setValue($type, $data[$locale], (string) $op->to, $value);
                }
                break;
        }
    }

    /**
     * @param  array<array-key, mixed>  $array
     */
    private function setValue(FileType $type, array &$array, string $key, string $value): void
    {
        if ($type === FileType::Json) {
            $array[$key] = $value;
        } else {
            Arr::set($array, $key, $value);
        }
    }

    /**
     * @param  array<array-key, mixed>  $array
     */
    private function hasValue(FileType $type, array $array, string $key): bool
    {
        return $type === FileType::Json ? array_key_exists($key, $array) : Arr::has($array, $key);
    }

    /**
     * @param  array<array-key, mixed>  $array
     */
    private function getValue(FileType $type, array $array, string $key): mixed
    {
        return $type === FileType::Json ? ($array[$key] ?? null) : Arr::get($array, $key);
    }

    /**
     * @param  array<array-key, mixed>  $array
     */
    private function forgetValue(FileType $type, array &$array, string $key): void
    {
        if ($type === FileType::Json) {
            unset($array[$key]);
        } else {
            Arr::forget($array, $key);
        }
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<string, string|null>
     */
    private function flatten(FileType $type, array $data): array
    {
        /** @var array<array-key, mixed> $flat */
        $flat = $type === FileType::Json ? $data : Arr::dot($data);

        $result = [];

        foreach ($flat as $key => $value) {
            $result[(string) $key] = is_scalar($value) ? (string) $value : null;
        }

        return $result;
    }

    private function file(FileType $type, ?string $group, string $locale): TranslationFile
    {
        if ($type === FileType::Json) {
            return new JsonTranslationFile($this->paths->jsonPath($locale), $this->backup);
        }

        if ($group === null) {
            throw new InvalidArgumentException('A group is required for PHP translation files.');
        }

        return new PhpArrayFile($this->paths->phpPath($group, $locale), $this->exporter, $this->backup);
    }
}

<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Support;

use Illuminate\Support\Arr;
use InvalidArgumentException;
use Kurt\Modules\I18n\Enums\FileType;
use Kurt\Modules\I18n\Exceptions\TranslationConflictException;

/**
 * Orchestrates reading and writing translation files for the HTTP layer.
 *
 * Builds locale-comparison grids, applies batches of {@see EditOperation}s with
 * optimistic-lock conflict detection, and creates new locale files. It never
 * touches a file outside the configured root (paths flow through {@see LangPaths}).
 */
final class TranslationManager
{
    public function __construct(
        private readonly LangPaths $paths,
        private readonly ArrayExporter $exporter,
        private readonly ?FileBackup $backup = null,
    ) {}

    public function catalog(): TranslationCatalog
    {
        return (new LocaleScanner($this->paths))->scan();
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

        $files = [];
        $data = [];
        $original = [];
        $stale = [];

        foreach ($locales as $locale) {
            $file = $this->file($type, $group, $locale);

            if (($baseHashes[$locale] ?? null) !== $file->hash()) {
                $stale[] = $locale;
            }

            $files[$locale] = $file;
            $data[$locale] = $file->read();
            $original[$locale] = serialize($data[$locale]);
        }

        if ($stale !== []) {
            throw new TranslationConflictException($stale);
        }

        foreach ($ops as $op) {
            $this->applyOp($type, $op, $data, $locales);
        }

        $changed = [];
        $hashes = [];

        foreach ($locales as $locale) {
            if (serialize($data[$locale]) !== $original[$locale]) {
                $files[$locale]->write($data[$locale]);
                $changed[] = $locale;
            }

            $hashes[$locale] = $files[$locale]->hash();
        }

        return ['hashes' => $hashes, 'changed' => $changed];
    }

    /**
     * @return array{locale: string, hash: string|null}
     */
    public function addLocale(FileType $type, string $locale, ?string $group = null): array
    {
        $file = $this->file($type, $group, $locale);

        if (! $file->exists()) {
            $file->write([]);
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

<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Support;

use Kurt\Modules\I18n\Contracts\Translator;
use Kurt\Modules\I18n\Enums\FileType;

/**
 * Fills a target locale's missing keys in one group by machine-translating the
 * reference values.
 *
 * For each key the reference defines but the target lacks, it asks the
 * configured {@see Translator} to translate the reference value and stages a
 * `set`. All the translations land as a single batch through
 * {@see TranslationManager::apply()}, so the write inherits the lock, backups,
 * and optimistic-hash conflict handling. Keys the target already has are left
 * untouched.
 */
final readonly class MissingKeyTranslator
{
    public function __construct(
        private TranslationManager $manager,
        private Translator $translator,
    ) {}

    /**
     * @param  array<string, string|null>  $baseHashes  must include the target locale
     * @return array{hashes: array<string, string|null>, changed: list<string>, translated: list<string>}
     */
    public function fill(FileType $type, ?string $group, string $reference, string $target, array $baseHashes): array
    {
        $grid = $this->manager->grid($type, $group, [$reference, $target]);

        $ops = [];
        $translated = [];

        foreach ($grid['keys'] as $key) {
            $source = $grid['rows'][$key][$reference] ?? null;

            // Translate only keys the reference defines and the target lacks.
            if ($source === null || ($grid['rows'][$key][$target] ?? null) !== null) {
                continue;
            }

            $ops[] = EditOperation::set($target, $key, $this->translator->translate($source, $reference, $target));
            $translated[] = $key;
        }

        // Always apply (even with no ops) so the optimistic-hash check still runs
        // and the caller gets fresh hashes back.
        $result = $this->manager->apply($type, $group, $baseHashes, $ops);

        return ['hashes' => $result['hashes'], 'changed' => $result['changed'], 'translated' => $translated];
    }
}

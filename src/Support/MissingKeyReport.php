<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Support;

use Kurt\Modules\I18n\Enums\FileType;

/**
 * Builds a cross-group "what still needs translating" report.
 *
 * Given a reference locale, it walks every group known on disk (the JSON
 * pseudo-group plus every project and vendor PHP group) and, for each target
 * locale, lists the keys that exist in the reference but are absent from that
 * locale's copy of the group. A group whose file is missing entirely for a
 * locale surfaces as every reference key being missing for it.
 */
final readonly class MissingKeyReport
{
    public function __construct(private TranslationManager $manager) {}

    /**
     * @param  list<string>|null  $targets  locales to check; defaults to every known locale except the reference
     * @return array{
     *     reference: string,
     *     locales: list<string>,
     *     groups: list<array{type: string, group: string|null, missing: array<string, list<string>>}>
     * }
     */
    public function generate(string $reference, ?array $targets = null): array
    {
        $targets = $this->resolveTargets($reference, $targets);

        $groups = [];

        foreach ($this->manager->groups() as $group) {
            $missing = $this->missingForGroup($group['type'], $group['group'], $reference, $targets);

            if ($missing !== []) {
                $groups[] = [
                    'type' => $group['type']->value,
                    'group' => $group['group'],
                    'missing' => $missing,
                ];
            }
        }

        return ['reference' => $reference, 'locales' => $targets, 'groups' => $groups];
    }

    /**
     * @param  list<string>  $targets
     * @return array<string, list<string>> only locales with at least one missing key
     */
    private function missingForGroup(FileType $type, ?string $group, string $reference, array $targets): array
    {
        $grid = $this->manager->grid($type, $group, [$reference, ...$targets]);

        $missing = [];

        foreach ($targets as $target) {
            $keys = [];

            foreach ($grid['keys'] as $key) {
                // Only keys the reference actually defines are candidates; a key
                // present only in some other locale is not "missing" here.
                if (($grid['rows'][$key][$reference] ?? null) === null) {
                    continue;
                }

                if (($grid['rows'][$key][$target] ?? null) === null) {
                    $keys[] = $key;
                }
            }

            if ($keys !== []) {
                $missing[$target] = $keys;
            }
        }

        return $missing;
    }

    /**
     * @param  list<string>|null  $targets
     * @return list<string>
     */
    private function resolveTargets(string $reference, ?array $targets): array
    {
        $targets ??= $this->manager->catalog()->locales;

        return array_values(array_filter(
            array_unique($targets),
            static fn (string $locale): bool => $locale !== $reference,
        ));
    }
}

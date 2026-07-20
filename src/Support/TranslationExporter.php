<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Support;

use Kurt\Modules\I18n\Enums\FileType;
use RuntimeException;

/**
 * Serializes a locale's translations to flat `key,value` rows in CSV or JSON.
 *
 * PHP groups are flattened to dot-paths (matching the edit grid), so a nested
 * `['title' => ['icon' => 'Manage']]` exports as the single key `title.icon`.
 * Only keys the locale actually defines are emitted; a `null` cell (the locale
 * lacks the key) is skipped.
 */
final readonly class TranslationExporter
{
    public function __construct(private TranslationManager $manager) {}

    /**
     * Rows for a single group and locale.
     *
     * @return list<array{key: string, value: string}>
     */
    public function group(FileType $type, ?string $group, string $locale): array
    {
        $grid = $this->manager->grid($type, $group, [$locale]);

        $rows = [];

        foreach ($grid['keys'] as $key) {
            $value = $grid['rows'][$key][$locale] ?? null;

            if ($value !== null) {
                $rows[] = ['key' => $key, 'value' => $value];
            }
        }

        return $rows;
    }

    /**
     * Rows for every group a locale participates in, each tagged with its
     * `type` and `group` so the flat list stays unambiguous across groups.
     *
     * @return list<array{type: string, group: string, key: string, value: string}>
     */
    public function all(string $locale): array
    {
        $rows = [];

        foreach ($this->manager->groups() as $group) {
            foreach ($this->group($group['type'], $group['group'], $locale) as $row) {
                $rows[] = [
                    'type' => $group['type']->value,
                    'group' => (string) $group['group'],
                    'key' => $row['key'],
                    'value' => $row['value'],
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @param  list<string>  $columns
     */
    public function toCsv(array $rows, array $columns): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException('Unable to open a temporary stream for CSV export.');
        }

        // Escape "" disables PHP's non-standard backslash escaping, keeping the
        // output RFC 4180 compliant (and silencing the 8.4 default-escape notice).
        fputcsv($handle, $columns, ',', '"', '');

        foreach ($rows as $row) {
            fputcsv($handle, array_map(static fn (string $column): string => $row[$column] ?? '', $columns), ',', '"', '');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    public function toJson(array $rows): string
    {
        $json = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException('Unable to encode the export to JSON: '.json_last_error_msg().'.');
        }

        return $json."\n";
    }
}

<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Support;

use Kurt\Modules\I18n\Enums\FileType;
use Kurt\Modules\I18n\Enums\PortableFormat;
use Kurt\Modules\I18n\Exceptions\MalformedImportException;
use RuntimeException;

/**
 * Parses a CSV/JSON `key,value` payload and applies it to a single group+locale.
 *
 * Parsing and applying are split so the caller can reject a malformed upload
 * before any write is attempted. The apply step goes through
 * {@see TranslationManager::apply()}, so it inherits the exclusive per-group
 * lock, optimistic-hash conflict detection, and backups — an import is just a
 * batch of `set` operations, never a raw file overwrite.
 */
final readonly class TranslationImporter
{
    public function __construct(private TranslationManager $manager) {}

    /**
     * @return array<string, string> key => value
     *
     * @throws MalformedImportException
     */
    public function parse(PortableFormat $format, string $content): array
    {
        return match ($format) {
            PortableFormat::Csv => $this->parseCsv($content),
            PortableFormat::Json => $this->parseJson($content),
        };
    }

    /**
     * Apply parsed rows to one locale as a batch of `set` operations.
     *
     * @param  array<string, string>  $rows  key => value
     * @param  array<string, string|null>  $baseHashes  must include the target locale
     * @return array{hashes: array<string, string|null>, changed: list<string>}
     */
    public function apply(FileType $type, ?string $group, string $locale, array $rows, array $baseHashes): array
    {
        $ops = [];

        foreach ($rows as $key => $value) {
            $ops[] = EditOperation::set($locale, $key, $value);
        }

        return $this->manager->apply($type, $group, $baseHashes, $ops);
    }

    /**
     * @return array<string, string>
     */
    private function parseCsv(string $content): array
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException('Unable to open a temporary stream for CSV import.');
        }

        fwrite($handle, $content);
        rewind($handle);

        $keyIndex = null;
        $valueIndex = null;
        $rows = [];

        while (($fields = fgetcsv($handle, null, ',', '"', '')) !== false) {
            // A blank line decodes to [null]; skip it.
            if ($fields === [null]) {
                continue;
            }

            if ($keyIndex === null) {
                $header = array_map(static fn (mixed $h): string => strtolower(trim((string) $h)), $fields);
                $keyIndex = array_search('key', $header, true);
                $valueIndex = array_search('value', $header, true);

                if ($keyIndex === false || $valueIndex === false) {
                    fclose($handle);

                    throw MalformedImportException::missingColumns();
                }

                continue;
            }

            $key = trim((string) ($fields[$keyIndex] ?? ''));

            if ($key === '') {
                fclose($handle);

                throw MalformedImportException::emptyKey();
            }

            $rows[$key] = (string) ($fields[$valueIndex] ?? '');
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return array<string, string>
     */
    private function parseJson(string $content): array
    {
        /** @var mixed $data */
        $data = json_decode($content, true);

        if (! is_array($data)) {
            throw MalformedImportException::invalidJson(json_last_error_msg());
        }

        // A list of {key, value} objects (mirrors the CSV rows) …
        if (array_is_list($data)) {
            return $this->rowsFromList($data);
        }

        // … or a flat { key: value } object of scalars.
        return $this->rowsFromObject($data);
    }

    /**
     * @param  list<mixed>  $data
     * @return array<string, string>
     */
    private function rowsFromList(array $data): array
    {
        $rows = [];

        foreach ($data as $row) {
            if (! is_array($row) || ! isset($row['key']) || ! is_scalar($row['key'])) {
                throw MalformedImportException::invalidShape();
            }

            $key = trim((string) $row['key']);

            if ($key === '') {
                throw MalformedImportException::emptyKey();
            }

            $value = $row['value'] ?? '';

            if (! is_scalar($value)) {
                throw MalformedImportException::invalidShape();
            }

            $rows[$key] = (string) $value;
        }

        return $rows;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<string, string>
     */
    private function rowsFromObject(array $data): array
    {
        $rows = [];

        foreach ($data as $key => $value) {
            if (! is_scalar($value)) {
                throw MalformedImportException::invalidShape();
            }

            $key = trim((string) $key);

            if ($key === '') {
                throw MalformedImportException::emptyKey();
            }

            $rows[$key] = (string) $value;
        }

        return $rows;
    }
}

<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Support;

use InvalidArgumentException;

/**
 * Renders a PHP array back to source code suitable for a Laravel lang file.
 *
 * Output is opinionated and deterministic: short-array syntax, four-space
 * indentation, and single-quoted, properly escaped string literals. Comments and
 * the original formatting of the source file are NOT preserved. Scalars are
 * emitted via {@see var_export()} so escaping is handled by PHP itself — user
 * input is only ever written as a quoted literal, never as executable code.
 */
class ArrayExporter
{
    private const string INDENT = '    ';

    /**
     * @param  array<array-key, mixed>  $data
     */
    public function export(array $data): string
    {
        return "<?php\n\nreturn ".$this->exportArray($data, 0).";\n";
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function exportArray(array $data, int $depth): string
    {
        if ($data === []) {
            return '[]';
        }

        $childIndent = str_repeat(self::INDENT, $depth + 1);
        $closeIndent = str_repeat(self::INDENT, $depth);

        $lines = [];

        foreach ($data as $key => $value) {
            $keyLiteral = is_int($key) ? (string) $key : var_export((string) $key, true);
            $lines[] = $childIndent.$keyLiteral.' => '.$this->exportValue($value, $depth + 1).',';
        }

        return "[\n".implode("\n", $lines)."\n".$closeIndent.']';
    }

    private function exportValue(mixed $value, int $depth): string
    {
        if (is_array($value)) {
            return $this->exportArray($value, $depth);
        }

        if ($value === null || is_scalar($value)) {
            return var_export($value, true);
        }

        throw new InvalidArgumentException(
            'Translation values must be scalars, null, or nested arrays; got '.get_debug_type($value).'.'
        );
    }
}

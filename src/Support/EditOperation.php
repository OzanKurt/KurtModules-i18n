<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Support;

use InvalidArgumentException;

/**
 * One change to apply to a translation file/group.
 *
 * - `set`    sets a single cell ({@see $locale}, {@see $key}, {@see $value}).
 * - `delete` removes {@see $key} from every loaded locale.
 * - `rename` moves {@see $from} to {@see $to} in every loaded locale.
 */
final readonly class EditOperation
{
    private function __construct(
        public string $op,
        public ?string $locale = null,
        public ?string $key = null,
        public ?string $value = null,
        public ?string $from = null,
        public ?string $to = null,
    ) {}

    public static function set(string $locale, string $key, string $value): self
    {
        return new self('set', locale: $locale, key: $key, value: $value);
    }

    public static function delete(string $key): self
    {
        return new self('delete', key: $key);
    }

    public static function rename(string $from, string $to): self
    {
        return new self('rename', from: $from, to: $to);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return match ($data['op'] ?? null) {
            'set' => self::set((string) ($data['locale'] ?? ''), (string) ($data['key'] ?? ''), (string) ($data['value'] ?? '')),
            'delete' => self::delete((string) ($data['key'] ?? '')),
            'rename' => self::rename((string) ($data['from'] ?? ''), (string) ($data['to'] ?? '')),
            default => throw new InvalidArgumentException('Unknown edit operation ['.var_export($data['op'] ?? null, true).'].'),
        };
    }
}

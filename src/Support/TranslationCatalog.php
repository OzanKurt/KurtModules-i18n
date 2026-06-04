<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Support;

/**
 * Immutable snapshot of what translation data exists on disk.
 */
final readonly class TranslationCatalog
{
    /**
     * @param  list<string>  $locales  every locale known across JSON and PHP files
     * @param  list<string>  $jsonLocales  locales that have a {locale}.json file
     * @param  list<string>  $phpGroups  every project PHP group (e.g. "users", "admin/users")
     * @param  list<array{name: string, locales: list<string>, groups: list<string>}>  $vendor  namespaced packages under lang/vendor
     */
    public function __construct(
        public array $locales,
        public array $jsonLocales,
        public array $phpGroups,
        public array $vendor = [],
    ) {}

    /**
     * @return array{
     *     locales: list<string>,
     *     json: array{locales: list<string>},
     *     php: array{groups: list<string>},
     *     vendor: list<array{name: string, locales: list<string>, groups: list<string>}>
     * }
     */
    public function toArray(): array
    {
        return [
            'locales' => $this->locales,
            'json' => ['locales' => $this->jsonLocales],
            'php' => ['groups' => $this->phpGroups],
            'vendor' => $this->vendor,
        ];
    }
}

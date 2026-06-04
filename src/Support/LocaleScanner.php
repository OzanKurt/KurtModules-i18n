<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Discovers locales, JSON files, and PHP groups under the translation root.
 */
final class LocaleScanner
{
    public function __construct(private readonly LangPaths $paths) {}

    public function scan(): TranslationCatalog
    {
        $root = $this->paths->root();

        $jsonLocales = [];
        $phpLocales = [];
        $groups = [];

        if (is_dir($root)) {
            foreach (glob($root.'/*.json') ?: [] as $file) {
                $locale = basename($file, '.json');

                if (LangPaths::isValidLocale($locale)) {
                    $jsonLocales[$locale] = true;
                }
            }

            foreach (glob($root.'/*', GLOB_ONLYDIR) ?: [] as $directory) {
                $locale = basename($directory);

                // "vendor" is reserved by Laravel for namespaced package translations
                // (lang/vendor/{package}/{locale}/...), so it is not a locale of its own.
                // Editing those needs proper namespace handling and is out of scope here.
                if ($locale === 'vendor' || ! LangPaths::isValidLocale($locale)) {
                    continue;
                }

                $found = $this->phpGroupsIn($directory);

                if ($found !== []) {
                    $phpLocales[$locale] = true;

                    foreach ($found as $group) {
                        $groups[$group] = true;
                    }
                }
            }
        }

        $locales = array_keys($jsonLocales + $phpLocales);
        $jsonList = array_keys($jsonLocales);
        $groupList = array_keys($groups);

        sort($locales);
        sort($jsonList);
        sort($groupList);

        return new TranslationCatalog($locales, $jsonList, $groupList, $this->scanVendor($root));
    }

    /**
     * Discover namespaced package translations under lang/vendor/{package}/{locale}/{group}.php.
     *
     * @return list<array{name: string, locales: list<string>, groups: list<string>}>
     */
    private function scanVendor(string $root): array
    {
        $vendorRoot = $root.'/vendor';

        if (! is_dir($vendorRoot)) {
            return [];
        }

        $packages = [];

        foreach (glob($vendorRoot.'/*', GLOB_ONLYDIR) ?: [] as $packageDir) {
            $package = basename($packageDir);

            if (! LangPaths::isValidPackage($package)) {
                continue;
            }

            $locales = [];
            $groups = [];

            foreach (glob($packageDir.'/*', GLOB_ONLYDIR) ?: [] as $localeDir) {
                $locale = basename($localeDir);

                if (! LangPaths::isValidLocale($locale)) {
                    continue;
                }

                $found = $this->phpGroupsIn($localeDir);

                if ($found !== []) {
                    $locales[$locale] = true;

                    foreach ($found as $group) {
                        $groups[$group] = true;
                    }
                }
            }

            if ($groups === []) {
                continue;
            }

            $localeList = array_keys($locales);
            $groupList = array_keys($groups);
            sort($localeList);
            sort($groupList);

            $packages[] = ['name' => $package, 'locales' => $localeList, 'groups' => $groupList];
        }

        usort($packages, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $packages;
    }

    /**
     * @return list<string>
     */
    private function phpGroupsIn(string $localeDirectory): array
    {
        $groups = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($localeDirectory, FilesystemIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($localeDirectory) + 1));
            $group = substr($relative, 0, -4);

            if (LangPaths::isValidGroup($group)) {
                $groups[] = $group;
            }
        }

        return $groups;
    }
}

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

                if (! LangPaths::isValidLocale($locale)) {
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

        return new TranslationCatalog($locales, $jsonList, $groupList);
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

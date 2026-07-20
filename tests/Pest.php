<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Kurt\Modules\I18n\Support\ArrayExporter;
use Kurt\Modules\I18n\Support\LangPaths;
use Kurt\Modules\I18n\Support\TranslationManager;
use Kurt\Modules\I18n\Tests\HeadlessTestCase;
use Kurt\Modules\I18n\Tests\TestCase;

require_once __DIR__.'/opcache_shim.php';

uses(TestCase::class)->in('Feature', 'Unit');
uses(HeadlessTestCase::class)->in('Http');

/**
 * A stand-in authenticated actor for exercising the auth-gated REST API. It is
 * a bare Authenticatable (no database row needed); `actingAs()` simply resolves
 * it as the current user so the "auth" middleware passes.
 */
function i18n_actor(): Authenticatable
{
    return new GenericUser(['id' => 1, 'name' => 'Translator']);
}

/**
 * Build a TranslationManager rooted at a specific directory (no backups), for
 * binding into the container during feature tests.
 */
function i18n_manager(string $root): TranslationManager
{
    return new TranslationManager(new LangPaths($root), new ArrayExporter);
}

/**
 * Create a fresh, unique temporary directory and return its path (forward slashes).
 */
function i18n_tmp_dir(): string
{
    $dir = sys_get_temp_dir().'/i18n_'.bin2hex(random_bytes(5));
    mkdir($dir, 0777, true);

    return str_replace('\\', '/', $dir);
}

/**
 * Recursively delete a directory and its contents.
 */
function i18n_rrmdir(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }

    @rmdir($dir);
}

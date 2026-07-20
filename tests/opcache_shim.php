<?php

declare(strict_types=1);

/*
 * Defines a namespaced opcache_invalidate() inside the package's Support
 * namespace. PHP resolves an unqualified opcache_invalidate() call there to
 * this stub before the global function, so PhpArrayFile::afterReplace() records
 * its invalidations through OpcacheSpy during the test suite regardless of
 * whether the opcache extension is loaded in the test runtime.
 */

namespace Kurt\Modules\I18n\Support;

use Kurt\Modules\I18n\Tests\OpcacheSpy;

if (! function_exists(__NAMESPACE__.'\\opcache_invalidate')) {
    function opcache_invalidate(string $filename, bool $force = false): bool
    {
        OpcacheSpy::record($filename, $force);

        return true;
    }
}

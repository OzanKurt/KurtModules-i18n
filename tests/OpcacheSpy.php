<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Tests;

/**
 * Records calls made to the stubbed opcache_invalidate() function so tests can
 * assert the PHP-array write path invalidates the opcode cache after replacing
 * a file. See tests/opcache_shim.php for the function that feeds it.
 */
final class OpcacheSpy
{
    /** @var list<array{path: string, force: bool}> */
    public static array $calls = [];

    public static function reset(): void
    {
        self::$calls = [];
    }

    public static function record(string $path, bool $force): void
    {
        self::$calls[] = ['path' => $path, 'force' => $force];
    }
}

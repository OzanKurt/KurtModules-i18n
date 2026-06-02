<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Tests;

use Illuminate\Foundation\Application;
use Kurt\Modules\Core\Testing\PackageTestCase;
use Kurt\Modules\I18n\Providers\I18nServiceProvider;

abstract class TestCase extends PackageTestCase
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function modulePackageProviders($app): array
    {
        return [I18nServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The UI routes run through the "web" middleware group, which encrypts
        // cookies and therefore needs an application key.
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('i18n-testing-key', 2)));
    }
}

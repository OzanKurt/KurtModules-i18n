<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Tests;

use Illuminate\Foundation\Application;

/**
 * A test case that boots the module in the default "headless" HTTP mode, so
 * assertions can verify that no REST API or UI routes are registered until a
 * consumer opts in.
 */
abstract class HeadlessTestCase extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('i18n.http.mode', 'headless');
    }
}

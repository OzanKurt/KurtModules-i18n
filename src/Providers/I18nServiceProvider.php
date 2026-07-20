<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Providers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Kurt\Modules\Core\Providers\PackageServiceProvider;
use Kurt\Modules\I18n\Contracts\Translator;
use Kurt\Modules\I18n\Http\Middleware\Authorize;
use Kurt\Modules\I18n\Support\ArrayExporter;
use Kurt\Modules\I18n\Support\FileBackup;
use Kurt\Modules\I18n\Support\LangPaths;
use Kurt\Modules\I18n\Support\NullTranslator;
use Kurt\Modules\I18n\Support\TranslationManager;
use Spatie\LaravelPackageTools\Package;

final class I18nServiceProvider extends PackageServiceProvider
{
    protected function module(): string
    {
        return 'i18n';
    }

    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-modules-i18n')
            ->hasConfigFile('i18n')
            ->hasTranslations();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(TranslationManager::class, function (Application $app): TranslationManager {
            /** @var string $root */
            $root = config('i18n.paths.root') ?: $app->langPath();

            $backup = config('i18n.backups.enabled', true)
                ? new FileBackup(
                    (string) (config('i18n.backups.path') ?: $app->storagePath('i18n-backups')),
                    (int) config('i18n.backups.keep', 10),
                )
                : null;

            return new TranslationManager(
                new LangPaths($root),
                new ArrayExporter,
                $backup,
                $app->make(Dispatcher::class),
                static fn (): mixed => Auth::user(),
            );
        });

        // The machine-translation seam. Bound to the configured class (which
        // must implement Translator); defaults to the null translator that
        // throws until the consumer wires a real provider.
        $this->app->bind(Translator::class, function (Application $app): Translator {
            /** @var mixed $class */
            $class = config('i18n.translator');
            $class = is_string($class) && $class !== '' ? $class : NullTranslator::class;

            /** @var Translator $translator */
            $translator = $app->make($class);

            return $translator;
        });
    }

    public function packageBooted(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'i18n');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'i18n');

        $this->publishes([
            __DIR__.'/../../resources/dist' => public_path('vendor/i18n'),
        ], 'i18n-assets');

        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        /** @var list<string> $middleware */
        $middleware = (array) config('i18n.route.middleware', ['web']);

        Route::middleware([...$middleware, Authorize::class])
            ->prefix((string) config('i18n.route.prefix', 'i18n'))
            ->group(__DIR__.'/../../routes/i18n.php');
    }
}

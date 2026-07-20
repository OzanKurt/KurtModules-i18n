<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Http\Controllers;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;
use Kurt\Modules\I18n\Support\TranslationManager;

final class UiController
{
    public function index(ViewFactory $view, TranslationManager $manager): View
    {
        return $view->make('i18n::app', [
            'bootstrap' => [
                // Base path of the JSON REST API the SPA talks to (registered
                // from routes/api.php under the module's http prefix).
                'prefix' => '/'.trim((string) config('i18n.http.prefix', 'api/i18n'), '/'),
                'csrf' => csrf_token(),
                'catalog' => $manager->catalog()->toArray(),
                'strings' => (array) trans('i18n::messages'),
            ],
        ]);
    }
}

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
                'prefix' => '/'.trim((string) config('i18n.route.prefix', 'i18n'), '/'),
                'csrf' => csrf_token(),
                'catalog' => $manager->catalog()->toArray(),
                'strings' => (array) trans('i18n::messages'),
            ],
        ]);
    }
}

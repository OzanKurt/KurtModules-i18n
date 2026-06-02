<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Kurt\Modules\I18n\Enums\FileType;
use Kurt\Modules\I18n\Http\Requests\AddLocaleRequest;
use Kurt\Modules\I18n\Support\TranslationManager;

final class LocaleController extends ApiController
{
    public function store(AddLocaleRequest $request, TranslationManager $manager): JsonResponse
    {
        $group = $request->input('group');

        return response()->json(
            $manager->addLocale(
                FileType::from((string) $request->input('type')),
                (string) $request->input('locale'),
                is_string($group) ? $group : null,
            ),
            201,
        );
    }
}

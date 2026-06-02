<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Kurt\Modules\I18n\Enums\FileType;
use Kurt\Modules\I18n\Http\Requests\ApplyEditsRequest;
use Kurt\Modules\I18n\Support\EditOperation;
use Kurt\Modules\I18n\Support\TranslationManager;

final class JsonTranslationController extends ApiController
{
    public function show(Request $request, TranslationManager $manager): JsonResponse
    {
        return response()->json(
            $manager->grid(FileType::Json, null, $this->localesFromRequest($request, $manager))
        );
    }

    public function update(ApplyEditsRequest $request, TranslationManager $manager): JsonResponse
    {
        return $this->save(fn (): array => $manager->apply(
            FileType::Json,
            null,
            $request->baseHashes(),
            array_map(EditOperation::fromArray(...), $request->ops()),
        ));
    }
}

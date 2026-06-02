<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Kurt\Modules\I18n\Enums\FileType;
use Kurt\Modules\I18n\Http\Requests\ApplyEditsRequest;
use Kurt\Modules\I18n\Support\EditOperation;
use Kurt\Modules\I18n\Support\LangPaths;
use Kurt\Modules\I18n\Support\TranslationManager;

final class PhpGroupController extends ApiController
{
    public function show(Request $request, TranslationManager $manager, string $group): JsonResponse
    {
        abort_unless(LangPaths::isValidGroup($group), 422, "Invalid group [{$group}].");

        return response()->json(
            $manager->grid(FileType::Php, $group, $this->localesFromRequest($request, $manager))
        );
    }

    public function update(ApplyEditsRequest $request, TranslationManager $manager, string $group): JsonResponse
    {
        abort_unless(LangPaths::isValidGroup($group), 422, "Invalid group [{$group}].");

        return $this->save(fn (): array => $manager->apply(
            FileType::Php,
            $group,
            $request->baseHashes(),
            array_map(EditOperation::fromArray(...), $request->ops()),
        ));
    }
}

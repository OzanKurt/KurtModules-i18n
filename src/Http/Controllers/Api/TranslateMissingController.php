<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Kurt\Modules\I18n\Enums\FileType;
use Kurt\Modules\I18n\Http\Requests\TranslateMissingRequest;
use Kurt\Modules\I18n\Support\LangPaths;
use Kurt\Modules\I18n\Support\MissingKeyTranslator;

final class TranslateMissingController extends ApiController
{
    public function __invoke(TranslateMissingRequest $request, MissingKeyTranslator $translator): JsonResponse
    {
        $type = FileType::from((string) $request->input('type'));
        $group = $type === FileType::Php ? (string) $request->input('group') : null;

        if ($group !== null) {
            abort_unless(LangPaths::isValidGroupRef($group), 422, "Invalid group [{$group}].");
        }

        $reference = (string) $request->input('reference');
        $target = (string) $request->input('locale');

        return $this->save(fn (): array => $translator->fill(
            $type,
            $group,
            $reference,
            $target,
            $request->baseHashes(),
        ));
    }
}

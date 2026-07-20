<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Kurt\Modules\I18n\Enums\FileType;
use Kurt\Modules\I18n\Enums\PortableFormat;
use Kurt\Modules\I18n\Http\Requests\ImportRequest;
use Kurt\Modules\I18n\Support\LangPaths;
use Kurt\Modules\I18n\Support\TranslationImporter;

final class ImportController extends ApiController
{
    public function __invoke(ImportRequest $request, TranslationImporter $importer): JsonResponse
    {
        $type = FileType::from((string) $request->input('type'));
        $group = $type === FileType::Php ? (string) $request->input('group') : null;

        if ($group !== null) {
            abort_unless(LangPaths::isValidGroupRef($group), 422, "Invalid group [{$group}].");
        }

        $format = PortableFormat::from((string) $request->input('format'));
        $locale = (string) $request->input('locale');
        $content = (string) $request->input('content');

        return $this->save(function () use ($importer, $type, $group, $locale, $format, $content, $request): array {
            $rows = $importer->parse($format, $content);

            return $importer->apply($type, $group, $locale, $rows, $request->baseHashes());
        });
    }
}

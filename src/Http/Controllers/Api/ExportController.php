<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Kurt\Modules\I18n\Enums\FileType;
use Kurt\Modules\I18n\Enums\PortableFormat;
use Kurt\Modules\I18n\Support\LangPaths;
use Kurt\Modules\I18n\Support\TranslationExporter;

final class ExportController extends ApiController
{
    /**
     * Export a locale's translations as a downloadable CSV or JSON file.
     *
     * With `type` (and `group` for PHP) a single group is exported as
     * `key,value` rows; without `type` every group is exported with `type` and
     * `group` columns so the flat list stays unambiguous.
     */
    public function __invoke(Request $request, TranslationExporter $exporter): Response
    {
        $locale = trim((string) $request->query('locale', ''));
        abort_if($locale === '', 422, 'A locale is required.');
        abort_unless(LangPaths::isValidLocale($locale), 422, "Invalid locale [{$locale}].");

        $format = PortableFormat::tryFrom((string) $request->query('format', 'json'));
        abort_if($format === null, 422, 'Invalid format; expected "csv" or "json".');

        $type = $request->query('type');

        if ($type === null || $type === '') {
            $rows = $exporter->all($locale);
            $columns = ['type', 'group', 'key', 'value'];
            $slug = $locale.'-all';
        } else {
            [$fileType, $group] = $this->resolveGroup((string) $type, $request);
            $rows = $exporter->group($fileType, $group, $locale);
            $columns = ['key', 'value'];
            $slug = $locale.'-'.($group === null ? 'json' : str_replace(['::', '/'], '__', $group));
        }

        $body = $format === PortableFormat::Csv
            ? $exporter->toCsv($rows, $columns)
            : $exporter->toJson($rows);

        return response($body, 200, [
            'Content-Type' => $format === PortableFormat::Csv ? 'text/csv; charset=UTF-8' : 'application/json',
            'Content-Disposition' => 'attachment; filename="'.$slug.'.'.$format->value.'"',
        ]);
    }

    /**
     * @return array{0: FileType, 1: string|null}
     */
    private function resolveGroup(string $type, Request $request): array
    {
        $fileType = FileType::tryFrom($type);
        abort_if($fileType === null, 422, 'Invalid type; expected "json" or "php".');

        if ($fileType === FileType::Json) {
            return [FileType::Json, null];
        }

        $group = trim((string) $request->query('group', ''));
        abort_if($group === '', 422, 'A group is required for PHP exports.');
        abort_unless(LangPaths::isValidGroupRef($group), 422, "Invalid group [{$group}].");

        return [FileType::Php, $group];
    }
}

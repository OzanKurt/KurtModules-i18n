<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Kurt\Modules\I18n\Support\LangPaths;
use Kurt\Modules\I18n\Support\MissingKeyReport;

final class MissingKeyReportController extends ApiController
{
    public function __invoke(Request $request, MissingKeyReport $report): JsonResponse
    {
        $reference = trim((string) $request->query('reference', ''));

        abort_if($reference === '', 422, 'A reference locale is required.');
        abort_unless(LangPaths::isValidLocale($reference), 422, "Invalid locale [{$reference}].");

        return $this->respond(
            $report->generate($reference, $this->optionalLocalesFromRequest($request))
        );
    }
}

<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Kurt\Modules\I18n\Support\TranslationManager;

final class CatalogController extends ApiController
{
    public function __invoke(TranslationManager $manager): JsonResponse
    {
        return $this->respond($manager->catalog()->toArray());
    }
}

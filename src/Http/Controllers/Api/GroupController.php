<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Kurt\Modules\I18n\Support\TranslationManager;

final class GroupController extends ApiController
{
    /**
     * List every translation group known on disk as `{type, group}` pairs: the
     * JSON pseudo-group (`type: "json"`, `group: null`) followed by every
     * project and vendor PHP group. A group's actual keys/values are read
     * through the grid endpoints (`GET json`, `GET php/{group}`).
     */
    public function index(TranslationManager $manager): JsonResponse
    {
        $groups = array_map(
            static fn (array $g): array => [
                'type' => $g['type']->value,
                'group' => $g['group'],
            ],
            $manager->groups(),
        );

        return $this->respond($groups, ['total' => count($groups)]);
    }
}

<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Kurt\Modules\I18n\Enums\FileType;
use Kurt\Modules\I18n\Http\Requests\DeleteTranslationRequest;
use Kurt\Modules\I18n\Http\Requests\SetTranslationRequest;
use Kurt\Modules\I18n\Support\EditOperation;
use Kurt\Modules\I18n\Support\LangPaths;
use Kurt\Modules\I18n\Support\TranslationManager;

/**
 * Single-key reads and writes over the same safe write path (flock + backup +
 * optimistic hash) the batch grid endpoints use. Each write is one
 * {@see EditOperation} handed to {@see TranslationManager::apply()}.
 */
final class TranslationController extends ApiController
{
    /**
     * Show one key's value across locales: `?type=json|php&group=..&key=..`.
     * Returns the per-locale values plus the current file hashes so a follow-up
     * write can pass them back as the optimistic-concurrency base.
     */
    public function show(Request $request, TranslationManager $manager): JsonResponse
    {
        [$type, $group] = $this->resolveTarget($request);

        $key = (string) $request->query('key', '');
        abort_if($key === '', 422, 'A key is required.');

        $locales = $this->localesFromRequest($request, $manager);
        $grid = $manager->grid($type, $group, $locales);

        return $this->respond([
            'type' => $type->value,
            'group' => $group,
            'key' => $key,
            'values' => $grid['rows'][$key] ?? array_fill_keys($locales, null),
            'hashes' => $grid['hashes'],
            'exists' => array_key_exists($key, $grid['rows']),
        ]);
    }

    /**
     * Set a single key for one locale.
     */
    public function set(SetTranslationRequest $request, TranslationManager $manager): JsonResponse
    {
        $type = FileType::from((string) $request->input('type'));
        $group = $this->groupFor($type, $request->input('group'));

        return $this->save(fn (): array => $manager->apply(
            $type,
            $group,
            $request->baseHashes(),
            [EditOperation::set(
                (string) $request->input('locale'),
                (string) $request->input('key'),
                (string) $request->input('value'),
            )],
        ));
    }

    /**
     * Delete a single key from every loaded locale.
     */
    public function destroy(DeleteTranslationRequest $request, TranslationManager $manager): JsonResponse
    {
        $type = FileType::from((string) $request->input('type'));
        $group = $this->groupFor($type, $request->input('group'));

        return $this->save(fn (): array => $manager->apply(
            $type,
            $group,
            $request->baseHashes(),
            [EditOperation::delete((string) $request->input('key'))],
        ));
    }

    /**
     * Resolve and validate the (type, group) target from the query string.
     *
     * @return array{0: FileType, 1: string|null}
     */
    private function resolveTarget(Request $request): array
    {
        $type = FileType::tryFrom((string) $request->query('type', ''));
        abort_if($type === null, 422, 'Invalid type; expected "json" or "php".');

        if ($type === FileType::Json) {
            return [FileType::Json, null];
        }

        $group = trim((string) $request->query('group', ''));
        abort_if($group === '', 422, 'A group is required for PHP translations.');
        abort_unless(LangPaths::isValidGroupRef($group), 422, "Invalid group [{$group}].");

        return [FileType::Php, $group];
    }

    /**
     * Validate the group ref carried in a write payload (already schema-checked
     * by the form request) against path-traversal before it reaches disk.
     */
    private function groupFor(FileType $type, mixed $group): ?string
    {
        if ($type === FileType::Json || ! is_string($group)) {
            return null;
        }

        abort_unless(LangPaths::isValidGroupRef($group), 422, "Invalid group [{$group}].");

        return $group;
    }
}

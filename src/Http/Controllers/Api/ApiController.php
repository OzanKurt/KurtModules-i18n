<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Http\Controllers\Api;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Kurt\Modules\I18n\Exceptions\TranslationConflictException;
use Kurt\Modules\I18n\Exceptions\TranslationPathException;
use Kurt\Modules\I18n\Support\LangPaths;
use Kurt\Modules\I18n\Support\TranslationManager;

abstract class ApiController
{
    /**
     * Parse and validate the `?locales=a,b,c` query parameter. Falls back to
     * every known locale when none are requested.
     *
     * @return list<string>
     */
    protected function localesFromRequest(Request $request, TranslationManager $manager): array
    {
        $raw = trim((string) $request->query('locales', ''));

        if ($raw === '') {
            return $manager->catalog()->locales;
        }

        $locales = array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            static fn (string $locale): bool => $locale !== '',
        ));

        foreach ($locales as $locale) {
            abort_unless(LangPaths::isValidLocale($locale), 422, "Invalid locale [{$locale}].");
        }

        return array_values(array_unique($locales));
    }

    /**
     * Run an apply() call and translate domain exceptions into HTTP responses.
     *
     * @param  Closure(): array{hashes: array<string, string|null>, changed: list<string>}  $apply
     */
    protected function save(Closure $apply): JsonResponse
    {
        try {
            return response()->json($apply());
        } catch (TranslationConflictException $e) {
            return response()->json(['message' => 'conflict', 'locales' => $e->locales], 409);
        } catch (TranslationPathException|InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}

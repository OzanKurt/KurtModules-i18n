<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the translation manager: access is allowed in any configured
 * environment, otherwise the request must pass the `viewI18n` gate.
 */
final class Authorize
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var list<string> $environments */
        $environments = (array) config('i18n.enabled_environments', ['local']);

        abort_unless(
            app()->environment($environments) || Gate::check('viewI18n'),
            403,
            'Translation manager access denied.',
        );

        return $next($request);
    }
}

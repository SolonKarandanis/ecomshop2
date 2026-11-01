<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers each API request in the language Nuxt asks for in Accept-Language
 * (the storefront's URL locale, #79). A regional variant such as el-GR counts
 * as el; an unsupported language, or no header, falls back to English.
 */
class SetLocaleFromRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var list<string> $supported */
        $supported = config('app.supported_locales');

        // Returns the first supported locale (en) when nothing in the header matches.
        App::setLocale((string) $request->getPreferredLanguage($supported));

        return $next($request);
    }
}

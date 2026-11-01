<?php

namespace App\Support;

use App\Models\User;

/**
 * Links into the Nuxt storefront (ADR-0001) in a given language. English, the
 * storefront's default, has no prefix; Greek and Turkish pages live under
 * /el and /tr (#79).
 */
class FrontendUrl
{
    /**
     * @param  string  $path  starting with '/', and may carry a query string
     */
    public static function to(string $path, ?string $locale = null): string
    {
        /** @var list<string> $supported */
        $supported = config('app.supported_locales');
        // The first supported locale is the storefront's unprefixed default.
        $prefix = $locale !== null && $locale !== $supported[0] && in_array($locale, $supported, true)
            ? '/'.$locale
            : '';

        return rtrim((string) config('app.frontend_url'), '/').$prefix.$path;
    }

    /** A link for `$user`, in their saved language (#78). */
    public static function forUser(User $user, string $path): string
    {
        return self::to($path, $user->preferredLocale());
    }
}

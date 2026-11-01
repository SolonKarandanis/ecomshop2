<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shows the Filament admin panel in the logged-in admin's saved language,
 * using Filament's own translations. There is no switcher in the panel.
 */
class SetLocaleFromUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            App::setLocale($user->preferredLocale());
        }

        return $next($request);
    }
}

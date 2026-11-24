<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets only a scraper holding PROMETHEUS_TOKEN read /prometheus. A missing
 * token in config refuses every request, so the endpoint is closed by default.
 */
class AuthenticatePrometheusScrape
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = (string) config('prometheus.token');
        $givenToken = (string) $request->bearerToken();

        if ($expectedToken === '' || ! hash_equals($expectedToken, $givenToken)) {
            return response('Unauthorized', Response::HTTP_UNAUTHORIZED, ['WWW-Authenticate' => 'Bearer']);
        }

        return $next($request);
    }
}

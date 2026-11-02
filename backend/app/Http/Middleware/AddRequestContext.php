<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tags everything this request logs, including the queued jobs it dispatches
 * (Laravel carries Context into their payloads), with one trace ID, so a
 * checkout can be followed from the request to the mail the worker sends.
 * The ID goes back in the response, so a reported error can be found in the log.
 *
 * The user isn't known yet here (Sanctum resolves them later), so `user_id`
 * is added by the Authenticated listener in AppServiceProvider instead.
 */
class AddRequestContext
{
    public const string HEADER = 'X-Request-Id';

    public function handle(Request $request, Closure $next): Response
    {
        // Reuse a caller's ID only if it's a UUID, so a client can't put
        // arbitrary text into the log.
        $incoming = $request->headers->get(self::HEADER);
        $traceId = is_string($incoming) && Str::isUuid($incoming) ? $incoming : (string) Str::uuid();

        Context::add([
            'trace_id' => $traceId,
            'method' => $request->method(),
            'route' => $request->route()?->getName(),
        ]);

        $response = $next($request);
        $response->headers->set(self::HEADER, $traceId);

        return $response;
    }
}

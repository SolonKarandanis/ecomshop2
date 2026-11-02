<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\AddRequestContext;
use App\Http\Middleware\AlwaysAcceptJsonMiddleware;
use App\Http\Middleware\SetLocaleFromRequest;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        // Stripe can't send a CSRF token; the webhook checks Stripe's signature instead.
        $middleware->preventRequestForgery(except: ['api/stripe/webhook']);
        $middleware->api(prepend: [AlwaysAcceptJsonMiddleware::class, SetLocaleFromRequest::class]);
        // Appended, so the route is matched by the time it runs. `web` covers
        // Livewire's requests; the Filament panel adds it to its own stack.
        $middleware->appendToGroup('api', AddRequestContext::class);
        $middleware->appendToGroup('web', AddRequestContext::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->apiError(null, 'Resource not found', 404, 'NotFound');
        });

        // A 4xx ApiException is an expected answer to the client, not a failure.
        $exceptions->dontReportWhen(fn (Throwable $e) => $e instanceof ApiException && ! $e->isServerError());

        $exceptions->render(function (ApiException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->apiError(null, $e->getMessage(), $e->status(), class_basename($e));
        });

        // Anything else that fails is a bug or an outage: it's reported with its
        // stack trace, and the client gets a translated generic message in the
        // API's usual shape. With APP_DEBUG on, Laravel's own debug output stays.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if ($response->getStatusCode() !== 500 || config('app.debug')
                || (! $request->is('api/*') && ! $request->expectsJson())) {
                return $response;
            }

            return response()->apiError(null, __('messages.server_error'), 500, 'ServerError');
        });
    })->create();

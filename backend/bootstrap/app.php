<?php

use App\Exceptions\CartException;
use App\Exceptions\EmptyCartException;
use App\Exceptions\OrderCountException;
use App\Exceptions\OrderException;
use App\Exceptions\PaymentException;
use App\Exceptions\ProductNotFoundException;
use App\Exceptions\ProfileException;
use App\Exceptions\ReviewException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            $status = match (get_class($e)) {
                OrderException::class,
                OrderCountException::class,
                CartException::class,
                EmptyCartException::class,
                PaymentException::class,
                ProductNotFoundException::class,
                ProfileException::class,
                ReviewException::class => $e->getCode(),
                default => null,
            };

            if ($status === null) {
                return null;
            }

            return response()->json(['message' => $e->getMessage()], $status);
        });
    })->create();

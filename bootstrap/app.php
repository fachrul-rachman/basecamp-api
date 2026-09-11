<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Keep API error responses matching docs/06-API-CONTRACT.md
        // (`{"message": "..."}`) regardless of APP_DEBUG. Expected
        // HTTP-level failures (401/403/404/405/...) never need a stack
        // trace; genuine bugs (500) still get full debug detail locally
        // when APP_DEBUG is true — this only normalizes the "expected
        // failure" family, validation/auth responses were already clean.
        $exceptions->renderable(function (HttpExceptionInterface $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage() ?: (Response::$statusTexts[$e->getStatusCode()] ?? 'Error'),
                ], $e->getStatusCode(), $e->getHeaders());
            }
        });
    })->create();

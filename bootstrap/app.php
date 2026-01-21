<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        \App\Providers\EventServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Add Sanctum middleware for SPA authentication
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // Add custom CORS cookie handling middleware
        // $middleware->web(append: [
        //     \App\Http\Middleware\HandleCorsCookies::class,
        // ]);

        $middleware->validateCsrfTokens(except: [
            'api/*',
            'sanctum/csrf-cookie',
        ]);

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'role' => \App\Http\Middleware\CheckRole::class,
            // 'handle.cors.cookies' => \App\Http\Middleware\HandleCorsCookies::class,
            'api.otp' => \App\Http\Middleware\EnsureOtpVerified::class,
        ]);

        //

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

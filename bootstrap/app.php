<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\EnsureStaffAccountIsUsable::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'payments/mpesa/callback/*',
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'mpesa.callback' => \App\Http\Middleware\VerifyMpesaCallback::class,
        ]);

        // Trusted proxies come from TRUSTED_PROXIES (config/trustedproxy.php),
        // read per request by the framework's TrustProxies middleware. Appended
        // after it: report forwarded headers from proxies that list misses.
        $middleware->append(\App\Http\Middleware\WarnOnUntrustedForwardedHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

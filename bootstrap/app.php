<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // For an external uptime monitor: 503 when the database, scheduler or email queue is down.
        // Outside the web group on purpose: no session row per ping.
        then: function () {
            \Illuminate\Support\Facades\Route::get('/health', function () {
                $failing = \App\Support\OperatorAlerts::probe();

                return response()->json(['status' => $failing ? 'fail' : 'ok', 'failing' => $failing], $failing ? 503 : 200)
                    ->header('Cache-Control', 'no-store');
            })->middleware('throttle:60,1')->name('health');
        },
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
            'pos.operator' => \App\Http\Middleware\RequirePosOperator::class,
        ]);

        // Trusted proxies come from TRUSTED_PROXIES (config/trustedproxy.php),
        // read per request by the framework's TrustProxies middleware. Appended
        // after it: report forwarded headers from proxies that list misses.
        $middleware->append(\App\Http\Middleware\WarnOnUntrustedForwardedHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // V1 alerting: count every reported error and email the owner (throttled). Logging carries on as normal.
        $exceptions->report(function (\Throwable $e) {
            \App\Support\OperatorAlerts::error($e);
        });
    })->create();

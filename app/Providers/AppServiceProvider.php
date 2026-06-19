<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // SendOrderConfirmation listens for App\Events\MpesaWebhookHandled via
        // Laravel's listener auto-discovery (app/Listeners) — no manual binding,
        // which would double-register and send the receipt twice.
    }
}

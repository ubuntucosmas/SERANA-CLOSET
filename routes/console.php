<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Return abandoned-checkout stock to the pool.
Schedule::command('stock:release-expired')->everyMinute()->withoutOverlapping();

// Resolve payments whose Safaricom callback never arrived. Runs every minute so
// confirmation lands inside the checkout's polling window; it paces itself past
// Safaricom's rate limit and withoutOverlapping() skips a tick if still running.
// runInBackground so its deliberate inter-query sleeps never delay the other
// every-minute jobs in the same scheduler tick.
Schedule::command('mpesa:reconcile')->everyMinute()->withoutOverlapping()->runInBackground();

// Greenlight or close drops once their reservation deadline passes.
Schedule::command('drops:close-expired')->everyFiveMinutes()->withoutOverlapping();

<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Return abandoned-checkout stock to the pool.
Schedule::command('stock:release-expired')->everyMinute()->withoutOverlapping();

// Resolve payments whose Safaricom callback never arrived.
Schedule::command('mpesa:reconcile')->everyThreeMinutes()->withoutOverlapping();

// Greenlight or close drops once their reservation deadline passes.
Schedule::command('drops:close-expired')->everyFiveMinutes()->withoutOverlapping();

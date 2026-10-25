<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Every job records when it last succeeded or failed (App\Support\JobRuns): preflight prints it, the Studio warns on failure.
$track = fn (\Illuminate\Console\Scheduling\Event $event, string $name) => $event
    ->onSuccess(fn () => \App\Support\JobRuns::ok($name))->onFailure(fn () => \App\Support\JobRuns::failed($name));

// Return abandoned-checkout stock to the pool.
$track(Schedule::command('stock:release-expired')->everyMinute()->withoutOverlapping(), 'stock:release-expired');

// Resolve payments whose Safaricom callback never arrived. Runs every minute so
// confirmation lands inside the checkout's polling window; it paces itself past
// Safaricom's rate limit and withoutOverlapping() skips a tick if still running.
// runInBackground so its deliberate inter-query sleeps never delay the other
// every-minute jobs in the same scheduler tick.
$track(Schedule::command('mpesa:reconcile')->everyMinute()->withoutOverlapping()->runInBackground(), 'mpesa:reconcile');

// Proof of life for serana:preflight: if this stops updating, nothing above runs.
$track(Schedule::call(fn () => \Illuminate\Support\Facades\Cache::forever(\App\Console\Commands\ProductionPreflight::HEARTBEAT, now()->timestamp))
    ->everyMinute()->name('scheduler-heartbeat'), 'scheduler-heartbeat');

// V1 alerting: email the owner when the Studio's warnings or the M-Pesa review list change (OperatorAlerts).
// A closure returning false counts as a failed run, so the result (sent or not) is not returned.
$track(Schedule::call(function () { \App\Support\OperatorAlerts::health(); })->everyFiveMinutes()->name('operator-alerts')->withoutOverlapping(), 'operator-alerts');

// Greenlight or close drops once their reservation deadline passes.
// Phase C: a late payment whose item sold out, still undecided after the policy's days, becomes a refund owed.
$track(Schedule::command('serana:resolve-overdue')->hourly()->withoutOverlapping(), 'serana:resolve-overdue');
$track(Schedule::command('drops:close-expired')->everyFiveMinutes()->withoutOverlapping(), 'drops:close-expired');

<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('catalog:sources:dispatch-schedules')->everyMinute()->withoutOverlapping();
Schedule::command('suppliers:dispatch-schedules')->everyMinute()->withoutOverlapping();
Schedule::command('catalog:relations:resolve --limit=50000')->hourly()->withoutOverlapping(55);

// Legacy fallback cadence for active suppliers that do not define an enabled
// per-supplier schedule for the corresponding mode.
Schedule::command('suppliers:sync --mode=stock')->everyFifteenMinutes()->withoutOverlapping(14);
Schedule::command('suppliers:sync --mode=prices')->hourly()->withoutOverlapping(55);
Schedule::command('suppliers:sync --mode=catalog')->dailyAt('02:10')->withoutOverlapping(180);

// A feed that stops updating fails silently by nature: the shop keeps serving the
// last known price and stock. This is the only thing that turns that into a signal.
Schedule::command('suppliers:health-check --notify')->hourly()->withoutOverlapping(55);

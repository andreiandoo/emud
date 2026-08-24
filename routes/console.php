<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('suppliers:sync --mode=stock')->everyFifteenMinutes()->withoutOverlapping(14);
Schedule::command('suppliers:sync --mode=prices')->hourly()->withoutOverlapping(55);
Schedule::command('suppliers:sync --mode=catalog')->dailyAt('02:10')->withoutOverlapping(180);

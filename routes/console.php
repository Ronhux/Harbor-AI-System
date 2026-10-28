<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// Execution still requires OS cron or Windows Task Scheduler to invoke schedule:run.
\Illuminate\Support\Facades\Schedule::command('harbor:backup monitor')->everyFiveMinutes()->withoutOverlapping(10);
if (config('backup.enabled')) {
    \Illuminate\Support\Facades\Schedule::command('harbor:backup create')->dailyAt(config('backup.time'))->timezone('Asia/Manila')->withoutOverlapping(120);
    \Illuminate\Support\Facades\Schedule::command('harbor:backup retain')->dailyAt('04:00')->timezone('Asia/Manila')->withoutOverlapping(120);
}

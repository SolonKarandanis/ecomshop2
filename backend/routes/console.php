<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hostinger keeps no long-running processes alive, so instead of a permanent
// `queue:work`, an hPanel cron runs `schedule:run` every minute and this starts
// a worker that drains the queue (queued mail and Notifications) and exits (#16).
Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=55')
    ->everyMinute()
    ->withoutOverlapping();

// Deletes database sessions older than session.lifetime (Laravel 13.39+). This
// replaces the per-request lottery, which is turned off in config/session.php.
// Hourly suits the default 120-minute lifetime.
Schedule::command('session:garbage-collect')->hourly();

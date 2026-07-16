<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:delete-unverified-users')
    ->daily()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/laravel.log'));

// Workaround for VPS environments without supervisor/terminal access:
// Spawn the queue worker every minute to process database queue jobs and exit.
Schedule::command('queue:work --stop-when-empty')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

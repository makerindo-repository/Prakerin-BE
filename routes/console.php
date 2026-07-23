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

// Subscription: send renewal reminders 3 days before expiry (runs at 08:00)
Schedule::command('subscription:send-renewal-reminders')
    ->dailyAt('08:00')
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/laravel.log'));

// Subscription: expire unpaid/overdue subscriptions and downgrade tiers (runs at 09:00)
Schedule::command('subscription:expire-unpaid')
    ->dailyAt('09:00')
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/laravel.log'));

// Workaround for VPS environments without supervisor/terminal access:
// Spawn the queue worker every minute to process database queue jobs and exit.
Schedule::command('queue:work --stop-when-empty')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

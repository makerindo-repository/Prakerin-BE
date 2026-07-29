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

// Subscription: safety net for pending QRIS payments that passed their
// payment window (see config('subscription.payment_expiry_seconds'),
// default 5 minutes) without anyone polling and without a webhook arriving
// (e.g. the payer closed the modal). Runs every minute so unpaid invoices
// resolve to 'expired' quickly instead of staying 'pending' forever.
Schedule::command('subscription:expire-pending-payments')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/laravel.log'));

// Workaround for VPS environments without supervisor/terminal access:
// Spawn the queue worker every minute to process database queue jobs and exit.
Schedule::command('queue:work --stop-when-empty')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
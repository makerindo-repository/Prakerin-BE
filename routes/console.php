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
    ->sendOutputTo(storage_path('logs/laravel.log'));

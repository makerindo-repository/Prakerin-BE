<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Subscription Packages
    |--------------------------------------------------------------------------
    |
    | Defines available premium subscription plans. Amounts are in IDR (rupiah),
    | stored as integers (no decimal). duration_days controls when the
    | subscription_end_date and next renewal_date are calculated.
    |
    */

    'packages' => [
        'monthly' => [
            'amount'       => 99000,  // Rp 99.000
            'name'         => 'Monthly Premium',
            'duration_days' => 30,
        ],
        'yearly' => [
            'amount'       => 999000, // Rp 999.000
            'name'         => 'Yearly Premium',
            'duration_days' => 365,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Renewal Logic
    |--------------------------------------------------------------------------
    */

    // How many days before renewal_date to send the reminder notification
    'renewal_reminder_days' => 3,

    // Frontend polling settings (ms) — mirrored here for reference
    'payment_polling_interval' => 5000,   // 5 seconds
    'payment_polling_timeout'  => 1800000, // 30 minutes

    /*
    |--------------------------------------------------------------------------
    | Xendit Credentials
    |--------------------------------------------------------------------------
    */

    'xendit' => [
        'secret_key'     => env('XENDIT_SECRET_KEY'),
        'webhook_token'  => env('XENDIT_WEBHOOK_TOKEN'),
        'base_url'       => 'https://api.xendit.co',
    ],

];

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

    // Berapa lama (detik) invoice QRIS berlaku sebelum otomatis expired.
    // Midtrans sendiri yang menandai transaksi 'expire' begitu durasi ini
    // lewat (dikirim sebagai `custom_expiry` saat createInvoice). Default 5
    // menit — sesuaikan lewat PAYMENT_EXPIRY_SECONDS di .env.
    'payment_expiry_seconds' => (int) env('PAYMENT_EXPIRY_SECONDS', env('XENDIT_INVOICE_DURATION_SECONDS', 300)),

    /*
    |--------------------------------------------------------------------------
    | Midtrans Credentials (Core API — QRIS)
    |--------------------------------------------------------------------------
    |
    | Server Key dipakai buat Basic Auth ke API Midtrans DAN buat verifikasi
    | signature webhook (SHA512). Base URL beda antara sandbox & production —
    | jangan salah pasang keys sandbox tapi is_production=true (atau
    | sebaliknya), nanti selalu dapat 401.
    |
    */

    'midtrans' => [
        'server_key'    => env('MIDTRANS_SERVER_KEY', ''),
        'client_key'    => env('MIDTRANS_CLIENT_KEY', ''), // gak dipakai server-side (Core API), disimpan buat referensi/kalau nanti butuh Snap.js
        'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
        'base_url'      => (bool) env('MIDTRANS_IS_PRODUCTION', false)
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | Xendit Credentials — LEGACY, tidak dipakai lagi setelah migrasi ke
    | Midtrans. Dibiarkan di sini (bukan dihapus) buat jaga-jaga rollback
    | cepat kalau ada masalah pas migrasi. Aman dihapus total kalau migrasi
    | Midtrans sudah stabil beberapa minggu.
    |--------------------------------------------------------------------------
    */

    'xendit' => [
        'secret_key'     => env('XENDIT_SECRET_KEY'),
        'webhook_token'  => env('XENDIT_WEBHOOK_TOKEN'),
        'base_url'       => 'https://api.xendit.co',
        'payment_methods' => array_values(array_filter(explode(',', (string) env('XENDIT_PAYMENT_METHODS', '')))),
    ],

];
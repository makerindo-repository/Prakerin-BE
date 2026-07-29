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
    // Xendit sendiri yang menandai invoice EXPIRED begitu durasi ini lewat
    // (dikirim sebagai `invoice_duration` saat createInvoice). Default 5
    // menit — sesuaikan lewat XENDIT_INVOICE_DURATION_SECONDS di .env.
    'payment_expiry_seconds' => (int) env('XENDIT_INVOICE_DURATION_SECONDS', 300),

    /*
    |--------------------------------------------------------------------------
    | Xendit Credentials
    |--------------------------------------------------------------------------
    */

    'xendit' => [
        'secret_key'     => env('XENDIT_SECRET_KEY'),
        'webhook_token'  => env('XENDIT_WEBHOOK_TOKEN'),
        'base_url'       => 'https://api.xendit.co',

        // Kosongkan (default) supaya Xendit menampilkan metode pembayaran
        // apapun yang SUDAH AKTIF di akun kamu (Virtual Account, e-wallet
        // test, dll — semua ini langsung bisa dipakai tanpa approval).
        // Isi dengan "QRCODE" (pisahkan koma kalau lebih dari satu, mis.
        // "QRCODE,EWALLET") HANYA setelah QRIS approved di akun Xendit kamu
        // (Settings > Payment Channels > aktivasi QRIS — butuh ~5 hari kerja
        // atau hubungi Account Manager/Customer Success Xendit).
        'payment_methods' => array_values(array_filter(explode(',', (string) env('XENDIT_PAYMENT_METHODS', '')))),
    ],

];
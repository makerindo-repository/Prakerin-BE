<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Revenue extends Model
{
    protected $table = 'revenue';

    protected $fillable = [
        'subscription_id',
        'user_id',
        'user_type',
        'amount',
        'currency',
        'payment_status',
        'payment_date',
        'period_start',
        'period_end',
        'payment_reference_id',
        'external_id',
        'invoice_url',
        'qr_code_url',
        'expiry_date',
        'notes',
    ];

    protected $casts = [
        'expiry_date' => 'datetime',
        'payment_date' => 'datetime',
        'period_start' => 'datetime',
        'period_end'   => 'datetime',
        'amount'       => 'decimal:2',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'user_id');
    }
}
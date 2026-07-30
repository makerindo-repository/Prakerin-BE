<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'user_id',
        'user_type',
        'amount',
        'currency',
        'status',
        'subscription_start_date',
        'subscription_end_date',
        'renewal_date',
        'payment_method',
        'payment_reference_id',
        'external_id',
    ];

    protected $casts = [
        'subscription_start_date' => 'datetime',
        'subscription_end_date'   => 'datetime',
        'renewal_date'            => 'datetime',
        'amount'                  => 'decimal:2',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function revenue()
    {
        return $this->hasMany(Revenue::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'user_id');
    }

    // ── Helper methods ─────────────────────────────────────────────────────

    /** Returns true if the subscription has passed its end date. */
    public function isExpired(): bool
    {
        return now()->greaterThan($this->subscription_end_date);
    }

    /** Returns true if the renewal date has arrived (payment is due). */
    public function isRenewalDue(): bool
    {
        return now()->greaterThanOrEqualTo($this->renewal_date);
    }

    /** Returns true if renewal is due within N days. */
    public function isRenewalDueSoon(int $days = 3): bool
    {
        return now()->greaterThanOrEqualTo($this->renewal_date->subDays($days));
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Traits\LogsActivity;

class Student extends Model
{
    use HasFactory, LogsActivity;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'school_id',
        'major_id',
        'name',
        'date_of_birth',
        'gender',
        'phone_number',
        'address',
        'is_verified',
        'class',
        'skill',
        'status_magang',
        'status_subscription',
        'subscription_renewed_at',
        'portofolio_link',
        'social_media_link',
    ];


    protected static function booted()
    {
        static::creating(function ($user) {
            if (!$user->id) {
                $user->id = (string) Str::uuid();
            }
        });
    }

    protected $casts = [
        'is_verified'            => 'boolean',
        'subscription_renewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, );
    }

    public function school()
    {
        return $this->belongsTo(School::class, );
    }

    public function reportTaskMessages()
    {
        return $this->hasMany(ReportTaskMessage::class);
    }

    public function curriculumVitae()
    {
        return $this->hasMany(CurriculumVitae::class);
    }

    public function major()
    {
        return $this->belongsTo(Major::class);
    }

    public function internships()
    {
        return $this->hasMany(Internship::class);
    }

    public function subscription()
    {
        return $this->hasOne(\App\Models\Subscription::class, 'user_id');
    }

    public function revenues()
    {
        return $this->hasMany(\App\Models\Revenue::class, 'user_id');
    }

    /**
     * Derive user_type for subscription based on the linked school's type.
     * Returns 'siswa' if school.type == 'school', else 'mahasiswa'.
     */
    public function getSubscriptionUserTypeAttribute(): string
    {
        return optional($this->school)->type === 'school' ? 'siswa' : 'mahasiswa';
    }
}

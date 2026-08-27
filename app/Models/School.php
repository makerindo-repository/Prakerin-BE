<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class School extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'is_verified' => 'boolean',
        'description' => 'array',
    ];

    protected $fillable = [
        'id',
        'user_id',
        'city_regency_id',
        'name',
        'type',
        'address',
        'phone_number',
        'is_verified',
        'accreditation',
        'website',
        'npsn',
        'status',
        'status_subscription',
        'subscription_renewed_at',
        'description',
        'report_template',
    ];

    protected static function booted()
    {
        static::creating(function ($user) {
            if (!$user->id) {
                $user->id = (string) Str::uuid();
            }
        });
    }



    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }

    public function mous()
    {
        return $this->hasMany(Mou::class);
    }
    public function cityRegency()
    {
        return $this->belongsTo(CityRegency::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Company extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'city_regency_id',
        'sector_id',
        'name',
        'description',
        'address',
        'phone_number',
        'is_verified',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
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


    public function sector()
    {
        return $this->belongsTo(Sector::class);
    }

    public function cityRegency()
    {
        return $this->belongsTo(CityRegency::class);
    }

    public function tests()
    {
        return $this->hasMany(Test::class);
    }
}

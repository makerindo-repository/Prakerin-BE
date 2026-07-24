<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CityRegency extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $casts = [
        'is_accepted' => 'boolean',
    ];
    protected $fillable = [
        'id',
        'province_id',
        'name',
        'external_id',
        'is_accepted',
        'synced_at',
    ];

    protected static function booted()
    {
        static::creating(function ($user) {
            if (!$user->id) {
                $user->id = (string) Str::uuid();
            }
        });
    }

    public function schools()
    {
        return $this->hasMany(School::class);
    }
    public function companies()
    {
        return $this->hasMany(Company::class);
    }

    public function province()
    {
        return $this->belongsTo(Province::class);
    }

}

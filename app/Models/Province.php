<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Province extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $casts = [
        'is_accepted' => 'boolean',
    ];
    protected $fillable = [
        'id',
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

    public function cityRegencies()
    {
        return $this->hasMany(CityRegency::class);
    }
}

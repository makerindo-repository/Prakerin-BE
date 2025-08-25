<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Duration extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $casts = [
        'is_accepted' => 'boolean',
    ];
    protected $fillable = [
        'id',
        'duration_value',
        'duration_unit',
        'is_accepted',
    ];

    protected static function booted()
    {
        static::creating(function ($user) {
            if (!$user->id) {
                $user->id = (string) Str::uuid();
            }
        });
    }

    public function jobOpenings()
    {
        return $this->hasMany(JobOpening::class, );
    }
}

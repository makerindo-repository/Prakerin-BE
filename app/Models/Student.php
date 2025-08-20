<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Student extends Model
{
    use HasFactory;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'school_id',
        'name',
        'date_of_birth',
        'gender',
        'phone_number',
        'address',
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

    protected $casts = [
        'is_accepted' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, );
    }

    public function school()
    {
        return $this->belongsTo(School::class, );
    }
}

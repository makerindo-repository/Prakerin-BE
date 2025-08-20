<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Str;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'username',
        'email',
        'password',
        'role',
        'photo_profile',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function student()
    {
        return $this->hasOne(Student::class);
    }
    public function school()
    {
        return $this->hasOne(School::class);
    }
    public function company()
    {
        return $this->hasOne(Company::class);
    }

    protected static function booted()
    {
        static::creating(function ($user) {
            if (!$user->id) {
                $user->id = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}

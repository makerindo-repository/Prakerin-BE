<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;
use App\Traits\LogsActivity;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles, LogsActivity;

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
        'last_login_at',
        'is_pro',
        'is_verified'
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

    public function feedbacksGiven()
    {
        return $this->hasMany(Feedback::class, 'from_user_id');
    }

    public function feedbacksReceived()
    {
        return $this->hasMany(Feedback::class, 'to_user_id');
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
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }


    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function toRate() {
        return $this->belongsToMany(User::class, 'user_user',  'user_id', 'related_user_id')->withTimestamps()->withPivot('is_done');
    }
    public function rated() {
        return $this->belongsToMany(User::class, 'user_user', 'related_user_id', 'user_id')->withTimestamps()->withPivot('is_done');
    }
}

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
        'major_id',
        'name',
        'date_of_birth',
        'gender',
        'phone_number',
        'address',
        'is_verified',
        'class',
        'skill',
        'portofolio_link',
        'social_media_link'

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
        'is_verified' => 'boolean',
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
}

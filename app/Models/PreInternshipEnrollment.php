<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PreInternshipEnrollment extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'student_id',
        'class_id',
        'status',
        'attendance_count',
        'total_sessions',
        'enrolled_at',
        'completed_at',
    ];

    protected $casts = [
        'attendance_count' => 'integer',
        'total_sessions' => 'integer',
        'enrolled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function class()
    {
        return $this->belongsTo(PreInternshipClass::class, 'class_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function attendance()
    {
        return $this->hasMany(ClassAttendance::class, 'enrollment_id');
    }
}

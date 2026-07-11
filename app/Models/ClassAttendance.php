<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ClassAttendance extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'class_attendance';

    protected $fillable = [
        'id',
        'enrollment_id',
        'session_date',
        'present',
        'notes',
    ];

    protected $casts = [
        'session_date' => 'datetime',
        'present' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function enrollment()
    {
        return $this->belongsTo(PreInternshipEnrollment::class, 'enrollment_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class StudentAward extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'student_id',
        'award_id',
        'reason',
        'awarded_at',
        'awarded_by_id',
        'is_public',
    ];

    protected $casts = [
        'awarded_at' => 'datetime',
        'is_public' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function award()
    {
        return $this->belongsTo(Award::class, 'award_id');
    }

    public function awardedBy()
    {
        return $this->belongsTo(User::class, 'awarded_by_id');
    }
}

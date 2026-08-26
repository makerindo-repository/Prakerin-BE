<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SchoolAiProfileHistory extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'school_id',
        'school_name',
        'type',
        'tagline',
        'about_school',
        'accreditation',
        'npsn',
        'website',
        'email',
        'phone',
        'address',
        'vision',
        'mission',
        'majors',
        'competencies',
        'facilities',
        'partnerships',
        'completeness_score',
    ];

    protected $casts = [
        'majors' => 'array',
        'competencies' => 'array',
        'facilities' => 'array',
        'partnerships' => 'array',
        'completeness_score' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($item) {
            if (!$item->id) {
                $item->id = (string) Str::uuid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}

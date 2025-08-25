<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class InternshipApplication extends Model
{
    use HasFactory;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'curriculum_vitae_id',
        'job_opening_id',
        'status',
        'step',
    ];

    protected static function booted()
    {
        static::creating(function ($user) {
            if (!$user->id) {
                $user->id = (string) Str::uuid();
            }
        });
    }

    public function curriculumVitae()
    {
        return $this->belongsTo(CurriculumVitae::class);
    }

    public function jobOpening()
    {
        return $this->belongsTo(JobOpening::class);
    }

    public function internshipApplicationTests()
    {
        return $this->hasMany(InternshipApplicationTest::class);
    }

}

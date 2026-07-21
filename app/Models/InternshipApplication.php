<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

use App\Traits\LogsActivity;

class InternshipApplication extends Model
{
    use HasFactory, LogsActivity;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $casts = [
        'cover_letter' => 'array',
    ];

    protected $fillable = [
        'id',
        'curriculum_vitae_id',
        'job_opening_id',
        'status',
        'cover_letter',
        'message_rejected',
    ];

    protected static function booted()
    {
        static::creating(function ($internshipApplication) {
            if (!$internshipApplication->id) {
                $internshipApplication->id = (string) Str::uuid();
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

    public function test(){
        return $this->belongsToMany(Test::class)->select("title", "type")->withPivot(['is_passed']);
    }

    public function internshipApplicationTests()
    {
        return $this->hasMany(InternshipApplicationTest::class);
    }

    public function internship()
    {
        return $this->hasOne(Internship::class);
    }
}

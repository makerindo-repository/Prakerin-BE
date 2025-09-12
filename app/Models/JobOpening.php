<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class JobOpening extends Model
{
    use HasFactory;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $casts = [
        'is_paid' => 'boolean',
        'is_available' => 'boolean',
        'description' => 'array'
    ];
    protected $fillable = [
        'id',
        'company_id',
        'field_id',
        'duration_id',
        'title',
        'description',
        'duration',
        'is_paid',
        'grade',
        'type',
        'location',
        'qouta',
        'is_available',
        'role'
    ];

    protected static function booted()
    {
        static::creating(function ($jobOpening) {
            if (!$jobOpening->id) {
                $jobOpening->id = (string) Str::uuid();
            }
        });
    }


    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function field()
    {
        return $this->belongsTo(Field::class);
    }

    public function duration()
    {
        return $this->belongsTo(Duration::class);
    }

    public function internshipApplications()
    {
        return $this->hasMany(InternshipApplication::class);
    }

    public function saveJobOpening()
    {
        return $this->hasMany(SaveJobOpening::class);
    }
}

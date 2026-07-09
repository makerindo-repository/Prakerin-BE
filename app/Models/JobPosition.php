<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class JobPosition extends Model
{
    use HasFactory;

    protected $table = 'job_positions';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'is_accepted' => 'boolean',
    ];

    protected $fillable = [
        'id',
        'name',
        'is_accepted',
    ];

    protected static function booted()
    {
        static::creating(function ($jobPosition) {
            if (!$jobPosition->id) {
                $jobPosition->id = (string) Str::uuid();
            }
        });
    }

    public function internships()
    {
        return $this->hasMany(Internship::class, 'job_position_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Internship extends Model
{
    use HasFactory;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $casts = [
        'is_completed' => 'boolean',
    ];

    protected $fillable = [
        'id',
        'internship_application_id',
        'start_date',
        'end_date',
        'is_completed',
        'role',
    ];

    protected static function booted()
    {
        static::creating(function ($user) {
            if (!$user->id) {
                $user->id = (string) Str::uuid();
            }
        });
    }

    public function internshipApplication()
    {
        return $this->belongsTo(InternshipApplication::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }


}
